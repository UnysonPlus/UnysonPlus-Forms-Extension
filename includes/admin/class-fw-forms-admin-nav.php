<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * One "Forms" item in the Unyson+ menu instead of three.
 *
 * Entries and Results stay registered pages (their URLs and every link to them keep working),
 * but leave the sidebar; the three screens share a tab bar (Forms | Entries | Results) and the
 * sidebar keeps "Forms" highlighted on all of them.
 */
class FW_Forms_Admin_Nav {

	public static function init() {
		// admin_head, not admin_menu: WordPress checks access to a page against the menu, so the items
		// must still be there for that check and leave only before the sidebar is printed.
		add_action( 'admin_head', array( __CLASS__, '_hide_submenus' ) );
		add_filter( 'submenu_file', array( __CLASS__, '_submenu_file' ) );
		add_action( 'all_admin_notices', array( __CLASS__, '_library_tabs' ) );
	}

	private static function library_url() {
		return admin_url( 'edit.php?post_type=' . FW_Forms_Library::POST_TYPE );
	}

	private static function hidden_pages() {
		return array( FW_Forms_Entries_Admin::PAGE_SLUG, FW_Forms_Reports::PAGE_SLUG );
	}

	/** @internal */
	public static function _hide_submenus() {
		foreach ( self::hidden_pages() as $slug ) {
			remove_submenu_page( FW_Forms_Entries_Admin::PARENT_SLUG, $slug );
		}
	}

	/** @internal Highlight "Forms" in the sidebar while on Entries or Results. */
	public static function _submenu_file( $submenu_file ) {
		global $plugin_page;
		return in_array( $plugin_page, self::hidden_pages(), true ) ? 'edit.php?post_type=' . FW_Forms_Library::POST_TYPE : $submenu_file;
	}

	/** @internal The tab bar on the forms list (a post-type screen, so it has no render callback of ours). */
	public static function _library_tabs() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'edit-' . FW_Forms_Library::POST_TYPE === $screen->id ) {
			// all_admin_notices prints before the screen's .wrap; give the bar the same box as on Entries / Results.
			echo '<div class="wrap" style="margin-bottom:0">';
			self::render( 'forms' );
			echo '</div>';
		}
	}

	/**
	 * @param string $active forms|entries|results
	 */
	public static function render( $active ) {
		$tabs = array( 'forms' => array( __( 'Forms', 'fw' ), self::library_url() ) );
		if ( current_user_can( FW_Forms_Entries_Admin::capability() ) ) {
			$tabs['entries'] = array( __( 'Entries', 'fw' ), admin_url( 'admin.php?page=' . FW_Forms_Entries_Admin::PAGE_SLUG ) );
			$tabs['results'] = array( __( 'Results', 'fw' ), FW_Forms_Reports::url() );
		}
		echo '<nav class="nav-tab-wrapper wp-clearfix fw-forms-tabs" style="margin-bottom:1em" aria-label="' . esc_attr__( 'Forms', 'fw' ) . '">';
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a href="%s" class="nav-tab%s"%s>%s</a>',
				esc_url( $tab[1] ),
				$key === $active ? ' nav-tab-active' : '',
				$key === $active ? ' aria-current="page"' : '',
				esc_html( $tab[0] )
			);
		}
		echo '</nav>';
	}
}
