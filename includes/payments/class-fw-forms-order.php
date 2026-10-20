<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * What a submission costs: each Product, Amount and "add to the total" Calculated field becomes an
 * order line, worked out on the server from the form's own definition and the raw posted values
 * (never from a figure the browser sent). Also gives every field a number for formulas.
 *
 * A field's number: Product = its line total, Amount = the amount, Calculated = its result,
 * a number / range / rating = the value, and a choice (dropdown, radio, checkboxes) = the LAST
 * number in the chosen text ("Airport pickup (+40)" → 40; several ticked boxes add up). Fields
 * hidden by a "show only when" rule count as 0.
 */
class FW_Forms_Order {

	/** @var bool An order is being worked out (a coupon's number then counts 0, so a formula cannot loop). */
	private static $building = false;

	/** @var array|null shortcode => item for the form being sent (set by the form builder). */
	private static $items = null;

	/** Remember the items of the form being validated / saved in this request. */
	public static function use_items( array $items ) {
		self::$items = self::flatten( $items );
	}

	/** @return array shortcode => item */
	public static function items() {
		return is_array( self::$items ) ? self::$items : array();
	}

	/** @return array shortcode => item, nested items included. */
	public static function flatten( array $items ) {
		$out = array();
		foreach ( $items as $k => $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$sc = (string) ( $it['shortcode'] ?? ( is_string( $k ) ? $k : '' ) );
			if ( '' !== $sc ) {
				$out[ $sc ] = $it;
			}
			if ( ! empty( $it['_items'] ) && is_array( $it['_items'] ) ) {
				$out += self::flatten( $it['_items'] );
			}
		}
		return $out;
	}

	private static function visible( array $item, array $items, array $input ) {
		return ! class_exists( 'FW_Forms_Conditions' ) || FW_Forms_Conditions::is_visible( $item, array_values( $items ), $input );
	}

	/** The last number in a piece of text, or 0. */
	public static function number_in( $text ) {
		if ( is_numeric( $text ) ) {
			return (float) $text;
		}
		if ( preg_match_all( '/-?\d[\d,]*(?:\.\d+)?/', (string) $text, $m ) ) {
			return (float) str_replace( ',', '', end( $m[0] ) );
		}
		return 0.0;
	}

	/**
	 * A field's number.
	 *
	 * @param string $shortcode
	 * @param array  $items shortcode => item
	 * @param array  $input posted values
	 * @param array  $seen  formulas being worked out (stops loops)
	 */
	public static function number_of( $shortcode, array $items, array $input, array $seen = array() ) {
		if ( ! isset( $items[ $shortcode ] ) || isset( $seen[ $shortcode ] ) ) {
			return 0.0;
		}
		$item = $items[ $shortcode ];
		if ( ! self::visible( $item, $items, $input ) ) {
			return 0.0;
		}
		$v = $input[ $shortcode ] ?? null;
		switch ( $item['type'] ?? '' ) {
			case 'product':
				$l = self::product_line( $item, $v );
				return $l ? $l['total'] : 0.0;
			case 'amount':
				return self::amount_of( $item, $v );
			case 'calculation':
				$seen[ $shortcode ] = true;
				return self::calculate( $item, $items, $input, $seen );
			case 'coupon':
				// The amount off the order (0 while the order itself is being worked out).
				return self::$building ? 0.0 : (float) self::build( $items, $input )['discount'];
		}
		if ( is_array( $v ) ) {
			$sum = 0.0;
			foreach ( $v as $x ) {
				$sum += is_scalar( $x ) ? self::number_in( $x ) : 0.0;
			}
			return $sum;
		}
		return is_scalar( $v ) ? self::number_in( $v ) : 0.0;
	}

	/** A Calculated field's result, rounded to its decimals. */
	public static function calculate( array $item, array $items, array $input, array $seen = array() ) {
		$o   = $item['options'] ?? array();
		$seen[ (string) ( $item['shortcode'] ?? '' ) ] = true;
		$v   = FW_Forms_Formula::evaluate( (string) ( $o['formula'] ?? '' ), function ( $ref ) use ( $items, $input, $seen ) {
			return self::number_of( $ref, $items, $input, $seen );
		} );
		$dec = 'money' === ( $o['format'] ?? 'number' ) ? FW_Forms_Money::decimals() : max( 0, min( 4, (int) ( $o['decimals'] ?? 0 ) ) );
		return round( $v, $dec );
	}

	/* ------------------------------------------------------------------ Products and amounts */

	/** @return array [ { name, price } ] for a Product field's choices. */
	public static function product_choices( array $o ) {
		$out = array();
		foreach ( (array) ( $o['choices'] ?? array() ) as $c ) {
			$name = trim( (string) ( $c['name'] ?? '' ) );
			if ( '' !== $name ) {
				$out[] = array( 'name' => $name, 'price' => FW_Forms_Money::parse( $c['price'] ?? 0 ) );
			}
		}
		return $out;
	}

	/** @return int[] min, max quantity. */
	public static function qty_bounds( array $o ) {
		$min = max( 0, (int) ( $o['qty_min'] ?? 1 ) );
		$max = max( 1, (int) ( $o['qty_max'] ?? 10 ) );
		return array( $min, max( $min, $max ) );
	}

	/**
	 * A Product's order line from its posted value { qty, choice }, or null when nothing is bought.
	 *
	 * @return array|null { label, detail, qty, unit, total }
	 */
	public static function product_line( array $item, $value ) {
		$o     = $item['options'] ?? array();
		$v     = is_array( $value ) ? $value : array();
		$mode  = (string) ( $o['quantity'] ?? 'fixed' );
		// fixed: always one (a fee, or one of the choices); checkbox: one when ticked; box: as typed.
		$qty   = 'box' === $mode ? (int) ( $v['qty'] ?? 0 ) : ( 'checkbox' === $mode ? ( empty( $v['buy'] ) ? 0 : 1 ) : 1 );
		$label = trim( (string) ( $o['label'] ?? '' ) );
		if ( 'choices' === ( $o['mode'] ?? 'single' ) ) {
			$pick = null;
			foreach ( self::product_choices( $o ) as $c ) {
				if ( isset( $v['choice'] ) && is_scalar( $v['choice'] ) && $c['name'] === (string) $v['choice'] ) {
					$pick = $c;
				}
			}
			if ( ! $pick ) {
				return null;
			}
			$detail = $pick['name'];
			$unit   = $pick['price'];
		} else {
			$detail = '';
			$unit   = FW_Forms_Money::parse( $o['price'] ?? 0 );
		}
		if ( $qty < 1 ) {
			return null;
		}
		return array(
			'label'    => $label,
			'detail'   => $detail,
			'qty'      => $qty,
			'unit'     => FW_Forms_Money::round( $unit ),
			'total'    => FW_Forms_Money::round( $unit * $qty ),
			'interval' => self::interval( $o ),
		);
	}

	/** A Product's billing: '' (once) | week | month | year. */
	public static function interval( array $o ) {
		$b = (string) ( $o['billing'] ?? '' );
		return in_array( $b, array( 'week', 'month', 'year' ), true ) ? $b : '';
	}

	/** "/ month" after a recurring price. */
	public static function per( $interval ) {
		$map = array( 'week' => __( '/ week', 'fw' ), 'month' => __( '/ month', 'fw' ), 'year' => __( '/ year', 'fw' ) );
		return $map[ $interval ] ?? '';
	}

	/** @return float[] The suggested amounts of an Amount field. */
	public static function suggestions( array $o ) {
		$out = array();
		foreach ( explode( ',', (string) ( $o['suggested'] ?? '' ) ) as $s ) {
			$n = FW_Forms_Money::parse( $s );
			if ( $n > 0 ) {
				$out[] = FW_Forms_Money::round( $n );
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** An Amount field's chosen amount from { choice, other }. */
	public static function amount_of( array $item, $value ) {
		$v = is_array( $value ) ? $value : array( 'other' => $value );
		$c = isset( $v['choice'] ) && is_scalar( $v['choice'] ) ? (string) $v['choice'] : '';
		if ( '' !== $c && 'other' !== $c ) {
			return in_array( FW_Forms_Money::round( FW_Forms_Money::parse( $c ) ), self::suggestions( $item['options'] ?? array() ), false ) ? FW_Forms_Money::round( FW_Forms_Money::parse( $c ) ) : 0.0;
		}
		$other = isset( $v['other'] ) && is_scalar( $v['other'] ) ? trim( (string) $v['other'] ) : '';
		return '' === $other ? 0.0 : FW_Forms_Money::round( FW_Forms_Money::parse( $other ) );
	}

	/* ------------------------------------------------------------------ The order */

	/**
	 * Every line of a submission and its total.
	 *
	 * @param array $items shortcode => item (or a list)
	 * @param array $input posted values
	 * @return array {
	 *     lines: [ { field, label, detail, qty, unit, total, interval, kind? } ] (a coupon is kind "discount"),
	 *     total:     what is charged now (one-off lines + the first period of recurring ones − discount),
	 *     currency,
	 *     coupon:    the code used, or '',
	 *     discount:  the amount off now,
	 *     intervals: the billing intervals in the order (more than one cannot be charged together),
	 *     recurring: null, or { interval, amount (each period, after a "forever" coupon), before }
	 * }
	 */
	public static function build( array $items, array $input ) {
		$was             = self::$building;
		self::$building  = true;
		$order           = self::build_order( self::flatten( $items ), $input );
		self::$building  = $was;
		/**
		 * Filters a submission's order (add a fee, a discount, a tax line).
		 *
		 * @param array $order see build_order()
		 * @param array $items
		 * @param array $input
		 */
		return apply_filters( 'fw_ext_forms_order', $order, $items, $input );
	}

	/** @see build() */
	private static function build_order( array $items, array $input ) {
		$lines = array();
		foreach ( $items as $sc => $item ) {
			if ( ! self::visible( $item, $items, $input ) ) {
				continue;
			}
			$type = $item['type'] ?? '';
			$o    = $item['options'] ?? array();
			if ( 'product' === $type ) {
				$l = self::product_line( $item, $input[ $sc ] ?? null );
				if ( $l ) {
					$lines[] = array( 'field' => $sc ) + $l;
				}
			} elseif ( 'amount' === $type ) {
				$a = self::amount_of( $item, $input[ $sc ] ?? null );
				if ( $a > 0 ) {
					$lines[] = array( 'field' => $sc, 'label' => (string) ( $o['label'] ?? '' ), 'detail' => '', 'qty' => 1, 'unit' => $a, 'total' => $a, 'interval' => '' );
				}
			} elseif ( 'calculation' === $type && 'yes' === ( $o['add_to_total'] ?? 'no' ) ) {
				$c = FW_Forms_Money::round( self::calculate( $item, $items, $input ) );
				if ( 0.0 != $c ) { // phpcs:ignore Universal.Operators.StrictComparisons
					$lines[] = array( 'field' => $sc, 'label' => (string) ( $o['label'] ?? '' ), 'detail' => '', 'qty' => 1, 'unit' => $c, 'total' => $c, 'interval' => '' );
				}
			}
		}
		$total     = 0.0;
		$recurring = 0.0;
		$intervals = array();
		foreach ( $lines as $l ) {
			$total += $l['total'];
			if ( '' !== $l['interval'] ) {
				$recurring              += $l['total'];
				$intervals[ $l['interval'] ] = true;
			}
		}
		// A Coupon field with a code that works: one minus line, worked out from the sum above.
		$coupon   = '';
		$discount = 0.0;
		$per      = $recurring;
		if ( class_exists( 'FW_Forms_Coupons' ) ) {
			foreach ( $items as $sc => $item ) {
				if ( 'coupon' !== ( $item['type'] ?? '' ) || ! self::visible( $item, $items, $input ) || empty( $input[ $sc ] ) || ! is_scalar( $input[ $sc ] ) ) {
					continue;
				}
				$c = FW_Forms_Coupons::check( (string) $input[ $sc ] );
				if ( is_array( $c ) ) {
					$discount = FW_Forms_Coupons::discount( $c, $total );
					if ( $discount > 0 ) {
						$coupon  = $c['code'];
						$lines[] = array( 'field' => $sc, 'label' => sprintf( __( 'Coupon %s', 'fw' ), $c['code'] ), 'detail' => FW_Forms_Coupons::label( $c ), 'qty' => 1, 'unit' => -$discount, 'total' => -$discount, 'interval' => '', 'kind' => 'discount', 'duration' => $c['duration'] );
						$total  -= $discount;
						if ( 'forever' === $c['duration'] && $recurring > 0 ) {
							$per = $recurring - FW_Forms_Coupons::discount( $c, $recurring );
						}
					}
				}
				break;
			}
		}
		$order = array(
			'lines'     => $lines,
			'total'     => FW_Forms_Money::round( max( 0, $total ) ),
			'currency'  => FW_Forms_Money::currency(),
			'coupon'    => $coupon,
			'discount'  => $discount,
			'intervals' => array_keys( $intervals ),
			'recurring' => 1 === count( $intervals ) && $recurring > 0 ? array( 'interval' => (string) key( $intervals ), 'amount' => FW_Forms_Money::round( max( 0, $per ) ), 'before' => FW_Forms_Money::round( $recurring ) ) : null,
		);
		return $order;
	}

	/** One line as text: "Airport pickup: Return × 2 = $80". */
	public static function line_text( array $l ) {
		$name = trim( $l['label'] . ( '' !== $l['detail'] ? ': ' . $l['detail'] : '' ) );
		return $name . ( $l['qty'] > 1 ? ' × ' . $l['qty'] : '' ) . ' = ' . FW_Forms_Money::format( $l['total'] ) . ( ! empty( $l['interval'] ) ? ' ' . self::per( $l['interval'] ) : '' );
	}

	/** A Product's answer, without its label (the entry shows the label): "Full day × 2 = $320". */
	public static function answer_text( array $l ) {
		if ( '' !== $l['detail'] ) {
			return $l['detail'] . ( $l['qty'] > 1 ? ' × ' . $l['qty'] : '' ) . ' = ' . FW_Forms_Money::format( $l['total'] ) . ( ! empty( $l['interval'] ) ? ' ' . self::per( $l['interval'] ) : '' );
		}
		return ( $l['qty'] > 1 ? $l['qty'] . ' × ' . FW_Forms_Money::format( $l['unit'] ) . ' = ' : '' ) . FW_Forms_Money::format( $l['total'] ) . ( ! empty( $l['interval'] ) ? ' ' . self::per( $l['interval'] ) : '' );
	}

	/** The whole order as plain text lines plus the total. */
	public static function summary( array $order ) {
		$out = array_map( array( __CLASS__, 'line_text' ), $order['lines'] );
		$out[] = sprintf( __( 'Total: %s', 'fw' ), FW_Forms_Money::format( $order['total'], $order['currency'] ) );
		if ( ! empty( $order['recurring'] ) ) {
			$out[] = sprintf( __( 'Then %1$s %2$s', 'fw' ), FW_Forms_Money::format( $order['recurring']['amount'], $order['currency'] ), self::per( $order['recurring']['interval'] ) );
		}
		return implode( "\n", $out );
	}

	/** The order of the form being sent in this request (after validation). */
	public static function current() {
		return self::build( self::items(), (array) FW_Request::POST() );
	}
}
