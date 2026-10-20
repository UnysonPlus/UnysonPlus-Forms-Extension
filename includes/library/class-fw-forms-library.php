<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * The form library: forms built once and placed anywhere.
 *
 * A saved form is a private post of type `fw_form`. Its fields and settings are the Contact Form
 * element's own "Form Fields" and "Settings" tabs, attached as post options, so it is edited with the
 * same builder (and opens on the form-type picker when new). A Contact Form element shows a saved form
 * by picking it under Saved Form (`library_form`); the element then keeps only its Style and Advanced
 * settings, and every page showing that form changes when the form is edited.
 *
 * The definition — `{ fields: <builder JSON string>, settings: { … } }` — is mirrored into
 * post_content on every save, so WordPress's own revisions keep a history that can be compared and
 * restored (a restore copies it back into the options). A saved form submits as form id `lib-<ID>`,
 * so its entries group under one form however many pages show it.
 */
class FW_Forms_Library {

	const POST_TYPE = 'fw_form';

	/** Settings keys that belong to the FORM (everything else on the element is presentation). */
	const SETTING_KEYS = array(
		'email_notify', 'email_to', 'email_cc', 'email_bcc', 'subject_message', 'submit_button_text', 'success_message',
		'failure_message', 'confirmation_type', 'confirmation_page', 'confirmation_url',
		'limit_entries', 'limit_opens', 'limit_closes', 'limit_closed_message', 'limit_seats',
		'quiz_enabled', 'quiz_points', 'quiz_outcomes',
		'pdf_notification', 'pdf_autoresponder', 'pdf_title',
		'drafts_enabled', 'drafts_days',
		'partial_enabled', 'partial_notice', 'retention_days',
		'views_allowed', 'views_approval', 'views_approvers', 'edit_own', 'edit_lock', 'edit_days',
		'email_routes', 'extra_notifications', 'confirmation_routes',
	);

	public static function init() {
		// The extension may boot after `init` has already run; register right away in that case.
		if ( did_action( 'init' ) ) {
			self::_register();
		} else {
			add_action( 'init', array( __CLASS__, '_register' ) );
		}
		add_filter( 'fw_post_options', array( __CLASS__, '_filter_post_options' ), 10, 2 );
		add_action( 'fw_post_options_update', array( __CLASS__, '_mirror' ), 20, 1 );
		add_action( 'wp_restore_post_revision', array( __CLASS__, '_restore' ), 10, 2 );
		add_filter( 'fw_ext_forms_entry_form_title', array( __CLASS__, '_entry_title' ), 20, 2 );
	}

	/** @internal Entries of a saved form are titled with the form's name. */
	public static function _entry_title( $title, $data ) {
		$id = (string) ( $data['id'] ?? '' );
		if ( 0 === strpos( $id, 'lib-' ) ) {
			$name = get_the_title( (int) substr( $id, 4 ) );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return $title;
	}

	/** @internal */
	public static function _register() {
		register_post_type( self::POST_TYPE, array(
			'labels'              => array(
				'name'          => __( 'Forms', 'fw' ),
				'singular_name' => __( 'Form', 'fw' ),
				'add_new'       => __( 'Add New Form', 'fw' ),
				'add_new_item'  => __( 'Add New Form', 'fw' ),
				'edit_item'     => __( 'Edit Form', 'fw' ),
				'new_item'      => __( 'New Form', 'fw' ),
				'search_items'  => __( 'Search Forms', 'fw' ),
				'not_found'     => __( 'No forms yet.', 'fw' ),
				'all_items'     => __( 'Forms', 'fw' ),
				'menu_name'     => __( 'Forms', 'fw' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'fw-extensions',
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title', 'revisions' ),
			'capability_type'     => 'page',
			'map_meta_cap'        => true,
		) );
	}

	/**
	 * The contact form's Form Fields and Settings tabs, for the library post's editor.
	 *
	 * @return array
	 */
	public static function definition_options() {
		$sc = function_exists( 'fw_ext' ) && fw_ext( 'shortcodes' ) ? fw_ext( 'shortcodes' )->get_shortcode( 'contact_form' ) : null;
		if ( ! $sc ) {
			return array();
		}
		$all = $sc->get_options();
		$out = array();
		foreach ( array( 'builder', 'settings' ) as $tab ) {
			if ( isset( $all[ $tab ] ) ) {
				$out[ $tab ] = $all[ $tab ];
			}
		}
		// Choosing a saved form makes no sense on a saved form.
		if ( isset( $out['builder']['options']['library_form'] ) ) {
			unset( $out['builder']['options']['library_form'] );
		}
		return $out;
	}

	/** @internal */
	public static function _filter_post_options( $options, $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return $options;
		}
		$def = self::definition_options();
		if ( ! $def ) {
			return $options;
		}
		$options['fw_form_definition'] = array(
			'title'   => __( 'Form', 'fw' ),
			'type'    => 'box',
			'context' => 'normal',
			'options' => $def,
		);
		return $options;
	}

	/**
	 * A saved form's definition as element atts: `form` plus the settings and action keys.
	 *
	 * @param int $post_id
	 * @return array|null
	 */
	public static function definition( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || self::POST_TYPE !== get_post_type( $post_id ) || 'trash' === get_post_status( $post_id ) ) {
			return null;
		}
		$opts = fw_get_db_post_option( $post_id );
		if ( ! is_array( $opts ) || empty( $opts['form'] ) ) {
			return null;
		}
		$out = array( 'form' => $opts['form'] );
		foreach ( $opts as $k => $v ) {
			if ( in_array( $k, self::SETTING_KEYS, true ) || 0 === strpos( (string) $k, 'action_' ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/** The form id a saved form submits as. */
	public static function form_id( $post_id ) {
		return 'lib-' . (int) $post_id;
	}

	/**
	 * Saved forms for a dropdown, read straight from the posts table (never WP_Query while element
	 * options are being built: its hooks can build options again).
	 *
	 * @return array ID => title
	 */
	public static function choices() {
		static $rows = null;
		if ( null === $rows ) {
			global $wpdb;
			$rows = array();
			$res  = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','private','draft') ORDER BY post_title ASC LIMIT 500", self::POST_TYPE ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $res as $r ) {
				$rows[ (int) $r->ID ] = '' !== $r->post_title ? $r->post_title : sprintf( __( 'Form #%d', 'fw' ), $r->ID );
			}
		}
		return $rows;
	}

	/**
	 * @internal Mirror the definition into post_content, so revisions keep it.
	 */
	public static function _mirror( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$def = self::definition( $post_id );
		if ( ! $def ) {
			return;
		}
		$json = wp_json_encode( self::export_shape( $def ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( get_post_field( 'post_content', $post_id ) === $json ) {
			return;
		}
		// wp_update_post makes the revision; keep our own hook from re-entering.
		remove_action( 'fw_post_options_update', array( __CLASS__, '_mirror' ), 20 );
		wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $json ) ) );
		add_action( 'fw_post_options_update', array( __CLASS__, '_mirror' ), 20, 1 );
	}

	/**
	 * @internal A restored revision brings its definition back into the options.
	 */
	public static function _restore( $post_id, $revision_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$data = json_decode( (string) get_post_field( 'post_content', $revision_id ), true );
		if ( is_array( $data ) ) {
			self::import_into( $post_id, $data );
		}
	}

	/**
	 * The portable shape (export, revisions): { version, fields: [items], settings: {…} }.
	 */
	public static function export_shape( array $def ) {
		$fields = isset( $def['form']['json'] ) ? json_decode( (string) $def['form']['json'], true ) : array();
		$settings = $def;
		unset( $settings['form'] );
		return array( 'version' => 1, 'fields' => is_array( $fields ) ? $fields : array(), 'settings' => $settings );
	}

	/**
	 * Write a portable definition into a saved form's options.
	 *
	 * @param int   $post_id
	 * @param array $data { fields: [items], settings: {…} }
	 * @return bool
	 */
	public static function import_into( $post_id, array $data ) {
		$fields = isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : array();
		if ( class_exists( 'FW_Forms_Starters' ) && $fields ) {
			$fields = FW_Forms_Starters::normalize( $fields ); // never trust a file's shape
		}
		$opts         = (array) fw_get_db_post_option( $post_id );
		$opts['form'] = array( 'json' => wp_json_encode( $fields ) );
		$settings     = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		foreach ( $settings as $k => $v ) {
			if ( in_array( $k, self::SETTING_KEYS, true ) || 0 === strpos( (string) $k, 'action_' ) ) {
				$opts[ $k ] = is_scalar( $v ) ? sanitize_text_field( (string) $v ) : $v;
			}
		}
		remove_action( 'fw_post_options_update', array( __CLASS__, '_mirror' ), 20 );
		fw_set_db_post_option( $post_id, null, $opts );
		add_action( 'fw_post_options_update', array( __CLASS__, '_mirror' ), 20, 1 );
		return true;
	}

	/**
	 * Create a saved form from a definition (the element's "Save to the library", import, duplicate).
	 *
	 * @return int|WP_Error
	 */
	public static function create( $title, array $data ) {
		$id = wp_insert_post( array(
			'post_type'   => self::POST_TYPE,
			'post_status' => 'publish',
			'post_title'  => sanitize_text_field( $title ),
		), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		self::import_into( $id, $data );
		self::_mirror( $id );
		return (int) $id;
	}

	/** Number of fields a visitor fills in (header and hidden fields excluded). */
	public static function field_count( $post_id ) {
		$def = self::definition( $post_id );
		$items = $def && isset( $def['form']['json'] ) ? json_decode( (string) $def['form']['json'], true ) : array();
		$n = 0;
		foreach ( (array) $items as $it ) {
			if ( ! in_array( $it['type'] ?? '', array( 'form-header-title', 'hidden', 'honeypot' ), true ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Posts whose page builder shows this saved form (for "Placed on").
	 *
	 * @return array [ { id, title, edit } ]
	 */
	public static function placements( $post_id ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"library_form":"' . (int) $post_id . '"' ) . '%';
		$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'fw:opt:ext:pb:page-builder:json' AND meta_value LIKE %s LIMIT 20", $like ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$out  = array();
		foreach ( (array) $ids as $id ) {
			$p = get_post( (int) $id );
			if ( $p && 'revision' !== $p->post_type && 'trash' !== $p->post_status ) {
				$out[] = array( 'id' => (int) $id, 'title' => get_the_title( $p ), 'edit' => get_edit_post_link( $p, 'raw' ) );
			}
		}
		return $out;
	}

	/**
	 * Element side: an element that shows a saved form takes the form's fields and settings; its own
	 * Style / Advanced stay. Returns the atts unchanged when no (valid) saved form is chosen.
	 */
	public static function apply_to_atts( array $atts ) {
		$lib = isset( $atts['library_form'] ) ? (int) $atts['library_form'] : 0;
		if ( $lib <= 0 ) {
			return $atts;
		}
		$def = self::definition( $lib );
		if ( ! $def ) {
			return $atts; // Deleted or emptied: fall back to the element's own fields.
		}
		$atts       = array_merge( $atts, $def );
		$atts['id'] = self::form_id( $lib );
		return $atts;
	}
}
