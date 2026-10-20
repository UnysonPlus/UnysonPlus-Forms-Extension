<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Range slider — a number picked by dragging (budget, guests, how many nights).
 *
 * A native `<input type="range">` (keyboard arrows work, screen readers announce the value) with the
 * current value printed beside it (static/js/fields.js keeps it in step, with the prefix / suffix).
 * The server re-checks the bounds and the step; the value stored is the number.
 */
class FW_Option_Type_Form_Builder_Item_Range extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'range';
	}

	protected function title() {
		return __( 'Range Slider', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Range slider (pick a number by dragging)', 'fw' );
	}

	public function get_options() {
		$num = function ( $label, $value, $desc = '' ) {
			return array( 'type' => 'text', 'label' => $label, 'value' => (string) $value, 'desc' => $desc );
		};
		return array(
			$this->label_group( __( 'Budget', 'fw' ), false ),
			array(
				'g_range' => array(
					'type'    => 'group',
					'options' => array(
						array( 'min' => $num( __( 'Lowest', 'fw' ), 0 ) ),
						array( 'max' => $num( __( 'Highest', 'fw' ), 100 ) ),
						array( 'step' => $num( __( 'Step', 'fw' ), 1, __( 'How much one move changes the value.', 'fw' ) ) ),
						array( 'default' => $num( __( 'Starts at', 'fw' ), 50 ) ),
						array( 'prefix' => array( 'type' => 'text', 'label' => __( 'Before the number', 'fw' ), 'desc' => __( 'e.g. $', 'fw' ), 'value' => '' ) ),
						array( 'suffix' => array( 'type' => 'text', 'label' => __( 'After the number', 'fw' ), 'desc' => __( 'e.g. nights', 'fw' ), 'value' => '' ) ),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	/** @return float[] min, max, step, default */
	public static function bounds( array $o ) {
		$f    = function ( $v, $d ) {
			return is_numeric( $v ) ? (float) $v : $d;
		};
		$min  = $f( $o['min'] ?? 0, 0 );
		$max  = $f( $o['max'] ?? 100, 100 );
		if ( $max <= $min ) {
			$max = $min + 1;
		}
		$step = $f( $o['step'] ?? 1, 1 );
		$step = $step > 0 ? $step : 1;
		$def  = min( $max, max( $min, $f( $o['default'] ?? $min, $min ) ) );
		return array( $min, $max, $step, $def );
	}

	private static function fmt( $n ) {
		return rtrim( rtrim( number_format( (float) $n, 6, '.', '' ), '0' ), '.' );
	}

	public function frontend_render( array $item, $input_value ) {
		$o = $item['options'];
		list( $min, $max, $step, $def ) = self::bounds( $o );
		$value = is_numeric( $input_value ) ? (float) $input_value : $def;
		$id    = 'id-' . fw_unique_increment();
		return $this->view( $item, array(
			'type'          => 'range',
			'name'          => $item['shortcode'] ?? '',
			'id'            => $id,
			'min'           => self::fmt( $min ),
			'max'           => self::fmt( $max ),
			'step'          => self::fmt( $step ),
			'value'         => self::fmt( $value ),
			'data-prefix'   => (string) ( $o['prefix'] ?? '' ),
			'data-suffix'   => (string) ( $o['suffix'] ?? '' ),
			'aria-valuetext' => trim( ( $o['prefix'] ?? '' ) . self::fmt( $value ) . ' ' . ( $o['suffix'] ?? '' ) ),
		), array( 'display' => trim( ( $o['prefix'] ?? '' ) . self::fmt( $value ) . ' ' . ( $o['suffix'] ?? '' ) ) ) );
	}

	public function frontend_validate( array $item, $input_value ) {
		list( $min, $max, $step ) = self::bounds( $item['options'] );
		$v = is_scalar( $input_value ) ? trim( (string) $input_value ) : '';
		if ( '' === $v ) {
			return ! empty( $item['options']['required'] ) ? $this->msg( $item, __( 'The {label} field is required', 'fw' ) ) : null;
		}
		if ( ! is_numeric( $v ) || (float) $v < $min || (float) $v > $max ) {
			return $this->msg( $item, sprintf( __( 'The {label} field must be between %1$s and %2$s', 'fw' ), self::fmt( $min ), self::fmt( $max ) ) );
		}
		$steps = ( (float) $v - $min ) / $step;
		if ( abs( $steps - round( $steps ) ) > 1e-6 ) {
			return $this->msg( $item, __( 'The {label} field has a value the slider cannot take', 'fw' ) );
		}
		return null;
	}

	public function get_value_from_item( $value ) {
		return is_numeric( $value ) ? self::fmt( $value ) : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Range' );
