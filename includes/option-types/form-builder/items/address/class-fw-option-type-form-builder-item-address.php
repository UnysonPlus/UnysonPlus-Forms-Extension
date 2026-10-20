<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Address — street, an optional second line, city, an optional state/region, postcode and country,
 * each box with its own autofill purpose (address-line1 … country-name) so a browser fills the lot.
 *
 * Required means street, city and country (not every country has postcodes or regions). The value
 * stored is one line, the filled parts joined with commas.
 */
class FW_Option_Type_Form_Builder_Item_Address extends FW_Option_Type_Form_Builder_Item_Simple {

	/** Part => autocomplete token. Order is the display order. */
	const PARTS = array(
		'street'   => 'address-line1',
		'line2'    => 'address-line2',
		'city'     => 'address-level2',
		'region'   => 'address-level1',
		'postcode' => 'postal-code',
		'country'  => 'country-name',
	);

	public function get_type() {
		return 'address';
	}

	protected function title() {
		return __( 'Address', 'fw' );
	}

	protected function tip() {
		return __( 'Add an Address field (street, city, postcode, country)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Address', 'fw' ), false ),
			array(
				'g_address' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'show_line2' => array(
								'type'  => 'switch',
								'label' => __( 'Second address line', 'fw' ),
								'desc'  => __( 'For an apartment, suite or building name.', 'fw' ),
								'value' => true,
							),
						),
						array(
							'show_region' => array(
								'type'  => 'switch',
								'label' => __( 'State / region', 'fw' ),
								'value' => true,
							),
						),
						array(
							'default_country' => array(
								'type'  => 'text',
								'label' => __( 'Country, filled in', 'fw' ),
								'desc'  => __( 'Optional. Most visitors are in one country? Fill it in for them; they can change it.', 'fw' ),
								'value' => '',
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public static function labels() {
		return array(
			'street'   => __( 'Street address', 'fw' ),
			'line2'    => __( 'Apartment, suite, building (optional)', 'fw' ),
			'city'     => __( 'City / town', 'fw' ),
			'region'   => __( 'State / region', 'fw' ),
			'postcode' => __( 'Postcode / ZIP', 'fw' ),
			'country'  => __( 'Country', 'fw' ),
		);
	}

	/** The parts shown for this item. */
	public static function shown( array $o ) {
		$parts = array_keys( self::PARTS );
		if ( empty( $o['show_line2'] ) ) {
			$parts = array_diff( $parts, array( 'line2' ) );
		}
		if ( empty( $o['show_region'] ) ) {
			$parts = array_diff( $parts, array( 'region' ) );
		}
		return array_values( $parts );
	}

	private static function parts( $value ) {
		$out = array();
		foreach ( array_keys( self::PARTS ) as $k ) {
			$v         = is_array( $value ) && isset( $value[ $k ] ) && is_scalar( $value[ $k ] ) ? (string) $value[ $k ] : '';
			$out[ $k ] = trim( preg_replace( '/\s+/', ' ', $v ) );
		}
		return $out;
	}

	public function frontend_render( array $item, $input_value ) {
		$o     = $item['options'];
		$parts = is_null( $input_value ) ? array() : self::parts( $input_value );
		if ( is_null( $input_value ) && ! empty( $o['default_country'] ) ) {
			$parts['country'] = (string) $o['default_country'];
		}
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'shown'  => self::shown( $o ),
			'parts'  => $parts,
			'labels' => self::labels(),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o = $item['options'];
		$p = self::parts( $input_value );
		foreach ( $p as $v ) {
			if ( mb_strlen( $v ) > 200 ) {
				return $this->msg( $item, __( 'The {label} field is too long', 'fw' ) );
			}
		}
		if ( ! empty( $o['required'] ) ) {
			foreach ( array( 'street', 'city', 'country' ) as $k ) {
				if ( '' === $p[ $k ] ) {
					return $this->msg( $item, __( 'Please complete the street, city and country in {label}', 'fw' ) );
				}
			}
		}
		return null;
	}

	public function get_value_from_item( $value ) {
		$p = self::parts( $value );
		return sanitize_text_field( implode( ', ', array_filter( $p, 'strlen' ) ) );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Address' );
