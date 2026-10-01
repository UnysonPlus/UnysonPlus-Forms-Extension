<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Creates and versions the form entries table.
 *
 * Same contract as the Newsletter CRM installer: `dbDelta()` is picky — two
 * spaces after PRIMARY KEY, one field per line, `KEY` not `INDEX`, and
 * `$wpdb->prefix` (per-site on multisite) rather than `base_prefix`.
 *
 * One table. An entry stores its fields as JSON rather than as columns, on
 * purpose: every form has a different shape, and a column per field would grow
 * a column per form. The few things worth indexing on — who sent it, which
 * form, when, what state it is in — are lifted out into real columns.
 */
class FW_Forms_Entries_Installer {

	const DB_VERSION        = '1.0.0';
	const DB_VERSION_OPTION = 'fw_ext_forms_entries_db_version';

	/**
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 'fw_form_entries';
	}

	public static function maybe_install() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		self::install();
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$collate = $wpdb->get_charset_collate();

		// `email` is varchar(190) so its index fits inside utf8mb4's key limit.
		// `status` is varchar, not ENUM, so a new state is a PHP whitelist change
		// rather than a schema migration.
		dbDelta( "CREATE TABLE {$table} (
	id bigint(20) unsigned NOT NULL auto_increment,
	form_id varchar(64) NOT NULL default '',
	form_type varchar(64) NOT NULL default '',
	form_title varchar(200) NOT NULL default '',
	post_id bigint(20) unsigned NOT NULL default 0,
	email varchar(190) NOT NULL default '',
	fields longtext NOT NULL,
	status varchar(20) NOT NULL default 'new',
	ip varchar(45) NOT NULL default '',
	user_agent varchar(255) NOT NULL default '',
	created_at datetime NOT NULL default '0000-00-00 00:00:00',
	PRIMARY KEY  (id),
	KEY form_id (form_id),
	KEY email (email),
	KEY status (status),
	KEY created_at (created_at)
) {$collate};" );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, true );
	}
}
