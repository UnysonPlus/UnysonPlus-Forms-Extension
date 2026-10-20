<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Calculated — a figure worked out from other answers ("{nights} * 120 + {product_cleaning}"),
 * shown live and worked out again on the server (FW_Forms_Formula / FW_Forms_Order; the browser's
 * figure is never stored). As money it can be added to the total to pay. A hidden one is a
 * step in a bigger sum. Stored as the formatted result.
 */
class FW_Option_Type_Form_Builder_Item_Calculation extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'calculation';
	}

	protected function title() {
		return __( 'Calculated', 'fw' );
	}

	protected function tip() {
		return __( 'Add a figure worked out from other answers, like a price or a total', 'fw' );
	}

	public function get_options() {
		return array(
			array(
				'g_label' => array(
					'type'    => 'group',
					'options' => array(
						array( 'label' => array( 'type' => 'text', 'label' => __( 'Label', 'fw' ), 'value' => __( 'Total', 'fw' ) ) ),
					),
				),
			),
			array(
				'g_calc' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'formula' => array(
								'type'  => 'textarea',
								'label' => __( 'Formula', 'fw' ),
								'desc'  => __( 'Use other fields by their name in curly brackets, numbers, + - * / and brackets, e.g. {number_guests} * 45 + {product_1}. Also round(x), round(x, 2), ceil, floor, min, max. A Product counts as its line total, an Amount as the amount, a choice as the last number in its text ("Airport pickup (+40)" counts 40).', 'fw' ),
								'value' => '',
							),
						),
						array(
							'format' => array(
								'type'    => 'select',
								'label'   => __( 'Show as', 'fw' ),
								'value'   => 'money',
								'choices' => array( 'money' => __( 'Money (the Forms currency)', 'fw' ), 'number' => __( 'A number', 'fw' ) ),
							),
						),
						array( 'decimals' => array( 'type' => 'select', 'label' => __( 'Decimals (numbers)', 'fw' ), 'value' => '0', 'choices' => array( '0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4' ) ) ),
						array( 'prefix' => array( 'type' => 'text', 'label' => __( 'Before the number', 'fw' ), 'value' => '' ) ),
						array( 'suffix' => array( 'type' => 'text', 'label' => __( 'After the number', 'fw' ), 'desc' => __( 'e.g. nights, kg', 'fw' ), 'value' => '' ) ),
						array(
							'add_to_total' => array(
								'type'         => 'switch',
								'label'        => __( 'Add it to the total to pay', 'fw' ),
								'desc'         => __( 'For a price worked out by the form. Products and amounts are always in the total; leave this off for a calculation that only adds them up, or they count twice.', 'fw' ),
								'value'        => 'no',
								'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
						array(
							'visible' => array(
								'type'         => 'switch',
								'label'        => __( 'Show it on the form', 'fw' ),
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no', 'label' => __( 'Hidden (a step in a bigger sum)', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Shown', 'fw' ) ),
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	/** The result written the field's way. */
	public static function display( array $o, $n ) {
		if ( 'number' === ( $o['format'] ?? 'money' ) ) {
			$d = max( 0, min( 4, (int) ( $o['decimals'] ?? 0 ) ) );
			$t = number_format( (float) $n, $d );
		} else {
			$t = FW_Forms_Money::format( $n );
		}
		return trim( ( $o['prefix'] ?? '' ) . $t . ' ' . ( $o['suffix'] ?? '' ) );
	}

	public function frontend_render( array $item, $input_value ) {
		$o = $item['options'];
		$n = FW_Forms_Order::calculate( $item, FW_Forms_Order::items() ? FW_Forms_Order::items() : array(), array() );
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'shown'   => 'no' !== ( $o['visible'] ?? 'yes' ),
			'display' => self::display( $o, $n ),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		return null; // nothing to check: the figure is worked out, not typed
	}

	public function get_value_from_item( $value, $item = array(), $all = array() ) {
		if ( ! $item ) {
			return '';
		}
		return self::display( $item['options'] ?? array(), FW_Forms_Order::calculate( $item, FW_Forms_Order::items(), (array) $all ) );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Calculation' );
