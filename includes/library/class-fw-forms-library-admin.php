<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Unyson+ → Forms: the list of saved forms (WordPress's own list screen for `fw_form`), with
 * fields / entries / "placed on" columns, Duplicate and Export row actions, an Import button,
 * and the element's "Save to the library" endpoint.
 *
 * Every action runs on admin-post / admin-ajax with a nonce and the edit_pages capability, and
 * redirects back (PRG); export streams a JSON file.
 */
class FW_Forms_Library_Admin {

	const NONCE = 'fw_forms_library';

	public static function init() {
		$pt = FW_Forms_Library::POST_TYPE;
		add_filter( "manage_{$pt}_posts_columns", array( __CLASS__, '_columns' ) );
		add_action( "manage_{$pt}_posts_custom_column", array( __CLASS__, '_column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, '_row_actions' ), 10, 2 );
		add_filter( "views_edit-{$pt}", array( __CLASS__, '_import_box' ) );
		add_action( 'admin_post_fw_forms_library_duplicate', array( __CLASS__, '_duplicate' ) );
		add_action( 'admin_post_fw_forms_library_export', array( __CLASS__, '_export' ) );
		add_action( 'admin_post_fw_forms_library_import', array( __CLASS__, '_import' ) );
		add_action( 'wp_ajax_fw_forms_library_save', array( __CLASS__, '_ajax_save' ) );
		add_action( 'admin_notices', array( __CLASS__, '_notice' ) );
	}

	private static function can() {
		return current_user_can( 'edit_pages' );
	}

	private static function list_url( array $args = array() ) {
		return add_query_arg( $args, admin_url( 'edit.php?post_type=' . FW_Forms_Library::POST_TYPE ) );
	}

	/* ------------------------------------------------------------------ list */

	/** @internal */
	public static function _columns( $cols ) {
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'title' === $k ) {
				$out['fw_fields']  = __( 'Fields', 'fw' );
				$out['fw_entries'] = __( 'Entries', 'fw' );
				$out['fw_placed']  = __( 'Placed on', 'fw' );
			}
		}
		return $out;
	}

	/** @internal */
	public static function _column( $col, $post_id ) {
		switch ( $col ) {
			case 'fw_fields':
				echo (int) FW_Forms_Library::field_count( $post_id );
				break;
			case 'fw_entries':
				$n = 0;
				if ( class_exists( 'FW_Forms_Entries' ) ) {
					$c = FW_Forms_Entries::counts( FW_Forms_Library::form_id( $post_id ) );
					$n = (int) ( $c['all'] ?? 0 );
				}
				$url = add_query_arg( array( 'page' => 'fw-form-entries', 'form_id' => FW_Forms_Library::form_id( $post_id ) ), admin_url( 'admin.php' ) );
				echo $n ? '<a href="' . esc_url( $url ) . '">' . (int) $n . '</a>' : '0';
				break;
			case 'fw_placed':
				$where = FW_Forms_Library::placements( $post_id );
				if ( ! $where ) {
					echo '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not placed on any page yet', 'fw' ) . '</span>';
					break;
				}
				$links = array();
				foreach ( $where as $w ) {
					$links[] = $w['edit'] ? '<a href="' . esc_url( $w['edit'] ) . '">' . esc_html( $w['title'] ) . '</a>' : esc_html( $w['title'] );
				}
				echo implode( ', ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
				break;
		}
	}

	/** @internal */
	public static function _row_actions( $actions, $post ) {
		if ( FW_Forms_Library::POST_TYPE !== $post->post_type || ! self::can() ) {
			return $actions;
		}
		$base = admin_url( 'admin-post.php' );
		$actions['fw_duplicate'] = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'fw_forms_library_duplicate', 'id' => $post->ID ), $base ), self::NONCE ) ) . '">' . esc_html__( 'Duplicate', 'fw' ) . '</a>';
		$actions['fw_export']    = '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'fw_forms_library_export', 'id' => $post->ID ), $base ), self::NONCE ) ) . '">' . esc_html__( 'Export', 'fw' ) . '</a>';
		return $actions;
	}

	/** @internal Import button above the list. */
	public static function _import_box( $views ) {
		if ( self::can() ) {
			?>
			<details class="fw-forms-import" style="margin:8px 0 12px">
				<summary class="button"><?php esc_html_e( 'Import a form', 'fw' ); ?></summary>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px">
					<input type="hidden" name="action" value="fw_forms_library_import">
					<?php wp_nonce_field( self::NONCE ); ?>
					<label for="fw-forms-import-file"><?php esc_html_e( 'A form exported from Unyson+ (.json):', 'fw' ); ?></label>
					<input type="file" id="fw-forms-import-file" name="form_file" accept=".json,application/json" required>
					<?php submit_button( __( 'Import', 'fw' ), 'secondary', 'submit', false ); ?>
				</form>
			</details>
			<?php
		}
		return $views;
	}

	/** @internal */
	public static function _notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . FW_Forms_Library::POST_TYPE !== $screen->id || empty( $_GET['fw_forms_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$msg  = sanitize_key( wp_unslash( $_GET['fw_forms_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$text = array(
			'duplicated' => __( 'Form duplicated.', 'fw' ),
			'imported'   => __( 'Form imported.', 'fw' ),
			'badfile'    => __( 'That file is not a form exported from Unyson+.', 'fw' ),
		);
		if ( isset( $text[ $msg ] ) ) {
			$cls = 'badfile' === $msg ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $text[ $msg ] ) . '</p></div>';
		}
	}

	/* ------------------------------------------------------------------ actions */

	/** @internal */
	public static function _duplicate() {
		check_admin_referer( self::NONCE );
		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$def = FW_Forms_Library::definition( $id );
		if ( ! self::can() || ! $def ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fw' ) );
		}
		/* translators: %s: form title */
		FW_Forms_Library::create( sprintf( __( '%s (copy)', 'fw' ), get_the_title( $id ) ), FW_Forms_Library::export_shape( $def ) );
		wp_safe_redirect( self::list_url( array( 'fw_forms_msg' => 'duplicated' ) ) );
		exit;
	}

	/** @internal */
	public static function _export() {
		check_admin_referer( self::NONCE );
		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$def = FW_Forms_Library::definition( $id );
		if ( ! self::can() || ! $def ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fw' ) );
		}
		$data = array( 'type' => 'unysonplus-form', 'title' => get_the_title( $id ) ) + FW_Forms_Library::export_shape( $def );
		$name = sanitize_file_name( 'form-' . ( get_post_field( 'post_name', $id ) ?: $id ) . '.json' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		exit;
	}

	/** @internal */
	public static function _import() {
		check_admin_referer( self::NONCE );
		if ( ! self::can() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'fw' ) );
		}
		$file = isset( $_FILES['form_file']['tmp_name'] ) ? (string) $_FILES['form_file']['tmp_name'] : '';
		$size = isset( $_FILES['form_file']['size'] ) ? (int) $_FILES['form_file']['size'] : 0;
		$data = ( $file && is_uploaded_file( $file ) && $size > 0 && $size < 1048576 ) ? json_decode( (string) file_get_contents( $file ), true ) : null; // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $data ) || ( $data['type'] ?? '' ) !== 'unysonplus-form' || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			wp_safe_redirect( self::list_url( array( 'fw_forms_msg' => 'badfile' ) ) );
			exit;
		}
		$title = isset( $data['title'] ) ? (string) $data['title'] : __( 'Imported form', 'fw' );
		FW_Forms_Library::create( $title, $data );
		wp_safe_redirect( self::list_url( array( 'fw_forms_msg' => 'imported' ) ) );
		exit;
	}

	/**
	 * @internal The element's "Save to the library": the current fields + settings become a saved form.
	 */
	public static function _ajax_save() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! self::can() ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'fw' ) ), 403 );
		}
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$fields = isset( $_POST['fields'] ) ? json_decode( wp_unslash( $_POST['fields'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- decoded and normalised below
		$settings = isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- filtered by import_into()
		if ( ! is_array( $fields ) || ! $fields ) {
			wp_send_json_error( array( 'message' => __( 'This form has no fields to save yet.', 'fw' ) ) );
		}
		$id = FW_Forms_Library::create( '' !== $title ? $title : __( 'Untitled form', 'fw' ), array( 'fields' => $fields, 'settings' => $settings ) );
		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}
		wp_send_json_success( array( 'id' => $id, 'title' => get_the_title( $id ), 'edit' => get_edit_post_link( $id, 'raw' ) ) );
	}
}
