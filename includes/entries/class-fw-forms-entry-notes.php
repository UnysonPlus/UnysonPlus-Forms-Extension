<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Working with entries (Form Entries → an entry): a star, an assignee, notes, a reply by email, and
 * corrections to an answer — every one of them recorded on the entry's history
 * (`{prefix}fw_form_entry_notes`: kind note | reply | change | assign | star).
 *
 * The list gains a ★ column and the views "Starred" and "Assigned to me"; an assignee gets an email.
 * Everything goes through admin-post with a nonce and the Form Entries capability.
 */
class FW_Forms_Entry_Notes {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'fw_ext_forms_entry_notes_db_version';

	public static function init() {
		self::maybe_install();
		add_action( 'fw_ext_forms_entry_panels', array( __CLASS__, '_panel' ), 20 );
		add_action( 'admin_post_fw_forms_entry_work', array( __CLASS__, '_handle' ) );
		add_filter( 'fw_ext_forms_entries_columns', array( __CLASS__, '_columns' ), 5 );
		add_filter( 'fw_ext_forms_entries_column', array( __CLASS__, '_column' ), 10, 3 );
		add_action( 'fw_ext_forms_entries_deleted', array( __CLASS__, '_deleted' ) );
	}

	/** @internal An entry's history goes with it. */
	public static function _deleted( $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( $ids ) {
			$wpdb->query( 'DELETE FROM ' . self::table() . ' WHERE entry_id IN (' . implode( ',', $ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL -- ints
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'fw_form_entry_notes';
	}

	public static function maybe_install() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
	id bigint(20) unsigned NOT NULL auto_increment,
	entry_id bigint(20) unsigned NOT NULL default 0,
	user_id bigint(20) unsigned NOT NULL default 0,
	kind varchar(20) NOT NULL default 'note',
	body longtext NOT NULL,
	created_at datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (id),
	KEY entry_id (entry_id)
) {$wpdb->get_charset_collate()};" );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		}
	}

	public static function add( $entry_id, $kind, $body ) {
		global $wpdb;
		$wpdb->insert( self::table(), array( 'entry_id' => (int) $entry_id, 'user_id' => get_current_user_id(), 'kind' => sanitize_key( $kind ), 'body' => (string) $body, 'created_at' => current_time( 'mysql' ) ) );
		/** Fires after something is recorded on an entry. */
		do_action( 'fw_ext_forms_entry_note', (int) $entry_id, $kind, $body );
	}

	public static function for_entry( $entry_id ) {
		global $wpdb;
		return (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY id DESC', (int) $entry_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** People who can work with entries (possible assignees). */
	public static function team() {
		return get_users( array( 'capability' => FW_Forms_Entries_Admin::capability(), 'fields' => array( 'ID', 'display_name', 'user_email' ), 'number' => 200 ) );
	}

	private static function set( $entry_id, array $cols ) {
		global $wpdb;
		$wpdb->update( FW_Forms_Entries::table(), $cols, array( 'id' => (int) $entry_id ) );
	}

	private static function url( $entry_id, $do, array $extra = array() ) {
		return wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'fw_forms_entry_work', 'entry' => (int) $entry_id, 'do' => $do ), $extra ), admin_url( 'admin-post.php' ) ), 'fw_forms_entry_work_' . (int) $entry_id );
	}

	/* ------------------------------------------------------------------ The panel */

	/** @internal */
	public static function _panel( $entry ) {
		$team    = self::team();
		$notes   = self::for_entry( $entry->id );
		$starred = ! empty( $entry->starred );
		$labels  = array( 'note' => __( 'Note', 'fw' ), 'reply' => __( 'Reply sent', 'fw' ), 'change' => __( 'Answer corrected', 'fw' ), 'assign' => __( 'Assigned', 'fw' ), 'star' => __( 'Star', 'fw' ) );
		$post    = esc_url( admin_url( 'admin-post.php' ) );
		$hidden  = function ( $do ) use ( $entry ) {
			return '<input type="hidden" name="action" value="fw_forms_entry_work"><input type="hidden" name="entry" value="' . (int) $entry->id . '"><input type="hidden" name="do" value="' . esc_attr( $do ) . '">' . wp_nonce_field( 'fw_forms_entry_work_' . (int) $entry->id, '_wpnonce', true, false );
		};
		?>
		<div class="fw-entries__work">
			<style>
				.fw-entries__work { margin-top: 1.5rem; display: grid; gap: 16px; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); max-width: 1100px; }
				@media (max-width: 960px) { .fw-entries__work { grid-template-columns: 1fr; } }
				.fw-entries__work section { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 14px 16px; }
				.fw-entries__work h2 { margin: 0 0 .6rem; font-size: 14px; }
				.fw-entries__work textarea, .fw-entries__work input[type="text"] { width: 100%; }
				.fw-entries__history { margin: 0; padding: 0; list-style: none; max-height: 420px; overflow: auto; }
				.fw-entries__history li { border-top: 1px solid #f0f0f1; padding: .5rem 0; }
				.fw-entries__history .meta { color: #646970; font-size: 12px; }
				.fw-entries__star { font-size: 18px; text-decoration: none; }
			</style>
			<section>
				<h2><?php esc_html_e( 'Follow-up', 'fw' ); ?></h2>
				<p>
					<a class="fw-entries__star" href="<?php echo esc_url( self::url( $entry->id, 'star' ) ); ?>" aria-pressed="<?php echo $starred ? 'true' : 'false'; ?>"><?php echo $starred ? '★' : '☆'; ?> <?php echo esc_html( $starred ? __( 'Starred', 'fw' ) : __( 'Star this entry', 'fw' ) ); ?></a>
				</p>
				<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>">
					<?php echo $hidden( 'assign' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<label for="fw-entry-assign"><?php esc_html_e( 'Assigned to', 'fw' ); ?></label>
					<select id="fw-entry-assign" name="user">
						<option value="0"><?php esc_html_e( 'Nobody', 'fw' ); ?></option>
						<?php foreach ( $team as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>"<?php selected( (int) $u->ID, (int) ( $entry->assigned_to ?? 0 ) ); ?>><?php echo esc_html( $u->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<button class="button"><?php esc_html_e( 'Save', 'fw' ); ?></button>
				</form>
				<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>" style="margin-top:1rem">
					<?php echo $hidden( 'note' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<label for="fw-entry-note"><?php esc_html_e( 'Add a note (only your team sees it)', 'fw' ); ?></label>
					<textarea id="fw-entry-note" name="body" rows="3" required></textarea>
					<button class="button"><?php esc_html_e( 'Add note', 'fw' ); ?></button>
				</form>
				<?php if ( is_email( $entry->email ) ) : ?>
					<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>" style="margin-top:1rem">
						<?php echo $hidden( 'reply' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<p><strong><?php echo esc_html( sprintf( __( 'Reply to %s', 'fw' ), $entry->email ) ); ?></strong></p>
						<label for="fw-entry-subject" class="screen-reader-text"><?php esc_html_e( 'Subject', 'fw' ); ?></label>
						<input type="text" id="fw-entry-subject" name="subject" value="<?php echo esc_attr( sprintf( __( 'Re: %s', 'fw' ), '' !== $entry->form_title ? $entry->form_title : get_bloginfo( 'name' ) ) ); ?>">
						<label for="fw-entry-reply" class="screen-reader-text"><?php esc_html_e( 'Message', 'fw' ); ?></label>
						<textarea id="fw-entry-reply" name="body" rows="5" required></textarea>
						<button class="button button-primary"><?php esc_html_e( 'Send reply', 'fw' ); ?></button>
					</form>
				<?php endif; ?>
			</section>
			<section>
				<h2><?php esc_html_e( 'History', 'fw' ); ?></h2>
				<?php if ( ! $notes ) : ?>
					<p class="description"><?php esc_html_e( 'Nothing yet. Notes, replies, corrections and assignments appear here.', 'fw' ); ?></p>
				<?php else : ?>
					<ul class="fw-entries__history">
						<?php foreach ( $notes as $n ) : $u = get_userdata( (int) $n->user_id ); ?>
							<li>
								<div class="meta"><?php echo esc_html( ( $labels[ $n->kind ] ?? $n->kind ) . ' · ' . ( $u ? $u->display_name : __( 'Someone', 'fw' ) ) . ' · ' . date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $n->created_at ) ) ); ?></div>
								<div><?php echo nl2br( esc_html( $n->body ) ); ?></div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<details style="margin-top:1rem">
					<summary><?php esc_html_e( 'Correct an answer', 'fw' ); ?></summary>
					<form method="post" action="<?php echo $post; // phpcs:ignore WordPress.Security.EscapeOutput ?>">
						<?php echo $hidden( 'change' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<?php foreach ( (array) $entry->fields as $i => $f ) : ?>
							<?php if ( ! empty( $f['file'] ) || is_array( $f['value'] ) || in_array( $f['type'] ?? '', array( 'notice', 'quiz', 'signature' ), true ) || 0 === strpos( (string) ( $f['id'] ?? '' ), '_' ) ) { continue; } ?>
							<p>
								<label for="fw-entry-f<?php echo (int) $i; ?>"><?php echo esc_html( $f['label'] ); ?></label>
								<input type="text" id="fw-entry-f<?php echo (int) $i; ?>" name="fields[<?php echo esc_attr( (string) $f['id'] ); ?>]" value="<?php echo esc_attr( (string) $f['value'] ); ?>">
							</p>
						<?php endforeach; ?>
						<button class="button"><?php esc_html_e( 'Save corrections', 'fw' ); ?></button>
						<span class="description"><?php esc_html_e( 'Each change is recorded in the history, with the old answer.', 'fw' ); ?></span>
					</form>
				</details>
			</section>
		</div>
		<?php
	}

	/* ------------------------------------------------------------------ Actions */

	/** @internal */
	public static function _handle() {
		$id = isset( $_REQUEST['entry'] ) ? absint( $_REQUEST['entry'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_admin_referer( 'fw_forms_entry_work_' . $id );
		if ( ! current_user_can( FW_Forms_Entries_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'fw' ) );
		}
		$entry = FW_Forms_Entries::find( $id );
		if ( ! $entry ) {
			wp_die( esc_html__( 'That entry no longer exists.', 'fw' ) );
		}
		$do = isset( $_REQUEST['do'] ) ? sanitize_key( $_REQUEST['do'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$me = wp_get_current_user();
		switch ( $do ) {
			case 'star':
				$on = empty( $entry->starred );
				self::set( $id, array( 'starred' => $on ? 1 : 0 ) );
				self::add( $id, 'star', $on ? __( 'Starred', 'fw' ) : __( 'Star removed', 'fw' ) );
				break;

			case 'assign':
				$uid = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$u   = $uid ? get_userdata( $uid ) : null;
				if ( $uid && ( ! $u || ! user_can( $u, FW_Forms_Entries_Admin::capability() ) ) ) {
					break;
				}
				if ( (int) $entry->assigned_to !== $uid ) {
					self::set( $id, array( 'assigned_to' => $uid ) );
					self::add( $id, 'assign', $u ? sprintf( __( 'Assigned to %s', 'fw' ), $u->display_name ) : __( 'No longer assigned', 'fw' ) );
					if ( $u && $uid !== (int) $me->ID ) {
						wp_mail( $u->user_email, sprintf( __( '[%1$s] An entry was assigned to you: %2$s', 'fw' ), wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), $entry->form_title ), sprintf( __( "%1\$s assigned you an entry from \"%2\$s\".\n\n%3\$s", 'fw' ), $me->display_name, $entry->form_title, FW_Forms_Entries_Admin::entry_url( $id ) ) );
					}
				}
				break;

			case 'note':
				$body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( '' !== trim( $body ) ) {
					self::add( $id, 'note', $body );
				}
				break;

			case 'reply':
				$body    = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
				if ( '' !== trim( $body ) && is_email( $entry->email ) ) {
					$sent = wp_mail( $entry->email, $subject, $body, array( 'Reply-To: ' . $me->display_name . ' <' . $me->user_email . '>' ) );
					self::add( $id, 'reply', $subject . "\n\n" . $body . ( $sent ? '' : "\n\n" . __( '(The email could not be sent.)', 'fw' ) ) );
				}
				break;

			case 'change':
				$new     = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput
				$fields  = (array) $entry->fields;
				$changes = array();
				foreach ( $fields as $i => $f ) {
					$key = (string) ( $f['id'] ?? '' );
					if ( ! isset( $new[ $key ] ) || is_array( $f['value'] ) || ! empty( $f['file'] ) ) {
						continue;
					}
					$v = 'textarea' === ( $f['type'] ?? '' ) ? sanitize_textarea_field( (string) $new[ $key ] ) : sanitize_text_field( (string) $new[ $key ] );
					if ( $v !== (string) $f['value'] ) {
						$changes[]             = sprintf( '%1$s: "%2$s" → "%3$s"', $f['label'], $f['value'], $v );
						$fields[ $i ]['value'] = $v;
					}
				}
				if ( $changes ) {
					FW_Forms_Entries::update_fields( $id, $fields, FW_Forms_Entries_Capture::primary_email( $fields ) );
					self::add( $id, 'change', implode( "\n", $changes ) );
				}
				break;
		}
		wp_safe_redirect( FW_Forms_Entries_Admin::entry_url( $id ) );
		exit;
	}

	/* ------------------------------------------------------------------ The list */

	/** @internal A ★ column after the checkbox. */
	public static function _columns( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'cb' === $k ) {
				$out['starred'] = '<span aria-hidden="true">★</span><span class="screen-reader-text">' . esc_html__( 'Starred', 'fw' ) . '</span>';
			}
		}
		return $out;
	}

	/** @internal */
	public static function _column( $html, $column, $item ) {
		if ( 'starred' === $column ) {
			$on = ! empty( $item->starred );
			return '<a href="' . esc_url( self::url( $item->id, 'star' ) ) . '" style="text-decoration:none" aria-label="' . esc_attr( $on ? __( 'Remove the star', 'fw' ) : __( 'Star this entry', 'fw' ) ) . '">' . ( $on ? '★' : '☆' ) . '</a>'
				. ( ! empty( $item->assigned_to ) && ( $u = get_userdata( (int) $item->assigned_to ) ) ? '<br><span class="description" title="' . esc_attr__( 'Assigned to', 'fw' ) . '">' . esc_html( $u->display_name ) . '</span>' : '' );
		}
		return $html;
	}
}
