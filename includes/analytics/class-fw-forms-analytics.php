<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Form analytics: per form and day, how many saw the form, started it, sent it and gave up — and the
 * field people were on when they gave up. Shown on Unyson+ → Form Results.
 *
 *   views / starts / abandons  static/js/track.js: a view once per page load when half the form is on
 *                              screen, a start at the first answer, an abandon when the page is left with
 *                              a started, unsent form (with that last field's name). Sent with sendBeacon
 *                              to POST /wp-json/fw-forms/v1/track. No cookies, no answers, no personal data:
 *                              only counts.
 *   submits                    counted on the server when a form is sent (spam not counted).
 *
 * Tables `{prefix}fw_form_stats` (form_id, day) and `{prefix}fw_form_stat_fields` (form_id, day, field).
 * Switch it off in Forms Settings → Entries (`analytics`). With Visitor Insights active, its Forms tab adds
 * page traffic and field-by-field timing; Form Results links to it.
 */
class FW_Forms_Analytics {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'fw_ext_forms_analytics_db_version';
	const BOTS              = '/bot|crawl|spider|slurp|facebookexternalhit|headless|lighthouse|pingdom|monitor/i';

	public static function init() {
		self::maybe_install();
		add_action( 'rest_api_init', array( __CLASS__, '_routes' ) );
		add_action( 'fw_ext_forms_frontend_submit', array( __CLASS__, '_submitted' ), 50 );
	}

	public static function enabled() {
		/** Filters whether forms count views, starts and abandons. */
		return (bool) apply_filters( 'fw_ext_forms_analytics', 'no' !== FW_Forms_Entries_Capture::setting( 'analytics', 'yes' ) );
	}

	public static function table( $which = 'stats' ) {
		global $wpdb;
		return $wpdb->prefix . ( 'fields' === $which ? 'fw_form_stat_fields' : 'fw_form_stats' );
	}

	public static function maybe_install() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$c = $wpdb->get_charset_collate();
		$s = self::table();
		$f = self::table( 'fields' );
		dbDelta( "CREATE TABLE {$s} (
	form_id varchar(64) NOT NULL default '',
	day date NOT NULL default '0000-00-00',
	views int(10) unsigned NOT NULL default 0,
	starts int(10) unsigned NOT NULL default 0,
	submits int(10) unsigned NOT NULL default 0,
	abandons int(10) unsigned NOT NULL default 0,
	PRIMARY KEY  (form_id,day)
) {$c};" );
		dbDelta( "CREATE TABLE {$f} (
	form_id varchar(64) NOT NULL default '',
	day date NOT NULL default '0000-00-00',
	field varchar(64) NOT NULL default '',
	lastn int(10) unsigned NOT NULL default 0,
	PRIMARY KEY  (form_id,day,field)
) {$c};" );
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $f ) ) === $f ) {
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
		}
	}

	/** Add to a day's counter. */
	public static function bump( $form_id, $column, $field = '' ) {
		global $wpdb;
		if ( ! in_array( $column, array( 'views', 'starts', 'submits', 'abandons' ), true ) ) {
			return;
		}
		$day = wp_date( 'Y-m-d' );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table() . " (form_id, day, {$column}) VALUES (%s, %s, 1) ON DUPLICATE KEY UPDATE {$column} = {$column} + 1", $form_id, $day ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( 'abandons' === $column && '' !== $field ) {
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table( 'fields' ) . ' (form_id, day, field, lastn) VALUES (%s, %s, %s, 1) ON DUPLICATE KEY UPDATE lastn = lastn + 1', $form_id, $day, $field ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		}
	}

	/** @internal */
	public static function _routes() {
		register_rest_route( 'fw-forms/v1', '/track', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'track' ),
		) );
	}

	public static function track( WP_REST_Request $r ) {
		$ok = new WP_REST_Response( null, 204 );
		if ( ! self::enabled() ) {
			return $ok;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( '' === $ua || preg_match( self::BOTS, $ua ) || is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			return $ok; // bots, and the site's own editors testing their forms
		}
		$p = json_decode( (string) $r->get_body(), true );
		if ( ! is_array( $p ) ) {
			return $ok;
		}
		$form  = isset( $p['f'] ) && is_string( $p['f'] ) && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $p['f'] ) ? $p['f'] : '';
		$event = isset( $p['e'] ) && is_string( $p['e'] ) ? $p['e'] : '';
		$field = isset( $p['l'] ) && is_string( $p['l'] ) && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $p['l'] ) ? $p['l'] : '';
		$map   = array( 'v' => 'views', 's' => 'starts', 'a' => 'abandons' );
		if ( '' === $form || ! isset( $map[ $event ] ) ) {
			return $ok;
		}
		// The form must exist (a contact form or a saved form), so nobody can fill the table with names.
		if ( ! class_exists( 'FW_Forms_Actions' ) || ! FW_Forms_Actions::form_settings( array( 'id' => $form, 'type' => 'contact-forms' ) ) ) {
			return $ok;
		}
		// 120 events per address per 10 minutes is far more than one person filling in forms.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'fw_forms_track_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 120 ) {
			return $ok;
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );
		self::bump( $form, $map[ $event ], $field );
		return $ok;
	}

	/** @internal A sent form (not spam). */
	public static function _submitted( $data ) {
		if ( ! self::enabled() || ! is_array( $data ) || empty( $data['id'] ) ) {
			return;
		}
		if ( class_exists( 'FW_Forms_Spam_Filter' ) && FW_Forms_Spam_Filter::is_spam( (string) $data['id'] ) ) {
			return;
		}
		self::bump( (string) $data['id'], 'submits' );
	}

	/**
	 * Totals for a form over a period.
	 *
	 * @return array { views, starts, submits, abandons, days: [ day => { views, starts, submits } ], fields: [ field => lastn ] }
	 */
	public static function report( $form_id, $days = 0 ) {
		global $wpdb;
		$from = $days > 0 ? wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS ) : '1970-01-01';
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT day, views, starts, submits, abandons FROM ' . self::table() . ' WHERE form_id = %s AND day >= %s ORDER BY day', $form_id, $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array( 'views' => 0, 'starts' => 0, 'submits' => 0, 'abandons' => 0, 'days' => array(), 'fields' => array() );
		foreach ( (array) $rows as $r ) {
			foreach ( array( 'views', 'starts', 'submits', 'abandons' ) as $k ) {
				$out[ $k ] += (int) $r->$k;
			}
			$out['days'][ $r->day ] = array( 'views' => (int) $r->views, 'starts' => (int) $r->starts, 'submits' => (int) $r->submits );
		}
		$fields = $wpdb->get_results( $wpdb->prepare( 'SELECT field, SUM(lastn) n FROM ' . self::table( 'fields' ) . ' WHERE form_id = %s AND day >= %s GROUP BY field ORDER BY n DESC LIMIT 8', $form_id, $from ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( (array) $fields as $f ) {
			$out['fields'][ $f->field ] = (int) $f->n;
		}
		return $out;
	}

	/** Field labels by name, from the form's own definition. */
	public static function labels( $form_id ) {
		$form  = class_exists( 'FW_Forms_Actions' ) ? FW_Forms_Actions::form_settings( array( 'id' => (string) $form_id, 'type' => 'contact-forms' ) ) : array();
		$items = isset( $form['form']['json'] ) ? json_decode( (string) $form['form']['json'], true ) : array();
		$out   = array();
		foreach ( FW_Forms_Order::flatten( is_array( $items ) ? $items : array() ) as $sc => $it ) {
			$out[ $sc ] = trim( wp_strip_all_tags( (string) ( $it['options']['label'] ?? $sc ) ) );
		}
		return $out;
	}

	/** The Performance card on Form Results. */
	public static function render_card( $form_id, $days ) {
		$r = self::report( $form_id, $days );
		$pct = function ( $a, $b ) {
			return $b > 0 ? (int) round( 100 * $a / $b ) : 0;
		};
		echo '<section class="fw-results__card fw-results__card--wide"><h2>' . esc_html__( 'How the form performs', 'fw' ) . '</h2>';
		if ( ! $r['views'] && ! $r['starts'] && ! $r['submits'] ) {
			echo '<p class="fw-results__meta">' . esc_html__( 'No visits counted yet. Views, starts and abandons are counted from now on (not for logged-in editors).', 'fw' ) . '</p></section>';
			return;
		}
		$steps = array(
			__( 'Saw the form', 'fw' )    => $r['views'],
			__( 'Started it', 'fw' )      => $r['starts'],
			__( 'Sent it', 'fw' )         => $r['submits'],
		);
		echo '<p class="fw-results__meta">' . esc_html( sprintf( __( '%1$d%% of those who saw it started it; %2$d%% of those who started it sent it.', 'fw' ), $pct( $r['starts'], $r['views'] ), $pct( $r['submits'], $r['starts'] ) ) ) . '</p>';
		echo '<table class="fw-results__bars"><caption class="screen-reader-text">' . esc_html__( 'From seeing the form to sending it', 'fw' ) . '</caption><tbody>';
		$top = max( 1, $r['views'], $r['starts'], $r['submits'] );
		foreach ( $steps as $label => $n ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td class="fw-results__bar"><span style="width:' . esc_attr( (string) max( 1, $pct( $n, $top ) ) ) . '%"></span></td><td class="fw-results__num">' . esc_html( number_format_i18n( $n ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
		if ( $r['fields'] ) {
			$labels = self::labels( $form_id );
			echo '<p class="fw-results__meta" style="margin-top:1rem">' . esc_html( sprintf( _n( 'Where %d person gave up', 'Where %d people gave up', $r['abandons'], 'fw' ), $r['abandons'] ) ) . '</p>';
			self::bars_raw( $r['fields'], $labels, $r['abandons'] );
		}
		if ( function_exists( 'fw_ext' ) && fw_ext( 'visitor-insights' ) ) {
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=fw-visitor-insights&tab=forms' ) ) . '">' . esc_html__( 'Page traffic and field-by-field detail in Visitor Insights → Forms', 'fw' ) . ' →</a></p>';
		}
		echo '</section>';
	}

	private static function bars_raw( array $counts, array $labels, $of ) {
		echo '<table class="fw-results__bars"><tbody>';
		foreach ( $counts as $field => $n ) {
			$p = $of > 0 ? round( 100 * $n / $of ) : 0;
			echo '<tr><th scope="row">' . esc_html( $labels[ $field ] ?? $field ) . '</th><td class="fw-results__bar"><span style="width:' . esc_attr( (string) max( 1, $p ) ) . '%;background:#d63638"></span></td><td class="fw-results__num">' . esc_html( number_format_i18n( $n ) ) . ' <span>(' . esc_html( $p ) . '%)</span></td></tr>';
		}
		echo '</tbody></table>';
	}
}
