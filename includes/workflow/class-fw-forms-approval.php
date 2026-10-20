<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Showing entries on the site, and approving them first (contact form Settings → On the site).
 *
 *   views_allowed    the form's entries may be shown by an Entry View element (off by default: entries
 *                    hold personal data, so showing them is a decision per form)
 *   views_approval   new entries wait for approval before any view shows them (on by default)
 *   views_approvers  who is asked (emails; empty = the site admin email)
 *
 * A new entry of such a form is stored as `approval = pending`; the approvers get an email with the
 * answers and two signed links (Approve / Reject, valid 30 days, no login needed); the entry screen has
 * the same buttons and the list a "To approve" view. Every decision is recorded in the entry's history.
 */
class FW_Forms_Approval {

	const KEYS = array( 'views_allowed', 'views_approval', 'views_approvers' );

	public static function init() {
		add_filter( 'fw_ext_forms_entry_before_insert', array( __CLASS__, '_mark' ), 20, 2 );
		add_action( 'fw_ext_forms_entry_stored', array( __CLASS__, '_ask' ), 20, 3 );
		add_action( 'template_redirect', array( __CLASS__, '_link' ), 1 );
		add_action( 'fw_ext_forms_entry_panels', array( __CLASS__, '_panel' ), 3 );
		add_action( 'admin_post_fw_forms_entry_approval', array( __CLASS__, '_admin' ) );
	}

	public static function options() {
		$sw = function ( $label, $desc, $on ) {
			return array( 'type' => 'switch', 'label' => $label, 'desc' => $desc, 'value' => $on ? 'yes' : 'no', 'left-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ), 'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ) );
		};
		return array(
			'type'    => 'group',
			'options' => array(
				'views_allowed'   => $sw( __( 'Entries can be shown on the site', 'fw' ), __( 'Lets an Entry View element show this form\'s entries (a directory, listings, testimonials). Only the fields chosen on the element are shown; uploaded files never are.', 'fw' ), false ),
				'views_approval'  => $sw( __( 'Approve each entry before it is shown', 'fw' ), __( 'New entries wait until an approver approves them, from the email they get or from the entry.', 'fw' ), true ),
				'views_approvers' => array( 'type' => 'text', 'label' => __( 'Approvers', 'fw' ), 'desc' => __( 'Email addresses, separated by commas. Empty = the site admin email.', 'fw' ), 'value' => '' ),
				'edit_own'        => $sw( __( 'Senders can edit their entry', 'fw' ), __( 'A logged-in sender gets an Edit link next to their entry in an Entry View, until it is locked.', 'fw' ), false ),
				'edit_lock'       => array(
					'type'    => 'select',
					'label'   => __( 'Editing stops', 'fw' ),
					'value'   => 'approved',
					'choices' => array( 'approved' => __( 'Once the entry is approved', 'fw' ), 'days' => __( 'After a number of days', 'fw' ), 'never' => __( 'Never (an edit is approved again)', 'fw' ) ),
				),
				'edit_days'       => array( 'type' => 'text', 'label' => __( 'Days (for "After a number of days")', 'fw' ), 'value' => '14' ),
			),
		);
	}

	public static function form( $form_id ) {
		return class_exists( 'FW_Forms_Actions' ) ? FW_Forms_Actions::form_settings( array( 'id' => (string) $form_id, 'type' => 'contact-forms' ) ) : array();
	}

	public static function needs_approval( array $form ) {
		return 'yes' === ( $form['views_allowed'] ?? 'no' ) && 'no' !== ( $form['views_approval'] ?? 'yes' );
	}

	/** @internal New entries of a form that approves first start as pending. */
	public static function _mark( $entry, $data ) {
		if ( is_array( $entry ) && is_array( $data ) && self::needs_approval( self::form( (string) ( $data['id'] ?? '' ) ) ) ) {
			$entry['approval'] = 'pending';
		}
		return $entry;
	}

	/* ------------------------------------------------------------------ Signed links */

	private static function sig( $id, $do, $exp ) {
		return hash_hmac( 'sha256', $id . '|' . $do . '|' . $exp, wp_salt( 'auth' ) . 'fw-forms-approval' );
	}

	public static function link( $id, $do ) {
		$exp = time() + 30 * DAY_IN_SECONDS;
		return add_query_arg( array( 'fw_entry_approval' => (int) $id, 'do' => $do, 'exp' => $exp, 'sig' => self::sig( (int) $id, $do, $exp ) ), home_url( '/' ) );
	}

	/** @internal */
	public static function _ask( $id, $entry, $data ) {
		if ( 'pending' !== ( $entry['approval'] ?? '' ) ) {
			return;
		}
		$form = self::form( (string) ( $data['id'] ?? '' ) );
		$to   = array_filter( array_map( 'trim', explode( ',', (string) ( $form['views_approvers'] ?? '' ) ) ), 'is_email' );
		$to   = $to ? $to : array( (string) get_option( 'admin_email' ) );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$rows = array();
		foreach ( (array) ( $entry['fields'] ?? array() ) as $f ) {
			$v = is_array( $f['value'] ) ? implode( ', ', $f['value'] ) : (string) $f['value'];
			if ( '' !== $v ) {
				$rows[] = $f['label'] . ': ' . $v;
			}
		}
		$body = sprintf( __( "A new entry for \"%s\" is waiting for approval before it is shown on the site.\n\n", 'fw' ), $entry['form_title'] ?? '' )
			. implode( "\n", $rows )
			. "\n\n" . __( 'Approve:', 'fw' ) . ' ' . self::link( $id, 'approve' )
			. "\n" . __( 'Reject:', 'fw' ) . ' ' . self::link( $id, 'reject' )
			. "\n\n" . __( 'Or open it:', 'fw' ) . ' ' . admin_url( 'admin.php?page=fw-form-entries&entry=' . (int) $id ) . "\n";
		wp_mail( $to, sprintf( __( '[%1$s] To approve: %2$s', 'fw' ), $site, $entry['form_title'] ?? '' ), $body );
	}

	/** Approve or reject, and record it. */
	public static function decide( $id, $do, $by = '' ) {
		$e = FW_Forms_Entries::find( $id );
		if ( ! $e || ! in_array( $do, array( 'approve', 'reject' ), true ) ) {
			return false;
		}
		$state = 'approve' === $do ? 'approved' : 'rejected';
		FW_Forms_Entries::set_approval( $id, $state );
		if ( class_exists( 'FW_Forms_Entry_Notes' ) ) {
			FW_Forms_Entry_Notes::add( $id, 'approval', ( 'approved' === $state ? __( 'Approved', 'fw' ) : __( 'Rejected', 'fw' ) ) . ( '' !== $by ? ' (' . $by . ')' : '' ) );
		}
		/**
		 * Fires after an entry is approved or rejected.
		 *
		 * @param int    $id
		 * @param string $state approved | rejected
		 */
		do_action( 'fw_ext_forms_entry_approval', $id, $state );
		return $state;
	}

	/** @internal The links in the approver's email. */
	public static function _link() {
		if ( empty( $_GET['fw_entry_approval'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id  = absint( $_GET['fw_entry_approval'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$do  = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$exp = isset( $_GET['exp'] ) ? (int) $_GET['exp'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sig = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $exp < time() || ! hash_equals( self::sig( $id, $do, $exp ), $sig ) ) {
			wp_die( esc_html__( 'This link has expired or is not valid. Open the entry from Form Entries instead.', 'fw' ), esc_html__( 'Approval', 'fw' ), array( 'response' => 403 ) );
		}
		$state = self::decide( $id, $do, __( 'from the email', 'fw' ) );
		$msg   = 'approved' === $state ? __( 'The entry is approved and can now be shown on the site.', 'fw' ) : ( 'rejected' === $state ? __( 'The entry is rejected and will not be shown.', 'fw' ) : __( 'That entry no longer exists.', 'fw' ) );
		wp_die( esc_html( $msg ), esc_html__( 'Approval', 'fw' ), array( 'response' => 200 ) );
	}

	/** @internal Approve / Reject on the entry. */
	public static function _panel( $entry ) {
		if ( '' === (string) ( $entry->approval ?? '' ) ) {
			return;
		}
		$labels = array( 'pending' => __( 'Waiting for approval', 'fw' ), 'approved' => __( 'Approved: shown on the site', 'fw' ), 'rejected' => __( 'Rejected: not shown', 'fw' ) );
		$url    = function ( $do ) use ( $entry ) {
			return wp_nonce_url( add_query_arg( array( 'action' => 'fw_forms_entry_approval', 'entry' => (int) $entry->id, 'do' => $do ), admin_url( 'admin-post.php' ) ), 'fw_forms_entry_approval_' . (int) $entry->id );
		};
		echo '<p class="fw-entries__actions"><strong>' . esc_html( $labels[ $entry->approval ] ?? $entry->approval ) . '</strong> ';
		if ( 'approved' !== $entry->approval ) {
			echo '<a class="button button-primary" href="' . esc_url( $url( 'approve' ) ) . '">' . esc_html__( 'Approve', 'fw' ) . '</a> ';
		}
		if ( 'rejected' !== $entry->approval ) {
			echo '<a class="button" href="' . esc_url( $url( 'reject' ) ) . '">' . esc_html__( 'Reject', 'fw' ) . '</a>';
		}
		echo '</p>';
	}

	/** @internal */
	public static function _admin() {
		$id = isset( $_GET['entry'] ) ? absint( $_GET['entry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'fw_forms_entry_approval_' . $id );
		if ( ! current_user_can( FW_Forms_Entries_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fw' ) );
		}
		self::decide( $id, isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : '', wp_get_current_user()->display_name ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( FW_Forms_Entries_Admin::entry_url( $id ) );
		exit;
	}
}
