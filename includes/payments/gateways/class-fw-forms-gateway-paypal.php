<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * PayPal (Orders v2, intent CAPTURE): the payer approves on PayPal's page and comes back; the
 * order is captured then (or by the CHECKOUT.ORDER.APPROVED notification, whichever is first).
 *
 *   webhook()  verified with PayPal's verify-webhook-signature and the webhook id:
 *              CHECKOUT.ORDER.APPROVED → capture, PAYMENT.CAPTURE.COMPLETED → paid,
 *              PAYMENT.CAPTURE.DENIED → failed, PAYMENT.CAPTURE.REFUNDED → refunded.
 *   refund()   POST /v2/payments/captures/{id}/refund (a subscription charge: /v1/payments/sale/{id}/refund).
 *   subscriptions  a catalog product (once) and a billing plan per set of terms (the setup fee = what is
 *              due now beyond the first period; a bigger discount = a one-cycle trial), then
 *              v1/billing/subscriptions. ACTIVATED / the payer's return → active and the opening payment
 *              paid; PAYMENT.SALE.COMPLETED → the first charge, then renewals; PAYMENT.FAILED → past due;
 *              CANCELLED / EXPIRED / SUSPENDED; cancel_subscription() → .../cancel.
 *
 * Keys: Forms Settings → Payments → PayPal; when empty, the ones saved in Membership → Payments.
 */
class FW_Forms_Gateway_PayPal extends FW_Forms_Gateway {

	public function get_id() {
		return 'paypal';
	}

	public function get_title() {
		return __( 'PayPal', 'fw' );
	}

	private function opt( $key, $member_key ) {
		return FW_Forms_Payments::setting( 'payments_paypal_' . $key, 'paypal', $member_key );
	}

	private function live() {
		return 'live' === $this->opt( 'mode', 'mode' );
	}

	private function base() {
		return $this->live() ? 'https://api-m.paypal.com/' : 'https://api-m.sandbox.paypal.com/';
	}

	public function is_ready() {
		return '' !== $this->opt( 'client_id', 'client_id' ) && '' !== $this->opt( 'client_secret', 'client_secret' );
	}

	/** @return string|WP_Error An access token, cached until shortly before it expires. */
	private function token() {
		$id  = $this->opt( 'client_id', 'client_id' );
		$key = 'fw_forms_pp_token_' . md5( $id . (int) $this->live() );
		$t   = get_transient( $key );
		if ( $t ) {
			return $t;
		}
		$res = wp_remote_post( $this->base() . 'v1/oauth2/token', array(
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Basic ' . base64_encode( $id . ':' . $this->opt( 'client_secret', 'client_secret' ) ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			'body'    => array( 'grant_type' => 'client_credentials' ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'fw_forms_paypal', __( 'PayPal did not accept the Client ID and Secret.', 'fw' ) );
		}
		set_transient( $key, $body['access_token'], max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 120 ) );
		return (string) $body['access_token'];
	}

	/** @return array|WP_Error */
	public function api( $method, $path, array $body = array(), $idempotency = '' ) {
		$token = $this->token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json', 'Prefer' => 'return=representation' ),
		);
		if ( '' !== $idempotency ) {
			$args['headers']['PayPal-Request-Id'] = $idempotency;
		}
		if ( 'GET' !== $method ) {
			$args['body'] = $body ? wp_json_encode( $body ) : '{}';
		}
		$res = wp_remote_request( $this->base() . ltrim( $path, '/' ), $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$out  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) {
			$msg = is_array( $out ) ? (string) ( $out['details'][0]['description'] ?? $out['message'] ?? '' ) : '';
			return new WP_Error( 'fw_forms_paypal', '' !== $msg ? $msg : 'PayPal returned HTTP ' . $code, array( 'status' => $code ) );
		}
		return is_array( $out ) ? $out : array();
	}

	/** "9.00" (or "900" for zero-decimal currencies). */
	private static function value( $amount, $currency ) {
		return 1 === self::minor_units( 1, $currency ) ? (string) (int) round( $amount ) : number_format( (float) $amount, 2, '.', '' );
	}

	private static function link( array $o, $rel ) {
		foreach ( (array) ( $o['links'] ?? array() ) as $l ) {
			if ( $rel === ( $l['rel'] ?? '' ) || ( 'approve' === $rel && 'payer-action' === ( $l['rel'] ?? '' ) ) ) {
				return (string) $l['href'];
			}
		}
		return '';
	}

	public function supports_recurring() {
		return true;
	}

	/** The PayPal catalog product all form subscriptions use (made once per mode). */
	private function product() {
		$key = 'fw_ext_forms_paypal_product_' . ( $this->live() ? 'live' : 'sandbox' );
		$id  = (string) get_option( $key );
		if ( '' !== $id ) {
			return $id;
		}
		$r = $this->api( 'POST', 'v1/catalogs/products', array(
			'name' => substr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' ' . __( 'subscriptions', 'fw' ), 0, 127 ),
			'type' => 'SERVICE',
		), 'fw-forms-product-' . md5( home_url() ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		update_option( $key, (string) $r['id'], false );
		return (string) $r['id'];
	}

	/**
	 * The billing plan for this payment. What is due now (the order total) = the setup fee + the first
	 * period; a discount larger than the one-off part makes the first period a cheaper one-cycle trial.
	 * Plans are remembered by their terms, so equal orders share one.
	 *
	 * @return string|WP_Error
	 */
	private function plan( $payment ) {
		$currency = strtoupper( $payment->currency );
		$rec      = (array) $payment->meta['recurring'];
		$regular  = (float) $rec['amount'];
		$now      = (float) $payment->amount;
		$unit     = array( 'week' => 'WEEK', 'month' => 'MONTH', 'year' => 'YEAR' );
		$freq     = array( 'interval_unit' => $unit[ $rec['interval'] ] ?? 'MONTH', 'interval_count' => 1 );
		$cycles   = array();
		if ( $now >= $regular ) {
			$setup = $now - $regular;
		} else {
			$setup    = 0;
			$cycles[] = array( 'frequency' => $freq, 'tenure_type' => 'TRIAL', 'sequence' => 1, 'total_cycles' => 1, 'pricing_scheme' => array( 'fixed_price' => array( 'value' => self::value( $now, $currency ), 'currency_code' => $currency ) ) );
		}
		$cycles[] = array( 'frequency' => $freq, 'tenure_type' => 'REGULAR', 'sequence' => count( $cycles ) + 1, 'total_cycles' => 0, 'pricing_scheme' => array( 'fixed_price' => array( 'value' => self::value( $regular, $currency ), 'currency_code' => $currency ) ) );
		$prefs    = array( 'auto_bill_outstanding' => true, 'setup_fee_failure_action' => 'CANCEL', 'payment_failure_threshold' => 2 );
		if ( $setup > 0 ) {
			$prefs['setup_fee'] = array( 'value' => self::value( $setup, $currency ), 'currency_code' => $currency );
		}
		$product = $this->product();
		if ( is_wp_error( $product ) ) {
			return $product;
		}
		$terms = md5( wp_json_encode( array( $product, $cycles, $prefs ) ) );
		$plans = (array) get_option( 'fw_ext_forms_paypal_plans', array() );
		if ( ! empty( $plans[ $terms ] ) ) {
			return (string) $plans[ $terms ];
		}
		$r = $this->api( 'POST', 'v1/billing/plans', array(
			'product_id'          => $product,
			'name'                => substr( self::description( $payment ), 0, 127 ),
			'billing_cycles'      => $cycles,
			'payment_preferences' => $prefs,
		), 'fw-forms-plan-' . $terms );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$plans[ $terms ] = (string) $r['id'];
		update_option( 'fw_ext_forms_paypal_plans', $plans, false );
		return (string) $r['id'];
	}

	private function start_subscription( $payment ) {
		$plan = $this->plan( $payment );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		$attempt = (int) ( $payment->meta['attempts'] ?? 0 ) + 1;
		$body    = array(
			'plan_id'             => $plan,
			'custom_id'           => (string) $payment->id,
			'application_context' => array(
				'brand_name'          => substr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), 0, 127 ),
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'SUBSCRIBE_NOW',
				'return_url'          => FW_Forms_Payments::url( $payment, 'success' ),
				'cancel_url'          => FW_Forms_Payments::url( $payment, 'cancel' ),
			),
		);
		if ( is_email( $payment->email ) ) {
			$body['subscriber'] = array( 'email_address' => $payment->email );
		}
		$sub = $this->api( 'POST', 'v1/billing/subscriptions', $body, 'fw-forms-sub-' . $payment->id . '-' . $attempt );
		if ( is_wp_error( $sub ) ) {
			return $sub;
		}
		$approve = self::link( $sub, 'approve' );
		if ( '' === $approve ) {
			return new WP_Error( 'fw_forms_paypal', 'PayPal returned no approval page.' );
		}
		FW_Forms_Payments::update( $payment->id, array( 'gateway_ref' => (string) $sub['id'], 'meta' => array( 'attempts' => $attempt ) ) );
		FW_Forms_Subscriptions::update( (int) $payment->meta['subscription_id'], array( 'gateway_ref' => (string) $sub['id'] ) );
		return array( 'status' => 'redirect', 'redirect' => $approve );
	}

	/** The subscription is approved and running: its opening payment is done. */
	private function activate( $sub_ref ) {
		$sub = FW_Forms_Subscriptions::find_by_gateway_ref( 'paypal', (string) $sub_ref );
		if ( ! $sub ) {
			return;
		}
		$s = $this->api( 'GET', 'v1/billing/subscriptions/' . rawurlencode( $sub_ref ) );
		if ( is_wp_error( $s ) || 'ACTIVE' !== ( $s['status'] ?? '' ) ) {
			return;
		}
		FW_Forms_Subscriptions::set_status( $sub->id, 'active', 'paypal' );
		$p = FW_Forms_Payments::find( (int) ( $sub->meta['first_payment'] ?? 0 ) );
		if ( $p && 'pending' === $p->status ) {
			FW_Forms_Payments::set_status( $p->id, 'paid', array( 'note' => 'paypal subscription' ) );
		}
	}

	public function start( $payment, array $ctx ) {
		if ( ! empty( $payment->meta['subscription_id'] ) && ! empty( $payment->meta['recurring'] ) ) {
			return $this->start_subscription( $payment );
		}
		$currency = strtoupper( $payment->currency );
		$attempt  = (int) ( $payment->meta['attempts'] ?? 0 ) + 1;
		$o        = $this->api( 'POST', 'v2/checkout/orders', array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array( array(
				'reference_id' => 'fw-forms-' . $payment->id,
				'custom_id'    => (string) $payment->id,
				'invoice_id'   => $payment->reference . ( $attempt > 1 ? '-' . $attempt : '' ),
				'description'  => substr( self::description( $payment ), 0, 127 ),
				'amount'       => array( 'currency_code' => $currency, 'value' => self::value( $payment->amount, $currency ) ),
			) ),
			'application_context' => array(
				'brand_name'          => substr( wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), 0, 127 ),
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'PAY_NOW',
				'return_url'          => FW_Forms_Payments::url( $payment, 'success' ),
				'cancel_url'          => FW_Forms_Payments::url( $payment, 'cancel' ),
			),
		), 'fw-forms-order-' . $payment->id . '-' . $attempt );
		if ( is_wp_error( $o ) ) {
			return $o;
		}
		$approve = self::link( $o, 'approve' );
		if ( '' === $approve ) {
			return new WP_Error( 'fw_forms_paypal', 'PayPal returned no approval page.' );
		}
		FW_Forms_Payments::update( $payment->id, array( 'gateway_ref' => (string) $o['id'], 'meta' => array( 'attempts' => $attempt ) ) );
		return array( 'status' => 'redirect', 'redirect' => $approve );
	}

	/** Capture an approved order and mark the payment paid. */
	public function capture( $order_id ) {
		$p = FW_Forms_Payments::find_by_gateway_ref( 'paypal', (string) $order_id );
		if ( ! $p || 'pending' !== $p->status ) {
			return;
		}
		$o = $this->api( 'POST', 'v2/checkout/orders/' . rawurlencode( $order_id ) . '/capture', array(), 'fw-forms-capture-' . $p->id . '-' . rawurlencode( $order_id ) );
		if ( is_wp_error( $o ) && 422 === (int) ( $o->get_error_data()['status'] ?? 0 ) ) {
			$o = $this->api( 'GET', 'v2/checkout/orders/' . rawurlencode( $order_id ) ); // already captured
		}
		if ( is_wp_error( $o ) || 'COMPLETED' !== ( $o['status'] ?? '' ) ) {
			return;
		}
		$cap = (array) ( $o['purchase_units'][0]['payments']['captures'][0] ?? array() );
		FW_Forms_Payments::update( $p->id, array( 'meta' => array( 'capture_id' => (string) ( $cap['id'] ?? '' ) ) ) );
		if ( 'COMPLETED' === ( $cap['status'] ?? 'COMPLETED' ) ) {
			FW_Forms_Payments::set_status( $p->id, 'paid', array( 'note' => 'paypal' ) );
		}
	}

	public function confirm_return( $payment ) {
		if ( 'pending' !== $payment->status || '' === (string) $payment->gateway_ref ) {
			return;
		}
		if ( 0 === strpos( (string) $payment->gateway_ref, 'I-' ) ) {
			$this->activate( $payment->gateway_ref );
		} else {
			$this->capture( $payment->gateway_ref );
		}
	}

	private function verify( WP_REST_Request $request, array $event ) {
		$id = $this->opt( 'webhook_id', 'webhook_id' );
		if ( '' === $id ) {
			return false;
		}
		$r = $this->api( 'POST', 'v1/notifications/verify-webhook-signature', array(
			'auth_algo'         => (string) $request->get_header( 'paypal_auth_algo' ),
			'cert_url'          => (string) $request->get_header( 'paypal_cert_url' ),
			'transmission_id'   => (string) $request->get_header( 'paypal_transmission_id' ),
			'transmission_sig'  => (string) $request->get_header( 'paypal_transmission_sig' ),
			'transmission_time' => (string) $request->get_header( 'paypal_transmission_time' ),
			'webhook_id'        => $id,
			'webhook_event'     => $event,
		) );
		return ! is_wp_error( $r ) && 'SUCCESS' === ( $r['verification_status'] ?? '' );
	}

	public function webhook( WP_REST_Request $request ) {
		$event = json_decode( (string) $request->get_body(), true );
		if ( ! is_array( $event ) || ! $this->verify( $request, $event ) ) {
			return new WP_Error( 'fw_forms_signature', 'Invalid signature.', array( 'status' => 400 ) );
		}
		$r = (array) ( $event['resource'] ?? array() );
		switch ( (string) ( $event['event_type'] ?? '' ) ) {
			case 'CHECKOUT.ORDER.APPROVED':
				$this->capture( (string) ( $r['id'] ?? '' ) );
				break;
			case 'PAYMENT.CAPTURE.COMPLETED':
			case 'PAYMENT.CAPTURE.DENIED':
				$p = FW_Forms_Payments::find( (int) ( $r['custom_id'] ?? 0 ) );
				if ( $p && 'paypal' === $p->gateway && 'pending' === $p->status ) {
					FW_Forms_Payments::update( $p->id, array( 'meta' => array( 'capture_id' => (string) ( $r['id'] ?? '' ) ) ) );
					FW_Forms_Payments::set_status( $p->id, 'PAYMENT.CAPTURE.COMPLETED' === $event['event_type'] ? 'paid' : 'failed', array( 'note' => 'paypal' ) );
				}
				break;
			case 'BILLING.SUBSCRIPTION.ACTIVATED':
				$this->activate( (string) ( $r['id'] ?? '' ) );
				break;
			case 'PAYMENT.SALE.COMPLETED':
				// A subscription charge: the first one belongs to the opening payment, later ones are renewals.
				$sub = FW_Forms_Subscriptions::find_by_gateway_ref( 'paypal', (string) ( $r['billing_agreement_id'] ?? '' ) );
				if ( $sub ) {
					$first = FW_Forms_Payments::find( (int) ( $sub->meta['first_payment'] ?? 0 ) );
					$amt   = (float) ( $r['amount']['total'] ?? 0 );
					$sales = $first ? (array) ( $first->meta['sale_ids'] ?? array() ) : array();
					$got   = $first ? (float) ( $first->meta['sales_total'] ?? 0 ) : 0;
					// The setup fee and the first period may come as two charges: both belong to the
					// opening payment until its amount is covered; anything after is a renewal.
					if ( $first && ! in_array( (string) ( $r['id'] ?? '' ), $sales, true ) && $got + $amt <= $first->amount + 0.01 ) {
						$sales[] = (string) ( $r['id'] ?? '' );
						FW_Forms_Payments::update( $first->id, array( 'meta' => array( 'sale_ids' => $sales, 'sale_id' => $sales[0], 'sales_total' => $got + $amt ) ) );
						if ( 'pending' === $first->status ) {
							FW_Forms_Payments::set_status( $first->id, 'paid', array( 'note' => 'paypal sale' ) );
						}
						FW_Forms_Subscriptions::set_status( $sub->id, 'active', 'paypal' );
					} elseif ( $first && in_array( (string) ( $r['id'] ?? '' ), $sales, true ) ) {
						break; // the same notification again
					} else {
						FW_Forms_Subscriptions::renewal( $sub, (float) ( $r['amount']['total'] ?? 0 ), (string) ( $r['id'] ?? '' ), array( 'sale_id' => (string) ( $r['id'] ?? '' ) ) );
					}
				}
				break;
			case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
				$sub = FW_Forms_Subscriptions::find_by_gateway_ref( 'paypal', (string) ( $r['id'] ?? '' ) );
				if ( $sub && 'active' === $sub->status ) {
					FW_Forms_Subscriptions::set_status( $sub->id, 'past_due', 'paypal' );
				}
				break;
			case 'BILLING.SUBSCRIPTION.CANCELLED':
			case 'BILLING.SUBSCRIPTION.EXPIRED':
			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$sub = FW_Forms_Subscriptions::find_by_gateway_ref( 'paypal', (string) ( $r['id'] ?? '' ) );
				if ( $sub ) {
					$map = array( 'BILLING.SUBSCRIPTION.CANCELLED' => 'cancelled', 'BILLING.SUBSCRIPTION.EXPIRED' => 'ended', 'BILLING.SUBSCRIPTION.SUSPENDED' => 'past_due' );
					FW_Forms_Subscriptions::set_status( $sub->id, $map[ $event['event_type'] ], 'paypal' );
				}
				break;
			case 'PAYMENT.SALE.REFUNDED':
				$p = FW_Forms_Payments::find_by_meta( 'sale_id', (string) ( $r['sale_id'] ?? '' ) );
				if ( $p && 'paypal' === $p->gateway ) {
					FW_Forms_Payments::set_status( $p->id, 'refunded', array( 'note' => 'paypal' ) );
				}
				break;
			case 'PAYMENT.CAPTURE.REFUNDED':
				$cap = '';
				foreach ( (array) ( $r['links'] ?? array() ) as $l ) {
					if ( 'up' === ( $l['rel'] ?? '' ) && preg_match( '#/captures/([^/]+)$#', (string) $l['href'], $m ) ) {
						$cap = $m[1];
					}
				}
				$p = FW_Forms_Payments::find_by_meta( 'capture_id', '' !== $cap ? $cap : (string) ( $r['id'] ?? '' ) );
				if ( $p && 'paypal' === $p->gateway ) {
					FW_Forms_Payments::set_status( $p->id, 'refunded', array( 'note' => 'paypal' ) );
				}
				break;
		}
		return true;
	}

	public function cancel_subscription( $subscription ) {
		$r = $this->api( 'POST', 'v1/billing/subscriptions/' . rawurlencode( $subscription->gateway_ref ) . '/cancel', array( 'reason' => 'Cancelled on the website' ) );
		return is_wp_error( $r ) ? $r : true;
	}

	public function refunds_online() {
		return true;
	}

	public function refund( $payment ) {
		$cap = (string) ( $payment->meta['capture_id'] ?? '' );
		if ( '' === $cap && ! empty( $payment->meta['sale_id'] ) ) {
			$currency = strtoupper( $payment->currency );
			$r        = $this->api( 'POST', 'v1/payments/sale/' . rawurlencode( $payment->meta['sale_id'] ) . '/refund', array( 'amount' => array( 'total' => self::value( $payment->amount, $currency ), 'currency' => $currency ) ), 'fw-forms-refund-' . $payment->id );
			return is_wp_error( $r ) ? $r : true;
		}
		if ( '' === $cap ) {
			return new WP_Error( 'fw_forms_paypal_refund', __( 'No PayPal payment found to refund. Refund it in PayPal, then mark it refunded here.', 'fw' ) );
		}
		$r = $this->api( 'POST', 'v2/payments/captures/' . rawurlencode( $cap ) . '/refund', array(), 'fw-forms-refund-' . $payment->id );
		return is_wp_error( $r ) ? $r : true;
	}
}
