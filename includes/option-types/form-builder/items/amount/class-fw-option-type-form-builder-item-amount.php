<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Amount — the visitor chooses how much to pay: suggested amounts as buttons, and / or an amount
 * of their own (donations, deposits, "pay what you like"). Posts `<shortcode>[choice]` (a suggested
 * amount or "other") and `[other]`. The server checks the amount against the suggestions and the
 * minimum / maximum. Stored as "$25".
 */
class FW_Option_Type_Form_Builder_Item_Amount extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'amount';
	}

	protected function title() {
		return __( 'Amount', 'fw' );
	}

	protected function tip() {
		return __( 'Add an amount the visitor chooses (donations, deposits), with suggested amounts', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Amount', 'fw' ), true ),
			array(
				'g_amount' => array(
					'type'    => 'group',
					'options' => array(
						array( 'suggested' => array( 'type' => 'text', 'label' => __( 'Suggested amounts', 'fw' ), 'desc' => __( 'Separate them with commas, e.g. 10, 25, 50. Leave empty for a box only.', 'fw' ), 'value' => '10, 25, 50, 100' ) ),
						array(
							'allow_other' => array(
								'type'         => 'switch',
								'label'        => __( 'Let them type another amount', 'fw' ),
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
						array( 'min' => array( 'type' => 'text', 'label' => __( 'At least', 'fw' ), 'value' => '1' ) ),
						array( 'max' => array( 'type' => 'text', 'label' => __( 'At most', 'fw' ), 'desc' => __( 'Leave empty for no limit.', 'fw' ), 'value' => '' ) ),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public function frontend_render( array $item, $input_value ) {
		$o    = $item['options'];
		$v    = is_array( $input_value ) ? $input_value : array();
		$sugg = FW_Forms_Order::suggestions( $o );
		$other_ok = 'no' !== ( $o['allow_other'] ?? 'yes' ) || ! $sugg;
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'suggested' => $sugg,
			'other_ok'  => $other_ok,
			'min'       => FW_Forms_Money::parse( $o['min'] ?? 0 ),
			'max'       => '' !== trim( (string) ( $o['max'] ?? '' ) ) ? FW_Forms_Money::parse( $o['max'] ) : 0,
			'value'     => array(
				'choice' => isset( $v['choice'] ) && is_scalar( $v['choice'] ) ? (string) $v['choice'] : '',
				'other'  => isset( $v['other'] ) && is_scalar( $v['other'] ) ? (string) $v['other'] : '',
			),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o      = $item['options'];
		$v      = is_array( $input_value ) ? $input_value : array();
		$choice = isset( $v['choice'] ) && is_scalar( $v['choice'] ) ? (string) $v['choice'] : '';
		$other  = isset( $v['other'] ) && is_scalar( $v['other'] ) ? trim( (string) $v['other'] ) : '';
		$sugg   = FW_Forms_Order::suggestions( $o );
		if ( ( '' === $choice || 'other' === $choice ) && '' === $other ) {
			return ! empty( $o['required'] ) ? $this->msg( $item, __( 'Please choose or enter an amount for {label}', 'fw' ) ) : null;
		}
		if ( '' !== $choice && 'other' !== $choice && ! in_array( FW_Forms_Money::round( FW_Forms_Money::parse( $choice ) ), $sugg, false ) ) {
			return $this->msg( $item, __( 'Please choose one of the {label} amounts', 'fw' ) );
		}
		if ( ( '' === $choice || 'other' === $choice ) ) {
			if ( 'no' === ( $o['allow_other'] ?? 'yes' ) && $sugg ) {
				return $this->msg( $item, __( 'Please choose one of the {label} amounts', 'fw' ) );
			}
			if ( ! preg_match( '/^\d+([.,]\d{1,2})?$/', str_replace( array( ' ', FW_Forms_Money::symbol() ), '', $other ) ) ) {
				return $this->msg( $item, __( 'Please enter {label} as a number, e.g. 25 or 12.50', 'fw' ) );
			}
		}
		$amount = FW_Forms_Order::amount_of( $item, $v );
		$min    = FW_Forms_Money::parse( $o['min'] ?? 0 );
		$max    = '' !== trim( (string) ( $o['max'] ?? '' ) ) ? FW_Forms_Money::parse( $o['max'] ) : 0;
		if ( $amount < max( 0.01, $min ) ) {
			return $this->msg( $item, sprintf( __( '{label} must be at least %s', 'fw' ), FW_Forms_Money::format( max( 0.01, $min ) ) ) );
		}
		if ( $max > 0 && $amount > $max ) {
			return $this->msg( $item, sprintf( __( '{label} can be at most %s', 'fw' ), FW_Forms_Money::format( $max ) ) );
		}
		return null;
	}

	public function get_value_from_item( $value, $item = array(), $all = array() ) {
		$a = $item ? FW_Forms_Order::amount_of( $item, $value ) : 0;
		return $a > 0 ? FW_Forms_Money::format( $a ) : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Amount' );
