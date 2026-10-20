<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Picture choices — options shown as image cards ("Which room?", "Pick a style").
 *
 * Underneath it is a radio group (one answer) or a set of checkboxes (several), with the image and its
 * caption inside each label, so keyboard use, screen readers and validation work like any choice. The
 * value stored is the chosen caption(s), checked server-side against the configured choices.
 */
class FW_Option_Type_Form_Builder_Item_Image_Choice extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'image-choice';
	}

	protected function title() {
		return __( 'Picture Choices', 'fw' );
	}

	protected function tip() {
		return __( 'Add a choice shown as pictures', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Which one do you prefer?', 'fw' ) ),
			array(
				'g_imgs' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'choices' => array(
								'type'            => 'addable-box',
								'label'           => __( 'Choices', 'fw' ),
								'box-options'     => array(
									'caption' => array( 'type' => 'text', 'label' => __( 'Caption', 'fw' ), 'value' => '' ),
									'image'   => array( 'type' => 'upload', 'label' => __( 'Picture', 'fw' ), 'images_only' => true ),
								),
								'template'        => '{{- caption }}',
								'add-button-text' => __( 'Add choice', 'fw' ),
								'sortable'        => true,
								'value'           => array(),
							),
						),
						array(
							'multiple' => array(
								'type'  => 'switch',
								'label' => __( 'Allow more than one', 'fw' ),
								'value' => false,
							),
						),
						array(
							'columns' => array(
								'type'    => 'select',
								'label'   => __( 'Per row', 'fw' ),
								'value'   => '3',
								'choices' => array( '2' => '2', '3' => '3', '4' => '4' ),
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	/** @return array [ [ caption, url ] ] */
	public static function choices( array $o ) {
		$out = array();
		foreach ( (array) ( $o['choices'] ?? array() ) as $c ) {
			$cap = isset( $c['caption'] ) ? trim( (string) $c['caption'] ) : '';
			if ( '' === $cap ) {
				continue;
			}
			$url = '';
			if ( ! empty( $c['image']['attachment_id'] ) ) {
				$src = wp_get_attachment_image_url( (int) $c['image']['attachment_id'], 'medium' );
				$url = $src ? $src : '';
			}
			if ( '' === $url && ! empty( $c['image']['url'] ) ) {
				$url = (string) $c['image']['url'];
			}
			$out[] = array( 'caption' => $cap, 'url' => $url );
		}
		return $out;
	}

	public function frontend_render( array $item, $input_value ) {
		$o = $item['options'];
		$c = self::choices( $o );
		if ( ! $c ) {
			return '';
		}
		$chosen = is_array( $input_value ) ? array_map( 'strval', $input_value ) : ( is_scalar( $input_value ) && '' !== (string) $input_value ? array( (string) $input_value ) : array() );
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'choices'  => $c,
			'multiple' => ! empty( $o['multiple'] ),
			'columns'  => in_array( (string) ( $o['columns'] ?? '3' ), array( '2', '3', '4' ), true ) ? (string) $o['columns'] : '3',
			'chosen'   => $chosen,
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o       = $item['options'];
		$allowed = wp_list_pluck( self::choices( $o ), 'caption' );
		$values  = is_array( $input_value ) ? $input_value : ( is_scalar( $input_value ) && '' !== (string) $input_value ? array( $input_value ) : array() );
		if ( ! $values ) {
			return ! empty( $o['required'] ) ? $this->msg( $item, __( 'Please choose in {label}', 'fw' ) ) : null;
		}
		if ( empty( $o['multiple'] ) && count( $values ) > 1 ) {
			return $this->msg( $item, __( 'Please choose one in {label}', 'fw' ) );
		}
		foreach ( $values as $v ) {
			if ( ! is_scalar( $v ) || ! in_array( (string) $v, $allowed, true ) ) {
				return $this->msg( $item, __( 'Please choose from the pictures in {label}', 'fw' ) );
			}
		}
		return null;
	}

	public function get_value_from_item( $value ) {
		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'sanitize_text_field', array_filter( array_map( 'strval', $value ), 'strlen' ) ) );
		}
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Image_Choice' );
