<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Payments taken by forms: one row per payment in `{prefix}fw_form_payments`, linked to the entry.
 *
 *   status    pending (waiting: a bank transfer, a card page not finished) | paid | failed |
 *             refunded | cancelled
 *   gateway   offline (Pay later) | stripe | paypal (later batches) — FW_Forms_Gateway subclasses,
 *             registered on `fw_ext_forms_gateways`
 *   reference a short code the payer quotes ("F-7K3QD"), made before the success message is built
 *             so {payment_ref} can show it
 *   order_lines  the order lines (JSON) as FW_Forms_Order built them on the server (`lines` once
 *             read; LINES is a reserved word in MySQL)
 *
 * Also: answer tags {order_total}, {order_summary}, {payment_ref}, {payment_method},
 * {payment_instructions}, {payment_link} (pay by card later); a Payment panel, column and Mark paid / Mark cancelled / Mark refunded on
 * Form Entries; the currency setting (Forms Settings → Payments).
 */
class FW_Forms_Payments {

	const DB_VERSION        = '1.0.1';
	const DB_VERSION_OPTION = 'fw_ext_forms_payments_db_version';
	const STATUSES          = array( 'pending', 'paid', 'failed', 'refunded', 'cancelled' );

	/** @var array|null The payment this request is about to make: { reference, gateway, instructions }. */
	private static $pending = null;

	public static function init() {
		self::maybe_install();
		require_once dirname( __FILE__ ) . '/gateways/class-fw-forms-gateway.php';
		add_filter( 'fw_ext_forms_actions', array( __CLASS__, '_register_action' ), 5 );
		add_filter( 'fw_ext_forms_merge_tags', array( __CLASS__, '_merge_tags' ), 10, 2 );
		add_filter( 'fw_ext_forms_entries_columns', array( __CLASS__, '_columns' ) );
		add_filter( 'fw_ext_forms_entries_column', array( __CLASS__, '_column' ), 10, 3 );
		add_action( 'fw_ext_forms_entry_panels', array( __CLASS__, '_panel' ) );
		add_filter( 'fw_ext_forms_entry_action', array( __CLASS__, '_entry_action' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, '_return' ), 1 );
		add_filter( 'the_content', array( __CLASS__, '_notice' ), 1 );
		add_action( 'rest_api_init', array( __CLASS__, '_routes' ) );
	}

	/* ------------------------------------------------------------------ Hosted payment pages */

	/** @var string Where the visitor goes after sending (a card page), set by the Take payment action. */
	private static $redirect = '';

	public static function set_redirect( $url ) {
		self::$redirect = (string) $url;
	}

	/**
	 * Called by Forms after the submit actions ran: when a payment page is waiting, it replaces the
	 * form's own redirect (which is kept to follow after paying) and the success message waits too.
	 *
	 * @param string $after The redirect the form would have used.
	 * @return string The payment page, or ''.
	 */
	public static function take_redirect( $after ) {
		if ( '' === self::$redirect ) {
			return '';
		}
		$to             = self::$redirect;
		self::$redirect = '';
		if ( self::$pending ) {
			$p = self::find_by_reference( self::$pending['reference'] );
			if ( $p && $after && ! self::same_page( $after, (string) ( $p->meta['page_url'] ?? '' ) ) ) {
				self::update( $p->id, array( 'meta' => array( 'after' => esc_url_raw( $after ) ) ) );
			}
		}
		return $to;
	}

	private static function same_page( $a, $b ) {
		$n = function ( $u ) {
			return untrailingslashit( strtok( (string) $u, '?#' ) );
		};
		return $n( $a ) === $n( $b );
	}

	/** Merge `meta` (array) and set other columns. */
	public static function update( $id, array $data ) {
		global $wpdb;
		$p = self::find( $id );
		if ( ! $p ) {
			return false;
		}
		$row = array();
		foreach ( array( 'gateway_ref', 'email' ) as $k ) {
			if ( isset( $data[ $k ] ) ) {
				$row[ $k ] = (string) $data[ $k ];
			}
		}
		if ( isset( $data['amount'] ) ) {
			$row['amount'] = FW_Forms_Money::round( $data['amount'], $p->currency );
		}
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$row['meta'] = wp_json_encode( array_merge( $p->meta, $data['meta'] ) );
		}
		return $row ? false !== $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) ) : true;
	}

	/** A payment found by a value in its meta (e.g. a payment_intent), newest first. */
	public static function find_by_meta( $key, $value ) {
		global $wpdb;
		if ( '' === (string) $value ) {
			return null;
		}
		$like = '%' . $wpdb->esc_like( '"' . $key . '":"' . $value . '"' ) . '%';
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE meta LIKE %s ORDER BY id DESC LIMIT 1', $like ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * The page links for a payment: back after paying, back after cancelling, and "pay now" (starts a
	 * fresh payment page; for a payment still waiting). Each carries the payment's secret key.
	 *
	 * @param string $what success | cancel | pay
	 */
	public static function url( $p, $what ) {
		$base = (string) ( $p->meta['page_url'] ?? home_url( '/' ) );
		return add_query_arg( array( 'fw_form_payment' => $p->reference, 'k' => (string) ( $p->meta['key'] ?? '' ), 'do' => $what ), $base );
	}

	/** @var array|null The notice shown above the page content after coming back. */
	private static $notice_box = null;

	/** @internal ?fw_form_payment=REF&k=KEY&do=success|cancel|pay */
	public static function _return() {
		if ( empty( $_GET['fw_form_payment'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$p  = self::find_by_reference( sanitize_text_field( wp_unslash( $_GET['fw_form_payment'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$k  = sanitize_text_field( wp_unslash( $_GET['k'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$do = isset( $_GET['do'] ) ? sanitize_key( $_GET['do'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $p || '' === (string) ( $p->meta['key'] ?? '' ) || ! hash_equals( (string) $p->meta['key'], $k ) ) {
			return;
		}
		$g = self::gateway( $p->gateway );
		if ( 'pay' === $do ) {
			if ( 'pending' === $p->status && $g && $g->is_ready() ) {
				$r = $g->start( $p, array() );
				if ( ! is_wp_error( $r ) && ! empty( $r['redirect'] ) ) {
					wp_redirect( $r['redirect'] ); // phpcs:ignore WordPress.Security.SafeRedirect -- the payment service's own page
					exit;
				}
			}
			self::$notice_box = array( 'error', __( 'This payment cannot be started right now. Please contact us.', 'fw' ) );
			return;
		}
		if ( 'success' === $do ) {
			if ( $g && 'pending' === $p->status ) {
				$g->confirm_return( $p ); // in case the service's notification is late
				$p = self::find( $p->id );
			}
			if ( 'paid' === $p->status && ! empty( $p->meta['after'] ) ) {
				wp_safe_redirect( (string) $p->meta['after'] );
				exit;
			}
			self::$notice_box = 'paid' === $p->status
				? array( 'success', sprintf( __( 'Payment received, thank you. Your reference is %s.', 'fw' ), $p->reference ) )
				: array( 'info', sprintf( __( 'Thank you. Your payment is being processed; we will confirm it by email. Your reference is %s.', 'fw' ), $p->reference ) );
			return;
		}
		if ( 'cancel' === $do && 'pending' === $p->status ) {
			self::$notice_box = array( 'error', sprintf(
				/* translators: %s: a "pay now" link */
				__( 'The payment was not completed and nothing was charged. Your details are saved: %s', 'fw' ),
				'<a href="' . esc_url( self::url( $p, 'pay' ) ) . '">' . esc_html__( 'try the payment again', 'fw' ) . '</a>'
			) );
		}
	}

	/** @internal The notice above the content after coming back from a payment page. */
	public static function _notice( $content ) {
		if ( ! self::$notice_box || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		list( $type, $html ) = self::$notice_box;
		self::$notice_box    = null;
		$role = 'error' === $type ? 'alert' : 'status';
		return '<div class="fw-form-result fw-form-result--' . esc_attr( $type ) . ' fw-form-payment-notice" role="' . $role . '" tabindex="-1">' . wp_kses_post( wpautop( $html ) ) . '</div>' . $content;
	}

	/** @internal POST /wp-json/fw-forms/v1/webhooks/{gateway} — the services' notifications. */
	public static function _routes() {
		register_rest_route( 'fw-forms/v1', '/webhooks/(?P<gateway>[a-z0-9_]+)', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true', // each gateway verifies the service's signature
			'callback'            => function ( WP_REST_Request $r ) {
				$g = self::gateway( (string) $r['gateway'] );
				if ( ! $g ) {
					return new WP_Error( 'fw_forms_gateway', 'Unknown payment method.', array( 'status' => 404 ) );
				}
				$res = $g->webhook( $r );
				return is_wp_error( $res ) ? $res : new WP_REST_Response( array( 'received' => true ), 200 );
			},
		) );
	}

	/** The address a payment service sends its notifications to. */
	public static function webhook_url( $gateway ) {
		return rest_url( 'fw-forms/v1/webhooks/' . $gateway );
	}

	/**
	 * A gateway setting: Forms Settings → Payments first, then (when empty) the same key saved in
	 * Membership → Payments, so one set of keys serves both.
	 *
	 * @param string $key        Forms setting id, e.g. payments_stripe_test_secret.
	 * @param string $member_gw  Membership gateway id.
	 * @param string $member_key Membership field.
	 */
	public static function setting( $key, $member_gw = '', $member_key = '' ) {
		$v = (string) fw_ext( 'forms' )->get_db_settings_option( $key, '' );
		if ( '' === trim( $v ) && $member_gw ) {
			$m = get_option( 'fw_ext_membership_payments', array() );
			$v = (string) ( $m['gateways'][ $member_gw ][ $member_key ] ?? '' );
		}
		return trim( $v );
	}

	/* ------------------------------------------------------------------ Storage */

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'fw_form_payments';
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
	form_id varchar(64) NOT NULL default '',
	email varchar(190) NOT NULL default '',
	reference varchar(32) NOT NULL default '',
	gateway varchar(32) NOT NULL default '',
	gateway_ref varchar(191) NOT NULL default '',
	status varchar(20) NOT NULL default 'pending',
	amount decimal(14,2) NOT NULL default 0,
	currency char(3) NOT NULL default 'USD',
	order_lines longtext NOT NULL,
	meta longtext NOT NULL,
	created_at datetime NOT NULL default '0000-00-00 00:00:00',
	paid_at datetime NULL default NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY reference (reference),
	KEY entry_id (entry_id),
	KEY form_id (form_id),
	KEY status (status),
	KEY gateway_ref (gateway_ref)
) {$wpdb->get_charset_collate()};" );
		// Stamp only once the table is really there, so a failed create is retried next load.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		}
	}

	/** @return object|null With `lines` (the order lines) and `meta` decoded. */
	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row->id       = (int) $row->id;
		$row->entry_id = (int) $row->entry_id;
		$row->amount   = (float) $row->amount;
		$row->lines    = json_decode( (string) $row->order_lines, true ) ?: array();
		unset( $row->order_lines );
		$row->meta     = json_decode( (string) $row->meta, true ) ?: array();
		return $row;
	}

	public static function find( $id ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function find_by_reference( $ref ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE reference = %s', (string) $ref ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function find_by_gateway_ref( $gateway, $ref ) {
		global $wpdb;
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE gateway = %s AND gateway_ref = %s', (string) $gateway, (string) $ref ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** @return object[] Newest first. */
	public static function for_entry( $entry_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY id DESC', (int) $entry_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return array_map( array( __CLASS__, 'hydrate' ), (array) $rows );
	}

	/**
	 * @param array $d entry_id, form_id, email, reference, gateway, amount, currency, lines, meta, status
	 * @return int|WP_Error
	 */
	public static function create( array $d ) {
		global $wpdb;
		$ok = $wpdb->insert( self::table(), array(
			'entry_id'   => (int) ( $d['entry_id'] ?? 0 ),
			'form_id'    => substr( (string) ( $d['form_id'] ?? '' ), 0, 64 ),
			'email'      => substr( sanitize_email( (string) ( $d['email'] ?? '' ) ), 0, 190 ),
			'reference'  => '' !== (string) ( $d['reference'] ?? '' ) ? (string) $d['reference'] : self::new_reference(),
			'gateway'    => sanitize_key( (string) ( $d['gateway'] ?? '' ) ),
			'gateway_ref' => (string) ( $d['gateway_ref'] ?? '' ),
			'status'     => in_array( $d['status'] ?? '', self::STATUSES, true ) ? $d['status'] : 'pending',
			'amount'     => FW_Forms_Money::round( $d['amount'] ?? 0, $d['currency'] ?? '' ),
			'currency'   => strtoupper( substr( (string) ( $d['currency'] ?? FW_Forms_Money::currency() ), 0, 3 ) ),
			'order_lines' => wp_json_encode( (array) ( $d['lines'] ?? array() ) ),
			'meta'       => wp_json_encode( (array) ( $d['meta'] ?? array() ) ),
			'created_at' => current_time( 'mysql' ),
		) );
		if ( ! $ok ) {
			return new WP_Error( 'fw_forms_payment_store', 'Could not store the payment.' );
		}
		$id = (int) $wpdb->insert_id;
		/** Fires after a form payment is recorded. */
		do_action( 'fw_ext_forms_payment_created', self::find( $id ) );
		return $id;
	}

	/**
	 * Change a payment's status (idempotent). Fires `fw_ext_forms_payment_paid` the first time it is paid.
	 *
	 * @param array $extra gateway_ref, a meta note
	 */
	public static function set_status( $id, $status, array $extra = array() ) {
		global $wpdb;
		$p = self::find( $id );
		if ( ! $p || ! in_array( $status, self::STATUSES, true ) ) {
			return false;
		}
		$row = array( 'status' => $status );
		if ( 'paid' === $status && ! $p->paid_at ) {
			$row['paid_at'] = current_time( 'mysql' );
		}
		if ( isset( $extra['gateway_ref'] ) ) {
			$row['gateway_ref'] = (string) $extra['gateway_ref'];
		}
		$meta = $p->meta;
		$meta['history'][] = array( 'at' => current_time( 'mysql' ), 'status' => $status, 'by' => get_current_user_id(), 'note' => (string) ( $extra['note'] ?? '' ) );
		$row['meta'] = wp_json_encode( $meta );
		$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
		if ( $p->status !== $status ) {
			/**
			 * Fires when a form payment changes status.
			 *
			 * @param object $payment (after the change)
			 * @param string $old
			 */
			do_action( 'fw_ext_forms_payment_status', self::find( $id ), $p->status );
			if ( 'paid' === $status ) {
				do_action( 'fw_ext_forms_payment_paid', self::find( $id ) );
			}
		}
		return true;
	}

	/** "F-7K3QD": letters and digits that cannot be misread, unique in the table. */
	public static function new_reference() {
		global $wpdb;
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		do {
			$ref = 'F-';
			for ( $i = 0; $i < 5; $i++ ) {
				$ref .= $chars[ wp_rand( 0, strlen( $chars ) - 1 ) ];
			}
		} while ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . self::table() . ' WHERE reference = %s', $ref ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $ref;
	}

	/* ------------------------------------------------------------------ The payment about to be made */

	/**
	 * Called by the Take payment action before the success message is built, so the answer tags can
	 * show the reference and the instructions.
	 */
	public static function prepare( FW_Forms_Gateway $gateway, array $form, $title = '' ) {
		self::$pending = array(
			'reference' => self::new_reference(),
			'gateway'   => $gateway->get_id(),
			'title'     => '' !== (string) $title ? (string) $title : $gateway->title( $form ),
			'gateway_o' => $gateway,
			'form'      => $form,
			// The secret in this payment's own links, and the page to come back to.
			'key'       => wp_generate_password( 20, false ),
			'page_url'  => self::form_page_url(),
		);
		return self::$pending;
	}

	/** The page the form was sent from. */
	public static function form_page_url() {
		$id = class_exists( 'FW_Forms_Entries_Capture' ) ? (int) FW_Forms_Entries_Capture::current_post_id() : 0;
		return $id ? (string) get_permalink( $id ) : home_url( '/' );
	}

	/** Add a value to the payment about to be made (e.g. its subscription). */
	public static function add_to_pending( $key, $value ) {
		if ( self::$pending ) {
			self::$pending[ $key ] = $value;
		}
	}

	/** @return array|null */
	public static function pending() {
		return self::$pending;
	}

	/* ------------------------------------------------------------------ Gateways */

	/** @return FW_Forms_Gateway[] id => gateway */
	public static function gateways() {
		static $list = null;
		if ( null === $list ) {
			require_once dirname( __FILE__ ) . '/gateways/class-fw-forms-gateway-offline.php';
			require_once dirname( __FILE__ ) . '/gateways/class-fw-forms-gateway-stripe.php';
			require_once dirname( __FILE__ ) . '/gateways/class-fw-forms-gateway-paypal.php';
			$list = array();
			/**
			 * Register payment methods for forms. Append FW_Forms_Gateway instances.
			 *
			 * @param FW_Forms_Gateway[] $gateways
			 */
			foreach ( (array) apply_filters( 'fw_ext_forms_gateways', array( new FW_Forms_Gateway_Stripe(), new FW_Forms_Gateway_PayPal(), new FW_Forms_Gateway_Offline() ) ) as $g ) {
				if ( $g instanceof FW_Forms_Gateway ) {
					$list[ $g->get_id() ] = $g;
				}
			}
		}
		return $list;
	}

	public static function gateway( $id ) {
		$all = self::gateways();
		return $all[ $id ] ?? null;
	}

	/** @internal */
	public static function _register_action( $actions ) {
		require_once dirname( dirname( __FILE__ ) ) . '/actions/class-fw-forms-action-payment.php';
		$actions[] = new FW_Forms_Action_Payment();
		return $actions;
	}

	/* ------------------------------------------------------------------ Answer tags */

	/** @internal */
	public static function _merge_tags( $tags, $values ) {
		$items = FW_Forms_Order::items();
		if ( ! $items ) {
			return $tags;
		}
		$order = FW_Forms_Order::current();
		if ( $order['lines'] ) {
			$tags['order_total']   = FW_Forms_Money::format( $order['total'], $order['currency'] );
			$tags['order_summary'] = FW_Forms_Order::summary( $order );
		}
		if ( self::$pending ) {
			$tags['payment_ref']          = self::$pending['reference'];
			$tags['payment_method']       = self::$pending['title'];
			$tags['payment_instructions'] = self::$pending['gateway_o']->instructions( self::$pending, $order );
			$tags['payment_link']         = add_query_arg( array( 'fw_form_payment' => self::$pending['reference'], 'k' => self::$pending['key'], 'do' => 'pay' ), self::$pending['page_url'] );
		}
		return $tags;
	}

	/* ------------------------------------------------------------------ Form Entries */

	public static function labels() {
		return array(
			'pending'   => __( 'Waiting for payment', 'fw' ),
			'paid'      => __( 'Paid', 'fw' ),
			'failed'    => __( 'Failed', 'fw' ),
			'refunded'  => __( 'Refunded', 'fw' ),
			'cancelled' => __( 'Cancelled', 'fw' ),
		);
	}

	/** @internal */
	public static function _columns( $cols ) {
		global $wpdb;
		static $any = null;
		if ( null === $any ) {
			$any = (bool) $wpdb->get_var( 'SELECT id FROM ' . self::table() . ' LIMIT 1' ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		if ( ! $any ) {
			return $cols; // no column until a form has taken a payment
		}
		$out = array();
		foreach ( $cols as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'status' === $k ) {
				$out['payment'] = __( 'Payment', 'fw' );
			}
		}
		return $out;
	}

	/** @internal */
	public static function _column( $html, $column, $item ) {
		if ( 'payment' !== $column ) {
			return $html;
		}
		$p = self::for_entry( (int) $item->id );
		if ( ! $p ) {
			return '—';
		}
		$p = $p[0];
		return esc_html( FW_Forms_Money::format( $p->amount, $p->currency ) ) . '<br><span class="fw-entries__status fw-entries__status--pay-' . esc_attr( $p->status ) . '">' . esc_html( self::labels()[ $p->status ] ?? $p->status ) . '</span>';
	}

	/** @internal The Payment panel on a single entry. */
	public static function _panel( $entry ) {
		$list = self::for_entry( (int) $entry->id );
		if ( ! $list ) {
			return;
		}
		$labels = self::labels();
		foreach ( $list as $p ) {
			$g = self::gateway( $p->gateway );
			?>
			<h2 class="fw-entries__h"><?php esc_html_e( 'Payment', 'fw' ); ?></h2>
			<table class="widefat fw-entries__meta fw-entries__payment">
				<tbody>
					<tr><th scope="row"><?php esc_html_e( 'Amount', 'fw' ); ?></th><td><strong><?php echo esc_html( FW_Forms_Money::format( $p->amount, $p->currency ) ); ?></strong></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Status', 'fw' ); ?></th><td><?php echo esc_html( $labels[ $p->status ] ?? $p->status ); ?><?php echo $p->paid_at ? ' · ' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $p->paid_at ) ) ) : ''; ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Method', 'fw' ); ?></th><td><?php echo esc_html( $p->meta['method_title'] ?? ( $g ? $g->get_title() : $p->gateway ) ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Reference', 'fw' ); ?></th><td><code><?php echo esc_html( $p->reference ); ?></code><?php echo '' !== $p->gateway_ref ? ' · ' . esc_html( $p->gateway_ref ) : ''; ?></td></tr>
					<?php foreach ( $p->lines as $l ) : ?>
						<tr><th scope="row"><?php echo esc_html( trim( $l['label'] . ( '' !== ( $l['detail'] ?? '' ) ? ': ' . $l['detail'] : '' ) ) ); ?></th><td><?php echo esc_html( ( $l['qty'] > 1 ? $l['qty'] . ' × ' . FW_Forms_Money::format( $l['unit'], $p->currency ) . ' = ' : '' ) . FW_Forms_Money::format( $l['total'], $p->currency ) . ( ! empty( $l['interval'] ) ? ' ' . FW_Forms_Order::per( $l['interval'] ) : '' ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p class="fw-entries__actions">
				<?php if ( 'paid' !== $p->status ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( FW_Forms_Entries_Admin::action_url( 'pay_paid', $p->id ) ); ?>"><?php esc_html_e( 'Mark paid', 'fw' ); ?></a>
				<?php endif; ?>
				<?php if ( 'pending' === $p->status ) : ?>
					<a class="button" href="<?php echo esc_url( FW_Forms_Entries_Admin::action_url( 'pay_cancelled', $p->id ) ); ?>"><?php esc_html_e( 'Mark cancelled', 'fw' ); ?></a>
				<?php endif; ?>
				<?php if ( 'paid' === $p->status ) : ?>
					<a class="button" href="<?php echo esc_url( FW_Forms_Entries_Admin::action_url( 'pay_refunded', $p->id ) ); ?>" onclick="return confirm('<?php echo esc_js( $g && $g->refunds_online() ? __( 'Refund this payment?', 'fw' ) : __( 'Mark this payment refunded? Send the money back yourself; this only records it.', 'fw' ) ); ?>');"><?php echo esc_html( $g && $g->refunds_online() ? __( 'Refund', 'fw' ) : __( 'Mark refunded', 'fw' ) ); ?></a>
				<?php endif; ?>
			</p>
			<?php
		}
	}

	/**
	 * @internal Mark paid / cancelled / refunded. The ids are PAYMENT ids (the panel's links).
	 */
	public static function _entry_action( $message, $action, $ids ) {
		$map = array( 'pay_paid' => 'paid', 'pay_cancelled' => 'cancelled', 'pay_refunded' => 'refunded' );
		if ( ! isset( $map[ $action ] ) ) {
			return $message;
		}
		$n = 0;
		foreach ( $ids as $id ) {
			$p = self::find( $id );
			if ( ! $p ) {
				continue;
			}
			if ( 'refunded' === $map[ $action ] ) {
				$g = self::gateway( $p->gateway );
				if ( $g && $g->refunds_online() ) {
					$r = $g->refund( $p );
					if ( is_wp_error( $r ) ) {
						return sprintf( __( 'The refund did not go through: %s', 'fw' ), $r->get_error_message() );
					}
				}
			}
			self::set_status( $p->id, $map[ $action ], array( 'note' => 'admin' ) );
			$n++;
		}
		return sprintf( _n( '%d payment updated.', '%d payments updated.', $n, 'fw' ), $n );
	}
}
