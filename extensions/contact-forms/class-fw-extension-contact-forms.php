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

		return ! empty( $form['subject_message'] ) ? (string) $form['subject_message'] : $title;
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

		$to = implode( ',', $to );

		$entry_data = array(
			'form_values'       => $form_values,
			'shortcode_to_item' => $data['shortcode_to_item'] ?? array(),
			/**
			 * Filters the CC recipient map used when a contact-form submission email is sent.
			 *
			 * @since 2.0.30
			 */
			'cc'  => apply_filters('fw:ext:contact-forms:email:cc',  array( /* 'john@smith.com' => 'John Smith' */ )),
			/**
			 * Filters the BCC recipient map used when a contact-form submission email is sent.
			 *
			 * @since 2.0.30
			 */
			'bcc' => apply_filters('fw:ext:contact-forms:email:bcc', array( /* 'john@smith.com' => 'John Smith' */ )),
			// Absolute paths of uploaded files (File Upload fields) → mailer attaches them.
			'attachments' => $data['attachments'] ?? array(),
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

		if ( false !== strpos( $subject, '{{' ) && function_exists( 'fw_dynamic_content' ) ) {
			$post_id = class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::current_post_id() : (int) get_queried_object_id();
			$subject = html_entity_decode( fw_dynamic_content()->resolve( $subject, array( 'post_id' => $post_id ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		}

		$result = fw_ext_mailer_send_mail(
			$to,
			$subject,
			$this->render_view( 'email', $entry_data ),
			$entry_data
		);

		if ( ! empty( $result['status'] ) ) {
			/** Fires after a contact-form email is sent successfully, passing the entry data so listeners can log or react to the submission. */
			do_action( 'fw:ext:contact-forms:sent', $entry_data );

			FW_Flash_Messages::add(
				$flash_id,
				fw_akg( 'success_message', $form, __( 'Message sent!', 'fw' ) ),
				'success'
			);
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

			FW_Flash_Messages::add(
				$flash_id,
				fw_akg( 'success_message', $form, __( 'Message sent!', 'fw' ) ),
				'success'
			);
		} else {
			FW_Flash_Messages::add(
				$flash_id,
				fw_akg( 'failure_message', $form, __( 'Oops something went wrong.', 'fw' ) ) . ' ' . ( $result['message'] ?? '' ),
				'error'
			);
		}
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
