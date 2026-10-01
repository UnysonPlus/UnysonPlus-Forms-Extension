<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Forms extension settings.
 *
 * The reCAPTCHA keys are stored GLOBALLY under this extension's settings option
 * `recaptcha-keys` (a multi of { site-key, secret-key } ). The reCAPTCHA form
 * field reads them via fw_ext( 'forms' )->get_db_settings_option( 'recaptcha-keys' ),
 * so a site only sets its keys once here instead of per-form.
 */
$options = array(
	'recaptcha_box' => array(
		'title'   => __( 'Google reCAPTCHA', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'group_recaptcha' => array(
				'type'    => 'group',
				'options' => array(
					'recaptcha-keys' => array(
						'type'          => 'multi',
						'label'         => false,
						'desc'          => false,
						// Stored as { site-key, secret-key } — the exact shape the
						// reCAPTCHA field reads. Keep these inner ids unchanged.
						'inner-options' => array(
							'site-key'   => array(
								'type'  => 'text',
								'label' => __( 'Site Key', 'fw' ),
								'desc'  => __( 'Your reCAPTCHA v2 site key. Used site-wide by every Recaptcha form field.', 'fw' ),
								'value' => '',
							),
							'secret-key' => array(
								'type'  => 'text',
								'label' => __( 'Secret Key', 'fw' ),
								'desc'  => __( 'Your reCAPTCHA v2 secret key. Used server-side to validate submissions. Keep it private.', 'fw' ),
								'value' => '',
							),
						),
					),
					'recaptcha_help' => array(
						'type'  => 'html-fixed',
						'label' => false,
						'html'  => sprintf(
							/* translators: %s: opening/closing anchor to the reCAPTCHA admin */
							__( 'Generate a pair of keys at %1$sGoogle reCAPTCHA → Admin Console%2$s (choose reCAPTCHA v2 “I\'m not a robot”). Once saved, add a Recaptcha field to any contact form.', 'fw' ),
							'<a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">',
							'</a>'
						),
					),
				),
			),
		),
	),
	'entries_box' => array(
		'title'   => __( 'Entries', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'group_entries' => array(
				'type'    => 'group',
				'options' => array(
					'entries_store' => array(
						'type'         => 'switch',
						'label'        => __( 'Store submissions', 'fw' ),
						'desc'         => __( 'Keep every form submission as an entry you can search, read and export under Unyson+ → Form Entries. Emails are still sent as before; this is in addition, not instead — and an entry is stored even if the email fails to send.', 'fw' ),
						'value'        => 'yes',
						'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
					),
					'entries_store_ip' => array(
						'type'         => 'switch',
						'label'        => __( 'Record IP address', 'fw' ),
						'desc'         => __( 'Store the IP address of the sender with each entry. Off by default: it is personal data under GDPR, and most sites have no use for it.', 'fw' ),
						'value'        => 'no',
						'left-choice'  => array( 'value' => 'no',  'label' => __( 'No', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
					),
					'entries_retention_days' => array(
						'type'  => 'text',
						'label' => __( 'Keep entries for (days)', 'fw' ),
						'desc'  => __( 'Entries older than this are deleted automatically once a day. Leave 0 to keep them until you delete them. Entries are also included in the WordPress Export / Erase Personal Data tools, so a data request covers them.', 'fw' ),
						'value' => '0',
					),
				),
			),
		),
	),
);
