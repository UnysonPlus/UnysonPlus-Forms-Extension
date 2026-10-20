<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Ranking — put the options in order of preference.
 *
 * Works without JavaScript: each option has a position dropdown (1…n). static/js/fields.js turns the
 * list into Move up / Move down buttons (keyboard-friendly, no drag needed) that keep the dropdowns in
 * step. The server requires every position used exactly once; the value stored is "1. A, 2. B, …".
 */
class FW_Option_Type_Form_Builder_Item_Ranking extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'ranking';
	}

	protected function title() {
		return __( 'Ranking', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Ranking (put options in order)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Put these in order of importance', 'fw' ), false ),
			array(
				'g_rank' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'choices' => array(
								'type'   => 'addable-option',
								'label'  => __( 'Options to rank', 'fw' ),
								'option' => array( 'type' => 'text' ),
								'value'  => array( __( 'Price', 'fw' ), __( 'Location', 'fw' ), __( 'Space', 'fw' ) ),
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public static function choices( array $o ) {
		return array_values( array_filter( array_map( function ( $c ) {
			return is_scalar( $c ) ? self::key( $c ) : '';
		}, (array) ( $o['choices'] ?? array() ) ), 'strlen' ) );
	}

	/** An option's text as a field-name key: brackets would split the posted array, so they become parentheses. */
	public static function key( $text ) {
		return trim( str_replace( array( '[', ']' ), array( '(', ')' ), (string) $text ) );
	}

	/**
	 * Submitted { option text => position } → option texts in ranked order, or null when it is not a
	 * full ranking of exactly these options with every position used once.
	 */
	private static function order( array $choices, $value ) {
		if ( ! is_array( $value ) || count( $value ) !== count( $choices ) ) {
			return null;
		}
		$pos = array();
		foreach ( $choices as $c ) {
			if ( ! isset( $value[ $c ] ) || ! is_numeric( $value[ $c ] ) ) {
				return null;
			}
			$pos[ $c ] = (int) $value[ $c ];
		}
		$sorted = array_values( $pos );
		sort( $sorted );
		if ( $sorted !== range( 1, count( $choices ) ) ) {
			return null;
		}
		asort( $pos );
		return array_keys( $pos );
	}

	public function frontend_render( array $item, $input_value ) {
		$c = self::choices( $item['options'] );
		if ( ! $c ) {
			return '';
		}
		$order = self::order( $c, $input_value );
		$order = null === $order ? $c : $order;
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'choices' => $c,
			'order'   => $order,
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$c = self::choices( $item['options'] );
		if ( ! $c ) {
			return null;
		}
		if ( null === self::order( $c, $input_value ) ) {
			return $this->msg( $item, __( 'Please give each option in {label} a different position', 'fw' ) );
		}
		return null;
	}

	/** "1. First, 2. Second, …" from the submitted { option text => position } (validated above). */
	public function get_value_from_item( $value ) {
		if ( ! is_array( $value ) ) {
			return '';
		}
		$pos = array();
		foreach ( $value as $label => $p ) {
			$pos[ (string) $label ] = (int) $p;
		}
		asort( $pos );
		$out = array();
		foreach ( array_keys( $pos ) as $n => $key ) {
			$out[] = ( $n + 1 ) . '. ' . sanitize_text_field( (string) $key );
		}
		return implode( ', ', $out );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Ranking' );
