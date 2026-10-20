<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Repeating group — a few fields the visitor can add again ("Add another guest").
 *
 * Each copy posts as `<shortcode>[<n>][<sub-field label>]`, keyed by the sub-field's LABEL (brackets
 * made safe), because the stored value is built without the item's options and must read on its own:
 * "1) Name: Ana, Age: 30; 2) Name: Leo, Age: 4". static/js/fields.js adds and removes copies (within
 * the minimum and maximum) and renumbers them; without it the form shows the minimum number of copies.
 * The server checks the count, every required sub-field and each sub-field's type.
 */
class FW_Option_Type_Form_Builder_Item_Repeater extends FW_Option_Type_Form_Builder_Item_Simple {

	const TYPES = array( 'text', 'email', 'phone', 'number', 'date' );

	public function get_type() {
		return 'repeater';
	}

	protected function title() {
		return __( 'Repeating Group', 'fw' );
	}

	protected function tip() {
		return __( 'Add a group of fields the visitor can repeat ("Add another guest")', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Guests', 'fw' ), false ),
			array(
				'g_repeat' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'fields' => array(
								'type'            => 'addable-box',
								'label'           => __( 'Fields in each group', 'fw' ),
								'box-options'     => array(
									'label'    => array( 'type' => 'text', 'label' => __( 'Label', 'fw' ), 'value' => '' ),
									'type'     => array(
										'type'    => 'select',
										'label'   => __( 'Type', 'fw' ),
										'value'   => 'text',
										'choices' => array( 'text' => __( 'Text', 'fw' ), 'email' => __( 'Email', 'fw' ), 'phone' => __( 'Phone', 'fw' ), 'number' => __( 'Number', 'fw' ), 'date' => __( 'Date', 'fw' ) ),
									),
									'required' => array( 'type' => 'switch', 'label' => __( 'Required', 'fw' ), 'value' => false ),
								),
								'template'        => '{{- label }}',
								'add-button-text' => __( 'Add field', 'fw' ),
								'sortable'        => true,
								'value'           => array(
									array( 'label' => __( 'Name', 'fw' ), 'type' => 'text', 'required' => true ),
									array( 'label' => __( 'Age', 'fw' ), 'type' => 'number', 'required' => false ),
								),
							),
						),
						array( 'item_title' => array( 'type' => 'text', 'label' => __( 'Name of one group', 'fw' ), 'desc' => __( 'Shown as "Guest 1", "Guest 2"…', 'fw' ), 'value' => __( 'Guest', 'fw' ) ) ),
						array( 'min' => array( 'type' => 'text', 'label' => __( 'At least', 'fw' ), 'value' => '1' ) ),
						array( 'max' => array( 'type' => 'text', 'label' => __( 'At most', 'fw' ), 'value' => '6' ) ),
						array( 'add_label' => array( 'type' => 'text', 'label' => __( 'Add button', 'fw' ), 'value' => __( 'Add another guest', 'fw' ) ) ),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public static function key( $label ) {
		return trim( str_replace( array( '[', ']' ), array( '(', ')' ), (string) $label ) );
	}

	/** Sub-fields: [ { key, label, type, required } ]. */
	public static function fields( array $o ) {
		$out  = array();
		$seen = array();
		foreach ( (array) ( $o['fields'] ?? array() ) as $f ) {
			$label = isset( $f['label'] ) ? trim( (string) $f['label'] ) : '';
			$key   = self::key( $label );
			if ( '' === $key || isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = array(
				'key'      => $key,
				'label'    => $label,
				'type'     => in_array( $f['type'] ?? '', self::TYPES, true ) ? $f['type'] : 'text',
				'required' => ! empty( $f['required'] ),
			);
		}
		return $out;
	}

	/** @return int[] min, max */
	public static function bounds( array $o ) {
		$min = max( 0, (int) ( $o['min'] ?? 1 ) );
		$max = max( 1, (int) ( $o['max'] ?? 6 ) );
		return array( min( $min, 50 ), max( min( $max, 50 ), max( 1, $min ) ) );
	}

	/** Submitted copies with at least one value, renumbered 0…n. */
	private static function rows( $value ) {
		$out = array();
		foreach ( is_array( $value ) ? $value : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean = array();
			foreach ( $row as $k => $v ) {
				$clean[ (string) $k ] = is_scalar( $v ) ? trim( (string) $v ) : '';
			}
			if ( implode( '', $clean ) !== '' ) {
				$out[] = $clean;
			}
		}
		return $out;
	}

	public function frontend_render( array $item, $input_value ) {
		$o      = $item['options'];
		$fields = self::fields( $o );
		if ( ! $fields ) {
			return '';
		}
		list( $min, $max ) = self::bounds( $o );
		$rows = self::rows( $input_value );
		while ( count( $rows ) < max( 1, $min ) ) {
			$rows[] = array();
		}
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => 'id-' . fw_unique_increment() ), array(
			'fields'     => $fields,
			'rows'       => array_slice( $rows, 0, $max ),
			'min'        => $min,
			'max'        => $max,
			'item_title' => (string) ( $o['item_title'] ?? '' ),
			'add_label'  => (string) ( $o['add_label'] ?? __( 'Add another', 'fw' ) ),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o      = $item['options'];
		$fields = self::fields( $o );
		list( $min, $max ) = self::bounds( $o );
		$rows = self::rows( $input_value );
		if ( count( $rows ) < $min && ( $min > 0 && ( ! empty( $o['required'] ) || count( $rows ) > 0 ) ) ) {
			return $this->msg( $item, sprintf( _n( 'Please fill in at least %d group in {label}', 'Please fill in at least %d groups in {label}', $min, 'fw' ), $min ) );
		}
		if ( count( $rows ) > $max ) {
			return $this->msg( $item, sprintf( __( 'Please keep to %d groups in {label}', 'fw' ), $max ) );
		}
		foreach ( $rows as $n => $row ) {
			foreach ( $fields as $f ) {
				$v = $row[ $f['key'] ] ?? '';
				if ( '' === $v ) {
					if ( $f['required'] ) {
						return $this->msg( $item, sprintf( __( 'Please fill in "%1$s" for number %2$d in {label}', 'fw' ), $f['label'], $n + 1 ) );
					}
					continue;
				}
				if ( mb_strlen( $v ) > 300
					|| ( 'email' === $f['type'] && ! is_email( $v ) )
					|| ( 'number' === $f['type'] && ! is_numeric( $v ) )
					|| ( 'date' === $f['type'] && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) )
					|| ( 'phone' === $f['type'] && ( strlen( preg_replace( '/\D/', '', $v ) ) < 6 || ! preg_match( '/^\+?[\d\s().\-\/]+$/', $v ) ) ) ) {
					return $this->msg( $item, sprintf( __( 'Please check "%1$s" for number %2$d in {label}', 'fw' ), $f['label'], $n + 1 ) );
				}
			}
		}
		return null;
	}

	public function get_value_from_item( $value ) {
		$out = array();
		foreach ( self::rows( $value ) as $n => $row ) {
			$parts = array();
			foreach ( $row as $k => $v ) {
				if ( '' !== $v ) {
					$parts[] = sanitize_text_field( $k ) . ': ' . sanitize_text_field( $v );
				}
			}
			$out[] = ( $n + 1 ) . ') ' . implode( ', ', $parts );
		}
		return implode( '; ', $out );
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Repeater' );
