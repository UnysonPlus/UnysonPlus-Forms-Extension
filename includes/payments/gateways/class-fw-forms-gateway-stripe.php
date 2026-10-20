<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Card payments through Stripe Checkout (Stripe's own hosted page; no card data touches the site).
 *
 *   start()          a Checkout Session in `payment` mode for the order total (one line, "Order F-…"),
 *                    metadata fw_form_payment = payment id; returns the page to send the payer to.
 *   confirm_return() on the payer's return, reads the session and marks the payment paid.
 *   webhook()        verified with the signing secret: checkout.session.completed /
 *                    async_payment_succeeded → paid, async_payment_failed → failed, charge.refunded →
 *                    refunded (found by payment_intent).
 *   refund()         POST /v1/refunds for the payment_intent.
 *   subscriptions    Checkout in `subscription` mode (the recurring price + a one-time item for one-off
 *                    products; a form coupon becomes a Stripe coupon, once or forever); invoice.paid
 *                    (subscription_cycle / _update) -> a renewal payment, invoice.payment_failed -> past
 *                    due, customer.subscription.updated / .deleted -> cancelled / ended;
 *                    cancel_subscription() = cancel_at_period_end.
 *
 * Keys: Forms Settings → Payments → Stripe; when empty, the keys saved in Membership → Payments.
 * REST through wp_remote_request, no SDK, pinned Stripe-Version (same as Membership).
 */
class FW_Forms_Gateway_Stripe extends FW_Forms_Gateway {

	const API     = 'https://api.stripe.com/v1/';
	const VERSION = '2024-06-20';

	public function get_id() {
		return 'stripe';
	}

	public function get_title() {
		return __( 'Card (Stripe)', 'fw' );
	}

	public function title( array $form ) {
		return __( 'Card', 'fw' );
	}

	private function live() {
		return 'live' === FW_Forms_Payments::setting( 'payments_stripe_mode', 'stripe', 'mode' );
	}

	private function secret_key() {
		return $this->live()
			? FW_Forms_Payments::setting( 'payments_stripe_live_secret', 'stripe', 'live_secret_key' )
			: FW_Forms_Payments::setting( 'payments_stripe_test_secret', 'stripe', 'test_secret_key' );
	}

	private function webhook_secret() {
		return $this->live()
			? FW_Forms_Payments::setting( 'payments_stripe_live_webhook', 'stripe', 'live_webhook_secret' )
			: FW_Forms_Payments::setting( 'payments_stripe_test_webhook', 'stripe', 'test_webhook_secret' );
	}

	public function is_ready() {
		$k = $this->secret_key();
		$m = $this->live() ? 'live_' : 'test_';
		return 0 === strpos( $k, 'sk_' . $m ) || 0 === strpos( $k, 'rk_' . $m );
	}

	/**
	 * @param string $method GET | POST
	 * @param string $path   e.g. checkout/sessions
	 * @param array  $params `_idempotency` becomes the Idempotency-Key header
	 * @return array|WP_Error
	 */
	public function api( $method, $path, array $params = array() ) {
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $this->secret_key(), 'Stripe-Version' => self::VERSION ),
		);
		$url  = self::API . ltrim( $path, '/' );
		if ( 'GET' === $method ) {
			$url = $params ? $url . '?' . http_build_query( $params ) : $url;
		} else {
			if ( ! empty( $params['_idempotency'] ) ) {
				$args['headers']['Idempotency-Key'] = (string) $params['_idempotency'];
			}
			unset( $params['_idempotency'] );
			$args['body'] = $params;
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 400 || ! is_array( $body ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] ) ? $body['error']['message'] : 'Stripe returned HTTP ' . $code;
			return new WP_Error( 'fw_forms_stripe', $msg, array( 'status' => $code ) );
		}
		return $body;
	}

	public function supports_recurring() {
		return true;
	}

	/**
	 * A Stripe coupon for the form coupon on this payment (made per payment, so Stripe's own rules -
	 * once / forever - apply to the subscription's invoices).
	 *
	 * @return string|WP_Error|null The coupon id, or null when there is no discount.
	 */
	private function stripe_coupon( $payment ) {
		$c = $payment->meta['coupon_def'] ?? null;
		if ( ! is_array( $c ) || (float) ( $payment->meta['discount'] ?? 0 ) <= 0 ) {
			return null;
		}
		$params = array( 'duration' => 'forever' === $c['duration'] ? 'forever' : 'once', 'name' => substr( (string) $c['code'], 0, 40 ), '_idempotency' => 'fw-forms-coupon-' . $payment->id );
		if ( 'percent' === $c['type'] ) {
			$params['percent_off'] = min( 100, (float) $c['amount'] );
		} else {
			$params['amount_off'] = self::minor_units( (float) $c['amount'], $payment->currency );
			$params['currency']   = strtolower( $payment->currency );
		}
		$r = $this->api( 'POST', 'coupons', $params );
		return is_wp_error( $r ) ? $r : (string) $r['id'];
	}

	public function start( $payment, array $ctx ) {
		if ( ! empty( $payment->meta['subscription_id'] ) && ! empty( $payment->meta['recurring'] ) ) {
			return $this->start_subscription( $payment );
		}
		$currency = strtolower( $payment->currency );
		$attempt  = (int) ( $payment->meta['attempts'] ?? 0 ) + 1;
		$params   = array(
			'mode'                => 'payment',
			'client_reference_id' => (string) $payment->id,
			'line_items'          => array( array(
				'quantity'   => 1,
				'price_data' => array(
					'currency'     => $currency,
					'unit_amount'  => self::minor_units( $payment->amount, $currency ),
					'product_data' => array( 'name' => self::description( $payment ) ),
				),
			) ),
			'metadata'            => array( 'fw_form_payment' => (string) $payment->id, 'reference' => $payment->reference, 'site' => home_url( '/' ) ),
			'payment_intent_data' => array( 'metadata' => array( 'fw_form_payment' => (string) $payment->id, 'reference' => $payment->reference ), 'description' => self::description( $payment ) ),
			// {CHECKOUT_SESSION_ID} must reach Stripe unencoded; Stripe fills it in.
			'success_url'         => FW_Forms_Payments::url( $payment, 'success' ) . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'          => FW_Forms_Payments::url( $payment, 'cancel' ),
			'_idempotency'        => 'fw-forms-checkout-' . $payment->id . '-' . $attempt,
		);
		if ( is_email( $payment->email ) ) {
			$params['customer_email'] = $payment->email;
		}
		/** Filters the Stripe Checkout session for a form payment (add tax, allow promotion codes…). */
		$params  = (array) apply_filters( 'fw_ext_forms_stripe_session', $params, $payment );
		$session = $this->api( 'POST', 'checkout/sessions', $params );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		FW_Forms_Payments::update( $payment->id, array( 'gateway_ref' => (string) $session['id'], 'meta' => array( 'attempts' => $attempt ) ) );
		return array( 'status' => 'redirect', 'redirect' => (string) $session['url'] );
	}

	/** Checkout in subscription mode: the recurring price, any one-off products, the coupon. */
	private function start_subscription( $payment ) {
		$currency = strtolower( $payment->currency );
		$rec      = (array) $payment->meta['recurring'];
		$attempt  = (int) ( $payment->meta['attempts'] ?? 0 ) + 1;
		$sub_id   = (int) $payment->meta['subscription_id'];
		$meta     = array( 'fw_form_payment' => (string) $payment->id, 'fw_form_subscription' => (string) $sub_id, 'reference' => $payment->reference );
		$items    = array( array(
			'quantity'   => 1,
			'price_data' => array(
				'currency'     => $currency,
				'unit_amount'  => self::minor_units( (float) $rec['before'], $currency ),
				'recurring'    => array( 'interval' => (string) $rec['interval'] ),
				'product_data' => array( 'name' => self::description( $payment ) ),
			),
		) );
		$one_off = self::one_off( $payment );
		if ( $one_off > 0 ) {
			$items[] = array( 'quantity' => 1, 'price_data' => array( 'currency' => $currency, 'unit_amount' => self::minor_units( $one_off, $currency ), 'product_data' => array( 'name' => __( 'One-time items', 'fw' ) ) ) );
		}
		$params = array(
			'mode'                => 'subscription',
			'client_reference_id' => (string) $payment->id,
			'line_items'          => $items,
			'metadata'            => $meta,
			'subscription_data'   => array( 'metadata' => $meta ),
			'success_url'         => FW_Forms_Payments::url( $payment, 'success' ) . '&session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'          => FW_Forms_Payments::url( $payment, 'cancel' ),
			'_idempotency'        => 'fw-forms-sub-checkout-' . $payment->id . '-' . $attempt,
		);
		if ( is_email( $payment->email ) ) {
			$params['customer_email'] = $payment->email;
		}
		$coupon = $this->stripe_coupon( $payment );
		if ( is_wp_error( $coupon ) ) {
			return $coupon; // never charge the full price instead
		}
		if ( $coupon ) {
			$params['discounts'] = array( array( 'coupon' => $coupon ) );
		}
		$params  = (array) apply_filters( 'fw_ext_forms_stripe_session', $params, $payment );
		$session = $this->api( 'POST', 'checkout/sessions', $params );
		if ( is_wp_error( $session ) ) {
			return $session;
		}
		FW_Forms_Payments::update( $payment->id, array( 'gateway_ref' => (string) $session['id'], 'meta' => array( 'attempts' => $attempt ) ) );
		return array( 'status' => 'redirect', 'redirect' => (string) $session['url'] );
	}

	/** Apply a Checkout Session (from the webhook or the payer's return). */
	public function apply_session( array $session ) {
		$pid = (int) ( $session['metadata']['fw_form_payment'] ?? $session['client_reference_id'] ?? 0 );
		$p   = $pid ? FW_Forms_Payments::find( $pid ) : null;
		if ( ! $p || 'stripe' !== $p->gateway || 'complete' !== ( $session['status'] ?? '' ) ) {
			return null;
		}
		if ( ! in_array( $session['payment_status'] ?? '', array( 'paid', 'no_payment_required' ), true ) ) {
			return null; // a delayed method: its own notification follows
		}
		$upd = array( 'meta' => array( 'payment_intent' => (string) ( $session['payment_intent'] ?? '' ), 'invoice' => (string) ( $session['invoice'] ?? '' ) ) );
		// A subscription's first payment: the subscription is now running with Stripe.
		if ( ! empty( $session['subscription'] ) && ! empty( $p->meta['subscription_id'] ) && class_exists( 'FW_Forms_Subscriptions' ) ) {
			$sub_ref = is_array( $session['subscription'] ) ? (string) $session['subscription']['id'] : (string) $session['subscription'];
			FW_Forms_Subscriptions::update( (int) $p->meta['subscription_id'], array( 'gateway_ref' => $sub_ref ) );
			FW_Forms_Subscriptions::set_status( (int) $p->meta['subscription_id'], 'active', 'stripe: first payment' );
		}
		if ( isset( $session['amount_total'] ) ) {
			$upd['amount'] = (float) $session['amount_total'] / self::minor_units( 1, (string) ( $session['currency'] ?? $p->currency ) );
		}
		FW_Forms_Payments::update( $pid, $upd );
		return FW_Forms_Payments::set_status( $pid, 'paid', array( 'gateway_ref' => (string) $session['id'], 'note' => 'stripe' ) );
	}

	public function confirm_return( $payment ) {
		if ( 'pending' !== $payment->status || 0 !== strpos( (string) $payment->gateway_ref, 'cs_' ) ) {
			return;
		}
		$session = $this->api( 'GET', 'checkout/sessions/' . rawurlencode( $payment->gateway_ref ) );
		if ( ! is_wp_error( $session ) ) {
			$this->apply_session( $session );
		}
	}

	/** Verify a Stripe-Signature header against the raw body (5-minute tolerance). */
	public static function verify_signature( $payload, $header, $secret, $tolerance = 300 ) {
		if ( '' === (string) $secret || '' === (string) $header ) {
			return false;
		}
		$t   = 0;
		$sig = array();
		foreach ( explode( ',', (string) $header ) as $part ) {
			$kv = explode( '=', trim( $part ), 2 );
			if ( 2 === count( $kv ) && 't' === $kv[0] ) {
				$t = (int) $kv[1];
			} elseif ( 2 === count( $kv ) && 'v1' === $kv[0] ) {
				$sig[] = $kv[1];
			}
		}
		if ( ! $t || ! $sig || abs( time() - $t ) > $tolerance ) {
			return false;
		}
		$expected = hash_hmac( 'sha256', $t . '.' . $payload, $secret );
		foreach ( $sig as $s ) {
			if ( hash_equals( $expected, $s ) ) {
				return true;
			}
		}
		return false;
	}

	public function webhook( WP_REST_Request $request ) {
		$payload = (string) $request->get_body();
		if ( ! self::verify_signature( $payload, (string) $request->get_header( 'stripe_signature' ), $this->webhook_secret() ) ) {
			return new WP_Error( 'fw_forms_signature', 'Invalid signature.', array( 'status' => 400 ) );
		}
		$event = json_decode( $payload, true );
		$o     = (array) ( $event['data']['object'] ?? array() );
		switch ( (string) ( $event['type'] ?? '' ) ) {
			case 'checkout.session.completed':
			case 'checkout.session.async_payment_succeeded':
				$this->apply_session( $o );
				break;
			case 'checkout.session.async_payment_failed':
				$pid = (int) ( $o['metadata']['fw_form_payment'] ?? 0 );
				$p   = $pid ? FW_Forms_Payments::find( $pid ) : null;
				if ( $p && 'stripe' === $p->gateway && 'pending' === $p->status ) {
					FW_Forms_Payments::set_status( $pid, 'failed', array( 'note' => 'stripe: async payment failed' ) );
				}
				break;
			case 'invoice.paid':
				// Renewals only: the first invoice is the Checkout session's.
				if ( in_array( (string) ( $o['billing_reason'] ?? '' ), array( 'subscription_cycle', 'subscription_update' ), true ) && class_exists( 'FW_Forms_Subscriptions' ) ) {
					$sub = FW_Forms_Subscriptions::find_by_gateway_ref( 'stripe', (string) ( $o['subscription'] ?? '' ) );
					if ( $sub ) {
						FW_Forms_Subscriptions::renewal( $sub, (float) ( $o['amount_paid'] ?? 0 ) / self::minor_units( 1, (string) ( $o['currency'] ?? $sub->currency ) ), (string) ( $o['id'] ?? '' ), array( 'payment_intent' => (string) ( $o['payment_intent'] ?? '' ), 'invoice' => (string) ( $o['id'] ?? '' ) ) );
					}
				}
				break;
			case 'invoice.payment_failed':
				$sub = class_exists( 'FW_Forms_Subscriptions' ) ? FW_Forms_Subscriptions::find_by_gateway_ref( 'stripe', (string) ( $o['subscription'] ?? '' ) ) : null;
				if ( $sub && 'active' === $sub->status ) {
					FW_Forms_Subscriptions::set_status( $sub->id, 'past_due', 'stripe: renewal failed' );
				}
				break;
			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				$sub = class_exists( 'FW_Forms_Subscriptions' ) ? FW_Forms_Subscriptions::find_by_gateway_ref( 'stripe', (string) ( $o['id'] ?? '' ) ) : null;
				if ( $sub ) {
					$st = (string) ( $o['status'] ?? '' );
					if ( 'customer.subscription.deleted' === $event['type'] || in_array( $st, array( 'canceled', 'incomplete_expired' ), true ) ) {
						FW_Forms_Subscriptions::set_status( $sub->id, 'ended', 'stripe' );
					} elseif ( ! empty( $o['cancel_at_period_end'] ) ) {
						FW_Forms_Subscriptions::set_status( $sub->id, 'cancelled', 'stripe' );
					} elseif ( 'active' === $st ) {
						FW_Forms_Subscriptions::set_status( $sub->id, 'active', 'stripe' );
					} elseif ( in_array( $st, array( 'past_due', 'unpaid' ), true ) ) {
						FW_Forms_Subscriptions::set_status( $sub->id, 'past_due', 'stripe' );
					}
				}
				break;
			case 'charge.refunded':
				$p = FW_Forms_Payments::find_by_meta( 'payment_intent', (string) ( $o['payment_intent'] ?? '' ) );
				if ( $p && 'stripe' === $p->gateway && ! empty( $o['refunded'] ) ) {
					FW_Forms_Payments::set_status( $p->id, 'refunded', array( 'note' => 'stripe' ) );
				}
				break;
		}
		return true;
	}

	public function cancel_subscription( $subscription ) {
		$r = $this->api( 'POST', 'subscriptions/' . rawurlencode( $subscription->gateway_ref ), array( 'cancel_at_period_end' => 'true' ) );
		return is_wp_error( $r ) ? $r : true;
	}

	public function refunds_online() {
		return true;
	}

	public function refund( $payment ) {
		$pi = (string) ( $payment->meta['payment_intent'] ?? '' );
		if ( '' === $pi ) {
			return new WP_Error( 'fw_forms_stripe_refund', __( 'No Stripe payment found to refund. Refund it in the Stripe Dashboard, then mark it refunded here.', 'fw' ) );
		}
		$r = $this->api( 'POST', 'refunds', array( 'payment_intent' => $pi, '_idempotency' => 'fw-forms-refund-' . $payment->id ) );
		return is_wp_error( $r ) ? $r : true;
	}
}
