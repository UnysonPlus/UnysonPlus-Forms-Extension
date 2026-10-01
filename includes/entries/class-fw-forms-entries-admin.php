<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Forms → Entries: the admin screen.
 *
 * Follows the Newsletter CRM's admin discipline:
 *  - a submenu under the shared `fw-extensions` parent;
 *  - EVERY action (single, bulk, export) runs on `load-{hook}` — before any
 *    output — then redirects (PRG), so a refresh never repeats it and the CSV
 *    can stream with clean headers;
 *  - the list is a native WP_List_Table.
 */
class FW_Forms_Entries_Admin {

	const PARENT_SLUG = 'fw-extensions';
	const PAGE_SLUG   = 'fw-form-entries';
	const NONCE       = 'fw_form_entries_action';

	/** @var string */
	private $hook_suffix = '';

	/** @var FW_Forms_Entries_List_Table|null */
	private $table = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, '_action_admin_menu' ) );
		add_filter( 'set-screen-option', array( $this, '_filter_screen_option' ), 10, 3 );
	}

	/* ---------------------------------------------------------------------- *
	 * Menu + URLs
	 * ---------------------------------------------------------------------- */

	/**
	 * @return string
	 */
	public static function capability() {
		/** Filters the capability (default manage_options) required to see form entries. */
		return apply_filters( 'fw_ext_forms_entries_capability', 'manage_options' );
	}

	/**
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * @param int $id
	 *
	 * @return string
	 */
	public static function entry_url( $id ) {
		return add_query_arg( 'entry', (int) $id, self::page_url() );
	}

	/**
	 * @param string $action
	 * @param int    $id
	 *
	 * @return string
	 */
	public static function action_url( $action, $id ) {
		return wp_nonce_url( add_query_arg( array( 'fw_entries_action' => $action, 'id' => (int) $id ), self::page_url() ), self::NONCE );
	}

	/**
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'new'      => __( 'New', 'fw' ),
			'read'     => __( 'Read', 'fw' ),
			'archived' => __( 'Archived', 'fw' ),
		);
	}

	/**
	 * @internal
	 */
	public function _action_admin_menu() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		$this->hook_suffix = add_submenu_page(
			self::PARENT_SLUG,
			__( 'Form Entries', 'fw' ),
			__( 'Form Entries', 'fw' ),
			self::capability(),
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( $this->hook_suffix ) {
			add_action( 'load-' . $this->hook_suffix, array( $this, '_action_load' ) );
			add_action( 'admin_enqueue_scripts', array( $this, '_action_enqueue' ) );
		}
	}

	/**
	 * @internal
	 */
	public function _filter_screen_option( $status, $option, $value ) {
		return 'fw_forms_entries_per_page' === $option ? max( 1, min( 500, (int) $value ) ) : $status;
	}

	/**
	 * @internal
	 */
	public function _action_enqueue( $hook ) {
		if ( $hook !== $this->hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'fw-forms-entries',
			fw_ext( 'forms' )->get_uri( '/static/css/entries-admin.css' ),
			array(),
			fw_ext( 'forms' )->manifest->get_version()
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Actions — all on load-{hook}, all PRG
	 * ---------------------------------------------------------------------- */

	/**
	 * @internal
	 */
	public function _action_load() {
		require_once dirname( __FILE__ ) . '/class-fw-forms-entries-list-table.php';

		add_screen_option( 'per_page', array(
			'label'   => __( 'Entries per page', 'fw' ),
			'default' => 20,
			'option'  => 'fw_forms_entries_per_page',
		) );

		$this->table = new FW_Forms_Entries_List_Table();

		// Opening an entry marks it read — the inbox convention.
		if ( isset( $_GET['entry'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$entry = FW_Forms_Entries::find( (int) $_GET['entry'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( $entry && 'new' === $entry->status ) {
				FW_Forms_Entries::set_status( $entry->id, 'read' );
			}

			return;
		}

		// Single-row link actions.
		if ( isset( $_GET['fw_entries_action'], $_GET['id'] ) ) {
			check_admin_referer( self::NONCE );
			$this->apply( sanitize_key( wp_unslash( $_GET['fw_entries_action'] ) ), array( (int) $_GET['id'] ) );
			$this->redirect();
		}

		// Export streams and exits — hence the load- hook.
		if ( isset( $_GET['fw_entries_action'] ) && 'export' === $_GET['fw_entries_action'] ) {
			check_admin_referer( self::NONCE );
			$this->export( $this->table->filters() );
		}

		// Bulk actions arrive from the list table's own form, nonced by core
		// under `bulk-{plural}`.
		$bulk = $this->table->current_action();

		if ( $bulk && ! empty( $_REQUEST['ids'] ) ) {
			check_admin_referer( 'bulk-entries' );
			$this->apply( $bulk, array_map( 'intval', (array) $_REQUEST['ids'] ) );
			$this->redirect();
		}
	}

	/**
	 * @param string $action
	 * @param int[]  $ids
	 */
	private function apply( $action, array $ids ) {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		switch ( $action ) {
			case 'read':
			case 'archive':
				$n = FW_Forms_Entries::set_status( $ids, 'read' === $action ? 'read' : 'archived' );
				$this->notice( 'success', sprintf( _n( '%d entry updated.', '%d entries updated.', $n, 'fw' ), $n ) );
				break;

			case 'delete':
				$n = FW_Forms_Entries::delete( $ids );
				$this->notice( 'success', sprintf( _n( '%d entry deleted.', '%d entries deleted.', $n, 'fw' ), $n ) );
				break;
		}
	}

	/**
	 * Redirect back to the list with the filters intact and the action params
	 * stripped, so a refresh cannot repeat the action.
	 */
	private function redirect() {
		$url = remove_query_arg( array( 'fw_entries_action', 'id', 'ids', 'action', 'action2', '_wpnonce', '_wp_http_referer', 'entry' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @param string $type
	 * @param string $message
	 */
	private function notice( $type, $message ) {
		set_transient( 'fw_form_entries_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 );
	}

	/* ---------------------------------------------------------------------- *
	 * Export
	 * ---------------------------------------------------------------------- */

	/**
	 * CSV of the CURRENT filtered view. One column per distinct field label
	 * across the exported rows, so different forms in one export do not
	 * collide and a spreadsheet opens with real headers.
	 *
	 * Streamed in chunks; never loads the table into memory.
	 *
	 * @param array $filters
	 */
	private function export( array $filters ) {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		// Pass 1: discover the column set.
		$labels = array();
		FW_Forms_Entries::each( $filters, function ( $row ) use ( &$labels ) {
			foreach ( $row->fields as $f ) {
				$labels[ $f['label'] ] = true;
			}
		} );
		$labels = array_keys( $labels );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="form-entries-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM so Excel reads UTF-8.
		fputcsv( $out, array_merge( array( 'ID', 'Form', 'Email', 'Status', 'Submitted' ), $labels ) );

		// Pass 2: stream rows.
		FW_Forms_Entries::each( $filters, function ( $row ) use ( $out, $labels ) {
			$by_label = array();

			foreach ( $row->fields as $f ) {
				$by_label[ $f['label'] ] = is_array( $f['value'] ) ? implode( ', ', $f['value'] ) : $f['value'];
			}

			$line = array( $row->id, $row->form_title, $row->email, $row->status, $row->created_at );

			foreach ( $labels as $label ) {
				$line[] = isset( $by_label[ $label ] ) ? self::csv_safe( $by_label[ $label ] ) : '';
			}

			fputcsv( $out, $line );
		} );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * A cell beginning with = + - @ is executed as a formula by spreadsheets.
	 * Form values are attacker-controlled text, so neutralise it.
	 *
	 * @param string $v
	 *
	 * @return string
	 */
	private static function csv_safe( $v ) {
		$v = (string) $v;

		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v;
	}

	/* ---------------------------------------------------------------------- *
	 * Render
	 * ---------------------------------------------------------------------- */

	public function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to view form entries.', 'fw' ) );
		}

		echo '<div class="wrap fw-ext-forms-entries">';

		if ( isset( $_GET['entry'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->render_single( (int) $_GET['entry'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$this->render_list();
		}

		echo '</div>';
	}

	private function render_notice() {
		$key    = 'fw_form_entries_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		if ( ! $notice ) {
			return;
		}

		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ) );
	}

	private function render_list() {
		$this->table->prepare_items();
		$filters    = $this->table->filters();
		$export_url = wp_nonce_url( add_query_arg( array_merge( array_filter( array(
			'form_id' => $filters['form_id'],
			'status'  => $filters['status'],
			's'       => $filters['search'],
			'from'    => $filters['date_from'],
			'to'      => $filters['date_to'],
		) ), array( 'fw_entries_action' => 'export' ) ), self::page_url() ), self::NONCE );
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Entries', 'fw' ); ?></h1>
		<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export these results (CSV)', 'fw' ); ?></a>
		<hr class="wp-header-end">
		<?php $this->render_notice(); ?>

		<?php if ( ! FW_Forms_Entries_Capture::enabled() ) : ?>
			<div class="notice notice-warning"><p>
				<?php
				printf(
					/* translators: %s: link to the Forms settings */
					esc_html__( 'Storing entries is switched off, so new submissions are emailed but not kept. Turn it on under %s.', 'fw' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=fw-extensions&sub-page=extension&extension=forms' ) ) . '">' . esc_html__( 'Forms → Settings', 'fw' ) . '</a>'
				);
				?>
			</p></div>
		<?php endif; ?>

		<form method="get">
			<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
			<?php if ( '' !== $filters['status'] ) : ?>
				<input type="hidden" name="status" value="<?php echo esc_attr( $filters['status'] ); ?>" />
			<?php endif; ?>
			<?php
			$this->table->views();
			$this->table->search_box( __( 'Search entries', 'fw' ), 'fw-entries' );
			$this->table->display();
			?>
		</form>
		<?php
	}

	/**
	 * @param int $id
	 */
	private function render_single( $id ) {
		$entry = FW_Forms_Entries::find( $id );
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Entry', 'fw' ); ?></h1>
		<a href="<?php echo esc_url( self::page_url() ); ?>" class="page-title-action">&larr; <?php esc_html_e( 'All entries', 'fw' ); ?></a>
		<hr class="wp-header-end">
		<?php
		if ( ! $entry ) {
			echo '<p>' . esc_html__( 'That entry no longer exists.', 'fw' ) . '</p>';
			return;
		}

		$labels = self::status_labels();
		$page   = $entry->post_id ? get_the_title( $entry->post_id ) : '';
		?>
		<div class="fw-entries__single">
			<table class="widefat striped fw-entries__fields">
				<tbody>
				<?php foreach ( $entry->fields as $f ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $f['label'] ); ?></th>
						<td>
							<?php
							if ( is_array( $f['value'] ) ) {
								echo esc_html( implode( ', ', $f['value'] ) );
							} elseif ( 'email' === $f['type'] && is_email( $f['value'] ) ) {
								echo '<a href="mailto:' . esc_attr( $f['value'] ) . '">' . esc_html( $f['value'] ) . '</a>';
							} else {
								echo nl2br( esc_html( $f['value'] ) );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<table class="widefat fw-entries__meta">
				<tbody>
					<tr><th scope="row"><?php esc_html_e( 'Form', 'fw' ); ?></th><td><?php echo esc_html( '' !== $entry->form_title ? $entry->form_title : $entry->form_id ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Submitted', 'fw' ); ?></th><td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at ) ) ); ?></td></tr>
					<?php if ( $page ) : ?>
						<tr><th scope="row"><?php esc_html_e( 'Page', 'fw' ); ?></th><td><a href="<?php echo esc_url( get_permalink( $entry->post_id ) ); ?>"><?php echo esc_html( $page ); ?></a></td></tr>
					<?php endif; ?>
					<tr><th scope="row"><?php esc_html_e( 'Status', 'fw' ); ?></th><td><?php echo esc_html( isset( $labels[ $entry->status ] ) ? $labels[ $entry->status ] : $entry->status ); ?></td></tr>
					<?php if ( '' !== $entry->ip ) : ?>
						<tr><th scope="row"><?php esc_html_e( 'IP address', 'fw' ); ?></th><td><?php echo esc_html( $entry->ip ); ?></td></tr>
					<?php endif; ?>
				</tbody>
			</table>

			<p class="fw-entries__actions">
				<?php if ( 'archived' !== $entry->status ) : ?>
					<a class="button" href="<?php echo esc_url( self::action_url( 'archive', $entry->id ) ); ?>"><?php esc_html_e( 'Archive', 'fw' ); ?></a>
				<?php endif; ?>
				<a class="button button-link-delete" href="<?php echo esc_url( self::action_url( 'delete', $entry->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this entry permanently?', 'fw' ) ); ?>');"><?php esc_html_e( 'Delete permanently', 'fw' ); ?></a>
			</p>
		</div>
		<?php
	}
}
