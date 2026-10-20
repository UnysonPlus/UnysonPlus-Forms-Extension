<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * The site's form currency (Forms Settings → Payments) and how amounts are written.
 * Whole amounts are written without decimals ("$1,500"), others with two ("$12.50");
 * zero-decimal currencies (JPY, KRW, …) are always whole.
 */
class FW_Forms_Money {

	const ZERO_DECIMAL = array( 'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF' );

	const SYMBOLS = array(
		'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AUD' => 'A$', 'CAD' => 'C$', 'NZD' => 'NZ$', 'JPY' => '¥',
		'INR' => '₹', 'IDR' => 'Rp ', 'PHP' => '₱', 'SGD' => 'S$', 'CHF' => 'CHF ', 'SEK' => 'kr ', 'NOK' => 'kr ',
		'DKK' => 'kr ', 'ZAR' => 'R ', 'BRL' => 'R$', 'MXN' => 'MX$', 'KRW' => '₩', 'THB' => '฿', 'MYR' => 'RM ',
		'HKD' => 'HK$', 'AED' => 'AED ', 'PLN' => 'zł ', 'CRC' => '₡',
	);

	/** @return string ISO 4217 code. */
	public static function currency() {
		$c = function_exists( 'fw_ext' ) && fw_ext( 'forms' ) ? (string) fw_ext( 'forms' )->get_db_settings_option( 'payments_currency', 'USD' ) : 'USD';
		$c = strtoupper( preg_replace( '/[^A-Za-z]/', '', $c ) );
		/** Filters the currency form prices and payments use. */
		return (string) apply_filters( 'fw_ext_forms_currency', 3 === strlen( $c ) ? $c : 'USD' );
	}

	public static function decimals( $currency = '' ) {
		return in_array( $currency ? $currency : self::currency(), self::ZERO_DECIMAL, true ) ? 0 : 2;
	}

	public static function symbol( $currency = '' ) {
		$currency = $currency ? $currency : self::currency();
		return self::SYMBOLS[ $currency ] ?? $currency . ' ';
	}

	/** Round to the currency's smallest unit. */
	public static function round( $n, $currency = '' ) {
		return round( (float) $n, self::decimals( $currency ) );
	}

	/** "$1,500", "$12.50", "-$3". */
	public static function format( $n, $currency = '' ) {
		$n   = self::round( $n, $currency );
		$dec = ( self::decimals( $currency ) && abs( $n - round( $n ) ) > 0.0001 ) ? 2 : 0;
		return ( $n < 0 ? '-' : '' ) . self::symbol( $currency ) . number_format( abs( $n ), $dec );
	}

	/** A price typed in an option ("1,500", "$25", "12.5") as a number; '' and junk are 0. */
	public static function parse( $v ) {
		$v = preg_replace( '/[^0-9.\-]/', '', str_replace( ',', '', (string) $v ) );
		return is_numeric( $v ) ? (float) $v : 0.0;
	}

	/** Everything the front-end script needs to write amounts the same way. */
	public static function js() {
		return array( 'currency' => self::currency(), 'symbol' => self::symbol(), 'decimals' => self::decimals() );
	}
}
