<?php if ( ! defined( 'FW' ) ) die( 'Forbidden' );

class FW_Extension_Contact_Forms extends FW_Extension_Forms_Form {

	public function _init() {
		add_filter( 'fw_ext_forms_entry_form_title', array( $this, '_filter_entry_form_title' ), 10, 2 );
		add_filter( 'fw_ext_forms_form_settings', array( $this, '_filter_form_settings' ), 10, 2 );
	}

	/**
	 * @internal
	 * Actions read their per-form options from the element's saved atts, which
	 * this form type keeps in its own option row. Only we know that.
	 */
	public function _filter_form_settings( $settings, $data ) {
		if ( ( $data['type'] ?? '' ) !== $this->get_name() ) {
			return $settings;
		}

		$form = $this->get_form_db_data( (string) ( $data['id'] ?? '' ) );

		return is_array( $form ) ? $form : $settings;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_form_builder_type() {
		return 'form-builder';
	}

	/**
	 * @internal
	 * The Entries screen asks each form type for a human title; ours is the
	 * email subject, the closest thing a contact form has to a name.
	 */
	public function _filter_entry_form_title( $title, $data ) {
		if ( ( $data['type'] ?? '' ) !== $this->get_name() ) {
			return $title;
		}

		$form = $this->get_form_db_data( (string) ( $data['id'] ?? '' ) );

		if ( empty( $form['subject_message'] ) ) {
			return $title;
		}

		// The title names the FORM, so it stays the same for every entry: answer tags and Dynamic
		// Content tokens in the subject are dropped rather than filled in.
		$name = trim( preg_replace( array( '/\{\{[^}]*\}\}/', '/\{[a-zA-Z0-9_\-]+\}/', '/\s{2,}/' ), array( '', '', ' ' ), (string) $form['subject_message'] ) );

		return '' !== $name ? $name : $title;
	}

	public function get_form_builder_value( $form_id ) {
		$form = $this->get_form_db_data( $form_id );

		if ( ! is_array( $form ) || empty( $form['form'] ) ) {
			return array();
		}

		return $form['form'];
	}

	/**
	 * @param $form_id
	 * @param $data
	 * * id - Form id
	 * * form - Builder value
	 * * email_to - Destination email
	 * * [subject_message]
	 * * [success_message]
	 * * [failure_message]
	 *
	 * @return bool
	 * @internal
	 */
	public function _set_form_db_data($form_id, $data) {
		if (!class_exists('_FW_Ext_Contact_Form_DB_Data')) {
			require_once dirname(__FILE__) .'/includes/helper/class--fw-ext-contact-form-db-data.php';
		}

		return _FW_Ext_Contact_Form_DB_Data::set($form_id, $data);
	}

	private function get_form_db_data($form_id) {
		if (!class_exists('_FW_Ext_Contact_Form_DB_Data')) {
			require_once dirname(__FILE__) .'/includes/helper/class--fw-ext-contact-form-db-data.php';
		}

		return _FW_Ext_Contact_Form_DB_Data::get($form_id);
	}

	/**
	 * @param array $data
	 * * id   - form id
	 * * form - builder value
	 * * [submit_button_text]
	 * @param array $view_data
	 * @return string
	 */
	public function render( $data, $view_data = array() ) {
		$form = $data['form'] ?? array();

		if ( empty( $form ) ) {
			return '';
		}

		$form_id = $data['id'] ?? '';
		$submit_button_text = empty( $data['submit_button_text'] )
			? __( 'Submit', 'fw' )
			: $data['submit_button_text'];

		/**
		 * @var FW_Extension_Forms $forms_extension
		 */
		$forms_extension = fw_ext( 'forms' );

		return $this->render_view(
			'form',
			array(
				'form_id'   => $form_id,
				'form_html' => $forms_extension->render_form(
					$form_id,
					$form,
					$this->get_name(),
					$this->render_view(
						'submit',
						array(
							'submit_button_text' => $submit_button_text,
							'form_id' => $form_id,
							'extra_data' => $view_data,
						)
					)
				),
				'extra_data' => $view_data,
			)
		);
	}

	public function process_form( $form_values, $data ) {
		$flash_id = 'fw_ext_contact_form_process';

		if ( empty( $form_values ) ) {
			FW_Flash_Messages::add(
				$flash_id,
				__( 'Unable to process the form', 'fw' ),
				'error'
			);

			return;
		}

		$form_id = FW_Request::POST( 'fw_ext_forms_form_id' );

		if ( empty( $form_id ) ) {
			FW_Flash_Messages::add(
				$flash_id,
				__( 'Unable to process the form', 'fw' ),
				'error'
			);

			return;
		}

		$form = $this->get_form_db_data( $form_id );

		// No recipient set: send it to the site's admin address rather than failing the visitor.
		if ( is_array( $form ) && empty( $form['email_to'] ) ) {
			$form['email_to'] = (string) get_option( 'admin_email' );
		}

		if ( empty( $form ) || empty( $form['email_to'] ) ) {
			FW_Flash_Messages::add(
				$flash_id,
				__( 'Unable to process the form', 'fw' ),
				'error'
			);

			return;
		}

		$to = array();

		foreach ( array_map( 'trim', explode( ',', (string) $form['email_to'] ) ) as $to_email ) {
			if ( filter_var( $to_email, FILTER_VALIDATE_EMAIL ) ) {
				$to[] = $to_email;
			} else {
				FW_Flash_Messages::add(
					$flash_id,
					__( 'Invalid destination email (please contact the site administrator)', 'fw' ),
					'error'
				);

				return;
			}
		}

		$items_map = (array) ( $data['shortcode_to_item'] ?? array() );

		// Settings → Notifications: send it to someone else (or someone more) by answer.
		if ( class_exists( 'FW_Forms_Notifications' ) ) {
			$to = FW_Forms_Notifications::route( $form, $to, (array) $form_values, $items_map );
		}

		$to = implode( ',', $to );

		$entry_data = array(
			'form_values'       => $form_values,
			'shortcode_to_item' => $data['shortcode_to_item'] ?? array(),
			/**
			 * Filters the CC recipient map used when a contact-form submission email is sent.
			 *
			 * @since 2.0.30
			 */
			'cc'  => apply_filters('fw:ext:contact-forms:email:cc',  self::address_map( $form['email_cc'] ?? '' )),
			/**
			 * Filters the BCC recipient map used when a contact-form submission email is sent.
			 *
			 * @since 2.0.30
			 */
			'bcc' => apply_filters('fw:ext:contact-forms:email:bcc', self::address_map( $form['email_bcc'] ?? '' )),
			// Absolute paths of uploaded files (File Upload fields) → mailer attaches them.
			/**
			 * Filters the notification email's attachments (absolute paths). Uploaded files are there
			 * already; a PDF copy of the answers is added here when the form asks for one.
			 *
			 * @param string[] $attachments
			 * @param array    $form The form's settings.
			 * @param array    $data { form_values, shortcode_to_item, uploads }
			 */
			'attachments' => (array) apply_filters( 'fw_ext_forms_notification_attachments', $data['attachments'] ?? array(), $form, array(
				'form_values'       => $form_values,
				'shortcode_to_item' => $data['shortcode_to_item'] ?? array(),
				'uploads'           => $data['uploads'] ?? array(),
			) ),
		);

		/**
		 * Use the first email field as Reply-To header
		 */
		foreach ( $entry_data['shortcode_to_item'] as $item ) {
			if (
				isset( $item['type'], $item['options']['required'], $item['shortcode'] )
				&& $item['type'] === 'email'
				&& $item['options']['required']
				&& isset( $entry_data['form_values'][ $item['shortcode'] ] )
			) {
				$entry_data['reply_to'] = $entry_data['form_values'][ $item['shortcode'] ];
				break;
			}
		}

		// Dynamic Content tokens in the subject ({{post_title}}, …) refer to the page the form was sent from.
		$subject = (string) fw_akg( 'subject_message', $form, '' );
		$post_id = class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::current_post_id() : (int) get_queried_object_id();

		if ( false !== strpos( $subject, '{{' ) && function_exists( 'fw_dynamic_content' ) ) {
			$subject = html_entity_decode( fw_dynamic_content()->resolve( $subject, array( 'post_id' => $post_id ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		// Answer tags ({text_name}, {all_fields}, …).
		$tag = function ( $text, $mode ) use ( $form_values, $entry_data, $post_id ) {
			return class_exists( 'FW_Forms_Merge_Tags' )
				? FW_Forms_Merge_Tags::replace( $text, (array) $form_values, (array) $entry_data['shortcode_to_item'], $mode, $post_id )
				: $text;
		};
		$subject = $tag( $subject, 'text' );
		$success = $tag( (string) fw_akg( 'success_message', $form, __( 'Message sent!', 'fw' ) ), 'html' );

		// Settings → Notifications: a different "after sending" when an answer matches.
		$routed = class_exists( 'FW_Forms_Notifications' ) ? FW_Forms_Notifications::confirmation( $form, (array) $form_values, $items_map ) : null;
		if ( $routed ) {
			if ( 'url' === $routed['type'] && '' !== trim( $routed['url'] ) ) {
				$form['confirmation_type'] = 'url';
				$form['confirmation_url']  = $routed['url'];
			} elseif ( '' !== trim( $routed['message'] ) ) {
				$form['confirmation_type'] = 'message';
				$success                   = $tag( $routed['message'], 'html' );
			}
		}

		// Judged spam: the sender sees the usual success (a refusal would teach a bot what to change),
		// nothing is emailed, and the entry is kept as Spam for review.
		if ( class_exists( 'FW_Forms_Spam_Filter' ) && FW_Forms_Spam_Filter::is_spam( $form_id ) ) {
			FW_Flash_Messages::add( $flash_id, $success, 'success' );

			return self::confirmation( $form, $tag );
		}

		// Settings → Notifications → More notifications: their own recipients and wording, sent even
		// when the main notification is off.
		if ( class_exists( 'FW_Forms_Notifications' ) ) {
			FW_Forms_Notifications::send_extra( $form, (array) $form_values, $items_map, $entry_data, $post_id );
		}

		// Settings → Options → "Send the notification email" off: the form only stores the entry and
		// runs its actions (e.g. a booking action that sends its own alert), so nothing is mailed here.
		if ( 'no' === ( $form['email_notify'] ?? 'yes' ) ) {
			FW_Flash_Messages::add( $flash_id, $success, 'success' );

			return self::confirmation( $form, $tag );
		}

		// Settings → Texts: a sender name and a written message body, both with answer tags.
		$from_name = trim( wp_strip_all_tags( $tag( (string) ( $form['email_from_name'] ?? '' ), 'text' ) ) );
		if ( '' !== $from_name ) {
			$entry_data['from_name'] = str_replace( array( "\r", "\n", '<', '>', '"' ), '', $from_name );
		}
		$body_text = trim( (string) ( $form['email_body'] ?? '' ) );
		$body      = '' !== $body_text
			? wpautop( $tag( esc_html( $body_text ), 'html' ) )
			: $this->render_view( 'email', $entry_data );

		$result = fw_ext_mailer_send_mail(
			$to,
			$subject,
			$body,
			$entry_data
		);

		if ( ! empty( $result['status'] ) ) {
			/** Fires after a contact-form email is sent successfully, passing the entry data so listeners can log or react to the submission. */
			do_action( 'fw:ext:contact-forms:sent', $entry_data );

			FW_Flash_Messages::add( $flash_id, $success, 'success' );

			return self::confirmation( $form, $tag );
		} elseif ( $this->entry_will_be_stored( $form_id ) ) {
			// The submission is kept as an entry, so the visitor's message did arrive: telling them
			// it failed would only make them send it again. The site owner sees the failure on the
			// entry instead.
			$mail_error = (string) ( $result['message'] ?? '' );

			add_filter( 'fw_ext_forms_entry_before_insert', function ( $entry ) use ( $mail_error ) {
				if ( is_array( $entry ) && isset( $entry['fields'] ) && is_array( $entry['fields'] ) ) {
					$entry['fields'][] = array(
						'id'    => '_notification',
						'type'  => 'notice',
						'label' => __( 'Notification email', 'fw' ),
						'value' => trim( __( 'Not sent.', 'fw' ) . ' ' . $mail_error ),
					);
				}

				return $entry;
			} );

			/** Fires when a contact-form notification email fails but the submission is stored as an entry. */
			do_action( 'fw:ext:contact-forms:mail-failed', $entry_data, $result );

			FW_Flash_Messages::add( $flash_id, $success, 'success' );

			return self::confirmation( $form, $tag );
		} else {
			FW_Flash_Messages::add(
				$flash_id,
				fw_akg( 'failure_message', $form, __( 'Oops something went wrong.', 'fw' ) ) . ' ' . ( $result['message'] ?? '' ),
				'error'
			);
		}
	}

	/**
	 * Where to go after a successful submission ("After Sending"). Returns the form save data:
	 * a `redirect` for a thank-you page or address (followed by the reload and, as JSON, by the
	 * background sender), nothing for the on-page message.
	 *
	 * @param array    $form The form's saved settings.
	 * @param callable $tag  Answer-tag replacer ( $text, $mode ).
	 *
	 * @return array|null
	 */
	private static function confirmation( array $form, $tag ) {
		$type = isset( $form['confirmation_type'] ) ? (string) $form['confirmation_type'] : 'message';
		$url  = '';

		if ( 'page' === $type && ! empty( $form['confirmation_page'] ) ) {
			$url = (string) get_permalink( (int) $form['confirmation_page'] );
		} elseif ( 'url' === $type && ! empty( $form['confirmation_url'] ) ) {
			$url = trim( (string) call_user_func( $tag, (string) $form['confirmation_url'], 'url' ) );
			if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) {
				$url = home_url( $url );
			}
			$url = wp_validate_redirect( esc_url_raw( $url ), '' );
		}

		/**
		 * Filters where the visitor goes after a successful submission ('' = stay on the page and
		 * show the message). The quiz uses it to send each score range to its own page.
		 *
		 * @param string $url
		 * @param array  $form The form's settings.
		 */
		$url = (string) apply_filters( 'fw_ext_forms_confirmation_url', $url, $form );
		if ( '' !== $url ) {
			$url = wp_validate_redirect( esc_url_raw( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ? home_url( $url ) : $url ), '' );
		}

		return $url !== '' ? array( 'redirect' => $url ) : null;
	}

	/**
	 * "a@x.com, b@y.com" → [ 'a@x.com' => '', 'b@y.com' => '' ] (the shape the mailer's cc / bcc take).
	 *
	 * @param string $list
	 *
	 * @return array
	 */
	private static function address_map( $list ) {
		$out = array();
		foreach ( array_map( 'trim', explode( ',', (string) $list ) ) as $email ) {
			if ( is_email( $email ) ) {
				$out[ $email ] = '';
			}
		}

		return $out;
	}

	/**
	 * Whether this submission will be kept under Form Entries (the global setting and the form's
	 * own Store entry action), which runs after the email on the submit hook.
	 *
	 * @param string $form_id
	 *
	 * @return bool
	 */
	private function entry_will_be_stored( $form_id ) {
		if ( ! class_exists( 'FW_Forms_Entries_Capture' ) || ! FW_Forms_Entries_Capture::enabled() ) {
			return false;
		}

		if ( class_exists( 'FW_Forms_Actions' ) ) {
			$actions = FW_Forms_Actions::all();
			$form    = FW_Forms_Actions::form_settings( array( 'id' => $form_id, 'type' => $this->get_name() ) );

			if ( isset( $actions['store'] ) && ! $actions['store']->is_enabled( $form ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @internal
	 */
	public function _action_post_form_type_save() {
		if ( ! fw_ext_mailer_is_configured() ) {
			FW_Flash_Messages::add(
				'fw-ext-forms-' . $this->get_form_type() . '-mailer',
				str_replace(
					array(
						'{mailer_link}'
					),
					array(
						// the fw()->extensions->manager->get_extension_link() method is available starting with v2.1.7
						version_compare( fw()->manifest->get_version(), '2.1.7', '>=' )
							? fw_html_tag( 'a',
							array( 'href' => fw()->extensions->manager->get_extension_link( 'forms' ) ),
							__( 'Mailer', 'fw' ) )
							: __( 'Mailer', 'fw' )
					),
					__( 'Please configure the {mailer_link} extension.', 'fw' )
				),
				'error'
			);
		}
	}

	/**
	 * Returns value of the form option
	 *
	 * @param string $id
	 * @param null|string $multikey
	 *
	 * @return mixed|null
	 */
	public function get_option( $id, $multikey = null ) {
		$form = $this->get_form_db_data( $id );

		if ( empty( $form ) || ! is_array( $form ) ) {
			return null;
		}

		if ( is_null( $multikey ) ) {
			return $form;
		}

		return fw_akg( $multikey, $form );
	}
}
