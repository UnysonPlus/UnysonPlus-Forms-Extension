<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Coupons for form payments: Forms Settings → Payments → Coupons (an addable list).
 *
 *   code      case-insensitive, stored upper-case ("SPRING10")
 *   type      percent | fixed
 *   amount    10 (= 10 % or 10 in the form currency)
 *   duration  once (the first charge) | forever (every renewal too; one-off orders: the same)
 *   expires   Y-m-d (last day it works) or empty
 *   max_uses  0 = no limit
 *
 * A Coupon field on the form takes the code (checked live through GET /wp-json/fw-forms/v1/coupon and
 * again on the server); FW_Forms_Order adds a minus line. A use is counted when the payment is
 * recorded, and given back if that payment ends cancelled or failed without being paid.
 */
class FW_Forms_Coupons {

	const USES_OPTION = 'fw_ext_forms_coupon_uses';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, '_routes' ) );
		add_action( 'fw_ext_forms_payment_created', array( __CLASS__, '_count' ) );
		add_action( 'fw_ext_forms_payment_status', array( __CLASS__, '_release' ), 10, 2 );
	}

	/** @return array code => coupon */
	public static function all() {
		$out = array();
		foreach ( (array) fw_ext( 'forms' )->get_db_settings_option( 'payments_coupons', array() ) as $c ) {
			$code = strtoupper( preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $c['code'] ?? '' ) ) );
			if ( '' === $code ) {
				continue;
			}
			$out[ $code ] = array(
				'code'     => $code,
				'type'     => 'fixed' === ( $c['type'] ?? 'percent' ) ? 'fixed' : 'percent',
				'amount'   => max( 0, FW_Forms_Money::parse( $c['amount'] ?? 0 ) ),
				'duration' => 'forever' === ( $c['duration'] ?? 'once' ) ? 'forever' : 'once',
				'expires'  => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $c['expires'] ?? '' ) ) ? (string) $c['expires'] : '',
				'max_uses' => max( 0, (int) ( $c['max_uses'] ?? 0 ) ),
			);
		}
		return $out;
	}

	public static function uses( $code ) {
		$u = (array) get_option( self::USES_OPTION, array() );
		return (int) ( $u[ strtoupper( $code ) ] ?? 0 );
	}

	/**
	 * A usable coupon for a code, or a message saying why not.
	 *
	 * @return array|string
	 */
	public static function check( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		$all  = self::all();
		if ( '' === $code || ! isset( $all[ $code ] ) || $all[ $code ]['amount'] <= 0 ) {
			return __( 'That code is not valid.', 'fw' );
		}
		$c = $all[ $code ];
		if ( '' !== $c['expires'] && wp_date( 'Y-m-d' ) > $c['expires'] ) {
			return __( 'That code has expired.', 'fw' );
		}
		if ( $c['max_uses'] && self::uses( $code ) >= $c['max_uses'] ) {
			return __( 'That code has been used up.', 'fw' );
		}
		return $c;
	}

	/** The amount off a sum. */
	public static function discount( array $c, $sum ) {
		$off = 'percent' === $c['type'] ? $sum * min( 100, $c['amount'] ) / 100 : $c['amount'];
		return FW_Forms_Money::round( min( max( 0, $sum ), $off ) );
	}

	/** "10% off" / "$5 off", plus "every payment" for a forever coupon. */
	public static function label( array $c ) {
		$t = 'percent' === $c['type']
			? sprintf( __( '%s%% off', 'fw' ), rtrim( rtrim( number_format( $c['amount'], 2, '.', '' ), '0' ), '.' ) )
			: sprintf( __( '%s off', 'fw' ), FW_Forms_Money::format( $c['amount'] ) );
		return 'forever' === $c['duration'] ? $t . ' ' . __( 'every payment', 'fw' ) : $t;
	}

	/** @internal GET /wp-json/fw-forms/v1/coupon?code= — the field's live check. */
	public static function _routes() {
		register_rest_route( 'fw-forms/v1', '/coupon', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => array( 'code' => array( 'type' => 'string', 'required' => true ) ),
			'callback'            => function ( WP_REST_Request $r ) {
				// A small brake on guessing codes: 30 checks per address per 10 minutes.
				$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
				$key = 'fw_forms_coupon_rl_' . md5( $ip );
				$n   = (int) get_transient( $key );
				if ( $n >= 30 ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Too many tries. Please wait a few minutes.', 'fw' ) ), 429 );
				}
				set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
				$c = self::check( (string) $r['code'] );
				if ( ! is_array( $c ) ) {
					return new WP_REST_Response( array( 'ok' => false, 'message' => $c ), 200 );
				}
				return new WP_REST_Response( array( 'ok' => true, 'code' => $c['code'], 'type' => $c['type'], 'amount' => $c['amount'], 'duration' => $c['duration'], 'label' => self::label( $c ) ), 200 );
			},
		) );
	}

	/** @internal Count a use when a payment with a coupon is recorded. */
	public static function _count( $payment ) {
		$code = $payment ? (string) ( $payment->meta['coupon'] ?? '' ) : '';
		if ( '' === $code ) {
			return;
		}
		$u          = (array) get_option( self::USES_OPTION, array() );
		$u[ $code ] = (int) ( $u[ $code ] ?? 0 ) + 1;
		update_option( self::USES_OPTION, $u, false );
	}

	/** @internal Give the use back when an unpaid payment is cancelled or fails. */
	public static function _release( $payment, $old ) {
		$code = $payment ? (string) ( $payment->meta['coupon'] ?? '' ) : '';
		if ( '' === $code || 'pending' !== $old || ! in_array( $payment->status, array( 'cancelled', 'failed' ), true ) ) {
			return;
		}
		$u          = (array) get_option( self::USES_OPTION, array() );
		$u[ $code ] = max( 0, (int) ( $u[ $code ] ?? 0 ) - 1 );
		update_option( self::USES_OPTION, $u, false );
	}
}
