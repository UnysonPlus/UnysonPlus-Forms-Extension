<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Entry View', 'fw' ),
	'description' => __( 'Show a form\'s entries on the site: a table, cards, a list, a map or a calendar, with search, a filter and a page per entry.', 'fw' ),
	'tab'         => __( 'Interactive Elements', 'fw' ),
	'popup_size'  => 'medium',
	'type'        => 'simple',
);
