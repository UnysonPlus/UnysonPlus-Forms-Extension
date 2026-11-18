<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Forms on the shared Payments extension.
 *
 * While the Payments extension is active, each of Forms' own gateways (Card, PayPal, Pay later)
 * is wrapped in this bridge, which sends every record to the side it was made on:
 *
 *   NEW payments follow Forms Settings → Payments → "Payment system". Set to the shared one,
 *   they go through the Payments extension (FW_Payments / FW_Payments_Subscriptions) and share
 *   its settings, ledger, refunds and webhooks with every other extension. Set to Forms' own
 *   (the default), they go to the wrapped gateway, exactly as before.
 *
 *   EXISTING payments and subscriptions stay where they are, whatever the setting says now.
 *
 * On the shared system a form can also offer the methods Forms has no gateway of its own for
 * (GCash, Maya, QR Ph…): each is a bridge with no wrapped gateway, id "pay_<provider>_<method>".
 *
 * How a record tells which side it is on:
 *   a payment row with meta.pay_charge             -> Payments charge id
 *   a subscription row with meta.pay_subscription  -> Payments subscription id
 *
 * The rest of Forms (and Bookings, which takes deposits through Forms) does not know the
 * difference: it keeps talking to FW_Forms_Gateway, FW_Forms_Payments and FW_Forms_Subscriptions.
 */
class FW_Forms_Gateway_Bridge extends FW_Forms_Gateway {

	const SOURCE     = 'forms';
	const ID_PREFIX  = 'pay_';
	const REF_PREFIX = 'fwp-';

	/** @var FW_Forms_Gateway|null The gateway being wrapped; null for a method only Payments has. */
	private $legacy;

	/** @var string The Payments provider id. */
	private $provider;

	/** @var string The provider's method id. */
	private $method;

	/** @var string The method's name, for a method only Payments has. */
	private $label;

	/** @var bool True while Forms itself is telling Payments about a change. */
	private static $asking = false;

	public function __construct( $legacy, $provider, $method, $label = '' ) {
		$this->legacy   = $legacy instanceof FW_Forms_Gateway ? $legacy : null;
		$this->provider = (string) $provider;
		$this->method   = (string) $method;
		$this->label    = (string) $label;
	}

	/** Anything else (the Stripe gateway's api(), apply_session()…) is the wrapped gateway's. */
	public function __call( $name, $args ) {
		return $this->legacy ? call_user_func_array( array( $this->legacy, $name ), $args ) : null;
	}

	/** @return bool New form payments go through the shared Payments extension. */
	public static function uses_shared() {
		if ( ! class_exists( 'FW_Payments' ) ) {
			return false;
		}
		$engine = (string) fw_ext( 'forms' )->get_db_settings_option( 'payments_engine', '' );
		if ( 'payments' === $engine || 'own' === $engine ) {
			return 'payments' === $engine;
		}
		// Automatic: the shared system, unless Forms has a payment set-up of its own.
		if ( null === self::$own_setup ) {
			self::$own_setup = self::has_own_setup();
		}
		return ! self::$own_setup;
	}

	/** @var bool|null Remembered for the request. */
	private static $own_setup = null;

	/**
	 * @return bool Forms has provider keys of its own (or borrows Membership's), or has taken a
	 *              payment with them: such a site stays on its own settings until its owner switches.
	 */
	public static function has_own_setup() {
		global $wpdb;
		$forms = fw_ext( 'forms' );
		foreach ( array( 'payments_stripe_test_secret', 'payments_stripe_live_secret', 'payments_paypal_client_id' ) as $key ) {
			if ( '' !== trim( (string) $forms->get_db_settings_option( $key, '' ) ) ) {
				return true;
			}
		}
		// Forms borrows Membership's keys when it has none: that is a set-up of its own too.
		$m = (array) get_option( 'fw_ext_membership_payments', array() );
		foreach ( array( 'stripe' => array( 'test_secret_key', 'live_secret_key' ), 'paypal' => array( 'client_id' ) ) as $g => $keys ) {
			foreach ( $keys as $k ) {
				if ( '' !== trim( (string) ( $m['gateways'][ $g ][ $k ] ?? '' ) ) && 'payments' !== ( $m['engine'] ?? 'own' ) ) {
					return true;
				}
			}
		}
		$t = FW_Forms_Payments::table();
		return (bool) $wpdb->get_var( "SELECT id FROM {$t} WHERE meta NOT LIKE '%\"pay_charge\"%' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Wrap Forms' gateways, and add the shared system's other methods.
	 *
	 * @param FW_Forms_Gateway[] $list id => gateway
	 *
	 * @return FW_Forms_Gateway[]
	 */
	public static function wrap( array $list ) {
		if ( ! class_exists( 'FW_Payments' ) ) {
			return $list;
		}
		foreach ( $list as $id => $g ) {
			if ( ! ( $g instanceof self ) && FW_Payments_Gateways::get( $id ) ) {
				$list[ $id ] = new self( $g, $id, $id );
			}
		}
		{
			$methods = FW_Payments::methods( array( 'currency' => FW_Forms_Money::currency() ) );
			foreach ( $methods as $m ) {
				if ( isset( $list[ $m['gateway'] ] ) ) {
					continue; // Card, PayPal and Pay later are the wrapped ones above
				}
				$id          = self::ID_PREFIX . $m['gateway'] . '_' . $m['method'];
				$list[ $id ] = new self( null, $m['gateway'], $m['method'], $m['label'] );
			}
		}
		return $list;
	}

	/**
	 * A method only Payments has, by its Forms id, for a payment whose method is no longer offered.
	 *
	 * @param string $id pay_<provider>_<method>
	 *
	 * @return self|null
	 */
	public static function resolve( $id ) {
		if ( ! class_exists( 'FW_Payments' ) || 0 !== strpos( (string) $id, self::ID_PREFIX ) ) {
			return null;
		}
		$rest = substr( (string) $id, strlen( self::ID_PREFIX ) );
		foreach ( FW_Payments_Gateways::all() as $pid => $g ) {
			if ( 0 === strpos( $rest, $pid . '_' ) ) {
				$m = substr( $rest, strlen( $pid ) + 1 );
				return new self( null, $pid, $m, $g->title() . ' · ' . $m );
			}
		}
		return null;
	}

	/** @return FW_Payments_Gateway|null */
	private function shared() {
		return class_exists( 'FW_Payments_Gateways' ) ? FW_Payments_Gateways::get( $this->provider ) : null;
	}

	private function on() {
		return ( ! $this->legacy || self::uses_shared() ) && null !== $this->shared();
	}

	/* ------------------------------------------------------------------ Identity */

	public function get_id() {
		return $this->legacy ? $this->legacy->get_id() : self::ID_PREFIX . $this->provider . '_' . $this->method;
	}

	public function get_title() {
		return $this->legacy ? $this->legacy->get_title() : $this->label;
	}

	public function title( array $form ) {
		return $this->legacy ? $this->legacy->title( $form ) : $this->label;
	}

	/** @return bool A method only the shared system has (GCash…). */
	public function is_extra() {
		return null === $this->legacy;
	}

	public function is_ready() {
		if ( ! $this->on() || ( ! $this->legacy && ! self::uses_shared() ) ) {
			return $this->legacy ? $this->legacy->is_ready() : false;
		}
		$g = $this->shared();
		return $g->is_enabled() && $g->is_ready();
	}

	public function supports_recurring() {
		if ( ! $this->on() ) {
			return $this->legacy ? $this->legacy->supports_recurring() : false;
		}
		return $this->shared()->supports_recurring();
	}

	public function refunds_online() {
		$g = $this->shared();
		return ( $g && $g->refunds_online() ) || ( $this->legacy && $this->legacy->refunds_online() );
	}

	/** The form's own instructions (its Pay later text) serve both sides. */
	public function instructions( array $pending, array $order ) {
		return $this->legacy ? $this->legacy->instructions( $pending, $order ) : '';
	}

	/* ------------------------------------------------------------------ Which side a record is on */

	/** @return int The Payments charge id of a form payment, or 0 when it is one of Forms' own. */
	public static function charge_id( $payment ) {
		return $payment ? (int) ( $payment->meta['pay_charge'] ?? 0 ) : 0;
	}

	/** @return int The Payments subscription id of a form subscription, or 0. */
	public static function subscription_id( $sub ) {
		return $sub ? (int) ( $sub->meta['pay_subscription'] ?? 0 ) : 0;
	}

	private static function asking( callable $fn ) {
		$was          = self::$asking;
		self::$asking = true;
		$r            = $fn();
		self::$asking = $was;
		return $r;
	}

	/* ------------------------------------------------------------------ Paying */

	public function start( $payment, array $ctx ) {
		// A payment that was started on Forms' own gateway is retried there ("try the payment again").
		$mine = self::charge_id( $payment ) > 0;
		if ( ! $this->on() || ( $this->legacy && ! $mine && (int) ( $payment->meta['attempts'] ?? 0 ) > 0 ) ) {
			return $this->legacy ? $this->legacy->start( $payment, $ctx ) : new WP_Error( 'fw_forms_gateway', __( 'Sorry, payments cannot be taken right now. Please try again later.', 'fw' ) );
		}

		$lines = array();
		foreach ( (array) $payment->lines as $l ) {
			$lines[] = array(
				'label'    => trim( $l['label'] . ( '' !== (string) ( $l['detail'] ?? '' ) ? ': ' . $l['detail'] : '' ) ),
				'amount'   => (float) ( $l['unit'] ?? $l['total'] ),
				'quantity' => max( 1, (int) ( $l['qty'] ?? 1 ) ),
			);
		}
		$user = get_current_user_id();
		$args = array(
			'source'      => self::SOURCE,
			'source_id'   => (int) $payment->id,
			'reference'   => (string) $payment->reference,
			'currency'    => (string) $payment->currency,
			'description' => self::description( $payment ),
			'line_items'  => $lines,
			'customer'    => array( 'user_id' => $user, 'email' => (string) $payment->email ),
			'gateway'     => $this->provider,
			'method'      => $this->method,
			'urls'        => array( 'success' => FW_Forms_Payments::url( $payment, 'success' ), 'cancel' => FW_Forms_Payments::url( $payment, 'cancel' ) ),
			'meta'        => array( 'form_id' => (string) $payment->form_id, 'entry_id' => (int) $payment->entry_id ),
		);

		// A code from the shared list has its use counted there (a code of Forms' own is not in it).
		if ( '' !== (string) ( $payment->meta['coupon'] ?? '' ) ) {
			$args['coupon']   = (string) $payment->meta['coupon'];
			$args['discount'] = (float) ( $payment->meta['discount'] ?? 0 );
		}

		$rec = $payment->meta['recurring'] ?? null;
		$sub = ! empty( $payment->meta['subscription_id'] ) && class_exists( 'FW_Forms_Subscriptions' ) ? FW_Forms_Subscriptions::find( (int) $payment->meta['subscription_id'] ) : null;
		if ( is_array( $rec ) && $sub ) {
			$coupon = $payment->meta['coupon_def'] ?? null;
			$more   = array(
				'amount'     => (float) $rec['before'],
				'bill_unit'  => (string) $rec['interval'],
				'bill_count' => 1,
				'setup_fee'  => self::one_off( $payment ),
				'due_now'    => (float) $payment->amount,
			);
			if ( is_array( $coupon ) && (float) ( $payment->meta['discount'] ?? 0 ) > 0 ) {
				$more['discount'] = array(
					'type'     => 'fixed' === $coupon['type'] ? 'fixed' : 'percent',
					'amount'   => (float) $coupon['amount'],
					'duration' => (string) ( $coupon['repeat'] ?? ( 'forever' === $coupon['duration'] ? 'forever' : 'once' ) ),
					'months'   => (int) ( $coupon['months'] ?? 0 ),
					'code'     => (string) $coupon['code'],
				);
			}
			$r = FW_Payments_Subscriptions::start( array_merge( $args, $more ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			FW_Forms_Subscriptions::update( $sub->id, array( 'gateway_ref' => 'ps_' . (int) $r['id'], 'meta' => array( 'pay_subscription' => (int) $r['id'] ) ) );
			FW_Forms_Payments::update( $payment->id, array( 'gateway_ref' => self::REF_PREFIX . (int) $r['charge_id'], 'meta' => array( 'pay_charge' => (int) $r['charge_id'], 'pay_subscription' => (int) $r['id'] ) ) );
			return array( 'status' => 'redirect', 'redirect' => (string) $r['redirect'] );
		}

		// "Try the payment again" on a charge that is still open: a fresh one replaces it.
		if ( $mine ) {
			self::asking( function () use ( $payment ) {
				FW_Payments::cancelled( self::charge_id( $payment ), 'A new attempt was started.' );
			} );
		}
		$args['amount'] = (float) $payment->amount;
		$r              = FW_Payments::charge( $args );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		FW_Forms_Payments::update( $payment->id, array( 'gateway_ref' => self::REF_PREFIX . (int) $r['id'], 'meta' => array( 'pay_charge' => (int) $r['id'] ) ) );
		if ( 'paid' === $r['status'] ) {
			return array( 'status' => 'paid', 'gateway_ref' => self::REF_PREFIX . (int) $r['id'] );
		}
		if ( ! empty( $r['redirect'] ) ) {
			return array( 'status' => 'redirect', 'redirect' => (string) $r['redirect'] );
		}
		return array( 'status' => 'pending' );
	}

	public function confirm_return( $payment ) {
		$cid = self::charge_id( $payment );
		if ( ! $cid ) {
			if ( $this->legacy ) {
				$this->legacy->confirm_return( $payment );
			}
			return;
		}
		$charge = FW_Payments::get( $cid );
		$g      = $charge ? FW_Payments_Gateways::get( $charge['gateway'] ) : null;
		if ( $g && in_array( $charge['status'], array( 'pending', 'waiting' ), true ) ) {
			$g->confirm_return( $charge ); // the paid hook below marks the form payment
		}
	}

	/** Forms' own webhook address keeps serving the payments made on Forms' own gateways. */
	public function webhook( WP_REST_Request $request ) {
		return $this->legacy ? $this->legacy->webhook( $request ) : parent::webhook( $request );
	}

	public function refund( $payment ) {
		$cid = self::charge_id( $payment );
		if ( ! $cid ) {
			return $this->legacy ? $this->legacy->refund( $payment ) : parent::refund( $payment );
		}
		return self::asking( function () use ( $cid ) {
			$charge = FW_Payments::get( $cid );
			if ( $charge && 'refunded' === $charge['status'] ) {
				return true;
			}
			return FW_Payments::refund( $cid );
		} );
	}

	public function cancel_subscription( $subscription ) {
		$sid = self::subscription_id( $subscription );
		if ( ! $sid ) {
			return $this->legacy ? $this->legacy->cancel_subscription( $subscription ) : parent::cancel_subscription( $subscription );
		}
		return self::asking( function () use ( $sid ) {
			$s = FW_Payments_Subscriptions::get( $sid );
			// One that never started has nothing to stop.
			if ( ! $s || in_array( $s['status'], array( 'pending', 'cancelling', 'ended' ), true ) ) {
				return true;
			}
			return FW_Payments_Subscriptions::cancel( $sid, false );
		} );
	}

	/* ------------------------------------------------------------------ Hearing from Payments */

	/** Registered whether or not Payments has loaded yet: its hooks simply never fire without it. */
	public static function listen() {
		add_action( 'unysonplus_payments_paid', array( __CLASS__, '_paid' ) );
		add_action( 'unysonplus_payments_failed', array( __CLASS__, '_failed' ) );
		add_action( 'unysonplus_payments_cancelled', array( __CLASS__, '_cancelled' ) );
		add_action( 'unysonplus_payments_refunded', array( __CLASS__, '_refunded' ), 10, 2 );
		add_action( 'unysonplus_payments_subscription_activated', array( __CLASS__, '_sub_active' ) );
		add_action( 'unysonplus_payments_subscription_resumed', array( __CLASS__, '_sub_active' ) );
		add_action( 'unysonplus_payments_subscription_renewed', array( __CLASS__, '_sub_renewed' ), 10, 2 );
		add_action( 'unysonplus_payments_subscription_renewal_failed', array( __CLASS__, '_sub_past_due' ) );
		add_action( 'unysonplus_payments_subscription_cancelling', array( __CLASS__, '_sub_cancelling' ) );
		add_action( 'unysonplus_payments_subscription_ended', array( __CLASS__, '_sub_ended' ) );
		// And the other way: "Mark paid / cancelled / refunded" on a form entry.
		add_action( 'fw_ext_forms_payment_status', array( __CLASS__, '_form_status' ), 10, 2 );
		add_action( 'fw_extension_settings_form_saved:forms', array( __CLASS__, '_settings_saved' ) );
		add_action( 'admin_notices', array( __CLASS__, '_engine_notice' ) );
	}

	/** The form payment a charge belongs to (the first payment, not a renewal's). */
	private static function payment_of( $charge ) {
		if ( ! is_array( $charge ) || self::SOURCE !== $charge['source'] || 'payment' !== ( $charge['type'] ?? 'payment' ) ) {
			return null;
		}
		$p = FW_Forms_Payments::find( (int) $charge['source_id'] );
		return $p && self::charge_id( $p ) === (int) $charge['id'] ? $p : null;
	}

	/** The form subscription a Payments subscription belongs to. */
	private static function sub_of( $sub ) {
		if ( ! is_array( $sub ) || self::SOURCE !== $sub['source'] || ! class_exists( 'FW_Forms_Subscriptions' ) ) {
			return null;
		}
		$p = FW_Forms_Payments::find( (int) $sub['source_id'] );
		$s = $p && ! empty( $p->meta['subscription_id'] ) ? FW_Forms_Subscriptions::find( (int) $p->meta['subscription_id'] ) : null;
		return $s && self::subscription_id( $s ) === (int) $sub['id'] ? $s : null;
	}

	/** @internal */
	public static function _paid( $charge ) {
		$p = self::$asking ? null : self::payment_of( $charge );
		if ( ! $p || 'paid' === $p->status ) {
			return;
		}
		if ( abs( (float) $charge['amount'] - $p->amount ) > 0.004 ) {
			FW_Forms_Payments::update( $p->id, array( 'amount' => (float) $charge['amount'] ) );
		}
		self::asking( function () use ( $p, $charge ) {
			FW_Forms_Payments::set_status( $p->id, 'paid', array( 'note' => 'payments #' . $charge['id'] ) );
		} );
	}

	/** @internal */
	public static function _failed( $charge ) {
		$p = self::$asking ? null : self::payment_of( $charge );
		if ( $p && 'pending' === $p->status ) {
			self::asking( function () use ( $p ) {
				FW_Forms_Payments::set_status( $p->id, 'failed', array( 'note' => 'payments' ) );
			} );
		}
	}

	/** @internal */
	public static function _cancelled( $charge ) {
		$p = self::$asking ? null : self::payment_of( $charge );
		if ( $p && 'pending' === $p->status ) {
			self::asking( function () use ( $p ) {
				FW_Forms_Payments::set_status( $p->id, 'cancelled', array( 'note' => 'payments' ) );
			} );
		}
	}

	/** @internal A form payment has no "part refunded": it is refunded once everything went back. */
	public static function _refunded( $charge, $amount ) {
		$p = self::$asking ? null : self::payment_of( $charge );
		if ( $p && 'paid' === $p->status && 'refunded' === $charge['status'] ) {
			self::asking( function () use ( $p ) {
				FW_Forms_Payments::set_status( $p->id, 'refunded', array( 'note' => 'payments' ) );
			} );
		}
	}

	/** @internal */
	public static function _sub_active( $sub ) {
		$s = self::sub_of( $sub );
		if ( $s && in_array( $s->status, array( 'pending', 'past_due', 'cancelled' ), true ) ) {
			FW_Forms_Subscriptions::set_status( $s->id, 'active', 'payments' );
		}
	}

	/** @internal Each renewal is a new paid payment on the entry. */
	public static function _sub_renewed( $sub, $charge ) {
		$s = self::sub_of( $sub );
		if ( $s && is_array( $charge ) ) {
			FW_Forms_Subscriptions::renewal( $s, (float) $charge['amount'], self::REF_PREFIX . (int) $charge['id'], array( 'pay_renewal' => (int) $charge['id'] ) );
		}
	}

	/** @internal */
	public static function _sub_past_due( $sub ) {
		$s = self::sub_of( $sub );
		if ( $s && 'active' === $s->status ) {
			FW_Forms_Subscriptions::set_status( $s->id, 'past_due', 'payments' );
		}
	}

	/** @internal */
	public static function _sub_cancelling( $sub ) {
		$s = self::$asking ? null : self::sub_of( $sub );
		if ( $s && in_array( $s->status, array( 'active', 'past_due' ), true ) ) {
			FW_Forms_Subscriptions::set_status( $s->id, 'cancelled', 'payments' );
		}
	}

	/** @internal */
	public static function _sub_ended( $sub ) {
		$s = self::sub_of( $sub );
		if ( $s && 'ended' !== $s->status ) {
			FW_Forms_Subscriptions::set_status( $s->id, 'ended', 'payments' );
		}
	}

	/**
	 * @internal An admin marked a form payment by hand: the shared ledger follows.
	 */
	public static function _form_status( $payment, $old ) {
		$cid = self::charge_id( $payment );
		if ( self::$asking || ! $cid || ! class_exists( 'FW_Payments' ) ) {
			return;
		}
		self::asking( function () use ( $payment, $cid ) {
			$charge = FW_Payments::get( $cid );
			if ( ! $charge ) {
				return;
			}
			switch ( $payment->status ) {
				case 'paid':
					FW_Payments::paid( $cid, array( 'note' => 'Marked paid on the form entry.' ) );
					break;
				case 'cancelled':
					FW_Payments::cancelled( $cid, 'Marked cancelled on the form entry.' );
					break;
				case 'refunded':
					if ( in_array( $charge['status'], array( 'paid', 'part_refunded' ), true ) ) {
						FW_Payments::refund( $cid, null, array( 'online' => false, 'note' => 'Marked refunded on the form entry.' ) );
					}
					break;
			}
		} );
	}

	/* ------------------------------------------------------------------ The setting */

	const NOTICE = 'fw_ext_forms_engine_error';

	/** @internal Choosing the shared system switches Payments on. */
	public static function _settings_saved() {
		if ( 'payments' !== (string) fw_ext( 'forms' )->get_db_settings_option( 'payments_engine', '' ) || ! function_exists( 'fw_ext_ensure_active' ) ) {
			return;
		}
		$r = fw_ext_ensure_active( 'payments' );
		if ( is_wp_error( $r ) ) {
			fw_set_db_ext_settings_option( 'forms', 'payments_engine', 'own' );
			set_transient( self::NOTICE, $r->get_error_message(), 5 * MINUTE_IN_SECONDS );
		}
	}

	/** @internal */
	public static function _engine_notice() {
		$msg = get_transient( self::NOTICE );
		if ( ! $msg || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( self::NOTICE );
		echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Forms still takes payments with its own settings, because the shared Payments system could not be switched on: %s', 'fw' ), $msg ) ) . '</p></div>';
	}
}
