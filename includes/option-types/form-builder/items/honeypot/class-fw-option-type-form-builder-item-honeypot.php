<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Honeypot anti-spam field.
 *
 * Renders a visually-hidden text input that real visitors never see or fill,
 * but most spam bots auto-fill. If it arrives non-empty, the submission is
 * rejected. The field collects no real data and is excluded from the email.
 */
class FW_Option_Type_Form_Builder_Item_Honeypot extends FW_Option_Type_Form_Builder_Item {
	public function get_type() {
		return 'honeypot';
	}

	private function get_uri( $append = '' ) {
		return fw_get_framework_directory_uri(
			'/extensions/forms/includes/option-types/' .
			$this->get_builder_type() . '/items/' .
			$this->get_type() . $append
		);
	}

	public function get_thumbnails() {
		return array(
			array(
				'html' =>
					'<div class="item-type-icon-title" data-hover-tip="' . __( 'Add an anti-spam honeypot', 'fw' ) . '">' .
					'<div class="item-type-icon"><span class="dashicons dashicons-shield-alt"></span></div>' .
					'<div class="item-type-title">' . __( 'Anti-Spam', 'fw' ) . '</div>' .
					'</div>'
			)
		);
	}

	public function enqueue_static() {
		wp_enqueue_style(
			'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
			$this->get_uri( '/static/css/styles.css' )
		);

		wp_enqueue_script(
			'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
			$this->get_uri( '/static/js/scripts.js' ),
			array( 'fw-events' ),
			false,
			true
		);

		fw()->backend->enqueue_options_static( $this->get_options() );
	}

	public function get_item_localization() {
		return array(
			'l10n'     => array(
				'item_title' => __( 'Anti-Spam (Honeypot)', 'fw' ),
				'edit'       => __( 'Edit', 'fw' ),
				'delete'     => __( 'Delete', 'fw' ),
			),
			'options'  => $this->get_options(),
			'defaults' => array(
				'type'    => $this->get_type(),
				'width'   => fw_ext( 'forms' )->get_config( 'items/width' ),
				'options' => fw_get_options_values_from_input( $this->get_options(), array() )
			)
		);
	}

	private function get_options() {
		return array(
			array(
				'g1' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Hidden Field Label', 'fw' ),
								'desc'  => __( 'Accessible label for the hidden honeypot input. Visitors never see it; it only tells assistive tech to skip the trap. Pick something a bot would want to fill, e.g. "Website" or "Leave blank".', 'fw' ),
								'value' => __( 'Leave this field empty', 'fw' ),
							)
						),
					)
				)
			),
			$this->get_extra_options()
		);
	}

	protected function get_fixed_attributes( $attributes ) {
		// no sub items
		unset( $attributes['_items'] );

		$default_attributes = array(
			'type'      => $this->get_type(),
			'shortcode' => false, // the builder generates one when empty
			'width'     => '',
			'options'   => array()
		);

		$attributes = array_intersect_key( $attributes, $default_attributes );
		$attributes = array_merge( $default_attributes, $attributes );

		$only_options = array();
		foreach ( fw_extract_only_options( $this->get_options() ) as $option_id => $option ) {
			if ( array_key_exists( $option_id, $attributes['options'] ) ) {
				$option['value'] = $attributes['options'][ $option_id ];
			}
			$only_options[ $option_id ] = $option;
		}
		$attributes['options'] = fw_get_options_values_from_input( $only_options, array() );

		return $attributes;
	}

	public function get_value_from_attributes( $attributes ) {
		return $this->get_fixed_attributes( $attributes );
	}

	public function frontend_render( array $item, $input_value ) {
		$attr = array(
			'type'         => 'text',
			'name'         => $item['shortcode'] ?? '',
			'value'        => '',
			'id'           => 'id-' . fw_unique_increment(),
			'tabindex'     => '-1',
			'autocomplete' => 'off',
		);

		return fw_render_view(
			$this->locate_path( '/views/view.php', dirname( __FILE__ ) . '/view.php' ),
			array(
				'item' => $item,
				'attr' => $attr,
			)
		);
	}

	public function frontend_validate( array $item, $input_value ) {
		// A real visitor never sees or fills this field — any value means a bot.
		if ( is_scalar( $input_value ) && trim( (string) $input_value ) !== '' ) {
			return __( 'Your submission could not be processed. Please try again.', 'fw' );
		}
		return null;
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Honeypot' );
