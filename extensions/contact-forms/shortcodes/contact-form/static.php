<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

// Loaded only when a contact form is on the page, so the form CSS/JS print in <head>
// there and nowhere else.
if ( fw_ext( 'forms' ) ) {
	fw_ext( 'forms' )->enqueue_frontend_static();
}
