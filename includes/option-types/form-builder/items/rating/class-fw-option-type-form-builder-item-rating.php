<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Rating scale — CSAT stars (1–5), NPS (0–10), or any 1–N scale.
 *
 * Rendered as a radio group: one native <input type="radio"> per point, with
 * the visual (stars or numbered pills) done in CSS over the labels. Radios are
 * what a rating IS — one choice from a fixed set — so keyboard navigation,
 * screen readers and the browser's own required-check all work with nothing
 * added. The value stored is the plain integer, which is what a report wants.
 */
class FW_Option_Type_Form_Builder_Item_Rating extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'rating';
	}

	protected function title() {
		return __( 'Rating', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Rating scale', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'How would you rate us?', 'fw' ) ),
			array(
				'g_scale' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'scale' => array(
								'type'    => 'radio',
								'label'   => __( 'Scale', 'fw' ),
								'value'   => 'stars5',
								'choices' => array(
									'stars5' => __( '1–5 stars (satisfaction)', 'fw' ),
									'nps'    => __( '0–10 (Net Promoter Score)', 'fw' ),
									'scale10' => __( '1–10', 'fw' ),
								),
							),
						),
						array(
							'low_label' => array(
								'type'  => 'text',
								'label' => __( 'Low end label', 'fw' ),
								'desc'  => __( 'Shown under the lowest point, e.g. "Not likely". Optional.', 'fw' ),
								'value' => '',
							),
						),
						array(
							'high_label' => array(
								'type'  => 'text',
								'label' => __( 'High end label', 'fw' ),
								'desc'  => __( 'Shown under the highest point, e.g. "Very likely". Optional.', 'fw' ),
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

	/**
	 * @param string $scale
	 *
	 * @return array [ min, max, style ]
	 */
	public static function range( $scale ) {
		switch ( $scale ) {
			case 'nps':
				return array( 0, 10, 'pills' );
			case 'scale10':
				return array( 1, 10, 'pills' );
			default:
				return array( 1, 5, 'stars' );
		}
	}

	public function frontend_render( array $item, $input_value ) {
		$o = $item['options'];
		list( $min, $max, $style ) = self::range( $o['scale'] ?? 'stars5' );

		$attr = array(
			'name' => $item['shortcode'] ?? '',
			'id'   => 'id-' . fw_unique_increment(),
		);

		return $this->view( $item, $attr, array(
			'min'     => $min,
			'max'     => $max,
			'style'   => $style,
			'current' => is_null( $input_value ) ? null : (int) $input_value,
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o = $item['options'];
		list( $min, $max ) = self::range( $o['scale'] ?? 'stars5' );

		$value = is_scalar( $input_value ) ? trim( (string) $input_value ) : '';

		if ( '' === $value ) {
			return ! empty( $o['required'] ) ? $this->msg( $item, __( 'The {label} field is required', 'fw' ) ) : null;
		}

		if ( ! preg_match( '/^\d{1,2}$/', $value ) || (int) $value < $min || (int) $value > $max ) {
			return $this->msg( $item, sprintf( __( 'The {label} field must be between %1$d and %2$d', 'fw' ), $min, $max ) );
		}

		return null;
	}

	/**
	 * Store the integer, not the string the radio posted.
	 */
	public function get_value_from_item( $value ) {
		return is_scalar( $value ) && '' !== trim( (string) $value ) ? (int) $value : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Rating' );
