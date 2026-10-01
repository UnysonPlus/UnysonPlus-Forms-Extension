<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Date / time field — the booking essential.
 *
 * Renders the native <input type="date|time|datetime-local"> rather than a JS
 * date picker: every current browser ships one, it is accessible and
 * localised by the OS, and it needs no library on the front end. `min`/`max`
 * ride on the input so the browser enforces them too, but the server is the
 * authority — the picker is a convenience, the validation below is the rule.
 *
 * Values are stored in the ISO shape the input speaks (Y-m-d, H:i, Y-m-dTH:i),
 * which sorts and compares as plain strings.
 */
class FW_Option_Type_Form_Builder_Item_Date extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'date';
	}

	protected function title() {
		return __( 'Date / Time', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Date / Time field', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Date', 'fw' ) ),
			array(
				'g_mode' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'mode' => array(
								'type'    => 'radio',
								'label'   => __( 'Ask for', 'fw' ),
								'value'   => 'date',
								'inline'  => true,
								'choices' => array(
									'date'     => __( 'Date', 'fw' ),
									'time'     => __( 'Time', 'fw' ),
									'datetime' => __( 'Date and time', 'fw' ),
								),
							),
						),
						array(
							'min' => array(
								'type'  => 'text',
								'label' => __( 'Earliest', 'fw' ),
								'desc'  => __( 'A date (YYYY-MM-DD), a time (HH:MM), or the word "today". Leave empty for no limit.', 'fw' ),
								'value' => '',
							),
						),
						array(
							'max' => array(
								'type'  => 'text',
								'label' => __( 'Latest', 'fw' ),
								'desc'  => __( 'Same formats. "today" means no dates in the future.', 'fw' ),
								'value' => '',
							),
						),
						array(
							'blackout_days' => array(
								'type'    => 'checkboxes',
								'label'   => __( 'Not available on', 'fw' ),
								'desc'    => __( 'Weekdays that cannot be chosen — e.g. the days you are closed.', 'fw' ),
								'inline'  => true,
								'value'   => array(),
								'choices' => array(
									'1' => __( 'Mon', 'fw' ),
									'2' => __( 'Tue', 'fw' ),
									'3' => __( 'Wed', 'fw' ),
									'4' => __( 'Thu', 'fw' ),
									'5' => __( 'Fri', 'fw' ),
									'6' => __( 'Sat', 'fw' ),
									'0' => __( 'Sun', 'fw' ),
								),
							),
						),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Bounds
	 * ---------------------------------------------------------------------- */

	/**
	 * A bound as the input's own string, or '' if unset / unparseable.
	 * "today" resolves at render/validate time in the site's timezone.
	 *
	 * @param string $raw
	 * @param string $mode
	 *
	 * @return string
	 */
	private function bound( $raw, $mode ) {
		$raw = trim( (string) $raw );

		if ( '' === $raw ) {
			return '';
		}

		if ( 'today' === strtolower( $raw ) ) {
			return 'time' === $mode ? '' : current_time( 'datetime' === $mode ? 'Y-m-d\TH:i' : 'Y-m-d' );
		}

		return $this->is_valid( $raw, $mode ) ? $raw : '';
	}

	/**
	 * Strict shape check for the value a given mode produces.
	 *
	 * @param string $v
	 * @param string $mode
	 *
	 * @return bool
	 */
	private function is_valid( $v, $mode ) {
		switch ( $mode ) {
			case 'time':
				return (bool) preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $v );
			case 'datetime':
				if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})T([01]\d|2[0-3]):[0-5]\d$/', $v, $m ) ) {
					return false;
				}

				return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
			default:
				if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m ) ) {
					return false;
				}

				return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
		}
	}

	/* ---------------------------------------------------------------------- *
	 * Front end
	 * ---------------------------------------------------------------------- */

	public function frontend_render( array $item, $input_value ) {
		$o    = $item['options'];
		$mode = in_array( $o['mode'] ?? '', array( 'date', 'time', 'datetime' ), true ) ? $o['mode'] : 'date';

		$attr = array(
			'type'  => 'datetime' === $mode ? 'datetime-local' : $mode,
			'name'  => $item['shortcode'] ?? '',
			'value' => is_null( $input_value ) ? '' : (string) $input_value,
			'id'    => 'id-' . fw_unique_increment(),
		);

		if ( ! empty( $o['required'] ) ) {
			$attr['required'] = 'required';
		}

		foreach ( array( 'min', 'max' ) as $b ) {
			$v = $this->bound( $o[ $b ] ?? '', $mode );

			if ( '' !== $v ) {
				$attr[ $b ] = $v;
			}
		}

		// Weekdays the browser cannot block natively; the server enforces them,
		// and the days are exposed for any front-end enhancement to read.
		$blackout = array_values( array_filter( (array) ( $o['blackout_days'] ?? array() ), 'is_numeric' ) );

		if ( $blackout && 'time' !== $mode ) {
			$attr['data-blackout-days'] = implode( ',', array_map( 'intval', $blackout ) );
		}

		return $this->view( $item, $attr );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o     = $item['options'];
		$mode  = in_array( $o['mode'] ?? '', array( 'date', 'time', 'datetime' ), true ) ? $o['mode'] : 'date';
		$value = is_scalar( $input_value ) ? trim( (string) $input_value ) : '';

		if ( '' === $value ) {
			return ! empty( $o['required'] ) ? $this->msg( $item, __( 'The {label} field is required', 'fw' ) ) : null;
		}

		if ( ! $this->is_valid( $value, $mode ) ) {
			return $this->msg( $item, __( 'The {label} field is not a valid date or time', 'fw' ) );
		}

		$min = $this->bound( $o['min'] ?? '', $mode );
		$max = $this->bound( $o['max'] ?? '', $mode );

		// ISO strings of one shape compare correctly as strings.
		if ( '' !== $min && strcmp( $value, $min ) < 0 ) {
			return $this->msg( $item, sprintf( __( 'The {label} field cannot be before %s', 'fw' ), $this->human( $min, $mode ) ) );
		}

		if ( '' !== $max && strcmp( $value, $max ) > 0 ) {
			return $this->msg( $item, sprintf( __( 'The {label} field cannot be after %s', 'fw' ), $this->human( $max, $mode ) ) );
		}

		if ( 'time' !== $mode ) {
			$blackout = array_map( 'intval', array_filter( (array) ( $o['blackout_days'] ?? array() ), 'is_numeric' ) );
			$weekday  = (int) gmdate( 'w', strtotime( substr( $value, 0, 10 ) . ' 12:00:00 UTC' ) );

			if ( $blackout && in_array( $weekday, $blackout, true ) ) {
				return $this->msg( $item, __( 'The {label} field falls on a day that is not available', 'fw' ) );
			}
		}

		return null;
	}

	/**
	 * A bound in the site's date/time format, for error messages.
	 *
	 * @param string $iso
	 * @param string $mode
	 *
	 * @return string
	 */
	private function human( $iso, $mode ) {
		$ts = strtotime( 'time' === $mode ? '1970-01-01 ' . $iso : str_replace( 'T', ' ', $iso ) );

		if ( ! $ts ) {
			return $iso;
		}

		switch ( $mode ) {
			case 'time':
				return date_i18n( get_option( 'time_format' ), $ts );
			case 'datetime':
				return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts );
			default:
				return date_i18n( get_option( 'date_format' ), $ts );
		}
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Date' );
