<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Coupon — a box for a discount code (Forms Settings → Payments → Coupons). "Apply" checks it live
 * (GET /wp-json/fw-forms/v1/coupon) and shows the discount; the server checks it again when the form
 * is sent (FW_Forms_Order adds the minus line). In a formula, {coupon_…} is the amount off.
 * Stored as "SPRING10 (10% off)".
 */
class FW_Option_Type_Form_Builder_Item_Coupon extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'coupon';
	}

	protected function title() {
		return __( 'Coupon', 'fw' );
	}

	protected function tip() {
		return __( 'Add a box for a discount code (for forms that take payment)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Discount code', 'fw' ), false ),
			array(
				'g_coupon' => array(
					'type'    => 'group',
					'options' => array(
						array( 'button' => array( 'type' => 'text', 'label' => __( 'Button', 'fw' ), 'value' => __( 'Apply', 'fw' ) ) ),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public function frontend_render( array $item, $input_value ) {
		$id = 'id-' . fw_unique_increment();
		return $this->view( $item, array(
			'type'           => 'text',
			'name'           => $item['shortcode'] ?? '',
			'id'             => $id,
			'value'          => is_scalar( $input_value ) ? strtoupper( (string) $input_value ) : '',
			'maxlength'      => '40',
			'autocomplete'   => 'off',
			'autocapitalize' => 'characters',
			'spellcheck'     => 'false',
		), array( 'button' => (string) ( $item['options']['button'] ?? __( 'Apply', 'fw' ) ) ) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$v = is_scalar( $input_value ) ? trim( (string) $input_value ) : '';
		if ( '' === $v ) {
			return ! empty( $item['options']['required'] ) ? $this->msg( $item, __( 'Please enter a code in {label}', 'fw' ) ) : null;
		}
		$c = FW_Forms_Coupons::check( $v );
		return is_array( $c ) ? null : $c;
	}

	public function get_value_from_item( $value, $item = array(), $all = array() ) {
		$c = is_scalar( $value ) && '' !== trim( (string) $value ) ? FW_Forms_Coupons::check( (string) $value ) : null;
		return is_array( $c ) ? $c['code'] . ' (' . FW_Forms_Coupons::label( $c ) . ')' : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Coupon' );
