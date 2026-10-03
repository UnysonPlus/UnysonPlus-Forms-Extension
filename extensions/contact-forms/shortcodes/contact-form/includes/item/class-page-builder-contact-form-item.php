<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

class Page_Builder_Contact_Form_Item extends Page_Builder_Item {
	public function get_type() {
		return 'contact-form';
	}

	private function get_shortcode_options() {
		$shortcode_instance = fw()->extensions->get( 'shortcodes' )->get_shortcode( 'contact_form' );
		return $shortcode_instance->get_options();
	}

	public function enqueue_static() {
		/**
		 * @var FW_Shortcode $cf_shortcode
		 */
		$cf_shortcode = fw()->extensions->get( 'shortcodes' )->get_shortcode( 'contact_form' );
		$uri = $cf_shortcode->get_declared_URI( '/includes/item/static' );

		wp_enqueue_style(
			$this->get_builder_type() . '_item_type_' . $this->get_type(),
			$uri. '/css/styles.css',
			array(),
			fw()->theme->manifest->get_version()
		);

		wp_enqueue_script(
			$this->get_builder_type() . '_item_type_' . $this->get_type(),
			$uri. '/js/scripts.js',
			array( 'fw-events', 'jquery' ),
			fw()->theme->manifest->get_version(),
			true
		);
	}

	/**
	 * No palette button.
	 *
	 * The contact form reaches the builder twice over. This dedicated item type is
	 * one route; the other is the generic "simple" path, which registers a button
	 * for any shortcode whose config declares `'type' => 'simple'` -- and this
	 * shortcode's config does, deliberately: it was changed from 'special' (an item
	 * type nothing implements) because every converted contact form was opening as
	 * "The shortcode contact_form not found". That fix was right, but it left two
	 * identical "Contact form" buttons sitting in the same tab.
	 *
	 * The generic one is the one to keep, because it is what makes the element
	 * editable. So this type registers no thumbnail -- while staying registered, so
	 * pages already saved with `type: 'contact-form'` still load, render and edit.
	 *
	 * @return array
	 */
	protected function get_thumbnails_data() {
		return array();
	}

	public function get_value_from_attributes( $attributes ) {
		$attributes         = is_array( $attributes ) ? $attributes : array();
		$attributes['type'] = $this->get_type();

		$options = $this->get_shortcode_options();
		if ( ! empty( $options ) ) {
			if ( empty( $attributes['atts'] ) || ! is_array( $attributes['atts'] ) ) {
				/**
				 * The options popup was never opened and there are no attributes.
				 * Extract options default values.
				 */
				$attributes['atts'] = fw_get_options_values_from_input(
					$options, array()
				);
			} else {
				/**
				 * There are saved attributes.
				 * But we need to execute the _get_value_from_input() method for all options,
				 * because some of them may be (need to be) changed (auto-generated) https://github.com/ThemeFuse/Unyson/issues/275
				 * Add the values to $option['value']
				 */
				$options = fw_extract_only_options( $options );

				foreach ( $attributes['atts'] as $option_id => $option_value ) {
					if ( isset( $options[ $option_id ] ) ) {
						$options[ $option_id ]['value'] = $option_value;
					}
				}

				$attributes['atts'] = fw_get_options_values_from_input(
					$options, array()
				);
			}
		}

		return $attributes;
	}

	public function get_shortcode_data( $atts = array() ) {
		$atts = is_array( $atts ) ? $atts : array();

		$default_width = fw_ext_builder_get_item_width( $this->get_builder_type() );

		if ( is_array( $default_width ) && ! empty( $default_width ) ) {
			end( $default_width ); // move to the last width (usually it's the biggest)
			$default_width = key( $default_width );
		} else {
			$default_width = '';
		}

		$return_atts = array(
			'width' => $default_width
		);
		if ( isset( $atts['atts'] ) && is_array( $atts['atts'] ) ) {
			$return_atts = array_merge( $return_atts, $atts['atts'] );
		}

		return array(
			'tag'  => str_replace( '-', '_', $this->get_type() ),
			'atts' => $return_atts
		);
	}
}

FW_Option_Type_Builder::register_item_type( 'Page_Builder_Contact_Form_Item' );
