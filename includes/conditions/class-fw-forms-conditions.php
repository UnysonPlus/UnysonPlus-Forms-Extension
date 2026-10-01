<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Conditional visibility: "show this field only when [field] [is / is not /
 * contains / is empty / is not empty] [value]". One rule per field.
 *
 * Two halves that must agree:
 *  - CLIENT: `static/js/conditions.js` reads the rules the form emits as JSON
 *    and toggles fields as the visitor types. Convenience.
 *  - SERVER: the builder's validate + collect passes call `is_visible()`; a
 *    hidden field is neither validated (so a hidden required field is not
 *    required) nor collected (so it is not stored or emailed). The rule.
 *
 * The controlling field is named by its LABEL, because that is the only thing
 * the person building the form can see. Matching is trimmed, tag-stripped and
 * case-insensitive; the first field with that label wins.
 */
class FW_Forms_Conditions {

	const OPS = array( 'is', 'is_not', 'contains', 'empty', 'not_empty' );

	/**
	 * The option group every field type gets, via the item base's
	 * get_extra_options().
	 *
	 * @return array
	 */
	public static function options_group() {
		return array(
			'g_conditions' => array(
				'type'    => 'group',
				'options' => array(
					array(
						'cond_enabled' => array(
							'type'         => 'switch',
							'label'        => __( 'Show only when…', 'fw' ),
							'desc'         => __( 'Show this field only when another field has a certain value. A hidden field is not required and is not submitted.', 'fw' ),
							'value'        => 'no',
							'left-choice'  => array( 'value' => 'no', 'label' => __( 'Always show', 'fw' ) ),
							'right-choice' => array( 'value' => 'yes', 'label' => __( 'Conditional', 'fw' ) ),
						),
					),
					array(
						'cond_field' => array(
							'type'  => 'text',
							'label' => __( 'Field', 'fw' ),
							'desc'  => __( 'The label of the field to watch, exactly as written — e.g. "Party size".', 'fw' ),
							'value' => '',
						),
					),
					array(
						'cond_op' => array(
							'type'    => 'select',
							'label'   => __( 'Condition', 'fw' ),
							'value'   => 'is',
							'choices' => array(
								'is'        => __( 'is', 'fw' ),
								'is_not'    => __( 'is not', 'fw' ),
								'contains'  => __( 'contains', 'fw' ),
								'not_empty' => __( 'has any value', 'fw' ),
								'empty'     => __( 'is empty', 'fw' ),
							),
						),
					),
					array(
						'cond_value' => array(
							'type'  => 'text',
							'label' => __( 'Value', 'fw' ),
							'desc'  => __( 'Compared case-insensitively. For a choice field, use the choice text. Ignored for "has any value" / "is empty".', 'fw' ),
							'value' => '',
						),
					),
				),
			),
		);
	}

	/**
	 * A field's rule, normalised, or null when it has none.
	 *
	 * @param array $item
	 *
	 * @return array|null { field, op, value }
	 */
	public static function rule( array $item ) {
		$o = isset( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array();

		if ( ( $o['cond_enabled'] ?? 'no' ) !== 'yes' ) {
			return null;
		}

		$field = self::norm( $o['cond_field'] ?? '' );
		$op    = in_array( $o['cond_op'] ?? '', self::OPS, true ) ? $o['cond_op'] : 'is';

		if ( '' === $field ) {
			return null; // A rule that watches nothing is no rule.
		}

		return array( 'field' => $field, 'op' => $op, 'value' => self::norm( $o['cond_value'] ?? '' ) );
	}

	/**
	 * Is this field visible given the submitted values?
	 *
	 * A rule whose controlling field cannot be found resolves to VISIBLE —
	 * hiding a field because of a typo would silently lose data, and a field
	 * that shows when it should not is the recoverable mistake.
	 *
	 * @param array $item
	 * @param array $items  All items of the form (flat).
	 * @param array $values shortcode => submitted value
	 *
	 * @return bool
	 */
	public static function is_visible( array $item, array $items, array $values ) {
		$rule = self::rule( $item );

		if ( ! $rule ) {
			return true;
		}

		$controller = self::find_by_label( $items, $rule['field'] );

		if ( ! $controller || ( $controller['shortcode'] ?? '' ) === ( $item['shortcode'] ?? '' ) ) {
			return true;
		}

		// The controller may itself be hidden — then its value does not count.
		if ( ! self::is_visible( $controller, $items, $values ) ) {
			return in_array( $rule['op'], array( 'is_not', 'empty' ), true );
		}

		return self::matches( $rule, $values[ $controller['shortcode'] ] ?? null );
	}

	/**
	 * @param array $rule
	 * @param mixed $actual
	 *
	 * @return bool
	 */
	public static function matches( array $rule, $actual ) {
		$list = array_map( array( __CLASS__, 'norm' ), is_array( $actual ) ? $actual : array( $actual ) );
		$list = array_values( array_filter( $list, 'strlen' ) );
		$has  = (bool) $list;

		switch ( $rule['op'] ) {
			case 'empty':
				return ! $has;
			case 'not_empty':
				return $has;
			case 'is':
				return in_array( $rule['value'], $list, true );
			case 'is_not':
				return ! in_array( $rule['value'], $list, true );
			case 'contains':
				foreach ( $list as $v ) {
					if ( '' !== $rule['value'] && false !== strpos( $v, $rule['value'] ) ) {
						return true;
					}
				}

				return false;
		}

		return true;
	}

	/**
	 * The rules of a form, keyed by the dependent field's shortcode, resolved to
	 * the controller's shortcode — what the front-end script consumes.
	 *
	 * @param array $items
	 *
	 * @return array shortcode => { field: controllerShortcode, op, value }
	 */
	public static function rules_for_client( array $items ) {
		$out = array();

		foreach ( $items as $item ) {
			$rule = self::rule( $item );

			if ( ! $rule || empty( $item['shortcode'] ) ) {
				continue;
			}

			$controller = self::find_by_label( $items, $rule['field'] );

			if ( ! $controller || empty( $controller['shortcode'] ) || $controller['shortcode'] === $item['shortcode'] ) {
				continue;
			}

			$out[ $item['shortcode'] ] = array( 'field' => $controller['shortcode'], 'op' => $rule['op'], 'value' => $rule['value'] );
		}

		return $out;
	}

	/* ---------------------------------------------------------------------- */

	/**
	 * @param array  $items
	 * @param string $label Normalised.
	 *
	 * @return array|null
	 */
	private static function find_by_label( array $items, $label ) {
		foreach ( $items as $it ) {
			if ( self::norm( $it['options']['label'] ?? '' ) === $label && ! empty( $it['shortcode'] ) ) {
				return $it;
			}
		}

		return null;
	}

	/**
	 * @param mixed $v
	 *
	 * @return string
	 */
	public static function norm( $v ) {
		return function_exists( 'mb_strtolower' )
			? mb_strtolower( trim( wp_strip_all_tags( (string) $v ) ) )
			: strtolower( trim( wp_strip_all_tags( (string) $v ) ) );
	}
}
