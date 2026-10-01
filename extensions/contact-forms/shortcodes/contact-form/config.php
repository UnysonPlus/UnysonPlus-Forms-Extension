<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Contact form', 'fw' ),
	'description' => __( 'Add a Contact Form', 'fw' ),
	'tab'         => __( 'Interactive Elements', 'fw' ),
	'popup_size'  => 'large',
	// 'simple' -- the ONLY item type the page builder implements. This read 'special', which no item type
	// handles: the builder's simple-item JS looks the shortcode up in page_builder_item_type_simple_data,
	// did not find it, and every converted contact form opened as "The shortcode contact_form not found."
	// The shortcode rendered correctly on the front end the whole time -- it was only uneditable in the
	// builder, which is exactly the breakage a converted page must not ship. It was the only shortcode in
	// the framework declaring 'special'.
	'type'        => 'simple'
);