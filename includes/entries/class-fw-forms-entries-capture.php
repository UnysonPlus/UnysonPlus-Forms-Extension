<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Turns a front-end form submission into a stored entry, and owns the two
 * housekeeping duties that come with keeping personal data: retention and the
 * WordPress privacy exporter / eraser.
 *
 * Built on the `fw_ext_forms_frontend_submit` hook rather than inside the
 * submit pipeline, for the same reason the Newsletter CRM sits on hooks: the
 * pipeline stays unaware that entries exist, and anything else that wants to
 * react to a submission gets the identical seam.
 *
 * The one thing that must hold: an entry is written whether or not the email
 * went out. Losing a booking because the mail server hiccuped is precisely the
 * failure storing entries exists to prevent.
 */
class FW_Forms_Entries_Capture {

	const CRON_HOOK = 'fw_ext_forms_entries_retention';

	public function __construct() {
		add_action( 'fw_ext_forms_frontend_submit', array( $this, '_action_submit' ) );

		add_action( self::CRON_HOOK, array( $this, '_action_retention' ) );
		add_action( 'init', array( $this, '_schedule' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( $this, '_register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, '_register_eraser' ) );
	}

	/* ---------------------------------------------------------------------- *
	 * Settings
	 * ---------------------------------------------------------------------- */

	/**
	 * @param string $key
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function setting( $key, $default = null ) {
		$v = fw_get_db_ext_settings_option( 'forms', $key );

		return null === $v ? $default : $v;
	}

	/**
	 * @return bool
	 */
	public static function enabled() {
		/** Filters whether form submissions are stored as entries (default: the Forms setting, on). */
		return (bool) apply_filters( 'fw_ext_forms_store_entries', 'no' !== self::setting( 'entries_store', 'yes' ) );
	}

	/* ---------------------------------------------------------------------- *
	 * Capture
	 * ---------------------------------------------------------------------- */

	/**
	 * @internal
	 *
	 * @param array $data id, type, instance, process_data, shortcode_to_item, builder_value, form_values, attachments
	 */
	public function _action_submit( $data ) {
		if ( ! self::enabled() || ! is_array( $data ) ) {
			return;
		}

		// Per-form switch (Settings → Actions → Store entry), under the global one.
		if ( class_exists( 'FW_Forms_Actions' ) ) {
			$form    = FW_Forms_Actions::form_settings( $data );
			$actions = FW_Forms_Actions::all();

			if ( isset( $actions['store'] ) && ! $actions['store']->is_enabled( $form ) ) {
				return;
			}
		}

		$values = isset( $data['form_values'] ) && is_array( $data['form_values'] ) ? $data['form_values'] : array();
		$items  = isset( $data['shortcode_to_item'] ) && is_array( $data['shortcode_to_item'] ) ? $data['shortcode_to_item'] : array();

		if ( ! $values ) {
			return;
		}

		$fields = self::fields_from_submission( $values, $items );

		if ( ! $fields ) {
			return;
		}

		$entry = array(
			'form_id'    => (string) ( $data['id'] ?? '' ),
			'form_type'  => (string) ( $data['type'] ?? '' ),
			'form_title' => self::form_title( $data ),
			'post_id'    => self::current_post_id(),
			'email'      => self::primary_email( $fields ),
			'fields'     => $fields,
			'ip'         => 'yes' === self::setting( 'entries_store_ip', 'no' ) ? self::client_ip() : '',
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 255 ) : '',
		);

		/**
		 * Filters an entry before it is stored. Return an empty array to skip it.
		 *
		 * @param array $entry
		 * @param array $data  The raw submit-hook payload.
		 */
		$entry = apply_filters( 'fw_ext_forms_entry_before_insert', $entry, $data );

		if ( ! $entry ) {
			return;
		}

		$id = FW_Forms_Entries::insert( $entry );

		if ( $id ) {
			/**
			 * Fires after a form entry has been stored.
			 *
			 * @param int   $id
			 * @param array $entry
			 * @param array $data The raw submit-hook payload.
			 */
			do_action( 'fw_ext_forms_entry_stored', $id, $entry, $data );
		}
	}

	/**
	 * The stored field list: one row per builder item, in builder order, with
	 * the LABEL captured at submit time. That last part is what keeps an old
	 * entry readable after the form is edited — a renamed or removed field still
	 * shows what the person was actually asked.
	 *
	 * @param array $values shortcode => submitted value
	 * @param array $items  shortcode => builder item
	 *
	 * @return array [ { id, type, label, value }, … ]
	 */
	public static function fields_from_submission( array $values, array $items ) {
		$out = array();

		// Builder order first; anything submitted without a matching item (a
		// programmatic field) is appended after.
		foreach ( $items as $shortcode => $item ) {
			if ( ! array_key_exists( $shortcode, $values ) ) {
				continue;
			}

			$type = isset( $item['type'] ) ? (string) $item['type'] : '';

			// Anti-spam plumbing is not a field the person filled in.
			if ( in_array( $type, array( 'honeypot', 'recaptcha', 'form-header-title' ), true ) ) {
				continue;
			}

			$row = array(
				'id'    => (string) $shortcode,
				'type'  => $type,
				'label' => isset( $item['options']['label'] ) ? sanitize_text_field( (string) $item['options']['label'] ) : (string) $shortcode,
				'value' => self::clean_value( $values[ $shortcode ] ),
			);

			// A consent box records WHAT was consented to, so the entry is
			// self-describing: a later reader (the CRM's subscribe action, a
			// data-request reviewer) needs no access to the form to interpret it.
			if ( 'consent' === $type ) {
				$row['purpose'] = sanitize_key( (string) ( $item['options']['purpose'] ?? '' ) );
			}

			$out[] = $row;
		}

		foreach ( $values as $shortcode => $value ) {
			if ( isset( $items[ $shortcode ] ) ) {
				continue;
			}

			$out[] = array(
				'id'    => (string) $shortcode,
				'type'  => '',
				'label' => (string) $shortcode,
				'value' => self::clean_value( $value ),
			);
		}

		return $out;
	}

	/**
	 * Values arrive as strings, or arrays for checkboxes. Strip tags, keep shape.
	 *
	 * @param mixed $value
	 *
	 * @return string|array
	 */
	private static function clean_value( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_map( array( __CLASS__, 'clean_value' ), $value ) );
		}

		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * The first email-typed field identifies the person. If the form has none,
	 * any value that happens to be a valid address is accepted as a fallback.
	 *
	 * @param array $fields
	 *
	 * @return string
	 */
	public static function primary_email( array $fields ) {
		$fallback = '';

		foreach ( $fields as $f ) {
			if ( 'email' !== $f['type'] || ! is_string( $f['value'] ) || ! is_email( $f['value'] ) ) {
				continue;
			}

			return sanitize_email( $f['value'] );
		}

		// No typed email field — accept any value that IS an email address.
		foreach ( $fields as $f ) {
			if ( is_string( $f['value'] ) && is_email( $f['value'] ) ) {
				$fallback = sanitize_email( $f['value'] );
				break;
			}
		}

		return $fallback;
	}

	/**
	 * A form has no title of its own — the contact form's closest thing is its
	 * email subject, so that is what the list shows. Falls back to the form id.
	 *
	 * @param array $data
	 *
	 * @return string
	 */
	private static function form_title( array $data ) {
		$form_id = (string) ( $data['id'] ?? '' );

		/**
		 * Filters the title recorded on an entry. Forms core only knows the id;
		 * each form type answers with something human (the contact form gives
		 * its email subject), because only it knows where that is stored.
		 *
		 * @param string $title Default: the form id.
		 * @param array  $data  The submit-hook payload.
		 */
		$title = (string) apply_filters( 'fw_ext_forms_entry_form_title', $form_id, $data );

		return '' !== $title ? $title : $form_id;
	}

	/**
	 * @return int
	 */
	private static function current_post_id() {
		// NOT wp_get_referer(): it returns false whenever the referer is the
		// current page — which a form posting to its own page always is. The
		// raw referer keeps that value; the request URI is the fallback.
		$candidates = array(
			wp_get_raw_referer(),
			isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		);

		foreach ( $candidates as $url ) {
			if ( $url && ( $id = url_to_postid( $url ) ) ) {
				return (int) $id;
			}
		}

		return (int) get_queried_object_id();
	}

	/**
	 * @return string
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/* ---------------------------------------------------------------------- *
	 * Retention
	 * ---------------------------------------------------------------------- */

	/**
	 * @internal
	 */
	public function _schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * @internal
	 */
	public function _action_retention() {
		$days = (int) self::setting( 'entries_retention_days', 0 );

		if ( $days > 0 ) {
			FW_Forms_Entries::delete_older_than( $days );
		}
	}

	/* ---------------------------------------------------------------------- *
	 * Privacy (Tools → Export / Erase Personal Data)
	 * ---------------------------------------------------------------------- */

	/**
	 * @internal
	 */
	public function _register_exporter( $exporters ) {
		$exporters['fw-form-entries'] = array(
			'exporter_friendly_name' => __( 'Form entries', 'fw' ),
			'callback'               => array( $this, '_export' ),
		);

		return $exporters;
	}

	/**
	 * @internal
	 */
	public function _register_eraser( $erasers ) {
		$erasers['fw-form-entries'] = array(
			'eraser_friendly_name' => __( 'Form entries', 'fw' ),
			'callback'             => array( $this, '_erase' ),
		);

		return $erasers;
	}

	/**
	 * @internal
	 */
	public function _export( $email, $page = 1 ) {
		$items = array();

		foreach ( FW_Forms_Entries::by_email( $email, 500 ) as $entry ) {
			$data = array(
				array( 'name' => __( 'Form', 'fw' ), 'value' => $entry->form_title ),
				array( 'name' => __( 'Submitted', 'fw' ), 'value' => $entry->created_at ),
			);

			foreach ( $entry->fields as $f ) {
				$data[] = array(
					'name'  => $f['label'],
					'value' => is_array( $f['value'] ) ? implode( ', ', $f['value'] ) : $f['value'],
				);
			}

			$items[] = array(
				'group_id'    => 'fw-form-entries',
				'group_label' => __( 'Form entries', 'fw' ),
				'item_id'     => 'fw-form-entry-' . $entry->id,
				'data'        => $data,
			);
		}

		return array( 'data' => $items, 'done' => true );
	}

	/**
	 * @internal
	 */
	public function _erase( $email, $page = 1 ) {
		$n = FW_Forms_Entries::delete_by_email( $email );

		return array(
			'items_removed'  => $n > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
