<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Top-level tabs (NOT wrapped in a `box`) so the Contact Form modal shows a plain
 * tab bar — Form Fields / Settings / Style / Advanced — consistent with every
 * other builder element. A `box` here rendered an empty collapsible post-box
 * header above the tabs.
 */
$options = array(
	'id'       => array(
		'type'  => 'unique',
	),
	'builder'  => array(
		'type'    => 'tab',
		'title'   => __( 'Form Fields', 'fw' ),
		'options' => array(
			'form' => array(
				'label' => false,
				'type'  => 'form-builder',
				'value' => array(
					/** Filters whether a new contact form seeds a default form-header-title item into the form builder. */
					'json' => apply_filters('fw:ext:forms:builder:load-item:form-header-title', true)
						? json_encode( array(
							array(
								'type'      => 'form-header-title',
								'shortcode' => 'form_header_title',
								'width'     => '',
								'options'   => array(
									'title'    => '',
									'subtitle' => '',
								)
							)
						) )
						: '[]'
				),
				'fixed_header' => true,
			),
		),
	),
	'settings' => array(
		'type'    => 'tab',
		'title'   => __( 'Settings', 'fw' ),
		'options' => array(
			'settings-options' => array(
				'title'   => __( 'Options', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'form_email_settings' => array(
						'type'    => 'group',
						'options' => array(
							'email_to' => array(
								'type'  => 'text',
								'label' => __( 'Email To', 'fw' ),
								'help' => __( 'We recommend you to use an email that you verify often', 'fw' ),
								'desc'  => __( 'The form will be sent to this email address.', 'fw' ),
							),
						),
					),
					'form_text_settings'  => array(
						'type'    => 'group',
						'options' => array(
							'subject-group' => array(
								'type' => 'group',
								'options' => array(
									'subject_message'    => array(
										'type'  => 'text',
										'label' => __( 'Subject Message', 'fw' ),
										'desc' => __( 'This text will be used as subject message for the email', 'fw' ),
										'value' => __( 'Contact Form', 'fw' ),
									),
								)
							),
							'submit-button-group' => array(
								'type' => 'group',
								'options' => array(
									'submit_button_text' => array(
										'type'  => 'text',
										'label' => __( 'Submit Button', 'fw' ),
										'desc' => __( 'This text will appear in submit button', 'fw' ),
										'value' => __( 'Send', 'fw' ),
									),
								)
							),
							'success-group' => array(
								'type' => 'group',
								'options' => array(
									'success_message'    => array(
										'type'  => 'text',
										'label' => __( 'Success Message', 'fw' ),
										'desc' => __( 'This text will be displayed when the form will successfully send', 'fw' ),
										'value' => __( 'Message sent!', 'fw' ),
									),
								)
							),
							'failure_message'    => array(
								'type'  => 'text',
								'label' => __( 'Failure Message', 'fw' ),
								'desc' => __( 'This text will be displayed when the form will fail to be sent', 'fw' ),
								'value' => __( 'Oops something went wrong.', 'fw' ),
							),
						),
					),
				)
			),
			'mailer-options'   => array(
				'title'   => __( 'Mailer', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'mailer' => array(
						'label' => false,
						'type'  => 'mailer'
					)
				)
			)
		),
	),
);

/**
 * Style + Advanced tabs — appended after definition so a missing shortcode
 * styling helper (e.g. if the shortcodes extension is deactivated) degrades to
 * the original Form Fields + Settings tabs instead of fataling. These bring the
 * Contact Form to parity with every other builder element: per-instance colours,
 * field/button styling, layout, spacing (Style) and the shared CSS ID / class /
 * custom CSS / position / responsive / display-conditions surface (Advanced).
 */
if ( function_exists( 'sc_get_advanced_tab' ) && function_exists( 'sc_color_field_compact' ) ) {

	$radius_choices = array(
		''      => __( 'Default', 'fw' ),
		'0'     => __( 'Square', 'fw' ),
		'4px'   => '4px',
		'6px'   => '6px',
		'8px'   => '8px',
		'12px'  => '12px',
		'999px' => __( 'Pill', 'fw' ),
	);

	$options['style'] = array(
		'title'   => __( 'Style', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'cf_layout' => array(
				'type'    => 'group',
				'options' => array(
					'form_max_width' => array(
						'type'    => 'select',
						'label'   => __( 'Form Width', 'fw' ),
						'desc'    => __( 'Constrain the form to a maximum width. "Full width" fills its column.', 'fw' ),
						'value'   => '',
						'choices' => array(
							''      => __( 'Full width', 'fw' ),
							'480px' => __( 'Narrow (480px)', 'fw' ),
							'600px' => __( 'Medium (600px)', 'fw' ),
							'720px' => __( 'Wide (720px)', 'fw' ),
							'880px' => __( 'Extra wide (880px)', 'fw' ),
						),
					),
					'form_align' => array(
						'type'    => 'select',
						'label'   => __( 'Form Alignment', 'fw' ),
						'desc'    => __( 'Horizontal placement when a Form Width is set (ignored at full width).', 'fw' ),
						'value'   => 'left',
						'choices' => array(
							'left'   => __( 'Left', 'fw' ),
							'center' => __( 'Center', 'fw' ),
							'right'  => __( 'Right', 'fw' ),
						),
					),
				),
			),
			'cf_fields' => array(
				'type'    => 'group',
				'options' => array(
					'field_bg'           => sc_color_field_compact( array( 'label' => __( 'Field Background', 'fw' ), 'kind' => 'bg' ) ),
					'field_text'         => sc_color_field_compact( array( 'label' => __( 'Field Text', 'fw' ), 'kind' => 'text' ) ),
					'field_border'       => sc_color_field_compact( array( 'label' => __( 'Field Border', 'fw' ), 'kind' => 'text' ) ),
					'field_focus'        => sc_color_field_compact( array( 'label' => __( 'Field Focus / Accent', 'fw' ), 'kind' => 'text', 'desc' => __( 'Border colour when a field is focused.', 'fw' ) ) ),
					'label_color'        => sc_color_field_compact( array( 'label' => __( 'Label Colour', 'fw' ), 'kind' => 'text' ) ),
					'field_radius'       => array( 'type' => 'select', 'label' => __( 'Field Corner Radius', 'fw' ), 'value' => '', 'choices' => $radius_choices ),
					'field_border_width' => array( 'type' => 'select', 'label' => __( 'Field Border Width', 'fw' ), 'value' => '', 'choices' => array( '' => __( 'Default', 'fw' ), '1px' => '1px', '2px' => '2px', '3px' => '3px' ) ),
					'field_padding'      => array( 'type' => 'select', 'label' => __( 'Field Padding', 'fw' ), 'value' => '', 'choices' => array( '' => __( 'Default', 'fw' ), '.45rem .7rem' => __( 'Compact', 'fw' ), '.65rem .9rem' => __( 'Comfortable', 'fw' ), '.85rem 1.1rem' => __( 'Roomy', 'fw' ) ) ),
				),
			),
			'cf_button' => array(
				'type'    => 'group',
				'options' => array(
					'button_style' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'fw' ),
						'desc'    => __( 'Reuses your Button Color Presets (Theme Settings → Buttons). "Default" is the bare .btn base.', 'fw' ),
						'value'   => sc_get_button_style_default(),
						'choices' => sc_get_button_style_choices(),
					),
					'button_size' => array(
						'type'    => 'select',
						'label'   => __( 'Button Size', 'fw' ),
						'value'   => '',
						'choices' => sc_get_button_size_choices(),
					),
					'button_shape' => array(
						'type'    => 'select',
						'label'   => __( 'Button Shape', 'fw' ),
						'desc'    => __( 'Overrides the corner radius from the Size preset.', 'fw' ),
						'value'   => '',
						'choices' => array( '' => __( 'Default', 'fw' ), 'rounded' => __( 'Rounded', 'fw' ), 'pill' => __( 'Pill', 'fw' ), 'square' => __( 'Square', 'fw' ) ),
					),
					'button_full'  => array( 'type' => 'switch', 'label' => __( 'Full-Width Button', 'fw' ), 'value' => false ),
					'button_align' => array(
						'type'    => 'select',
						'label'   => __( 'Button Alignment', 'fw' ),
						'desc'    => __( 'Ignored when Full-Width is on.', 'fw' ),
						'value'   => 'left',
						'choices' => array( 'left' => __( 'Left', 'fw' ), 'center' => __( 'Center', 'fw' ), 'right' => __( 'Right', 'fw' ) ),
					),
				),
			),
			'cf_spacing' => array(
				'type'    => 'group',
				'options' => array(
					'margin' => sc_spacing_field( array( 'label' => __( 'Outer Margin', 'fw' ), 'prefix' => 'm' ) ),
				),
			),
		),
	);

	$options['advanced'] = array(
		'title'   => __( 'Advanced', 'fw' ),
		'type'    => 'tab',
		'options' => array(
			'advanced_settings' => array(
				'type'    => 'group',
				'options' => sc_get_advanced_tab(),
			),
		),
	);
}
