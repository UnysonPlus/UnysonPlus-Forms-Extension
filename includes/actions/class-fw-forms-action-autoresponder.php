<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Email the sender" — a confirmation email to the person who sent the form.
 *
 * Off by default, and by default it carries only the site owner's own message, not the
 * visitor's answers. A form will email whatever address is typed into it, so a copy of the
 * answers would let anyone send their own text to a stranger "from" the site; including them
 * is a separate, explicit switch, and is safest with a captcha on the form. Answer tags work in
 * the subject and the message ({text_name}, {all_fields}, {site_name}…).
 */
class FW_Forms_Action_Autoresponder extends FW_Forms_Action {

	public function get_id() {
		return 'autoresponder';
	}

	public function get_title() {
		return __( 'Email the sender', 'fw' );
	}

	public function get_options() {
		return array(
			'action_autoresponder'         => $this->switch_option(
				__( 'Send a confirmation email to the person who sent the form', 'fw' ),
				__( 'Sent to the address in the form\'s first email field.', 'fw' )
			),
			'action_autoresponder_subject' => array(
				'type'  => 'text',
				'label' => __( 'Subject', 'fw' ),
				'value' => __( 'Thanks for getting in touch', 'fw' ),
				'desc'  => __( 'Answer tags work here, e.g. {site_name}.', 'fw' ),
			),
			'action_autoresponder_message' => array(
				'type'  => 'textarea',
				'label' => __( 'Message', 'fw' ),
				'value' => __( "Thank you for your message. We have received it and will reply as soon as we can.\n\n{site_name}", 'fw' ),
				'desc'  => __( 'Answer tags work here: {field_name} inserts an answer (the field name shown in the form builder, e.g. {text_name}); {all_fields}, {site_name}, {page_title}, {page_url} and {date} are available too.', 'fw' ),
			),
			'action_autoresponder_answers' => $this->switch_option(
				__( 'Add a copy of their answers', 'fw' ),
				__( 'Appends everything they sent. Because a form emails whatever address is typed into it, turn this on only with a captcha on the form, so it cannot be used to send text to strangers.', 'fw' )
			),
		);
	}

	/**
	 * The answers a greeting may use without the "copy of their answers" switch: name fields only,
	 * at most 40 characters, with anything that looks like a link or an address removed.
	 *
	 * @return array shortcode => value
	 */
	private static function greeting_values( array $values, array $items ) {
		$out = array();
		foreach ( $values as $key => $value ) {
			$item = isset( $items[ $key ] ) ? $items[ $key ] : array();
			$type = $item['type'] ?? '';
			if ( 'name' === $type ) {
				$value = is_array( $value ) ? trim( ( $value['first'] ?? '' ) . ' ' . ( $value['last'] ?? '' ) ) : $value;
			} elseif ( 'text' !== $type || ! class_exists( 'FW_Forms_Autocomplete' ) ) {
				continue;
			} else {
				$hint = FW_Forms_Autocomplete::for_label( $item['options']['label'] ?? '' );
				if ( ! in_array( $hint['autocomplete'] ?? '', array( 'name', 'given-name', 'family-name' ), true ) ) {
					continue;
				}
			}
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$v = preg_replace( '#\S*(https?://|www\.|@|\.[a-z]{2,}\b)\S*#i', '', wp_strip_all_tags( (string) $value ) );
			$out[ $key ] = trim( mb_substr( preg_replace( '/\s+/', ' ', $v ), 0, 40 ) );
		}
		return $out;
	}

	public function run( array $ctx ) {
		$to = isset( $ctx['email'] ) ? sanitize_email( (string) $ctx['email'] ) : '';
		if ( ! is_email( $to ) ) {
			return; // No email field filled in: nothing to confirm to.
		}
		if ( ! function_exists( 'fw_ext_mailer_send_mail' ) ) {
			return new WP_Error( 'fw_forms_autoresponder_mailer', __( 'The Mailer extension is not active.', 'fw' ) );
		}

		$form    = isset( $ctx['form'] ) && is_array( $ctx['form'] ) ? $ctx['form'] : array();
		$payload = isset( $ctx['payload'] ) && is_array( $ctx['payload'] ) ? $ctx['payload'] : array();
		$values  = isset( $payload['form_values'] ) && is_array( $payload['form_values'] ) ? $payload['form_values'] : array();
		$items   = isset( $payload['shortcode_to_item'] ) && is_array( $payload['shortcode_to_item'] ) ? $payload['shortcode_to_item'] : array();
		$post_id = class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::current_post_id() : 0;
		$answers = 'yes' === self::opt( $form, 'action_autoresponder_answers', 'no' );

		// Without the explicit switch, the visitor's text reaches the email only through a NAME field
		// (for "Hi {text_name}"), cut short and with anything link-like removed, so the form cannot be
		// used to send a message of someone else's choosing to a stranger.
		$tag_values = $answers ? $values : self::greeting_values( $values, $items );

		$subject  = (string) self::opt( $form, 'action_autoresponder_subject', __( 'Thanks for getting in touch', 'fw' ) );
		$template = (string) self::opt( $form, 'action_autoresponder_message', '' );
		// The builder may hand the message over as paragraphs of HTML; treat it as HTML then.
		$is_html  = $template !== wp_strip_all_tags( $template );
		$mode     = $is_html ? 'html' : 'text';
		$tags     = class_exists( 'FW_Forms_Merge_Tags' );

		$subject = $tags ? FW_Forms_Merge_Tags::replace( $subject, $tag_values, $items, 'text', $post_id ) : $subject;
		$message = $tags ? FW_Forms_Merge_Tags::replace( $template, $tag_values, $items, $mode, $post_id ) : $template;

		if ( $answers && $tags && false === strpos( $template, '{all_fields}' ) ) {
			$copy = FW_Forms_Merge_Tags::replace( '{all_fields}', $values, $items, $mode, $post_id );
			if ( '' !== trim( wp_strip_all_tags( $copy ) ) ) {
				$label    = __( 'What you sent:', 'fw' );
				$message .= $is_html ? '<p><strong>' . esc_html( $label ) . '</strong><br>' . $copy . '</p>' : "\n\n" . $label . "\n" . $copy;
			}
		}

		// Unreplaced tags (answers withheld, or a typo) read as noise in an email; drop them.
		$message = trim( preg_replace( '/\{[a-zA-Z0-9_\-]+\}/', '', $message ) );
		$subject = trim( preg_replace( '/\{[a-zA-Z0-9_\-]+\}/', '', $subject ) );
		$body    = $is_html ? wp_kses_post( $message ) : wpautop( esc_html( $message ) );

		// Replies go to the form's own recipient, not to a no-reply void.
		$reply = '';
		foreach ( array_map( 'trim', explode( ',', (string) ( $form['email_to'] ?? '' ) ) ) as $e ) {
			if ( is_email( $e ) ) {
				$reply = $e;
				break;
			}
		}
		if ( '' === $reply ) {
			$reply = (string) get_option( 'admin_email' );
		}

		/**
		 * Filters the confirmation email's attachments (absolute paths), e.g. a PDF copy.
		 *
		 * @param string[] $attachments
		 * @param array    $ctx The action context.
		 */
		$attachments = (array) apply_filters( 'fw_ext_forms_autoresponder_attachments', array(), $ctx );

		$result = fw_ext_mailer_send_mail(
			$to,
			wp_strip_all_tags( $subject ),
			$body,
			array( 'reply_to' => $reply, 'attachments' => $attachments )
		);

		if ( empty( $result['status'] ) ) {
			return new WP_Error( 'fw_forms_autoresponder_failed', (string) ( $result['message'] ?? __( 'The confirmation email could not be sent.', 'fw' ) ) );
		}
	}
}
