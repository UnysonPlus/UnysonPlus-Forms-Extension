<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Recurring payments: a form whose order has Products billed every week / month / year starts a
 * subscription with the payment service (Card through Stripe, or PayPal). One row per subscription in
 * `{prefix}fw_form_subscriptions`; every renewal is a new paid row in fw_form_payments
 * (meta.subscription_id, meta.renewal = 1) on the same entry.
 *
 *   status  pending (the first payment is not done yet) | active | past_due (a renewal failed; the
 *           service retries) | cancelled (will not renew; runs to the end of the paid period) | ended
 *
 * The site owner cancels from the entry; the payer from the link in {subscription_cancel_link}.
 * Gateways that can do this implement supports_recurring() and cancel_subscription().
 */
class FW_Forms_Subscriptions {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'fw_ext_forms_subscriptions_db_version';
	const STATUSES          = array( 'pending', 'active', 'past_due', 'cancelled', 'ended' );

	public static function init() {
		self::maybe_install();
		add_action( 'fw_ext_forms_entry_panels', array( __CLASS__, '_panel' ), 5 );
		add_filter( 'fw_ext_forms_entry_action', array( __CLASS__, '_entry_action' ), 10, 3 );
		add_action( 'template_redirect', array( __CLASS__, '_payer_cancel' ), 1 );
		add_filter( 'fw_ext_forms_merge_tags', array( __CLASS__, '_merge_tags' ), 20, 2 );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'fw_form_subscriptions';
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
	billing_interval varchar(10) NOT NULL default 'month',
	order_lines longtext NOT NULL,
	meta longtext NOT NULL,
	created_at datetime NOT NULL default '0000-00-00 00:00:00',
	ended_at datetime NULL default NULL,
	PRIMARY KEY  (id),
	UNIQUE KEY reference (reference),
	KEY entry_id (entry_id),
	KEY status (status),
	KEY gateway_ref (gateway_ref)
) {$wpdb->get_charset_collate()};" );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		}
	}

	private static function hydrate( $row ) {
		if ( ! $row ) {
			return null;
		}
		$row->id       = (int) $row->id;
		$row->entry_id = (int) $row->entry_id;
		$row->amount   = (float) $row->amount;
		$row->interval = (string) $row->billing_interval;
		$row->lines    = json_decode( (string) $row->order_lines, true ) ?: array();
		$row->meta     = json_decode( (string) $row->meta, true ) ?: array();
		unset( $row->order_lines );
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
		if ( '' === (string) $ref ) {
			return null;
		}
		return self::hydrate( $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE gateway = %s AND gateway_ref = %s', (string) $gateway, (string) $ref ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function for_entry( $entry_id ) {
		global $wpdb;
		return array_map( array( __CLASS__, 'hydrate' ), (array) $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d ORDER BY id DESC', (int) $entry_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** @return int|WP_Error */
	public static function create( array $d ) {
		global $wpdb;
		$ref = '' !== (string) ( $d['reference'] ?? '' ) ? (string) $d['reference'] : self::new_reference();
		$ok  = $wpdb->insert( self::table(), array(
			'entry_id'         => (int) ( $d['entry_id'] ?? 0 ),
			'form_id'          => substr( (string) ( $d['form_id'] ?? '' ), 0, 64 ),
			'email'            => substr( sanitize_email( (string) ( $d['email'] ?? '' ) ), 0, 190 ),
			'reference'        => $ref,
			'gateway'          => sanitize_key( (string) ( $d['gateway'] ?? '' ) ),
			'status'           => 'pending',
			'amount'           => FW_Forms_Money::round( $d['amount'] ?? 0, $d['currency'] ?? '' ),
			'currency'         => strtoupper( substr( (string) ( $d['currency'] ?? FW_Forms_Money::currency() ), 0, 3 ) ),
			'billing_interval' => in_array( $d['interval'] ?? '', array( 'week', 'month', 'year' ), true ) ? $d['interval'] : 'month',
			'order_lines'      => wp_json_encode( (array) ( $d['lines'] ?? array() ) ),
			'meta'             => wp_json_encode( (array) ( $d['meta'] ?? array() ) ),
			'created_at'       => current_time( 'mysql' ),
		) );
		return $ok ? (int) $wpdb->insert_id : new WP_Error( 'fw_forms_sub_store', 'Could not store the subscription.' );
	}

	public static function update( $id, array $data ) {
		global $wpdb;
		$s = self::find( $id );
		if ( ! $s ) {
			return false;
		}
		$row = array();
		if ( isset( $data['gateway_ref'] ) ) {
			$row['gateway_ref'] = (string) $data['gateway_ref'];
		}
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$row['meta'] = wp_json_encode( array_merge( $s->meta, $data['meta'] ) );
		}
		return $row ? false !== $wpdb->update( self::table(), $row, array( 'id' => (int) $id ) ) : true;
	}

	/** Change the status (idempotent); fires `fw_ext_forms_subscription_status`. */
	public static function set_status( $id, $status, $note = '' ) {
		global $wpdb;
		$s = self::find( $id );
		if ( ! $s || ! in_array( $status, self::STATUSES, true ) || $s->status === $status ) {
			return (bool) $s;
		}
		$meta              = $s->meta;
		$meta['history'][] = array( 'at' => current_time( 'mysql' ), 'status' => $status, 'note' => (string) $note );
		$row               = array( 'status' => $status, 'meta' => wp_json_encode( $meta ) );
		if ( 'ended' === $status ) {
			$row['ended_at'] = current_time( 'mysql' );
		}
		$wpdb->update( self::table(), $row, array( 'id' => (int) $id ) );
		/**
		 * Fires when a form subscription changes status.
		 *
		 * @param object $subscription (after)
		 * @param string $old
		 */
		do_action( 'fw_ext_forms_subscription_status', self::find( $id ), $s->status );
		return true;
	}

	/**
	 * A renewal the service charged: a new paid payment on the entry. Safe to call twice for the same
	 * charge (`$charge_ref` is checked).
	 *
	 * @return int|false The payment id.
	 */
	public static function renewal( $sub, $amount, $charge_ref, array $meta = array() ) {
		if ( '' !== (string) $charge_ref && FW_Forms_Payments::find_by_meta( 'charge_ref', (string) $charge_ref ) ) {
			return false;
		}
		$id = FW_Forms_Payments::create( array(
			'entry_id' => $sub->entry_id,
			'form_id'  => $sub->form_id,
			'email'    => $sub->email,
			'gateway'  => $sub->gateway,
			'amount'   => $amount,
			'currency' => $sub->currency,
			'lines'    => $sub->lines,
			'status'   => 'pending',
			'meta'     => array_merge( array( 'subscription_id' => $sub->id, 'renewal' => 1, 'charge_ref' => (string) $charge_ref, 'method_title' => (string) ( $sub->meta['method_title'] ?? '' ) ), $meta ),
		) );
		if ( is_wp_error( $id ) ) {
			return false;
		}
		FW_Forms_Payments::set_status( $id, 'paid', array( 'note' => 'renewal' ) ); // stamps paid_at, fires the paid hook
		if ( in_array( $sub->status, array( 'pending', 'past_due' ), true ) ) {
			self::set_status( $sub->id, 'active', 'renewal paid' );
		}
		return $id;
	}

	/** "S-7K3QD", unique among subscriptions. */
	public static function new_reference() {
		do {
			$ref = 'S-' . substr( FW_Forms_Payments::new_reference(), 2 );
		} while ( self::find_by_reference( $ref ) );
		return $ref;
	}

	/** "$25 / month". */
	public static function price( $sub ) {
		return trim( FW_Forms_Money::format( $sub->amount, $sub->currency ) . ' ' . FW_Forms_Order::per( $sub->interval ) );
	}

	public static function labels() {
		return array(
			'pending'   => __( 'Waiting for the first payment', 'fw' ),
			'active'    => __( 'Active', 'fw' ),
			'past_due'  => __( 'Payment overdue', 'fw' ),
			'cancelled' => __( 'Cancelled (no more renewals)', 'fw' ),
			'ended'     => __( 'Ended', 'fw' ),
		);
	}

	/** The payer's "cancel my subscription" link. */
	public static function cancel_url( $sub ) {
		$base = (string) ( $sub->meta['page_url'] ?? home_url( '/' ) );
		return add_query_arg( array( 'fw_form_subscription' => $sub->reference, 'k' => (string) ( $sub->meta['key'] ?? '' ) ), $base );
	}

	/** Cancel with the service (no more renewals) and record it. */
	public static function cancel( $sub, $by = 'admin' ) {
		$g = FW_Forms_Payments::gateway( $sub->gateway );
		if ( ! $g || ! method_exists( $g, 'cancel_subscription' ) ) {
			return new WP_Error( 'fw_forms_sub_cancel', __( 'This payment method cannot cancel subscriptions here.', 'fw' ) );
		}
		if ( '' !== (string) $sub->gateway_ref ) {
			$r = $g->cancel_subscription( $sub );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		self::set_status( $sub->id, 'pending' === $sub->status ? 'ended' : 'cancelled', 'cancelled by ' . $by );
		return true;
	}

	/* ------------------------------------------------------------------ Admin + payer */

	/** @internal */
	public static function _panel( $entry ) {
		foreach ( self::for_entry( (int) $entry->id ) as $s ) {
			$labels = self::labels();
			?>
			<h2 class="fw-entries__h"><?php esc_html_e( 'Subscription', 'fw' ); ?></h2>
			<table class="widefat fw-entries__meta fw-entries__subscription">
				<tbody>
					<tr><th scope="row"><?php esc_html_e( 'Price', 'fw' ); ?></th><td><strong><?php echo esc_html( self::price( $s ) ); ?></strong></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Status', 'fw' ); ?></th><td><?php echo esc_html( $labels[ $s->status ] ?? $s->status ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Method', 'fw' ); ?></th><td><?php echo esc_html( (string) ( $s->meta['method_title'] ?? $s->gateway ) ); ?></td></tr>
					<tr><th scope="row"><?php esc_html_e( 'Reference', 'fw' ); ?></th><td><code><?php echo esc_html( $s->reference ); ?></code><?php echo '' !== $s->gateway_ref ? ' · ' . esc_html( $s->gateway_ref ) : ''; ?></td></tr>
				</tbody>
			</table>
			<?php if ( in_array( $s->status, array( 'pending', 'active', 'past_due' ), true ) ) : ?>
				<p class="fw-entries__actions">
					<a class="button" href="<?php echo esc_url( FW_Forms_Entries_Admin::action_url( 'sub_cancel', $s->id ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Cancel this subscription? There will be no more renewals; what is paid runs to the end of its period.', 'fw' ) ); ?>');"><?php esc_html_e( 'Cancel subscription', 'fw' ); ?></a>
				</p>
			<?php endif; ?>
			<?php
		}
	}

	/** @internal Ids are SUBSCRIPTION ids. */
	public static function _entry_action( $message, $action, $ids ) {
		if ( 'sub_cancel' !== $action ) {
			return $message;
		}
		foreach ( $ids as $id ) {
			$s = self::find( $id );
			if ( $s ) {
				$r = self::cancel( $s, 'admin' );
				if ( is_wp_error( $r ) ) {
					return sprintf( __( 'The subscription was not cancelled: %s', 'fw' ), $r->get_error_message() );
				}
			}
		}
		return __( 'Subscription cancelled. There will be no more renewals.', 'fw' );
	}

	/** @var array|null */
	private static $notice = null;

	/**
	 * @internal ?fw_form_subscription=REF&k=KEY shows a confirm button; posting it cancels.
	 */
	public static function _payer_cancel() {
		if ( empty( $_GET['fw_form_subscription'] ) || empty( $_GET['k'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$s = self::find_by_reference( sanitize_text_field( wp_unslash( $_GET['fw_form_subscription'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$k = sanitize_text_field( wp_unslash( $_GET['k'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $s || '' === (string) ( $s->meta['key'] ?? '' ) || ! hash_equals( (string) $s->meta['key'], $k ) ) {
			return;
		}
		if ( ! in_array( $s->status, array( 'pending', 'active', 'past_due' ), true ) ) {
			self::$notice = array( 'info', sprintf( __( 'Your subscription %s is already cancelled.', 'fw' ), $s->reference ) );
		} elseif ( isset( $_POST['fw_form_subscription_cancel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the secret key in the address is the check
			$r            = self::cancel( $s, 'payer' );
			self::$notice = is_wp_error( $r )
				? array( 'error', __( 'Your subscription could not be cancelled right now. Please contact us.', 'fw' ) )
				: array( 'success', sprintf( __( 'Your subscription %s is cancelled. There will be no more payments.', 'fw' ), $s->reference ) );
		} else {
			self::$notice = array( 'info', sprintf( __( 'Cancel your subscription %1$s (%2$s)? There will be no more payments.', 'fw' ), $s->reference, self::price( $s ) )
				. '<form method="post"><button type="submit" name="fw_form_subscription_cancel" value="1" class="btn">' . esc_html__( 'Cancel my subscription', 'fw' ) . '</button></form>' );
		}
		add_filter( 'the_content', array( __CLASS__, '_notice' ), 1 );
	}

	/** @internal */
	public static function _notice( $content ) {
		if ( ! self::$notice || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		list( $type, $html ) = self::$notice;
		self::$notice        = null;
		$allowed             = wp_kses_allowed_html( 'post' ) + array(
			'form'   => array( 'method' => true ),
			'button' => array( 'type' => true, 'name' => true, 'value' => true, 'class' => true ),
		);
		return '<div class="fw-form-result fw-form-result--' . esc_attr( $type ) . ' fw-form-payment-notice" role="status">' . wp_kses( wpautop( $html ), $allowed ) . '</div>' . $content;
	}

	/** @internal {subscription_ref}, {subscription_price}, {subscription_cancel_link} (made before actions run). */
	public static function _merge_tags( $tags, $values ) {
		$p = FW_Forms_Payments::pending();
		if ( $p && ! empty( $p['subscription'] ) ) {
			$tags['subscription_ref']         = $p['subscription']['reference'];
			$tags['subscription_price']       = $p['subscription']['price'];
			$tags['subscription_cancel_link'] = add_query_arg( array( 'fw_form_subscription' => $p['subscription']['reference'], 'k' => $p['key'] ), $p['page_url'] );
		}
		return $tags;
	}
}
