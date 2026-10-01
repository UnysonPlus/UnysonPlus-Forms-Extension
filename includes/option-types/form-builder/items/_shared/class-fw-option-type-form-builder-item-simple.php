<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Shared base for the newer form-builder items (date, rating, hidden, consent).
 *
 * The original items each carry ~200 lines of identical plumbing — URI helper,
 * thumbnail, enqueue, localization, attribute fixing. This base owns that once,
 * and pairs with `_shared/js/simple-item.js` on the canvas side, so a new item
 * is only what is genuinely its own: options, render, validate.
 *
 * Existing items are deliberately left as they are.
 */
abstract class FW_Option_Type_Form_Builder_Item_Simple extends FW_Option_Type_Form_Builder_Item {

	/** @return string The item's human title, e.g. "Date". */
	abstract protected function title();

	/** @return string The tray hover tip, e.g. "Add a Date field". */
	abstract protected function tip();

	/**
	 * The option schema. Public for the same reason the older items made it so:
	 * other renderers (the blocks bridge) build an editor from it.
	 *
	 * @return array
	 */
	abstract public function get_options();

	/**
	 * @param string $append
	 *
	 * @return string
	 */
	protected function get_uri( $append = '' ) {
		return fw_get_framework_directory_uri(
			'/extensions/forms/includes/option-types/' . $this->get_builder_type() . '/items/' . $this->get_type() . $append
		);
	}

	/**
	 * @return string
	 */
	protected function shared_uri( $append = '' ) {
		return fw_get_framework_directory_uri(
			'/extensions/forms/includes/option-types/' . $this->get_builder_type() . '/items/_shared' . $append
		);
	}

	public function get_thumbnails() {
		return array(
			array(
				'html' =>
					'<div class="item-type-icon-title" data-hover-tip="' . esc_attr( $this->tip() ) . '">' .
					'<div class="item-type-icon"><img src="' . esc_attr( $this->get_uri( '/static/images/icon.svg' ) ) . '" alt="" /></div>' .
					'<div class="item-type-title">' . esc_html( $this->title() ) . '</div>' .
					'</div>',
			),
		);
	}

	public function enqueue_static() {
		$version = fw_ext( 'forms' )->manifest->get_version();

		// The one shared canvas factory; every simple item depends on it.
		wp_enqueue_script(
			'fw-builder-form-builder-simple-item',
			fw_min_uri( $this->shared_uri( '/js/simple-item.js' ) ),
			array( 'fw-events' ),
			$version,
			true
		);

		wp_enqueue_script(
			'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
			fw_min_uri( $this->get_uri( '/static/js/scripts.js' ) ),
			array( 'fw-events', 'fw-builder-form-builder-simple-item' ),
			$version,
			true
		);

		$css = dirname( __FILE__ ) . '/../' . $this->get_type() . '/static/css/styles.css';

		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'fw-builder-' . $this->get_builder_type() . '-item-' . $this->get_type(),
				fw_min_uri( $this->get_uri( '/static/css/styles.css' ) ),
				array(),
				$version
			);
		}

		fw()->backend->enqueue_options_static( $this->get_options() );
	}

	public function get_item_localization() {
		return array(
			'options'  => $this->get_options(),
			'l10n'     => array(
				'item_title'      => $this->title(),
				'label'           => __( 'Label', 'fw' ),
				'edit_label'      => __( 'Edit Label', 'fw' ),
				'toggle_required' => __( 'Toggle mandatory field', 'fw' ),
				'edit'            => __( 'Edit', 'fw' ),
				'delete'          => __( 'Delete', 'fw' ),
			),
			'defaults' => array(
				'type'    => $this->get_type(),
				'width'   => fw_ext( 'forms' )->get_config( 'items/width' ),
				'options' => fw_get_options_values_from_input( $this->get_options(), array() ),
			),
		);
	}

	/**
	 * Same contract as the older items: drop unknown attributes, run every
	 * option's _get_value_from_input() so stored values are always in the
	 * option type's canonical shape.
	 *
	 * @param array $attributes
	 *
	 * @return array
	 */
	protected function get_fixed_attributes( $attributes ) {
		unset( $attributes['_items'] );

		$defaults = array(
			'type'      => $this->get_type(),
			'shortcode' => false,
			'width'     => '',
			'options'   => array(),
		);

		$attributes = array_merge( $defaults, array_intersect_key( (array) $attributes, $defaults ) );

		$only = array();

		foreach ( fw_extract_only_options( $this->get_options() ) as $id => $option ) {
			if ( is_array( $attributes['options'] ) && array_key_exists( $id, $attributes['options'] ) ) {
				$option['value'] = $attributes['options'][ $id ];
			}

			$only[ $id ] = $option;
		}

		$attributes['options'] = fw_get_options_values_from_input( $only, array() );

		return $this->fix_options( $attributes );
	}

	/**
	 * Item-specific normalisation of the fixed attributes. Override when needed.
	 *
	 * @param array $attributes
	 *
	 * @return array
	 */
	protected function fix_options( array $attributes ) {
		return $attributes;
	}

	public function get_value_from_attributes( $attributes ) {
		return $this->get_fixed_attributes( $attributes );
	}

	/* ---------------------------------------------------------------------- *
	 * Helpers for subclasses
	 * ---------------------------------------------------------------------- */

	/**
	 * The two option rows every field has: label + mandatory switch.
	 *
	 * @param string $label_default
	 * @param bool   $required_default
	 *
	 * @return array A group definition.
	 */
	protected function label_group( $label_default, $required_default = true ) {
		return array(
			'g_label' => array(
				'type'    => 'group',
				'options' => array(
					array(
						'label' => array(
							'type'  => 'text',
							'label' => __( 'Label', 'fw' ),
							'desc'  => __( 'Shown above the field on the site.', 'fw' ),
							'value' => $label_default,
						),
					),
					array(
						'required' => array(
							'type'  => 'switch',
							'label' => __( 'Mandatory Field', 'fw' ),
							'desc'  => __( 'Make this field mandatory?', 'fw' ),
							'value' => $required_default,
						),
					),
				),
			),
		);
	}

	/**
	 * @return array A group with the "Instructions for Users" textarea.
	 */
	protected function info_group() {
		return array(
			'g_info' => array(
				'type'    => 'group',
				'options' => array(
					array(
						'info' => array(
							'type'  => 'textarea',
							'label' => __( 'Help Text', 'fw' ),
							'desc'  => __( 'Help text shown under the field, e.g. âA sentence or two is plenty.â It is linked to the field for screen readers.', 'fw' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Render this item's view.php with the standard variables.
	 *
	 * @param array $item
	 * @param array $attr
	 * @param array $extra
	 *
	 * @return string
	 */
	protected function view( array $item, array $attr, array $extra = array() ) {
		return fw_render_view(
			$this->locate_path( '/views/view.php', dirname( __FILE__ ) . '/../' . $this->get_type() . '/view.php' ),
			array_merge( array( 'item' => $item, 'attr' => $attr, 'options' => $item['options'] ), $extra )
		);
	}

	/**
	 * "The {label} field is required" and friends, with the label substituted.
	 *
	 * @param array  $item
	 * @param string $template
	 *
	 * @return string
	 */
	protected function msg( array $item, $template ) {
		return str_replace( '{label}', (string) ( $item['options']['label'] ?? $this->title() ), $template );
	}
}
