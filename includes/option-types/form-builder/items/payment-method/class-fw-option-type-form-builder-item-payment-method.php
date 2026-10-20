<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Payment method — lets the payer choose how to pay (Card, PayPal, Pay later) on a form with the
 * Take payment action. Only methods that are set up (keys in Forms Settings → Payments) are shown;
 * the server checks the choice again. Without this field the action uses its own method.
 * Posts the method id; stored as its label ("Card").
 */
class FW_Option_Type_Form_Builder_Item_Payment_Method extends FW_Option_Type_Form_Builder_Item_Simple {

	const METHODS = array( 'stripe', 'paypal', 'offline' );

	public function get_type() {
		return 'payment-method';
	}

	protected function title() {
		return __( 'Payment Method', 'fw' );
	}

	protected function tip() {
		return __( 'Let the payer choose: card, PayPal or pay later (for forms that take payment)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'How would you like to pay?', 'fw' ), true ),
			array(
				'g_methods' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'methods' => array(
								'type'    => 'checkboxes',
								'label'   => __( 'Offer', 'fw' ),
								'desc'    => __( 'Methods without keys in Forms Settings → Payments are left out on the site.', 'fw' ),
								'value'   => array( 'stripe' => true, 'offline' => true ),
								'choices' => array( 'stripe' => __( 'Card (Stripe)', 'fw' ), 'paypal' => __( 'PayPal', 'fw' ), 'offline' => __( 'Pay later', 'fw' ) ),
							),
						),
						array( 'stripe_label' => array( 'type' => 'text', 'label' => __( 'Card: label', 'fw' ), 'value' => __( 'Card', 'fw' ) ) ),
						array( 'paypal_label' => array( 'type' => 'text', 'label' => __( 'PayPal: label', 'fw' ), 'value' => __( 'PayPal', 'fw' ) ) ),
						array( 'offline_label' => array( 'type' => 'text', 'label' => __( 'Pay later: label', 'fw' ), 'value' => __( 'Bank transfer', 'fw' ) ) ),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	/** @return array id => label, the methods offered AND ready. */
	public static function available( array $o ) {
		$out    = array();
		$chosen = array_keys( array_filter( (array) ( $o['methods'] ?? array() ) ) );
		foreach ( self::METHODS as $id ) {
			if ( ! in_array( $id, $chosen, true ) ) {
				continue;
			}
			$g = class_exists( 'FW_Forms_Payments' ) ? FW_Forms_Payments::gateway( $id ) : null;
			if ( $g && $g->is_ready() ) {
				$label      = trim( (string) ( $o[ $id . '_label' ] ?? '' ) );
				$out[ $id ] = '' !== $label ? $label : $g->get_title();
			}
		}
		return $out;
	}

	public function frontend_render( array $item, $input_value ) {
		$methods = self::available( $item['options'] );
		if ( ! $methods ) {
			return '';
		}
		$value = is_scalar( $input_value ) && isset( $methods[ (string) $input_value ] ) ? (string) $input_value : (string) key( $methods );
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array( 'methods' => $methods, 'value' => $value ) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$methods = self::available( $item['options'] );
		$v       = is_scalar( $input_value ) ? (string) $input_value : '';
		if ( '' === $v ) {
			return ! empty( $item['options']['required'] ) && $methods ? $this->msg( $item, __( 'Please choose how to pay', 'fw' ) ) : null;
		}
		return isset( $methods[ $v ] ) ? null : $this->msg( $item, __( 'That way to pay is not available. Please choose another.', 'fw' ) );
	}

	public function get_value_from_item( $value, $item = array(), $all = array() ) {
		$methods = $item ? self::available( $item['options'] ?? array() ) : array();
		return is_scalar( $value ) && isset( $methods[ (string) $value ] ) ? $methods[ (string) $value ] : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Payment_Method' );
