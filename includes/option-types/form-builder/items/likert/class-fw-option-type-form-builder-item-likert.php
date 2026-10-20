<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Survey grid (Likert) — several statements rated on one scale ("Strongly disagree" … "Strongly agree").
 *
 * A table of radio groups: one row per statement, one column per point of the scale, each radio
 * labelled "<statement>: <point>" for screen readers. On phones the rows stack (see frontend.css).
 * Required means every statement answered. Stored as "Statement: answer; …".
 */
class FW_Option_Type_Form_Builder_Item_Likert extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'likert';
	}

	protected function title() {
		return __( 'Survey Grid', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Survey grid (several statements on one scale)', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'How much do you agree?', 'fw' ) ),
			array(
				'g_likert' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'rows' => array(
								'type'   => 'addable-option',
								'label'  => __( 'Statements', 'fw' ),
								'option' => array( 'type' => 'text' ),
								'value'  => array( __( 'The booking was easy', 'fw' ), __( 'The information was clear', 'fw' ) ),
							),
						),
						array(
							'scale' => array(
								'type'   => 'addable-option',
								'label'  => __( 'Scale', 'fw' ),
								'option' => array( 'type' => 'text' ),
								'value'  => array( __( 'Strongly disagree', 'fw' ), __( 'Disagree', 'fw' ), __( 'Neutral', 'fw' ), __( 'Agree', 'fw' ), __( 'Strongly agree', 'fw' ) ),
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	private static function list_of( $v ) {
		return array_values( array_filter( array_map( function ( $c ) {
			return is_scalar( $c ) ? trim( str_replace( array( '[', ']' ), array( '(', ')' ), (string) $c ) ) : '';
		}, (array) $v ), 'strlen' ) );
	}

	public function frontend_render( array $item, $input_value ) {
		$o     = $item['options'];
		$rows  = self::list_of( $o['rows'] ?? array() );
		$scale = self::list_of( $o['scale'] ?? array() );
		if ( ! $rows || count( $scale ) < 2 ) {
			return '';
		}
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'rows'   => $rows,
			'scale'  => $scale,
			'chosen' => is_array( $input_value ) ? $input_value : array(),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o     = $item['options'];
		$rows  = self::list_of( $o['rows'] ?? array() );
		$scale = self::list_of( $o['scale'] ?? array() );
		$given = is_array( $input_value ) ? $input_value : array();
		foreach ( $given as $row => $answer ) {
			if ( ! in_array( (string) $row, $rows, true ) || ! is_scalar( $answer ) || ! in_array( (string) $answer, $scale, true ) ) {
				return $this->msg( $item, __( 'Please answer {label} using the scale shown', 'fw' ) );
			}
		}
		if ( ! empty( $o['required'] ) && count( $given ) < count( $rows ) ) {
			return $this->msg( $item, __( 'Please answer every statement in {label}', 'fw' ) );
		}
		return null;
	}

	public function get_value_from_item( $value ) {
		if ( ! is_array( $value ) ) {
			return '';
		}
		$out = array();
		foreach ( $value as $row => $answer ) {
			if ( is_scalar( $answer ) && '' !== (string) $answer ) {
				$out[] = sanitize_text_field( (string) $row ) . ': ' . sanitize_text_field( (string) $answer );
			}
		}
		return implode( '; ', $out );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Likert' );
