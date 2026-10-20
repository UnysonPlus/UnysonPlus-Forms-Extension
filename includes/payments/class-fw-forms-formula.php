<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * A small, safe arithmetic evaluator for the Calculated field. static/js/calc.js implements the
 * same grammar, so the live figure and the server's figure agree.
 *
 *   expression  term (( + | - ) term)*
 *   term        factor (( * | / ) factor)*
 *   factor      number | {field_name} | ( expression ) | - factor | name( expression, … )
 *   functions   round(x[, decimals]) ceil floor abs min max
 *
 * {field_name} is the field's number (FW_Forms_Order::number_of). Anything else is a syntax error,
 * evaluated as 0; division by zero is 0. No code is ever run: it is a hand-written parser.
 */
class FW_Forms_Formula {

	/** @var array tokens */
	private $t = array();
	private $i = 0;
	/** @var callable ( string $field ) => float */
	private $ref;

	/**
	 * @param string   $formula
	 * @param callable $ref Resolves a {field_name}.
	 * @return float
	 */
	public static function evaluate( $formula, $ref ) {
		$p      = new self();
		$p->ref = $ref;
		$p->t   = self::tokens( (string) $formula );
		if ( null === $p->t || ! $p->t ) {
			return 0.0;
		}
		$v = $p->expr();
		if ( null === $v || $p->i !== count( $p->t ) || ! is_finite( $v ) ) {
			return 0.0;
		}
		return (float) $v;
	}

	/** @return string[] The {field_name}s a formula uses. */
	public static function refs( $formula ) {
		preg_match_all( '/\{([A-Za-z0-9_\-]+)\}/', (string) $formula, $m );
		return array_values( array_unique( $m[1] ) );
	}

	/** @return array|null [ [type, value] … ] or null on a character it does not know. */
	private static function tokens( $s ) {
		$out = array();
		$n   = strlen( $s );
		for ( $i = 0; $i < $n; ) {
			$c = $s[ $i ];
			if ( ctype_space( $c ) ) {
				$i++;
			} elseif ( ctype_digit( $c ) || '.' === $c ) {
				preg_match( '/^[0-9]*\.?[0-9]+|^[0-9]+\.?/', substr( $s, $i ), $m );
				$out[] = array( 'n', (float) $m[0] );
				$i    += strlen( $m[0] );
			} elseif ( '{' === $c && preg_match( '/^\{([A-Za-z0-9_\-]+)\}/', substr( $s, $i ), $m ) ) {
				$out[] = array( 'r', $m[1] );
				$i    += strlen( $m[0] );
			} elseif ( ctype_alpha( $c ) && preg_match( '/^[a-z]+/i', substr( $s, $i ), $m ) ) {
				$out[] = array( 'f', strtolower( $m[0] ) );
				$i    += strlen( $m[0] );
			} elseif ( false !== strpos( '+-*/(),', $c ) ) {
				$out[] = array( 'o', $c );
				$i++;
			} else {
				return null;
			}
		}
		return $out;
	}

	private function peek( $v ) {
		return isset( $this->t[ $this->i ] ) && 'o' === $this->t[ $this->i ][0] && $v === $this->t[ $this->i ][1];
	}

	private function expr() {
		$v = $this->term();
		while ( null !== $v && ( $this->peek( '+' ) || $this->peek( '-' ) ) ) {
			$op = $this->t[ $this->i++ ][1];
			$r  = $this->term();
			if ( null === $r ) {
				return null;
			}
			$v = '+' === $op ? $v + $r : $v - $r;
		}
		return $v;
	}

	private function term() {
		$v = $this->factor();
		while ( null !== $v && ( $this->peek( '*' ) || $this->peek( '/' ) ) ) {
			$op = $this->t[ $this->i++ ][1];
			$r  = $this->factor();
			if ( null === $r ) {
				return null;
			}
			$v = '*' === $op ? $v * $r : ( 0.0 == $r ? 0.0 : $v / $r ); // phpcs:ignore Universal.Operators.StrictComparisons
		}
		return $v;
	}

	private function factor() {
		if ( ! isset( $this->t[ $this->i ] ) ) {
			return null;
		}
		list( $type, $val ) = $this->t[ $this->i ];
		if ( 'n' === $type ) {
			$this->i++;
			return $val;
		}
		if ( 'r' === $type ) {
			$this->i++;
			return (float) call_user_func( $this->ref, $val );
		}
		if ( 'o' === $type && '-' === $val ) {
			$this->i++;
			$v = $this->factor();
			return null === $v ? null : -$v;
		}
		if ( 'o' === $type && '(' === $val ) {
			$this->i++;
			$v = $this->expr();
			if ( null === $v || ! $this->peek( ')' ) ) {
				return null;
			}
			$this->i++;
			return $v;
		}
		if ( 'f' === $type ) {
			$this->i++;
			if ( ! $this->peek( '(' ) ) {
				return null;
			}
			$this->i++;
			$args = array();
			do {
				$a = $this->expr();
				if ( null === $a ) {
					return null;
				}
				$args[] = $a;
			} while ( $this->peek( ',' ) && ++$this->i );
			if ( ! $this->peek( ')' ) ) {
				return null;
			}
			$this->i++;
			return self::call( $val, $args );
		}
		return null;
	}

	private static function call( $name, array $a ) {
		switch ( $name ) {
			case 'round':
				return round( $a[0], isset( $a[1] ) ? max( 0, min( 6, (int) $a[1] ) ) : 0 );
			case 'ceil':
				return ceil( $a[0] );
			case 'floor':
				return floor( $a[0] );
			case 'abs':
				return abs( $a[0] );
			case 'min':
				return min( $a );
			case 'max':
				return max( $a );
		}
		return null;
	}
}
