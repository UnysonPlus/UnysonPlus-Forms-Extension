<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Name — one "Full name" box, or First and Last side by side.
 *
 * Each box declares its autofill purpose (name / given-name / family-name). The value stored is one
 * readable string ("Ada Lovelace"), so entries, emails and answer tags ({name_1}) read naturally.
 */
class FW_Option_Type_Form_Builder_Item_Name extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'name';
	}

	protected function title() {
		return __( 'Name', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Name field (full name, or first and last)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Your name', 'fw' ) ),
			array(
				'g_name' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'format' => array(
								'type'    => 'radio',
								'label'   => __( 'Ask for', 'fw' ),
								'value'   => 'full',
								'choices' => array(
									'full'       => __( 'Full name (one box)', 'fw' ),
									'first_last' => __( 'First and last name (two boxes)', 'fw' ),
								),
							),
						),
						array(
							'first_label' => array( 'type' => 'text', 'label' => __( 'First name label', 'fw' ), 'value' => __( 'First name', 'fw' ) ),
						),
						array(
							'last_label' => array( 'type' => 'text', 'label' => __( 'Last name label', 'fw' ), 'value' => __( 'Last name', 'fw' ) ),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	private static function parts( $value ) {
		if ( is_array( $value ) ) {
			return array(
				'first' => is_scalar( $value['first'] ?? '' ) ? trim( (string) ( $value['first'] ?? '' ) ) : '',
				'last'  => is_scalar( $value['last'] ?? '' ) ? trim( (string) ( $value['last'] ?? '' ) ) : '',
			);
		}
		return array( 'full' => is_scalar( $value ) ? trim( (string) $value ) : '' );
	}

	public function frontend_render( array $item, $input_value ) {
		$id = 'id-' . fw_unique_increment();
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => $id ), array(
			'format' => ( $item['options']['format'] ?? 'full' ) === 'first_last' ? 'first_last' : 'full',
			'parts'  => is_null( $input_value ) ? array() : self::parts( $input_value ),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o     = $item['options'];
		$split = ( $o['format'] ?? 'full' ) === 'first_last';
		$p     = self::parts( $input_value );
		$all   = $split ? array( $p['first'] ?? '', $p['last'] ?? '' ) : array( $p['full'] ?? '' );

		foreach ( $all as $v ) {
			if ( mb_strlen( $v ) > 100 ) {
				return $this->msg( $item, __( 'The {label} field is too long', 'fw' ) );
			}
		}
		if ( ! empty( $o['required'] ) ) {
			if ( $split && ( '' === ( $p['first'] ?? '' ) || '' === ( $p['last'] ?? '' ) ) ) {
				return $this->msg( $item, __( 'Please enter both your first and last name', 'fw' ) );
			}
			if ( ! $split && '' === ( $p['full'] ?? '' ) ) {
				return $this->msg( $item, __( 'The {label} field is required', 'fw' ) );
			}
		}
		return null;
	}

	/** "First Last" (or the full name), as one string. */
	public function get_value_from_item( $value ) {
		$p = self::parts( $value );
		$s = isset( $p['full'] ) ? $p['full'] : trim( ( $p['first'] ?? '' ) . ' ' . ( $p['last'] ?? '' ) );
		return sanitize_text_field( $s );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Name' );
