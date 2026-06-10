<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * File-upload form field.
 *
 * The submitted file lives in $_FILES (not $_POST), so it bypasses the builder's
 * normal value pipeline (FW_Option_Type_Form_Builder::frontend_get_value_from_items
 * only collects fields present in $_POST). Threading:
 *   - frontend_validate() validates $_FILES[shortcode] with NO side effects.
 *   - _collect_uploads() (hooked on 'fw:ext:forms:collect-uploads', applied in
 *     FW_Extension_Forms::_frontend_form_save AFTER validation passes) moves the
 *     file with wp_handle_upload() and returns { shortcode => {name,file,url,type} }.
 *   - The Forms save flow merges the filename into form_values (for the email body)
 *     and the absolute path into the mailer 'attachments' (Mailer attaches it).
 */
class FW_Option_Type_Form_Builder_Item_File_Upload extends FW_Option_Type_Form_Builder_Item {
	public function get_type() {
		return 'file-upload';
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
					'<div class="item-type-icon-title" data-hover-tip="' . __( 'Add a File Upload', 'fw' ) . '">' .
					'<div class="item-type-icon"><span class="dashicons dashicons-upload"></span></div>' .
					'<div class="item-type-title">' . __( 'File Upload', 'fw' ) . '</div>' .
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
				'item_title'      => __( 'File Upload', 'fw' ),
				'label'           => __( 'Label', 'fw' ),
				'toggle_required' => __( 'Toggle mandatory field', 'fw' ),
				'edit'            => __( 'Edit', 'fw' ),
				'delete'          => __( 'Delete', 'fw' ),
				'edit_label'      => __( 'Edit Label', 'fw' ),
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
								'label' => __( 'Label', 'fw' ),
								'desc'  => __( 'Enter field label (it will be displayed on the web site)', 'fw' ),
								'value' => __( 'File Upload', 'fw' ),
							)
						),
						array(
							'required' => array(
								'type'  => 'switch',
								'label' => __( 'Mandatory Field', 'fw' ),
								'desc'  => __( 'Make this field mandatory?', 'fw' ),
								'value' => false,
							)
						),
					)
				)
			),
			array(
				'g2' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'allowed_ext' => array(
								'type'  => 'text',
								'label' => __( 'Allowed File Types', 'fw' ),
								'desc'  => __( 'Comma-separated list of allowed file extensions. Leave empty to allow any type WordPress permits.', 'fw' ),
								'value' => 'pdf, doc, docx, jpg, jpeg, png',
							)
						),
						array(
							'max_size' => array(
								'type'  => 'short-text',
								'label' => __( 'Max File Size (MB)', 'fw' ),
								'desc'  => __( 'Maximum allowed file size in megabytes. 0 = no limit (still bounded by the server).', 'fw' ),
								'value' => '5',
							)
						),
					)
				)
			),
			array(
				'g3' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'info' => array(
								'type'  => 'textarea',
								'label' => __( 'Instructions for Users', 'fw' ),
								'desc'  => __( 'The users will see these instructions in a tooltip near the field', 'fw' ),
							)
						),
					)
				)
			),
			$this->get_extra_options()
		);
	}

	protected function get_fixed_attributes( $attributes ) {
		unset( $attributes['_items'] );

		$default_attributes = array(
			'type'      => $this->get_type(),
			'shortcode' => false,
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
		$options = $item['options'];

		$attr = array(
			'type' => 'file',
			'name' => $item['shortcode'] ?? '',
			'id'   => 'id-' . fw_unique_increment(),
		);

		if ( ! empty( $options['required'] ) ) {
			$attr['required'] = 'required';
		}

		$accept = self::build_accept( $options['allowed_ext'] ?? '' );
		if ( $accept !== '' ) {
			$attr['accept'] = $accept;
		}

		return fw_render_view(
			$this->locate_path( '/views/view.php', dirname( __FILE__ ) . '/view.php' ),
			array(
				'item' => $item,
				'attr' => $attr,
			)
		);
	}

	public function frontend_validate( array $item, $input_value ) {
		// No side effects here — just validate $_FILES. The actual move happens in
		// _collect_uploads() (after the whole form validates), so a failed submit
		// never leaves an orphaned upload.
		$options   = $item['options'];
		$shortcode = $item['shortcode'] ?? '';
		$file      = isset( $_FILES[ $shortcode ] ) && is_array( $_FILES[ $shortcode ] ) ? $_FILES[ $shortcode ] : null; // phpcs:ignore

		$has_file = $file
			&& isset( $file['error'] )
			&& (int) $file['error'] !== UPLOAD_ERR_NO_FILE
			&& ! empty( $file['name'] );

		if ( ! $has_file ) {
			if ( ! empty( $options['required'] ) ) {
				return str_replace( '{label}', $options['label'], __( 'The {label} field is required', 'fw' ) );
			}
			return null; // optional + nothing uploaded
		}

		if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
			if ( (int) $file['error'] === UPLOAD_ERR_INI_SIZE || (int) $file['error'] === UPLOAD_ERR_FORM_SIZE ) {
				return sprintf( __( 'The uploaded file for "%s" exceeds the maximum allowed size.', 'fw' ), $options['label'] );
			}
			return sprintf( __( 'Could not upload the file for "%s". Please try again.', 'fw' ), $options['label'] );
		}

		$max_mb    = isset( $options['max_size'] ) ? (float) $options['max_size'] : 0;
		$max_bytes = $max_mb > 0 ? (int) round( $max_mb * 1024 * 1024 ) : 0;
		if ( $max_bytes > 0 && (int) $file['size'] > $max_bytes ) {
			return sprintf( __( 'The file is too large for "%1$s". Maximum size is %2$s MB.', 'fw' ), $options['label'], $options['max_size'] );
		}

		$allowed = self::parse_allowed_ext( $options['allowed_ext'] ?? '' );
		if ( ! empty( $allowed ) ) {
			$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, $allowed, true ) ) {
				return sprintf(
					__( 'The file type ".%1$s" is not allowed for "%2$s". Allowed types: %3$s.', 'fw' ),
					$ext,
					$options['label'],
					implode( ', ', $allowed )
				);
			}
		}

		return null;
	}

	/**
	 * Move validated uploads into the WP uploads dir and return their info.
	 * Hooked on 'fw:ext:forms:collect-uploads' (applied once, after validation).
	 *
	 * @param array $uploads { shortcode => {name,file,url,type} }
	 * @param array $shortcode_to_item { shortcode => item }
	 * @return array
	 */
	public static function _collect_uploads( $uploads, $shortcode_to_item ) {
		if ( ! is_array( $uploads ) ) {
			$uploads = array();
		}
		if ( ! is_array( $shortcode_to_item ) ) {
			return $uploads;
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		foreach ( $shortcode_to_item as $shortcode => $item ) {
			if ( ( $item['type'] ?? '' ) !== 'file-upload' ) {
				continue;
			}
			if ( empty( $_FILES[ $shortcode ] ) || ! isset( $_FILES[ $shortcode ]['error'] ) ) { // phpcs:ignore
				continue;
			}

			$file = $_FILES[ $shortcode ]; // phpcs:ignore
			if ( (int) $file['error'] !== UPLOAD_ERR_OK || empty( $file['name'] ) ) {
				continue;
			}

			$options   = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array();
			$allowed   = self::parse_allowed_ext( $options['allowed_ext'] ?? '' );
			$overrides = array( 'test_form' => false );

			if ( ! empty( $allowed ) ) {
				$mimes = array();
				foreach ( $allowed as $e ) {
					$ft = wp_check_filetype( 'x.' . $e );
					if ( ! empty( $ft['type'] ) ) {
						$mimes[ $e ] = $ft['type'];
					}
				}
				if ( ! empty( $mimes ) ) {
					$overrides['mimes'] = $mimes;
				}
			}

			$moved = wp_handle_upload( $file, $overrides );

			if ( is_array( $moved ) && empty( $moved['error'] ) && ! empty( $moved['file'] ) ) {
				$uploads[ $shortcode ] = array(
					'name' => sanitize_file_name( (string) $file['name'] ),
					'file' => $moved['file'],
					'url'  => $moved['url'] ?? '',
					'type' => $moved['type'] ?? '',
				);
			}
		}

		return $uploads;
	}

	/** Parse a comma-separated extension list → ['pdf','jpg',…] (lowercase, dot-stripped). */
	private static function parse_allowed_ext( $str ) {
		$out = array();
		foreach ( explode( ',', (string) $str ) as $piece ) {
			$e = strtolower( trim( ltrim( trim( $piece ), '.' ) ) );
			$e = preg_replace( '/[^a-z0-9]/', '', $e );
			if ( $e !== '' ) {
				$out[] = $e;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** Build an HTML "accept" attribute (".pdf,.jpg") from the allowed list. */
	private static function build_accept( $str ) {
		$exts = self::parse_allowed_ext( $str );
		if ( empty( $exts ) ) {
			return '';
		}
		return '.' . implode( ',.', $exts );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_File_Upload' );

// Move validated uploads after the form passes validation (see _collect_uploads).
add_filter( 'fw:ext:forms:collect-uploads', array( 'FW_Option_Type_Form_Builder_Item_File_Upload', '_collect_uploads' ), 10, 2 );
