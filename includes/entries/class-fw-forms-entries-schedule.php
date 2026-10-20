<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Scheduled export (Forms Settings → Entries): a CSV of the entries that came in since the last one,
 * emailed every day or every week (Mondays) at about 7:00 site time. Nothing is sent when there are
 * no new entries. Spam and unfinished entries are left out. The per-form retention ("Delete entries
 * after N days", contact form Settings → Options) runs in the daily clean-up
 * (FW_Forms_Entries_Capture::_action_retention).
 */
class FW_Forms_Entries_Schedule {

	const HOOK = 'fw_forms_entries_digest';
	const LAST = 'fw_ext_forms_entries_digest_last';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/** Contact form Settings → Options → "Delete entries after". */
	public static function retention_option() {
		return array(
			'type'    => 'group',
			'options' => array(
				'retention_days' => array( 'type' => 'text', 'label' => __( 'Delete this form\'s entries after (days)', 'fw' ), 'desc' => __( 'Empty or 0 = the site-wide period in Forms Settings. Entries older than this are deleted once a day.', 'fw' ), 'value' => '' ),
			),
		);
	}

	private static function setting( $k, $d = '' ) {
		return FW_Forms_Entries_Capture::setting( $k, $d );
	}

	/** Is a digest due now? */
	public static function due( $now = null ) {
		$freq = (string) self::setting( 'entries_digest', 'off' );
		if ( ! in_array( $freq, array( 'daily', 'weekly' ), true ) ) {
			return false;
		}
		$now  = null === $now ? time() : (int) $now;
		$tz   = wp_timezone();
		$here = ( new DateTimeImmutable( '@' . $now ) )->setTimezone( $tz );
		if ( (int) $here->format( 'G' ) < 7 || ( 'weekly' === $freq && '1' !== $here->format( 'N' ) ) ) {
			return false;
		}
		$last = (int) get_option( self::LAST, 0 );
		if ( ! $last ) {
			return true;
		}
		$was = ( new DateTimeImmutable( '@' . $last ) )->setTimezone( $tz );
		return $was->format( 'Y-m-d' ) !== $here->format( 'Y-m-d' );
	}

	/**
	 * Send the digest when due (or now, with $force).
	 *
	 * @return string What happened (for the settings screen and tests).
	 */
	public static function run( $force = false ) {
		if ( ! $force && ! self::due() ) {
			return 'not due';
		}
		// The CSV writer lives with the admin screens, which cron requests do not load.
		if ( ! class_exists( 'FW_Forms_Entries_Admin' ) ) {
			require_once dirname( __FILE__ ) . '/class-fw-forms-entries-admin.php';
		}
		$to = array_filter( array_map( 'trim', explode( ',', (string) self::setting( 'entries_digest_to', '' ) ) ), 'is_email' );
		if ( ! $to ) {
			$to = array( (string) get_option( 'admin_email' ) );
		}
		$last  = (int) get_option( self::LAST, 0 );
		$since = $last ? $last : time() - ( 'weekly' === self::setting( 'entries_digest', 'off' ) ? WEEK_IN_SECONDS : DAY_IN_SECONDS );
		update_option( self::LAST, time(), false );

		$filters = array( 'since' => wp_date( 'Y-m-d H:i:s', $since ) );
		$dir     = FW_Forms_Entries::upload_dir()['path'] . '/digest-' . wp_generate_password( 12, false, false );
		wp_mkdir_p( $dir );
		$file = $dir . '/form-entries-' . wp_date( 'Y-m-d' ) . '.csv';
		$out  = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$n    = FW_Forms_Entries_Admin::write_csv( $filters, $out );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$result = 'no new entries';
		if ( $n > 0 ) {
			$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			wp_mail( $to, sprintf( _n( '[%1$s] %2$d new form entry', '[%1$s] %2$d new form entries', $n, 'fw' ), $site, $n ), sprintf( __( "The form entries that came in since %1\$s are attached as a spreadsheet (CSV).\n\nSee them all: %2\$s", 'fw' ), wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $since ), FW_Forms_Entries_Admin::page_url() ), '', array( $file ) );
			$result = sprintf( 'sent %d', $n );
		}
		@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $result;
	}
}
