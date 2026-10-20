<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Page break — splits a form into steps. Everything after it is the next step.
 *
 * static/js/steps.js turns the breaks into steps with a progress bar and Back / Next, checking each
 * step's fields before moving on. Without the script the whole form shows at once and each break is
 * a heading for the part that follows, so the form still reads correctly. The server validates the
 * whole submission either way; after a failed submit the script opens the first step with a problem.
 */
class FW_Option_Type_Form_Builder_Item_Page_Break extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'page-break';
	}

	protected function title() {
		return __( 'Page Break', 'fw' );
	}

	protected function tip() {
		return __( 'Split the form into steps (everything after this is the next step)', 'fw' );
	}

	public function get_options() {
		return array(
			array(
				'g_break' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Next step title', 'fw' ),
								'desc'  => __( 'Names the step that starts here, e.g. "Your stay". Shown in the progress bar.', 'fw' ),
								'value' => __( 'Next step', 'fw' ),
							),
						),
						array(
							'next_label' => array( 'type' => 'text', 'label' => __( 'Next button', 'fw' ), 'value' => __( 'Next', 'fw' ) ),
						),
						array(
							'back_label' => array( 'type' => 'text', 'label' => __( 'Back button', 'fw' ), 'value' => __( 'Back', 'fw' ) ),
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
		return $this->view( $item, array(
			'class'      => 'fw-form-step-break',
			'data-title' => (string) ( $o['label'] ?? '' ),
			'data-next'  => (string) ( $o['next_label'] ?? __( 'Next', 'fw' ) ),
			'data-back'  => (string) ( $o['back_label'] ?? __( 'Back', 'fw' ) ),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		return null;
	}

	public function get_value_from_item( $value ) {
		return '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Page_Break' );
