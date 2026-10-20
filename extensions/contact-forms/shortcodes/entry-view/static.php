<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

if ( fw_ext( 'forms' ) ) {
	wp_enqueue_style(
		'fw-shortcode-entry-view',
		fw_ext( 'contact-forms' )->get_declared_URI( '/shortcodes/entry-view/static/css/styles.css' ),
		array(),
		fw_ext( 'forms' )->manifest->get_version()
	);
}
