<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * The entries list. Native WP_List_Table so it inherits core's sorting,
 * pagination, bulk actions and screen options rather than reimplementing them.
 */
class FW_Forms_Entries_List_Table extends WP_List_Table {

	/** @var array */
	private $counts = array();

	public function __construct() {
		parent::__construct( array(
			'singular' => 'entry',
			'plural'   => 'entries',
			'ajax'     => false,
		) );
	}

	/* ---------------------------------------------------------------------- *
	 * Data
	 * ---------------------------------------------------------------------- */

	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'fw_forms_entries_per_page', 20 );
		$args     = $this->filters();

		$this->counts = FW_Forms_Entries::counts( $args['form_id'] );

		$result = FW_Forms_Entries::query( array_merge( $args, array(
			'per_page' => $per_page,
			'page'     => $this->get_pagenum(),
		) ) );

		$this->items = $result['rows'];

		$this->set_pagination_args( array(
			'total_items' => $result['total'],
			'per_page'    => $per_page,
		) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * The current filters, read once from the request so every method agrees.
	 *
	 * @return array
	 */
	public function filters() {
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order  = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		return array(
			'form_id'   => isset( $_GET['form_id'] ) ? sanitize_text_field( wp_unslash( $_GET['form_id'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status'    => in_array( $status, FW_Forms_Entries::STATUSES, true ) ? $status : '',
			'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_from' => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'date_to'   => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'orderby'   => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'     => $order,
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Columns
	 * ---------------------------------------------------------------------- */

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'email'      => __( 'From', 'fw' ),
			'summary'    => __( 'Summary', 'fw' ),
			'form_title' => __( 'Form', 'fw' ),
			'status'     => __( 'Status', 'fw' ),
			'created_at' => __( 'Submitted', 'fw' ),
		);
	}

	public function get_sortable_columns() {
		return array(
			'email'      => array( 'email', false ),
			'form_title' => array( 'form_title', false ),
			'status'     => array( 'status', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', $item->id );
	}

	protected function column_email( $item ) {
		$view  = FW_Forms_Entries_Admin::entry_url( $item->id );
		$email = '' !== $item->email ? $item->email : __( '(no email)', 'fw' );

		// Unread entries are bold, the way an inbox does it.
		$label = 'new' === $item->status ? '<strong>' . esc_html( $email ) . '</strong>' : esc_html( $email );

		$actions = array(
			'view'   => '<a href="' . esc_url( $view ) . '">' . esc_html__( 'View', 'fw' ) . '</a>',
			'delete' => '<a href="' . esc_url( FW_Forms_Entries_Admin::action_url( 'delete', $item->id ) ) . '" class="submitdelete" onclick="return confirm(\'' . esc_js( __( 'Delete this entry permanently?', 'fw' ) ) . '\');">' . esc_html__( 'Delete', 'fw' ) . '</a>',
		);

		if ( 'archived' !== $item->status ) {
			$actions['archive'] = '<a href="' . esc_url( FW_Forms_Entries_Admin::action_url( 'archive', $item->id ) ) . '">' . esc_html__( 'Archive', 'fw' ) . '</a>';
		}

		return '<a href="' . esc_url( $view ) . '">' . $label . '</a>' . $this->row_actions( $actions );
	}

	/**
	 * The first few non-email fields, so a row can be recognised without opening
	 * it — a booking shows its date and party size, a ticket its subject line.
	 */
	protected function column_summary( $item ) {
		$bits = array();

		foreach ( $item->fields as $f ) {
			if ( 'email' === $f['type'] || '' === $f['value'] || array() === $f['value'] ) {
				continue;
			}

			$value  = is_array( $f['value'] ) ? implode( ', ', $f['value'] ) : $f['value'];
			$bits[] = '<span class="fw-entries__field"><span class="fw-entries__label">' . esc_html( $f['label'] ) . ':</span> ' . esc_html( wp_html_excerpt( $value, 60, '…' ) ) . '</span>';

			if ( count( $bits ) >= 3 ) {
				break;
			}
		}

		return $bits ? implode( ' ', $bits ) : '<span class="description">' . esc_html__( '—', 'fw' ) . '</span>';
	}

	protected function column_form_title( $item ) {
		$url = add_query_arg( 'form_id', rawurlencode( $item->form_id ), FW_Forms_Entries_Admin::page_url() );

		return '<a href="' . esc_url( $url ) . '">' . esc_html( '' !== $item->form_title ? $item->form_title : $item->form_id ) . '</a>';
	}

	protected function column_status( $item ) {
		$labels = FW_Forms_Entries_Admin::status_labels();

		return '<span class="fw-entries__status fw-entries__status--' . esc_attr( $item->status ) . '">'
			. esc_html( isset( $labels[ $item->status ] ) ? $labels[ $item->status ] : $item->status )
			. '</span>';
	}

	protected function column_created_at( $item ) {
		$ts = strtotime( $item->created_at );

		return '<span title="' . esc_attr( $item->created_at ) . '">'
			. esc_html( sprintf(
				/* translators: %s: human-readable time difference */
				__( '%s ago', 'fw' ),
				human_time_diff( $ts, current_time( 'timestamp' ) )
			) )
			. '</span><br><span class="description">' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $ts ) ) . '</span>';
	}

	/* ---------------------------------------------------------------------- *
	 * Views, filters, bulk
	 * ---------------------------------------------------------------------- */

	protected function get_views() {
		$current = $this->filters()['status'];
		$labels  = array( 'all' => __( 'All', 'fw' ) ) + FW_Forms_Entries_Admin::status_labels();
		$base    = FW_Forms_Entries_Admin::page_url();
		$form_id = $this->filters()['form_id'];
		$views   = array();

		foreach ( $labels as $key => $label ) {
			$count = isset( $this->counts[ $key ] ) ? (int) $this->counts[ $key ] : 0;
			$url   = 'all' === $key ? remove_query_arg( 'status', $base ) : add_query_arg( 'status', $key, $base );

			if ( '' !== $form_id ) {
				$url = add_query_arg( 'form_id', rawurlencode( $form_id ), $url );
			}

			$is_current    = ( 'all' === $key && '' === $current ) || $key === $current;
			$views[ $key ] = sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$is_current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				$count
			);
		}

		return $views;
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$forms   = FW_Forms_Entries::forms();
		$filters = $this->filters();
		?>
		<div class="alignleft actions">
			<label for="fw-entries-form" class="screen-reader-text"><?php esc_html_e( 'Filter by form', 'fw' ); ?></label>
			<select name="form_id" id="fw-entries-form">
				<option value=""><?php esc_html_e( 'All forms', 'fw' ); ?></option>
				<?php foreach ( $forms as $id => $title ) : ?>
					<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $filters['form_id'], $id ); ?>><?php echo esc_html( $title ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="date" name="from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" aria-label="<?php esc_attr_e( 'From date', 'fw' ); ?>" />
			<input type="date" name="to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" aria-label="<?php esc_attr_e( 'To date', 'fw' ); ?>" />
			<?php submit_button( __( 'Filter', 'fw' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	protected function get_bulk_actions() {
		return array(
			'read'    => __( 'Mark as read', 'fw' ),
			'archive' => __( 'Archive', 'fw' ),
			'delete'  => __( 'Delete permanently', 'fw' ),
		);
	}

	public function no_items() {
		esc_html_e( 'No entries yet. Submissions from every contact form on the site will appear here.', 'fw' );
	}
}
