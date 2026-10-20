<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Save and continue later (contact form Settings → Options: drafts_enabled, drafts_days).
 *
 *   static/js/drafts.js adds "Save and continue later" next to the submit button. The visitor gives an
 *   email address; the answers so far go to POST /wp-json/fw-forms/v1/drafts and are kept in
 *   `{prefix}fw_form_drafts` for drafts_days (30). An email carries a link back to the page
 *   (?fw_draft=<token>); opening it fills the form in again (GET /wp-json/fw-forms/v1/drafts/<token>).
 *
 * Only a hash of the token is stored. Passwords, files and drawn signatures are never saved; the
 * draft is deleted when the form is sent, and expired drafts are removed once a day. Both endpoints
 * are rate-limited per address.
 */
class FW_Forms_Drafts {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'fw_ext_forms_drafts_db_version';
	const MAX_BYTES         = 65536;

	public static function init() {
		self::maybe_install();
		add_action( 'rest_api_init', array( __CLASS__, '_routes' ) );
		add_action( 'fw_ext_forms_frontend_submit', array( __CLASS__, '_finished' ), 40 );
		add_action( 'fw_forms_drafts_cleanup', array( __CLASS__, 'cleanup' ) );
		if ( ! wp_next_scheduled( 'fw_forms_drafts_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'fw_forms_drafts_cleanup' );
		}
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'fw_form_drafts';
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
	token_hash char(64) NOT NULL default '',
	form_id varchar(64) NOT NULL default '',
	email varchar(190) NOT NULL default '',
	page_url text NOT NULL,
	answers longtext NOT NULL,
	created_at datetime NOT NULL default '0000-00-00 00:00:00',
	expires_at datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (id),
	UNIQUE KEY token_hash (token_hash),
	KEY expires_at (expires_at)
) {$wpdb->get_charset_collate()};" );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $t ) ) === $t ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		}
	}

	/** Settings → Options → Save and continue later. */
	public static function options() {
		return array(
			'type'    => 'group',
			'options' => array(
				'drafts_enabled' => array(
					'type'         => 'switch',
					'label'        => __( 'Save and continue later', 'fw' ),
					'desc'         => __( 'A button that emails the visitor a link to finish the form later, with their answers kept. For long applications and questionnaires.', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
				'drafts_days'    => array( 'type' => 'text', 'label' => __( 'Keep saved answers for (days)', 'fw' ), 'value' => '30' ),
			),
		);
	}

	private static function limited( $what, $max ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'fw_forms_drafts_' . $what . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return false;
	}

	/** The settings of a contact form by id (drafts only work for forms that switch them on). */
	private static function form( $form_id ) {
		return class_exists( 'FW_Forms_Actions' ) ? FW_Forms_Actions::form_settings( array( 'id' => (string) $form_id, 'type' => 'contact-forms' ) ) : array();
	}

	/** Keep only plain answers: field names and short string values. */
	private static function clean( $answers ) {
		$out = array();
		$n   = 0;
		foreach ( (array) $answers as $name => $value ) {
			$name = (string) $name;
			if ( ! preg_match( '/^[A-Za-z0-9_\-\[\] ().,\/&\']{1,120}$/', $name ) || 0 === strpos( $name, 'fw_' ) || 0 === strpos( $name, '_wp' ) || false !== strpos( $name, '[image]' ) || ++$n > 300 ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $name ] = array_values( array_map( function ( $v ) {
					return substr( sanitize_textarea_field( (string) $v ), 0, 5000 );
				}, array_filter( $value, 'is_scalar' ) ) );
			} elseif ( is_scalar( $value ) ) {
				$out[ $name ] = substr( sanitize_textarea_field( (string) $value ), 0, 5000 );
			}
		}
		return $out;
	}

	/** @internal */
	public static function _routes() {
		register_rest_route( 'fw-forms/v1', '/drafts', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'save' ),
		) );
		register_rest_route( 'fw-forms/v1', '/drafts/(?P<token>[A-Za-z0-9]{32})', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'load' ),
		) );
	}

	public static function save( WP_REST_Request $r ) {
		$p       = (array) $r->get_json_params();
		$form_id = (string) ( $p['form_id'] ?? '' );
		$form    = self::form( $form_id );
		if ( ! $form || 'yes' !== ( $form['drafts_enabled'] ?? 'no' ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'This form cannot be saved for later.', 'fw' ) ), 400 );
		}
		$email = sanitize_email( (string) ( $p['email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Please enter a valid email address.', 'fw' ) ), 400 );
		}
		if ( self::limited( 'save', 10 ) ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'Too many tries. Please wait a while.', 'fw' ) ), 429 );
		}
		$answers = wp_json_encode( self::clean( $p['answers'] ?? array() ) );
		if ( strlen( $answers ) > self::MAX_BYTES ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'These answers are too long to save.', 'fw' ) ), 400 );
		}
		$page = wp_validate_redirect( esc_url_raw( (string) ( $p['page_url'] ?? '' ) ), home_url( '/' ) );
		$page = remove_query_arg( 'fw_draft', strtok( $page, '#' ) );
		$days  = max( 1, min( 365, (int) ( $form['drafts_days'] ?? 30 ) ) );
		$token = wp_generate_password( 32, false, false );
		global $wpdb;
		$wpdb->insert( self::table(), array(
			'token_hash' => hash( 'sha256', $token ),
			'form_id'    => substr( $form_id, 0, 64 ),
			'email'      => $email,
			'page_url'   => $page,
			'answers'    => $answers,
			'created_at' => current_time( 'mysql' ),
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + $days * DAY_IN_SECONDS ),
		) );
		$link = add_query_arg( 'fw_draft', $token, $page );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$body = sprintf(
			/* translators: 1: site name, 2: link, 3: date */
			__( "Your answers on %1\$s are saved.\n\nContinue where you left off:\n%2\$s\n\nThe link works until %3\$s. If you did not ask for this, you can ignore this email.", 'fw' ),
			$site,
			$link,
			wp_date( get_option( 'date_format' ), time() + $days * DAY_IN_SECONDS )
		);
		/** Filters the "continue your form" email: { to, subject, message }. */
		$mail = apply_filters( 'fw_ext_forms_draft_email', array( 'to' => $email, 'subject' => sprintf( __( 'Continue your form on %s', 'fw' ), $site ), 'message' => $body ), $form_id );
		wp_mail( $mail['to'], $mail['subject'], $mail['message'] );
		return new WP_REST_Response( array( 'ok' => true, 'message' => sprintf( __( 'Saved. We have emailed a link to %s so you can finish later.', 'fw' ), $email ) ), 200 );
	}

	public static function load( WP_REST_Request $r ) {
		if ( self::limited( 'load', 60 ) ) {
			return new WP_REST_Response( array( 'ok' => false ), 429 );
		}
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT form_id, answers, expires_at FROM ' . self::table() . ' WHERE token_hash = %s', hash( 'sha256', (string) $r['token'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( ! $row || strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return new WP_REST_Response( array( 'ok' => false, 'message' => __( 'This link has expired or the form was already sent.', 'fw' ) ), 404 );
		}
		return new WP_REST_Response( array( 'ok' => true, 'form_id' => $row->form_id, 'answers' => json_decode( $row->answers, true ) ), 200 );
	}

	/** @internal A sent form no longer needs its draft. */
	public static function _finished( $data ) {
		$token = FW_Request::POST( 'fw_draft_token' );
		if ( is_string( $token ) && preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			global $wpdb;
			$wpdb->delete( self::table(), array( 'token_hash' => hash( 'sha256', $token ) ) );
		}
	}

	/** Remove expired drafts (daily). */
	public static function cleanup() {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE expires_at < %s', gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL
	}
}
