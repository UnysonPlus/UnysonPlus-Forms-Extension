<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

if ( ! function_exists( 'fw_ext_contact_form_page_choices' ) ) {
	/**
	 * Published pages for "Thank-you Page", read straight from the posts table. get_pages() would
	 * run WP_Query and its hooks while element options are being built, and those hooks can ask for
	 * element options again; on the page builder's options request that recursed until PHP ran out
	 * of memory. Memoised per request.
	 *
	 * @return array page ID => title
	 */
	function fw_ext_contact_form_page_choices() {
		static $choices = null;
		if ( null === $choices ) {
			global $wpdb;
			$choices = array( '' => __( '— Choose a page —', 'fw' ) );
			$rows    = $wpdb->get_results( "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' ORDER BY post_title ASC LIMIT 300" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			foreach ( (array) $rows as $r ) {
				$choices[ (string) $r->ID ] = '' !== $r->post_title ? $r->post_title : '#' . $r->ID;
			}
		}
		return $choices;
	}
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
			'library_form' => array(
				'type'    => 'select',
				'label'   => __( 'Saved Form', 'fw' ),
				'desc'    => __( 'Show a form from Unyson+ → Forms instead of the fields below. Edit it there and every page showing it changes. This element keeps its own Style.', 'fw' ),
				'value'   => '',
				'choices' => array( '' => __( 'None (use the fields below)', 'fw' ) ) + ( class_exists( 'FW_Forms_Library' ) ? FW_Forms_Library::choices() : array() ),
			),
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
			// What happens after a successful submit — store, subscribe, … Each
			// action (Forms' own and other extensions') contributes its own group.
			'settings-actions' => class_exists( 'FW_Forms_Actions' ) ? FW_Forms_Actions::options_tab() : array( 'type' => 'hidden', 'value' => '' ),
			'settings-options' => array(
				'title'   => __( 'Options', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'form_email_settings' => array(
						'type'    => 'group',
						'options' => array(
							'email_notify' => array(
								'type'         => 'switch',
								'label'        => __( 'Send the notification email', 'fw' ),
								'desc'         => __( 'Email each submission to the address below. Turn it off when an action already tells you, for example a booking action that sends its own alert, or when stored entries are enough.', 'fw' ),
								'value'        => 'yes',
								'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
								'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
							),
							'email_to' => array(
								'type'  => 'text',
								'label' => __( 'Email To', 'fw' ),
								'help' => __( 'We recommend you to use an email that you verify often', 'fw' ),
								'desc'  => __( 'The form will be sent to this email address. Separate several addresses with commas. Leave empty to send it to the site admin email.', 'fw' ),
							),
							'email_cc' => array(
								'type'  => 'text',
								'label' => __( 'CC', 'fw' ),
								'desc'  => __( 'Optional. Also send a copy to these addresses (comma-separated).', 'fw' ),
								'value' => '',
							),
							'email_bcc' => array(
								'type'  => 'text',
								'label' => __( 'BCC', 'fw' ),
								'desc'  => __( 'Optional. Send a hidden copy to these addresses (comma-separated).', 'fw' ),
								'value' => '',
							),
						),
					),
					'form_pdf_settings'   => class_exists( 'FW_Forms_PDF' ) ? FW_Forms_PDF::options() : array( 'type' => 'hidden', 'value' => '' ),
					'form_draft_settings' => class_exists( 'FW_Forms_Drafts' ) ? FW_Forms_Drafts::options() : array( 'type' => 'hidden', 'value' => '' ),
					'form_partial_settings' => class_exists( 'FW_Forms_Partial_Entries' ) ? FW_Forms_Partial_Entries::options() : array( 'type' => 'hidden', 'value' => '' ),
					'form_retention_settings' => class_exists( 'FW_Forms_Entries_Schedule' ) ? FW_Forms_Entries_Schedule::retention_option() : array( 'type' => 'hidden', 'value' => '' ),
					'form_text_settings'  => array(
						'type'    => 'group',
						'options' => array(
							'subject-group' => array(
								'type' => 'group',
								'options' => array(
									'subject_message'    => array(
										'type'  => 'text',
										'label' => __( 'Subject Message', 'fw' ),
										'desc' => __( 'The subject of the notification email. Answer tags work here: {field_name} inserts an answer (the field name shown in the form builder, e.g. {text_name}), and {site_name}, {page_title}, {page_url} and {date} are available too.', 'fw' ),
										'value' => __( 'Contact Form', 'fw' ),
									),
								)
							),
							'email-from-group' => array(
								'type' => 'group',
								'options' => array(
									'email_from_name' => array(
										'type'  => 'text',
										'label' => __( 'From Name', 'fw' ),
										'desc'  => __( 'The sender name the notification shows, e.g. {your_name} via {site_name}. Answer tags work here. Leave empty to use the Mailer sender name; the address is always the site address.', 'fw' ),
										'value' => '',
									),
									'email_body' => array(
										'type'  => 'textarea',
										'label' => __( 'Message Body', 'fw' ),
										'desc'  => __( 'The text of the notification email. Answer tags work here: {all_fields} lists every answer. Leave empty for the standard table of answers.', 'fw' ),
										'value' => '',
									),
								),
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
										'desc' => __( 'Shown after the form is sent. Answer tags such as {text_name} work here.', 'fw' ),
										'value' => __( 'Message sent!', 'fw' ),
									),
									'confirmation_type' => array(
										'type'    => 'select',
										'label'   => __( 'After Sending', 'fw' ),
										'desc'    => __( 'What the visitor sees once the form is sent.', 'fw' ),
										'value'   => 'message',
										'choices' => array(
											'message' => __( 'Show the message and keep the form (emptied)', 'fw' ),
											'hide'    => __( 'Show the message instead of the form', 'fw' ),
											'page'    => __( 'Go to a page', 'fw' ),
											'url'     => __( 'Go to an address', 'fw' ),
										),
									),
									'messages_position' => array(
										'type'    => 'select',
										'label'   => __( 'Messages Appear', 'fw' ),
										'desc'    => __( 'Where the "sent" message and any problems with the form show: above the form, or under the Send button.', 'fw' ),
										'value'   => 'above',
										'choices' => array(
											'above' => __( 'Above the form', 'fw' ),
											'below' => __( 'Below the Send button', 'fw' ),
										),
									),
									'confirmation_page' => array(
										'type'    => 'select',
										'label'   => __( 'Thank-you Page', 'fw' ),
										'desc'    => __( 'Used when After Sending is "Go to a page".', 'fw' ),
										'value'   => '',
										'choices' => fw_ext_contact_form_page_choices(),
									),
									'confirmation_url' => array(
										'type'  => 'text',
										'label' => __( 'Address', 'fw' ),
										'desc'  => __( 'Used when After Sending is "Go to an address", e.g. https://example.com/thanks or /thanks. Answer tags work here, e.g. ?name={text_name}.', 'fw' ),
										'value' => '',
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
			'notifications-options' => array(
				'title'   => __( 'Notifications', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'form_notifications' => class_exists( 'FW_Forms_Notifications' ) ? FW_Forms_Notifications::options() : array( 'type' => 'hidden', 'value' => '' ),
				),
			),
			'quiz-options'     => array(
				'title'   => __( 'Quiz', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'form_quiz' => class_exists( 'FW_Forms_Quiz' ) ? FW_Forms_Quiz::options() : array( 'type' => 'hidden', 'value' => '' ),
				),
			),
			'limits-options'   => array(
				'title'   => __( 'Limits', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'form_limits' => class_exists( 'FW_Forms_Limits' ) ? FW_Forms_Limits::options() : array( 'type' => 'hidden', 'value' => '' ),
				),
			),
			'site-options'     => array(
				'title'   => __( 'On the site', 'fw' ),
				'type'    => 'tab',
				'options' => array(
					'form_on_site' => class_exists( 'FW_Forms_Approval' ) ? FW_Forms_Approval::options() : array( 'type' => 'hidden', 'value' => '' ),
				),
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
			'cf_style' => array(
				'type'    => 'group',
				'options' => array(
					'form_style' => array(
						'type'    => 'select',
						'label'   => __( 'Form Style', 'fw' ),
						'desc'    => __( 'The look of the fields, from your Form Styles presets (Theme Settings → Components → Form Styles). Edit a preset there to restyle every form that uses it. The settings under "This form only" still override it for this one form.', 'fw' ),
						'value'   => class_exists( 'FW_Forms_Style_Presets' ) ? FW_Forms_Style_Presets::default_choice() : '',
						'choices' => class_exists( 'FW_Forms_Style_Presets' ) ? FW_Forms_Style_Presets::choices() : array( '' => __( 'None (plain fields)', 'fw' ) ),
					),
					'form_layout' => array(
						'type'    => 'select',
						'label'   => __( 'Layout', 'fw' ),
						'desc'    => __( 'One question at a time shows each field on its own screen with a progress bar; Enter moves on. Good for short surveys and sign-ups on phones.', 'fw' ),
						'value'   => '',
						'choices' => array( '' => __( 'All fields at once', 'fw' ), 'conversational' => __( 'One question at a time', 'fw' ) ),
					),
				),
			),
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
					'field_bg'           => sc_color_field_compact( array( 'label' => __( 'Field Background', 'fw' ), 'kind' => 'bg', 'desc' => __( 'This form only: the settings in this group override the Form Style for this one form. Leave them empty to use the preset.', 'fw' ) ) ),
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
