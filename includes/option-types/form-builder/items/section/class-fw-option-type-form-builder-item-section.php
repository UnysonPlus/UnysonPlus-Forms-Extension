<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Section — a heading and a short explanation between fields ("About your stay", "Emergency
 * contact"). It collects nothing: no value is stored, emailed or validated.
 */
class FW_Option_Type_Form_Builder_Item_Section extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'section';
	}

	protected function title() {
		return __( 'Section', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Section heading with optional text', 'fw' );
	}

	public function get_options() {
		return array(
			array(
				'g_section' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Heading', 'fw' ),
								'value' => __( 'About you', 'fw' ),
							),
						),
						array(
							'text' => array(
								'type'  => 'textarea',
								'label' => __( 'Text', 'fw' ),
								'desc'  => __( 'Optional. A sentence or two explaining this part of the form.', 'fw' ),
								'value' => '',
							),
						),
						array(
							'level' => array(
								'type'    => 'select',
								'label'   => __( 'Heading level', 'fw' ),
								'value'   => 'h3',
								'choices' => array( 'h3' => 'H3', 'h4' => 'H4', 'h5' => 'H5' ),
							),
						),
					),
				),
			),
		);
	}

	protected function fix_options( array $attributes ) {
		$attributes['width'] = '1_1';
		return $attributes;
	}

	public function frontend_render( array $item, $input_value ) {
		$o = $item['options'];
		if ( '' === trim( (string) ( $o['label'] ?? '' ) ) && '' === trim( (string) ( $o['text'] ?? '' ) ) ) {
			return '';
		}
		return $this->view( $item, array(), array(
			'level' => in_array( $o['level'] ?? '', array( 'h3', 'h4', 'h5' ), true ) ? $o['level'] : 'h3',
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		return null;
	}

	public function get_value_from_item( $value ) {
		return '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Section' );
