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
	'captcha_box' => array(
		'title'   => __( 'Cloudflare Turnstile and hCaptcha', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'group_captcha' => array(
				'type'    => 'group',
				'options' => array(
					'turnstile-keys' => array(
						'type'          => 'multi',
						'label'         => false,
						'desc'          => false,
						'inner-options' => array(
							'site-key'   => array( 'type' => 'text', 'label' => __( 'Turnstile Site Key', 'fw' ), 'value' => '' ),
							'secret-key' => array( 'type' => 'text', 'label' => __( 'Turnstile Secret Key', 'fw' ), 'desc' => __( 'Kept on the server; never shown to visitors.', 'fw' ), 'value' => '' ),
						),
					),
					'turnstile_help' => array(
						'type'  => 'html-fixed',
						'label' => false,
						'html'  => sprintf(
							/* translators: %1$s %2$s: link tags */
							__( 'Turnstile is free with no usage cap and usually shows nothing to real visitors. Create a widget and copy its keys at %1$sCloudflare dashboard → Turnstile%2$s, then add a Turnstile field to any form.', 'fw' ),
							'<a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">',
							'</a>'
						),
					),
					'hcaptcha-keys' => array(
						'type'          => 'multi',
						'label'         => false,
						'desc'          => false,
						'inner-options' => array(
							'site-key'   => array( 'type' => 'text', 'label' => __( 'hCaptcha Site Key', 'fw' ), 'value' => '' ),
							'secret-key' => array( 'type' => 'text', 'label' => __( 'hCaptcha Secret Key', 'fw' ), 'desc' => __( 'Kept on the server; never shown to visitors.', 'fw' ), 'value' => '' ),
						),
					),
					'hcaptcha_help' => array(
						'type'  => 'html-fixed',
						'label' => false,
						'html'  => sprintf(
							/* translators: %1$s %2$s: link tags */
							__( 'Get hCaptcha keys at %1$shCaptcha dashboard%2$s, then add an hCaptcha field to any form. A form needs only one human check.', 'fw' ),
							'<a href="https://dashboard.hcaptcha.com/" target="_blank" rel="noopener">',
							'</a>'
						),
					),
				),
			),
		),
	),
	'spam_box' => array(
		'title'   => __( 'Spam filtering', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'group_spam' => array(
				'type'    => 'group',
				'options' => array(
					'spam_intro' => array(
						'type'  => 'html-fixed',
						'label' => false,
						'html'  => esc_html__( 'A submission caught here still sees the normal success message, so a bot learns nothing. No email is sent and no action runs; it is kept under Form Entries → Spam, where "Not spam" restores it.', 'fw' ),
					),
					'spam_akismet' => array(
						'type'         => 'switch',
						'label'        => __( 'Check with Akismet', 'fw' ),
						'desc'         => class_exists( 'FW_Forms_Spam_Filter' ) && FW_Forms_Spam_Filter::akismet_ready()
							? __( 'Akismet is set up on this site. Each submission is checked with it (the answers are sent to Akismet for the check).', 'fw' )
							: __( 'Needs the Akismet plugin, active and with its key. Until then this switch does nothing.', 'fw' ),
						'value'        => 'no',
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
					),
					'spam_blocked_words' => array(
						'type'  => 'textarea',
						'label' => __( 'Blocked words', 'fw' ),
						'desc'  => __( 'One word or phrase per line. A submission containing any of them is marked as spam. Not case-sensitive; whole words only.', 'fw' ),
						'value' => '',
					),
					'spam_blocked_domains' => array(
						'type'  => 'textarea',
						'label' => __( 'Blocked email domains', 'fw' ),
						'desc'  => __( 'One per line, e.g. example.com. A submission whose email address is at that domain (or a subdomain of it) is marked as spam.', 'fw' ),
						'value' => '',
					),
					'spam_max_links' => array(
						'type'  => 'text',
						'label' => __( 'Most links allowed', 'fw' ),
						'desc'  => __( 'A submission with more links than this is marked as spam. Leave empty for no limit; 0 allows no links at all.', 'fw' ),
						'value' => '',
					),
				),
			),
		),
	),
	'payments_box' => array(
		'title'   => __( 'Payments', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'group_payments' => array(
				'type'    => 'group',
				'options' => array(
					'payments_currency' => array(
						'type'    => 'select',
						'label'   => __( 'Currency', 'fw' ),
						'desc'    => __( 'Used by Product, Amount and Calculated money fields and by the "Take payment" action on every form.', 'fw' ),
						'value'   => 'USD',
						'choices' => array(
							'USD' => 'USD — US dollar', 'EUR' => 'EUR — Euro', 'GBP' => 'GBP — Pound sterling', 'AUD' => 'AUD — Australian dollar',
							'CAD' => 'CAD — Canadian dollar', 'NZD' => 'NZD — New Zealand dollar', 'CHF' => 'CHF — Swiss franc', 'SEK' => 'SEK — Swedish krona',
							'NOK' => 'NOK — Norwegian krone', 'DKK' => 'DKK — Danish krone', 'PLN' => 'PLN — Polish złoty', 'JPY' => 'JPY — Japanese yen',
							'KRW' => 'KRW — South Korean won', 'INR' => 'INR — Indian rupee', 'IDR' => 'IDR — Indonesian rupiah', 'PHP' => 'PHP — Philippine peso',
							'SGD' => 'SGD — Singapore dollar', 'MYR' => 'MYR — Malaysian ringgit', 'THB' => 'THB — Thai baht', 'HKD' => 'HKD — Hong Kong dollar',
							'AED' => 'AED — UAE dirham', 'ZAR' => 'ZAR — South African rand', 'BRL' => 'BRL — Brazilian real', 'MXN' => 'MXN — Mexican peso',
							'CRC' => 'CRC — Costa Rican colón',
						),
					),
				),
			),
			'group_coupons' => array(
				'type'    => 'group',
				'options' => array(
					'payments_coupons' => array(
						'type'            => 'addable-box',
						'label'           => __( 'Coupons', 'fw' ),
						'desc'            => __( 'Codes for the Coupon field on a form. A use is counted when a payment with the code is recorded.', 'fw' ),
						'box-options'     => array(
							'code'     => array( 'type' => 'text', 'label' => __( 'Code', 'fw' ), 'desc' => __( 'Letters, numbers, - and _. Not case-sensitive.', 'fw' ), 'value' => '' ),
							'type'     => array( 'type' => 'select', 'label' => __( 'Discount', 'fw' ), 'value' => 'percent', 'choices' => array( 'percent' => __( 'A percentage', 'fw' ), 'fixed' => __( 'A fixed amount', 'fw' ) ) ),
							'amount'   => array( 'type' => 'text', 'label' => __( 'Amount', 'fw' ), 'desc' => __( 'e.g. 10 (10% or 10 in the form currency).', 'fw' ), 'value' => '' ),
							'duration' => array( 'type' => 'select', 'label' => __( 'On subscriptions', 'fw' ), 'value' => 'once', 'choices' => array( 'once' => __( 'The first payment only', 'fw' ), 'forever' => __( 'Every payment', 'fw' ) ) ),
							'expires'  => array( 'type' => 'text', 'label' => __( 'Last day it works', 'fw' ), 'desc' => __( 'YYYY-MM-DD, or empty.', 'fw' ), 'value' => '' ),
							'max_uses' => array( 'type' => 'text', 'label' => __( 'Uses allowed', 'fw' ), 'desc' => __( '0 or empty = no limit.', 'fw' ), 'value' => '' ),
						),
						'template'        => '{{- code }}',
						'add-button-text' => __( 'Add coupon', 'fw' ),
						'value'           => array(),
					),
				),
			),
			'group_stripe' => array(
				'type'    => 'group',
				'options' => array(
					'payments_stripe_help'         => array(
						'type'  => 'html-fixed',
						'label' => __( 'Card (Stripe)', 'fw' ),
						'html'  => '<p>' . esc_html__( 'Payers pay on the secure Stripe page; no card details touch this site. Keys: Stripe Dashboard → Developers → API keys. Notifications: add an endpoint in Developers → Webhooks with the events checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed and charge.refunded, then paste its signing secret below. Leave everything empty to use the keys saved in Membership → Payments.', 'fw' ) . '</p><p><code>' . esc_html( function_exists( 'rest_url' ) ? rest_url( 'fw-forms/v1/webhooks/stripe' ) : '' ) . '</code></p>',
					),
					'payments_stripe_mode'         => array(
						'type'    => 'select',
						'label'   => __( 'Stripe mode', 'fw' ),
						'value'   => '',
						'choices' => array( '' => __( 'As in Membership, or Test', 'fw' ), 'test' => __( 'Test (no real money)', 'fw' ), 'live' => __( 'Live', 'fw' ) ),
					),
					'payments_stripe_test_secret'  => array( 'type' => 'password', 'label' => __( 'Test secret key', 'fw' ), 'desc' => __( 'Starts with sk_test_ (or a restricted rk_test_ key).', 'fw' ), 'value' => '' ),
					'payments_stripe_test_webhook' => array( 'type' => 'password', 'label' => __( 'Test signing secret', 'fw' ), 'desc' => __( 'Starts with whsec_.', 'fw' ), 'value' => '' ),
					'payments_stripe_live_secret'  => array( 'type' => 'password', 'label' => __( 'Live secret key', 'fw' ), 'desc' => __( 'Starts with sk_live_.', 'fw' ), 'value' => '' ),
					'payments_stripe_live_webhook' => array( 'type' => 'password', 'label' => __( 'Live signing secret', 'fw' ), 'value' => '' ),
				),
			),
			'group_paypal' => array(
				'type'    => 'group',
				'options' => array(
					'payments_paypal_help'          => array(
						'type'  => 'html-fixed',
						'label' => __( 'PayPal', 'fw' ),
						'html'  => '<p>' . esc_html__( 'Create an app in the PayPal Developer Dashboard (Apps & Credentials) for its Client ID and Secret. Notifications: add a webhook to the app with the events Checkout order approved, Payment capture completed, Payment capture denied and Payment capture refunded, and paste its Webhook ID below. Leave everything empty to use the keys saved in Membership → Payments.', 'fw' ) . '</p><p><code>' . esc_html( function_exists( 'rest_url' ) ? rest_url( 'fw-forms/v1/webhooks/paypal' ) : '' ) . '</code></p>',
					),
					'payments_paypal_mode'          => array(
						'type'    => 'select',
						'label'   => __( 'PayPal mode', 'fw' ),
						'value'   => '',
						'choices' => array( '' => __( 'As in Membership, or Sandbox', 'fw' ), 'sandbox' => __( 'Sandbox (no real money)', 'fw' ), 'live' => __( 'Live', 'fw' ) ),
					),
					'payments_paypal_client_id'     => array( 'type' => 'text', 'label' => __( 'Client ID', 'fw' ), 'value' => '' ),
					'payments_paypal_client_secret' => array( 'type' => 'password', 'label' => __( 'Secret', 'fw' ), 'value' => '' ),
					'payments_paypal_webhook_id'    => array( 'type' => 'text', 'label' => __( 'Webhook ID', 'fw' ), 'value' => '' ),
				),
			),
		),
	),
	'sheets_box' => array(
		'title'   => __( 'Google Sheets', 'fw' ),
		'type'    => 'box',
		'options' => array(
			'group_sheets' => array(
				'type'    => 'group',
				'options' => array(
					'sheets_service_account' => array(
						'type'  => 'textarea',
						'label' => __( 'Service account key (JSON)', 'fw' ),
						'desc'  => __( 'For the "Send to a spreadsheet" action. In Google Cloud: create a project, turn on the Google Sheets API, create a service account and a JSON key for it, and paste the whole key here. Then share each spreadsheet with the account\'s email (client_email in the key) as an editor. Keep the key private.', 'fw' ),
						'value' => '',
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
						'desc'         => __( 'Keep every form submission as an entry you can search, read and export under Unyson+ → Forms → Entries. Emails are still sent as before; this is in addition, not instead — and an entry is stored even if the email fails to send.', 'fw' ),
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
					'analytics' => array(
						'type'         => 'switch',
						'label'        => __( 'Count views, starts and abandons', 'fw' ),
						'desc'         => __( 'For Form Results: how many saw each form, started it, sent it, and the field where people gave up. Counts only, with no cookies and nothing typed; logged-in editors are not counted.', 'fw' ),
						'value'        => 'yes',
						'left-choice'  => array( 'value' => 'no', 'label' => __( 'No', 'fw' ) ),
						'right-choice' => array( 'value' => 'yes', 'label' => __( 'Yes', 'fw' ) ),
					),
					'entries_digest' => array(
						'type'    => 'select',
						'label'   => __( 'Email a spreadsheet of new entries', 'fw' ),
						'desc'    => __( 'A CSV of the entries that came in since the last one, at about 7:00 (weekly: on Mondays). Nothing is sent when there are none.', 'fw' ),
						'value'   => 'off',
						'choices' => array( 'off' => __( 'Off', 'fw' ), 'daily' => __( 'Every day', 'fw' ), 'weekly' => __( 'Every week', 'fw' ) ),
					),
					'entries_digest_to' => array( 'type' => 'text', 'label' => __( 'Send it to', 'fw' ), 'desc' => __( 'Email addresses, separated by commas. Empty = the site admin email.', 'fw' ), 'value' => '' ),
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
