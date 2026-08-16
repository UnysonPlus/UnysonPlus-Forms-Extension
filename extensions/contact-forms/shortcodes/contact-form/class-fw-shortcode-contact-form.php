<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

class FW_Shortcode_Contact_Form extends FW_Shortcode
{
	private $restricted_types = array( 'contact-form' );

	/**
	 * @internal
	 */
	public function _init()
	{
		add_action(
			'fw_option_type_builder:page-builder:register_items',
			array($this, '_action_register_builder_item_types')
		);

		add_filter( 'fw_ext:shortcodes:collect_shortcodes_data', array(
			$this, '_filter_add_contact_form_data'
		) );
	}

	/**
	 * @internal
	 */
	public function _filter_add_contact_form_data( $structure ) {
		$data['contact_form'] = $this->get_item_data();
		return array_merge( $structure, $data );
	}

	public function _action_register_builder_item_types() {
		if (fw_ext('page-builder')) {
			require $this->get_declared_path('/includes/item/class-page-builder-contact-form-item.php');
		}
	}

	protected function _render($atts, $content = null, $tag = '')
	{
		$atts = is_array($atts) ? $atts : array();

		$form_data = array(
			'id'              => $atts['id']              ?? '',
			'form'            => $atts['form']            ?? array(),
			'email_to'        => $atts['email_to']        ?? '',
			'subject_message' => $atts['subject_message'] ?? '',
			'success_message' => $atts['success_message'] ?? '',
			'failure_message' => $atts['failure_message'] ?? '',
		);

		/**
		 * @var FW_Extension_Contact_Forms $extension
		 */
		$extension = fw_ext('contact-forms');

		/**
		 * Save form data because the extension needs to access it (by id) on form submit
		 *
		 * There is no other possibility to save form data by id because contact form is a shortcode
		 * it has no save action and we can't access it by id (we don't know in which post it is)
		 */
		$extension->_set_form_db_data($form_data['id'], $atts);

		$html = $extension->render(
			array(
				'id'                 => $form_data['id'],
				'form'               => $form_data['form'],
				'submit_button_text' => $atts['submit_button_text'] ?? '',
			),
			/**
			 * Extra options added by theme developer in shortcode options.php will be sent in form view
			 */
			array_diff_key(
				$atts,
				array(
					'width'              => true,
					'mailer'             => true,
					'submit_button_text' => true,
				),
				$form_data
			)
		);

		if ( $html === '' || $html === null ) {
			return $html;
		}

		// --- Wrapper attribute plumbing (Advanced tab) + Style-tab scoped CSS ---
		// Give the form the same wrapper surface every other element has: a base
		// class + a unique per-instance class (for scoped Style CSS), plus the CSS
		// ID / custom class / custom CSS / position / overflow / responsive-hide /
		// custom-attributes that sc_build_wrapper_attr() and its filters apply.
		if ( ! function_exists( 'sc_build_wrapper_attr' ) ) {
			return $html;
		}

		$wrap_atts                     = $atts;
		$wrap_atts['base_class']       = 'sc-contact-form';
		$wrap_atts['unique_id_prefix'] = 'cf-';

		$attr         = sc_build_wrapper_attr( $wrap_atts );
		$unique_class = function_exists( 'sc_element_unique_class' ) ? sc_element_unique_class( $wrap_atts ) : '';
		$scoped_css   = ( $unique_class !== '' ) ? $this->build_scoped_css( '.' . $unique_class, $atts ) : '';
		$attr_html    = function_exists( 'fw_attr_to_html' ) ? fw_attr_to_html( $attr ) : '';

		return $scoped_css . '<div ' . $attr_html . '>' . $html . '</div>';
	}

	/**
	 * Build the per-instance <style> block from the Style tab values. Everything is
	 * scoped to the wrapper's unique class ($sel), so two forms on a page can look
	 * different. Colours resolve through sc_color_to_css() (preset -> var(--color-*),
	 * custom -> sanitized literal); selects come from fixed whitelists.
	 *
	 * @param string $sel  Scoped selector, e.g. ".cf-abcd1234".
	 * @param array  $atts Flattened shortcode option values.
	 * @return string Empty when nothing is set, otherwise a <style> element.
	 */
	private function build_scoped_css( $sel, $atts ) {
		$col = function ( $v ) {
			return function_exists( 'sc_color_to_css' )
				? sc_color_to_css( $v, '' )
				: ( is_string( $v ) ? $v : '' );
		};
		$css = '';

		// Layout — max width + horizontal placement.
		$max = isset( $atts['form_max_width'] ) ? (string) $atts['form_max_width'] : '';
		if ( $max !== '' ) {
			$align = isset( $atts['form_align'] ) ? $atts['form_align'] : 'left';
			$mx    = ( $align === 'center' ) ? 'margin-left:auto;margin-right:auto;'
				: ( ( $align === 'right' ) ? 'margin-left:auto;' : '' );
			$css  .= $sel . '{max-width:' . esc_attr( $max ) . ';' . $mx . '}';
		}

		// Field appearance — inputs / textarea / select (never the submit button).
		$fields = $sel . ' input:not([type=submit]):not([type=button]):not([type=checkbox]):not([type=radio]),'
			. $sel . ' textarea,' . $sel . ' select';
		$fd = '';
		if ( ( $v = $col( $atts['field_bg'] ?? '' ) ) !== '' )     { $fd .= 'background:' . $v . ';'; }
		if ( ( $v = $col( $atts['field_text'] ?? '' ) ) !== '' )   { $fd .= 'color:' . $v . ';'; }
		if ( ( $v = $col( $atts['field_border'] ?? '' ) ) !== '' ) { $fd .= 'border-color:' . $v . ';'; }
		$fr  = (string) ( $atts['field_radius'] ?? '' );        if ( $fr  !== '' ) { $fd .= 'border-radius:' . esc_attr( $fr ) . ';'; }
		$fbw = (string) ( $atts['field_border_width'] ?? '' );  if ( $fbw !== '' ) { $fd .= 'border-width:' . esc_attr( $fbw ) . ';border-style:solid;'; }
		$fp  = (string) ( $atts['field_padding'] ?? '' );       if ( $fp  !== '' ) { $fd .= 'padding:' . esc_attr( $fp ) . ';'; }
		if ( $fd !== '' ) { $css .= $fields . '{' . $fd . '}'; }

		// Focus / accent.
		if ( ( $v = $col( $atts['field_focus'] ?? '' ) ) !== '' ) {
			$css .= $sel . ' input:focus,' . $sel . ' textarea:focus,' . $sel . ' select:focus{border-color:' . $v . ';outline-color:' . $v . ';}';
		}

		// Labels.
		if ( ( $v = $col( $atts['label_color'] ?? '' ) ) !== '' ) {
			$css .= $sel . ' label{color:' . $v . ';}';
		}

		// NOTE: the submit button is styled with the theme's Button presets
		// (Style / Size / Shape / Full-width / Alignment), applied as `.btn …`
		// classes on the <input> in contact-forms/views/submit.php — not here.

		return ( $css === '' ) ? '' : '<style>' . $css . '</style>';
	}

	/**
	 * Collect data for the Contact Form Shortcode itself. This data is used
	 * for now just in Page Builder, may be used by anyone else around.
	 *
	 * @since 1.0.2
	 */
	public function get_item_data() {
		/**
		 * @var FW_Shortcode_Contact_Form $shortcode
		 */
		$shortcode = fw_ext( 'shortcodes' )->get_shortcode( 'contact_form' );

		$data = shortcode_atts(
			array(
				'title'      => __( 'Contact Form', 'fw' ),
				'icon'       => $this->locate_URI( '/static/img/page_builder.png' ),
				'popup_size' => 'large'
			),
			$shortcode->get_config( 'page_builder' )
		);

		$data['mailer']          = fw_ext_mailer_is_configured();
		$data['configureMailer'] = __( 'Configure Mailer', 'fw' );
		$data['edit']            = __( 'Edit', 'fw' );
		$data['duplicate']       = __( 'Duplicate', 'fw' );
		$data['remove']          = __( 'Remove', 'fw' );
		$data['restrictedTypes'] = $this->restricted_types;
		$data['header_elements'] = $this->get_config( 'page_builder/popup_header_elements' );

		$options = $this->get_options();

		if ( $options ) {
			// NOTE: do NOT call fw()->backend->enqueue_options_static( $options )
			// here — get_item_data() runs during builder data collection and that
			// enqueue is expensive enough to time out wp-scripts. The page-builder
			// already enqueues option-type assets globally, so the Style/Advanced
			// controls (compact colour picker, code editor, …) render without it.
			// fw()->backend->enqueue_options_static( $options );

			$data['options'] = $this->transform_options( $options );

			$data['default_values'] = fw_get_options_values_from_input(
				$options, array()
			);
		}

		$data['tag'] = 'contact_form';

		return $data;
	}

	/*
	 * Puts each option into a separate array
	 * to keep it's order inside the modal dialog
	 */
	private function transform_options( $options ) {
		$transformed_options = array();
		foreach ( $options as $id => $option ) {
			if ( is_int( $id ) ) {
				/**
				 * this happens when in options array are loaded external options using fw()->theme->get_options()
				 * and the array looks like this
				 * array(
				 *    'hello' => array('type' => 'text'), // this has string key
				 *    array('hi' => array('type' => 'text')) // this has int key
				 * )
				 */
				$transformed_options[] = $option;
			} else {
				$transformed_options[] = array( $id => $option );
			}
		}

		return $transformed_options;
	}
}
