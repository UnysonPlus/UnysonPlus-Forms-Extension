<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Signature — drawn with a mouse, finger or pen (static/js/fields.js), or typed as a name for anyone
 * who cannot draw. Posts `<shortcode>[image]` (a PNG data URL) and `[typed]`. The server checks the
 * image is a real PNG under 400 KB and stores it privately like a File Upload (downloadable from the
 * entry, attached to the notification email); a typed name is stored as text.
 */
class FW_Option_Type_Form_Builder_Item_Signature extends FW_Option_Type_Form_Builder_Item_Simple {

	const MAX_BYTES = 409600;

	public function get_type() {
		return 'signature';
	}

	protected function title() {
		return __( 'Signature', 'fw' );
	}

	protected function tip() {
		return __( 'Add a signature box (draw, or type a name)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Signature', 'fw' ), true ),
			array(
				'g_sig' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'allow_typed' => array(
								'type'         => 'switch',
								'label'        => __( 'Allow typing a name instead', 'fw' ),
								'desc'         => __( 'For people who cannot draw with a mouse or a finger. Leave it on unless a drawn signature is required.', 'fw' ),
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public function frontend_render( array $item, $input_value ) {
		$v = is_array( $input_value ) ? $input_value : array();
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'typed_ok' => 'no' !== ( $item['options']['allow_typed'] ?? 'yes' ),
			'typed'    => isset( $v['typed'] ) && is_scalar( $v['typed'] ) ? (string) $v['typed'] : '',
		) );
	}

	/** The decoded PNG of a posted data URL, or null. */
	public static function png( $value ) {
		$data = is_array( $value ) && isset( $value['image'] ) && is_string( $value['image'] ) ? $value['image'] : '';
		if ( 0 !== strpos( $data, 'data:image/png;base64,' ) || strlen( $data ) > self::MAX_BYTES * 1.4 ) {
			return null;
		}
		$bin = base64_decode( substr( $data, 22 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $bin || strlen( $bin ) > self::MAX_BYTES || "\x89PNG\r\n\x1a\n" !== substr( $bin, 0, 8 ) ) {
			return null;
		}
		$size = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $bin ) : array( 1, 1 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $size || $size[0] > 4000 || $size[1] > 2000 ) {
			return null;
		}
		return $bin;
	}

	private static function typed( $value, array $item ) {
		if ( 'no' === ( $item['options']['allow_typed'] ?? 'yes' ) ) {
			return '';
		}
		return is_array( $value ) && isset( $value['typed'] ) && is_scalar( $value['typed'] ) ? trim( sanitize_text_field( (string) $value['typed'] ) ) : '';
	}

	public function frontend_validate( array $item, $input_value ) {
		$has_image = is_array( $input_value ) && ! empty( $input_value['image'] );
		if ( $has_image && null === self::png( $input_value ) ) {
			return $this->msg( $item, __( 'The {label} could not be read. Please sign again.', 'fw' ) );
		}
		$typed = self::typed( $input_value, $item );
		if ( ! $has_image && '' === $typed && ! empty( $item['options']['required'] ) ) {
			return $this->msg( $item, __( 'Please sign in the {label} box', 'fw' ) );
		}
		if ( mb_strlen( $typed ) > 100 ) {
			return $this->msg( $item, __( 'The {label} name is too long', 'fw' ) );
		}
		return null;
	}

	public function get_value_from_item( $value, $item = array(), $all = array() ) {
		if ( self::png( $value ) ) {
			return __( 'Signed (drawn)', 'fw' );
		}
		$typed = $item ? self::typed( $value, $item ) : '';
		return '' !== $typed ? sprintf( __( 'Signed (typed): %s', 'fw' ), $typed ) : '';
	}

	/**
	 * Store drawn signatures privately, the way File Upload stores files (hooked on
	 * 'fw:ext:forms:collect-uploads', which runs once the form has passed validation).
	 */
	public static function _collect( $uploads, $shortcode_to_item ) {
		$uploads = is_array( $uploads ) ? $uploads : array();
		if ( ! class_exists( 'FW_Forms_Entries' ) ) {
			return $uploads;
		}
		foreach ( (array) $shortcode_to_item as $shortcode => $item ) {
			if ( 'signature' !== ( $item['type'] ?? '' ) ) {
				continue;
			}
			$png = self::png( FW_Request::POST( $shortcode ) );
			if ( ! $png ) {
				continue;
			}
			$dir  = FW_Forms_Entries::upload_dir();
			$name = wp_generate_password( 24, false, false ) . '-signature.png';
			if ( false !== file_put_contents( $dir['path'] . '/' . $name, $png ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				$uploads[ $shortcode ] = array(
					'name' => sprintf( 'signature-%s.png', wp_date( 'Y-m-d' ) ),
					'file' => $dir['path'] . '/' . $name,
					'url'  => '',
					'type' => 'image/png',
				);
			}
		}
		return $uploads;
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Signature' );
add_filter( 'fw:ext:forms:collect-uploads', array( 'FW_Option_Type_Form_Builder_Item_Signature', '_collect' ), 20, 2 );
