<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Entry and seat limits (contact form Settings → Options → Limits):
 *
 *   limit_entries         close after this many entries (spam not counted; needs "Store entry" on)
 *   limit_opens           Y-m-d or "Y-m-d H:i": the form opens then (site time)
 *   limit_closes          Y-m-d or "Y-m-d H:i": the form closes then (a date alone = the end of that day)
 *   limit_closed_message  shown instead of the form while it is closed
 *   limit_seats           one line per choice: "Saturday workshop | 20" — places for an answer of any
 *                         dropdown, single-choice or checkbox field
 *
 * A closed form is not shown, and a submission that reaches a closed form or a full choice is
 * refused (`fw_ext_forms_validate_submission`). Places are counted from the stored entries, so
 * deleting an entry frees its place; "3 places left" / "full" is written next to the choice by
 * static/js/fields.js from the form's data-fw-seats attribute.
 */
class FW_Forms_Limits {

	const KEYS = array( 'limit_entries', 'limit_opens', 'limit_closes', 'limit_closed_message', 'limit_seats' );

	public static function init() {
		add_filter( 'fw_ext_forms_validate_submission', array( __CLASS__, '_validate' ), 5, 2 );
		add_action( 'fw_ext_forms_entry_stored', array( __CLASS__, '_flush' ) );
	}

	/** The contact form's Settings → Options → Limits group. */
	public static function options() {
		return array(
			'type'    => 'group',
			'options' => array(
				'limit_entries'        => array( 'type' => 'text', 'label' => __( 'Close after this many entries', 'fw' ), 'desc' => __( 'Empty or 0 = no limit. Counts stored entries (Settings → Actions → Store entry), not spam.', 'fw' ), 'value' => '' ),
				'limit_opens'          => array( 'type' => 'text', 'label' => __( 'Opens on', 'fw' ), 'desc' => __( 'YYYY-MM-DD, or YYYY-MM-DD HH:MM in the site time. Empty = open now.', 'fw' ), 'value' => '' ),
				'limit_closes'         => array( 'type' => 'text', 'label' => __( 'Closes on', 'fw' ), 'desc' => __( 'YYYY-MM-DD (the end of that day), or YYYY-MM-DD HH:MM. Empty = never.', 'fw' ), 'value' => '' ),
				'limit_seats'          => array( 'type' => 'textarea', 'label' => __( 'Places per choice', 'fw' ), 'desc' => __( 'One line per choice, the text exactly as in the field, a bar, then the number of places: Saturday workshop | 20. The form shows how many are left and refuses a full choice.', 'fw' ), 'value' => '' ),
				'limit_closed_message' => array( 'type' => 'text', 'label' => __( 'Message when closed', 'fw' ), 'value' => __( 'This form is closed.', 'fw' ) ),
			),
		);
	}

	/** A date setting as a timestamp in the site's time zone, or 0. */
	private static function when( $v, $end_of_day ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) {
			$v .= $end_of_day ? ' 23:59:59' : ' 00:00:00';
		} elseif ( ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v ) ) {
			return 0;
		}
		try {
			return ( new DateTimeImmutable( $v, wp_timezone() ) )->getTimestamp();
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Why the form is closed right now, or '' when it is open.
	 *
	 * @param array  $form    The form's settings (element atts).
	 * @param string $form_id
	 */
	public static function closed_reason( array $form, $form_id ) {
		if ( class_exists( 'FW_Forms_Entry_Edit' ) && FW_Forms_Entry_Edit::editing( $form_id ) ) {
			return '';
		}
		$now   = time();
		$opens = self::when( $form['limit_opens'] ?? '', false );
		if ( $opens && $now < $opens ) {
			return 'not_open';
		}
		$closes = self::when( $form['limit_closes'] ?? '', true );
		if ( $closes && $now > $closes ) {
			return 'closed';
		}
		$max = (int) ( $form['limit_entries'] ?? 0 );
		if ( $max > 0 && '' !== (string) $form_id && class_exists( 'FW_Forms_Entries' ) ) {
			$c = FW_Forms_Entries::counts( (string) $form_id );
			if ( (int) $c['all'] >= $max ) {
				return 'full';
			}
		}
		return '';
	}

	public static function closed_message( array $form ) {
		$m = trim( (string) ( $form['limit_closed_message'] ?? '' ) );
		return '' !== $m ? $m : __( 'This form is closed.', 'fw' );
	}

	/** @return array choice text => places */
	public static function seat_rules( array $form ) {
		$out = array();
		foreach ( FW_Forms_Action::lines( $form['limit_seats'] ?? '' ) as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) );
			if ( 2 === count( $p ) && '' !== $p[0] && ctype_digit( $p[1] ) ) {
				$out[ $p[0] ] = (int) $p[1];
			}
		}
		return $out;
	}

	/**
	 * Places per choice: { choice: { places, taken, left } }.
	 *
	 * @param string $form_id
	 * @param array  $form
	 */
	public static function seats( $form_id, array $form ) {
		$rules = self::seat_rules( $form );
		if ( ! $rules || '' === (string) $form_id ) {
			return array();
		}
		$key   = 'fw_forms_seats_' . md5( $form_id );
		$taken = get_transient( $key );
		if ( ! is_array( $taken ) ) {
			$taken = array();
			if ( class_exists( 'FW_Forms_Entries' ) ) {
				FW_Forms_Entries::each( array( 'form_id' => (string) $form_id ), function ( $row ) use ( &$taken ) {
					if ( in_array( $row->status ?? '', array( 'spam', 'partial' ), true ) ) {
						return;
					}
					foreach ( (array) $row->fields as $f ) {
						foreach ( (array) ( $f['value'] ?? array() ) as $v ) {
							if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
								$taken[ trim( (string) $v ) ] = ( $taken[ trim( (string) $v ) ] ?? 0 ) + 1;
							}
						}
					}
				} );
			}
			set_transient( $key, $taken, 5 * MINUTE_IN_SECONDS );
		}
		$out = array();
		foreach ( $rules as $choice => $places ) {
			$t              = (int) ( $taken[ $choice ] ?? 0 );
			$out[ $choice ] = array( 'places' => $places, 'taken' => $t, 'left' => max( 0, $places - $t ) );
		}
		return $out;
	}

	/** @internal A new entry changes the counts. */
	public static function _flush( $entry_id = 0 ) {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_fw\\_forms\\_seats\\_%' OR option_name LIKE '\\_transient\\_timeout\\_fw\\_forms\\_seats\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/** @internal Refuse a submission to a closed form or for a full choice. */
	public static function _validate( $errors, $data ) {
		if ( ! empty( $errors ) || ! is_array( $data ) || ! class_exists( 'FW_Forms_Actions' ) ) {
			return $errors;
		}
		$form = FW_Forms_Actions::form_settings( $data );
		if ( ! $form ) {
			return $errors;
		}
		$id = (string) ( $data['id'] ?? '' );
		if ( class_exists( 'FW_Forms_Entry_Edit' ) && FW_Forms_Entry_Edit::editing( $id ) ) {
			return $errors;
		}
		if ( '' !== self::closed_reason( $form, $id ) ) {
			$errors['form-closed'] = self::closed_message( $form );
			return $errors;
		}
		$seats = self::seats( $id, $form );
		if ( $seats ) {
			foreach ( (array) ( $data['form_values'] ?? array() ) as $value ) {
				foreach ( (array) $value as $v ) {
					$one = is_scalar( $v ) ? trim( (string) $v ) : '';
					if ( isset( $seats[ $one ] ) && $seats[ $one ]['left'] <= 0 ) {
						/* translators: %s: a choice, e.g. "Saturday workshop" */
						$errors['seat-full'] = sprintf( __( 'Sorry, "%s" is full. Please choose another.', 'fw' ), $one );
						return $errors;
					}
				}
			}
		}
		return $errors;
	}
}
