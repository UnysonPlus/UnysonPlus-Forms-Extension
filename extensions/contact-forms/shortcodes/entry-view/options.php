<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$sw = function ( $label, $desc, $on ) {
	return array( 'type' => 'switch', 'label' => $label, 'desc' => $desc, 'value' => $on ? 'yes' : 'no', 'left-choice' => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ), 'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ) );
};
$field = __( 'A field\'s name, as in its answer tag without the braces (e.g. text_name).', 'fw' );

$options = array(
	'tab_content' => array(
		'title'   => __( 'Content', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'form_id'     => array(
				'type'    => 'select',
				'label'   => __( 'Form', 'fw' ),
				'desc'    => __( 'Only forms whose Settings → On the site allow it can be shown.', 'fw' ),
				'value'   => '',
				'choices' => class_exists( 'FW_Forms_Views' ) ? FW_Forms_Views::form_choices() : array( '' => '—' ),
			),
			'fields'      => array( 'type' => 'text', 'label' => __( 'Fields to show', 'fw' ), 'desc' => __( 'Field names, separated by commas. Nothing else from the entry is shown or searched.', 'fw' ), 'value' => '' ),
			'title_field' => array( 'type' => 'text', 'label' => __( 'Title field', 'fw' ), 'desc' => $field, 'value' => '' ),
			'layout'      => array(
				'type'    => 'select',
				'label'   => __( 'Show as', 'fw' ),
				'value'   => 'table',
				'choices' => array( 'table' => __( 'Table', 'fw' ), 'grid' => __( 'Cards', 'fw' ), 'list' => __( 'List', 'fw' ), 'map' => __( 'Map', 'fw' ), 'calendar' => __( 'Calendar', 'fw' ) ),
			),
			'map_field'      => array( 'type' => 'text', 'label' => __( 'Location field (map)', 'fw' ), 'desc' => __( 'A field holding "latitude, longitude".', 'fw' ), 'value' => '' ),
			'map_height'     => array( 'type' => 'text', 'label' => __( 'Map height (px)', 'fw' ), 'value' => '420' ),
			'calendar_field' => array( 'type' => 'text', 'label' => __( 'Date field (calendar)', 'fw' ), 'desc' => __( 'Empty = the day the entry was sent.', 'fw' ), 'value' => '' ),
			'search'       => $sw( __( 'Search box', 'fw' ), '', true ),
			'filter_field' => array( 'type' => 'text', 'label' => __( 'Filter by', 'fw' ), 'desc' => __( 'A field (usually a dropdown or choice) to filter on. Empty = no filter.', 'fw' ), 'value' => '' ),
			'detail'       => $sw( __( 'A page per entry', 'fw' ), __( 'Each entry links to its own view with the chosen fields.', 'fw' ), true ),
			'only_mine'    => $sw( __( 'Only the visitor\'s own entries', 'fw' ), __( 'Shows a logged-in person what they sent (and Edit links when the form allows it).', 'fw' ), false ),
			'per_page'     => array( 'type' => 'text', 'label' => __( 'Per page', 'fw' ), 'value' => '12' ),
			'order'        => array( 'type' => 'select', 'label' => __( 'Order', 'fw' ), 'value' => 'newest', 'choices' => array( 'newest' => __( 'Newest first', 'fw' ), 'oldest' => __( 'Oldest first', 'fw' ) ) ),
			'empty_text'   => array( 'type' => 'text', 'label' => __( 'When there is nothing to show', 'fw' ), 'value' => '' ),
		),
	),
);

if ( function_exists( 'sc_get_advanced_tab' ) ) {
	$options['tab_advanced'] = array(
		'title'   => __( 'Advanced', 'fw' ),
		'type'    => 'tab',
		'options' => array( 'advanced_settings' => array( 'type' => 'group', 'options' => sc_get_advanced_tab() ) ),
	);
}
