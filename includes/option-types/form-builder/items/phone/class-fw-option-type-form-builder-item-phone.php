<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Phone — a `type="tel"` box (the phone keypad on mobiles, autofill as `tel`).
 *
 * Accepts the ways people actually write a number: digits with spaces, dots, dashes, brackets and a
 * leading +, between 6 and 20 digits. Stored as typed, tidied (runs of spaces collapsed), because a
 * number reformatted for one country is wrong for the next.
 */
class FW_Option_Type_Form_Builder_Item_Phone extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'phone';
	}

	protected function title() {
		return __( 'Phone', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Phone number field', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Phone number', 'fw' ), false ),
			array(
				'g_phone' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'placeholder' => array(
								'type'  => 'text',
								'label' => __( 'Placeholder', 'fw' ),
								'desc'  => __( 'An example number, e.g. +1 555 010 0100. Shown inside the empty box.', 'fw' ),
								'value' => '',
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	private static function clean( $v ) {
		return trim( preg_replace( '/\s+/', ' ', is_scalar( $v ) ? (string) $v : '' ) );
	}

	public function frontend_render( array $item, $input_value ) {
		$o    = $item['options'];
		$attr = array(
			'type'         => 'tel',
			'name'         => $item['shortcode'] ?? '',
			'id'           => 'id-' . fw_unique_increment(),
			'value'        => is_null( $input_value ) ? '' : self::clean( $input_value ),
			'autocomplete' => 'tel',
			'inputmode'    => 'tel',
			'maxlength'    => '30',
			'placeholder'  => (string) ( $o['placeholder'] ?? '' ),
		);
		if ( ! empty( $o['required'] ) ) {
			$attr['required'] = 'required';
		}
		return $this->view( $item, $attr );
	}

	public function frontend_validate( array $item, $input_value ) {
		$v = self::clean( $input_value );
		if ( '' === $v ) {
			return ! empty( $item['options']['required'] ) ? $this->msg( $item, __( 'The {label} field is required', 'fw' ) ) : null;
		}
		$digits = preg_replace( '/\D/', '', $v );
		if ( ! preg_match( '/^\+?[\d\s().\-\/]+$/', $v ) || strlen( $digits ) < 6 || strlen( $digits ) > 20 ) {
			return $this->msg( $item, __( 'The {label} field needs a phone number in digits; a leading + and the country code are fine', 'fw' ) );
		}
		return null;
	}

	public function get_value_from_item( $value ) {
		return sanitize_text_field( self::clean( $value ) );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Phone' );
