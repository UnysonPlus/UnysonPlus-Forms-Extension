<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Senders edit their own entry (contact form Settings → On the site → "Senders can edit their entry").
 *
 * The Edit link (in an Entry View with "Only the visitor's own entries", or a page per entry) opens the
 * form's own page with `?fw_edit_entry=<id>&k=<signature>`. The form starts filled with the stored
 * answers; sending it updates that entry instead of adding one: no notification, no actions, a change
 * note in the entry's history, and — on a form that approves first — the entry waits for approval again.
 *
 * Only the logged-in person who sent the entry can edit it, and only until it locks
 * (edit_lock: approved | days | never). Uploaded files and signatures keep their stored value.
 */
class FW_Forms_Entry_Edit {

	/** @var object|null The entry being edited on this request. */
	private static $entry = null;

	public static function init() {
		add_filter( 'fw_ext_forms_view_edit_link', array( __CLASS__, '_link' ), 10, 3 );
		add_filter( 'fw_ext_forms_prefill_values', array( __CLASS__, '_prefill' ), 20, 2 );
		add_filter( 'fw_ext_forms_pre_process', array( __CLASS__, '_save' ), 10, 2 );
	}

	private static function sig( $id, $user ) {
		return substr( hash_hmac( 'sha256', $id . '|' . $user, wp_salt( 'auth' ) . 'fw-forms-edit' ), 0, 20 );
	}

	/** Whether the current person may edit this entry now ('' = yes, else why not). */
	public static function refusal( $entry, array $form = null ) {
		$form = null === $form ? FW_Forms_Approval::form( $entry->form_id ) : $form;
		if ( 'yes' !== ( $form['edit_own'] ?? 'no' ) ) {
			return __( 'This form\'s entries cannot be edited.', 'fw' );
		}
		if ( ! is_user_logged_in() || (int) $entry->user_id !== get_current_user_id() ) {
			return __( 'Only the person who sent this entry can edit it.', 'fw' );
		}
		if ( in_array( $entry->status, array( 'spam', 'partial' ), true ) ) {
			return __( 'This entry cannot be edited.', 'fw' );
		}
		$lock = (string) ( $form['edit_lock'] ?? 'approved' );
		if ( 'approved' === $lock && 'approved' === (string) $entry->approval ) {
			return __( 'This entry is approved and can no longer be edited.', 'fw' );
		}
		if ( 'days' === $lock && strtotime( $entry->created_at ) + max( 1, (int) ( $form['edit_days'] ?? 14 ) ) * DAY_IN_SECONDS < current_time( 'timestamp' ) ) {
			return __( 'The time to edit this entry has passed.', 'fw' );
		}
		return '';
	}

	public static function url( $entry ) {
		$page = $entry->post_id ? get_permalink( $entry->post_id ) : '';
		return $page ? add_query_arg( array( 'fw_edit_entry' => (int) $entry->id, 'k' => self::sig( (int) $entry->id, (int) $entry->user_id ) ), $page ) : '';
	}

	/** @internal */
	public static function _link( $html, $entry, $form ) {
		if ( '' !== self::refusal( $entry, $form ) || '' === ( $u = self::url( $entry ) ) ) {
			return $html;
		}
		$state = 'pending' === $entry->approval ? ' <span>' . esc_html__( '(waiting for approval)', 'fw' ) . '</span>' : '';
		return '<p class="fw-entry-view__edit"><a href="' . esc_url( $u ) . '">' . esc_html__( 'Edit', 'fw' ) . '</a>' . $state . '</p>';
	}

	/** Whether this request edits an entry of that form. */
	public static function editing( $form_id ) {
		$e = self::current();
		return $e && (string) $e->form_id === (string) $form_id;
	}

	/** The entry asked for on this request, when the signature, the owner and the lock all agree. */
	private static function current() {
		if ( null !== self::$entry ) {
			return self::$entry ? self::$entry : null;
		}
		self::$entry = false;
		$id  = isset( $_GET['fw_edit_entry'] ) ? absint( $_GET['fw_edit_entry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sig = isset( $_GET['k'] ) ? sanitize_text_field( wp_unslash( $_GET['k'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $id || '' === $sig || ! is_user_logged_in() ) {
			return null;
		}
		$e = FW_Forms_Entries::find( $id );
		if ( ! $e || ! hash_equals( self::sig( $id, (int) $e->user_id ), $sig ) || '' !== self::refusal( $e ) ) {
			return null;
		}
		$e->raw = json_decode( (string) ( $e->raw_values ?? '' ), true );
		$e->raw = is_array( $e->raw ) ? $e->raw : array();
		self::$entry = $e;
		return $e;
	}

	/** @internal Start the form with the stored answers. */
	public static function _prefill( $out, $items ) {
		$e = self::current();
		if ( ! $e || 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return $out;
		}
		$codes = array();
		foreach ( (array) $items as $item ) {
			if ( ! empty( $item['shortcode'] ) ) {
				$codes[ $item['shortcode'] ] = true;
			}
		}
		foreach ( $e->raw as $code => $v ) {
			if ( isset( $codes[ $code ] ) ) {
				$out[ $code ] = $v;
			}
		}
		return $out;
	}

	/**
	 * @internal Sending the form while editing: update the entry, skip everything else.
	 *
	 * @param array|null $result null = go on as usual; array( 'redirect' => url ) = handled.
	 * @param array      $data   form_id, form_type, form_values, shortcode_to_item, uploads
	 */
	public static function _save( $result, $data ) {
		$e = self::current();
		if ( null !== $result || ! $e || (string) $e->form_id !== (string) ( $data['form_id'] ?? '' ) ) {
			return $result;
		}
		$items  = (array) ( $data['shortcode_to_item'] ?? array() );
		$fields = FW_Forms_Entries_Capture::fields_from_submission( (array) ( $data['form_values'] ?? array() ), $items, (array) ( $data['uploads'] ?? array() ) );
		$old    = array();
		foreach ( (array) $e->fields as $f ) {
			$old[ (string) ( $f['id'] ?? '' ) ] = $f;
		}
		$changes = array();
		foreach ( $fields as $i => $f ) {
			$k = (string) ( $f['id'] ?? '' );
			$o = $old[ $k ] ?? null;
			// Files and signatures are not sent again: keep what was stored.
			if ( $o && ( ! empty( $o['file'] ) || in_array( $items[ $k ]['type'] ?? '', array( 'file-upload', 'signature' ), true ) ) ) {
				$fields[ $i ] = $o;
				continue;
			}
			$a = $o ? ( is_array( $o['value'] ) ? implode( ', ', $o['value'] ) : (string) $o['value'] ) : '';
			$b = is_array( $f['value'] ) ? implode( ', ', $f['value'] ) : (string) $f['value'];
			if ( $a !== $b ) {
				$changes[] = $f['label'] . ': "' . $a . '" → "' . $b . '"';
			}
		}
		$raw = array_merge( $e->raw, FW_Forms_Entries_Capture::raw_values( $items ) );
		FW_Forms_Entries::update_fields( $e->id, $fields, FW_Forms_Entries_Capture::primary_email( $fields ), $raw );

		$form = FW_Forms_Approval::form( $e->form_id );
		if ( $changes ) {
			if ( class_exists( 'FW_Forms_Entry_Notes' ) ) {
				FW_Forms_Entry_Notes::add( $e->id, 'change', __( 'Edited by the sender', 'fw' ) . "\n" . implode( "\n", $changes ) );
			}
			if ( FW_Forms_Approval::needs_approval( $form ) && 'pending' !== $e->approval ) {
				FW_Forms_Entries::set_approval( $e->id, 'pending' );
				FW_Forms_Approval::_ask( $e->id, array( 'approval' => 'pending', 'fields' => $fields, 'form_title' => $e->form_title ), array( 'id' => $e->form_id ) );
			}
			/**
			 * Fires after a sender edited their entry.
			 *
			 * @param int   $id
			 * @param array $changes "Label: old → new" lines
			 */
			do_action( 'fw_ext_forms_entry_edited', $e->id, $changes );
		}

		FW_Flash_Messages::add( 'fw_ext_contact_form_process', $changes ? __( 'Your changes are saved.', 'fw' ) : __( 'Nothing was changed.', 'fw' ), 'success' );
		// The same address: the form stays open with the saved answers and shows the message.
		return array( 'redirect' => fw_current_url() );
	}
}
