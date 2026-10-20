<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Describe a form, get a form": the form-type picker's free-text box.
 *
 * The description goes to the site's AI provider through WordPress's AI Client
 * (`wp_ai_client_prompt()`, the same client the AI Assistant's panel uses), asking for a small JSON
 * shape — a title, the email subject, button and thank-you text, and a list of fields. Nothing the
 * model returns is trusted as a form: every field is checked against the field types this site has
 * (an unknown type is dropped), rebuilt with the starters' own shorthands and run through each item
 * class (FW_Forms_Starters::normalize), exactly like a starter. A newsletter opt-in is forced
 * optional; actions are never set. The result only fills the builder; the person still saves it.
 *
 * Without a configured AI provider the picker says how to connect one rather than offering a box
 * that cannot work.
 */
class FW_Forms_AI_Generate {

	const AJAX  = 'fw_forms_ai_generate';
	const NONCE = 'fw_forms_ai_generate';

	/** Field types the model may use, and what each needs. */
	const TYPES = array( 'text', 'email', 'textarea', 'number', 'website', 'select', 'radio', 'checkboxes', 'date', 'rating', 'consent', 'file-upload' );

	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX, array( __CLASS__, '_ajax' ) );
	}

	/** Can this site generate a form from a description right now? */
	public static function available() {
		static $ok = null;
		if ( null === $ok ) {
			$ok = false;
			if ( function_exists( 'wp_ai_client_prompt' ) && function_exists( 'wp_supports_ai' ) && wp_supports_ai() ) {
				// Asked on every form-builder load, so remember the answer for a few minutes.
				$cached = get_transient( 'fw_forms_ai_ready' );
				if ( false !== $cached ) {
					$ok = 'yes' === $cached;
				} else {
					try {
						$ok = true === wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation();
					} catch ( \Throwable $e ) {
						$ok = false;
					}
					set_transient( 'fw_forms_ai_ready', $ok ? 'yes' : 'no', 10 * MINUTE_IN_SECONDS );
				}
			}
		}
		/** Whether "describe a form" is offered. */
		return (bool) apply_filters( 'fw_ext_forms_ai_generate_available', $ok );
	}

	/** @internal */
	public static function _ajax() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'fw' ) ), 403 );
		}
		$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
		$description = trim( mb_substr( $description, 0, 1000 ) );
		if ( mb_strlen( $description ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'Describe the form in a sentence or two.', 'fw' ) ) );
		}
		if ( ! self::available() ) {
			wp_send_json_error( array( 'message' => __( 'No AI provider is connected on this site.', 'fw' ) ) );
		}

		$result = self::generate( $description );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * @param string $description
	 * @return array|WP_Error { title, items: [], settings: { subject_message, submit_button_text, success_message } }
	 */
	public static function generate( $description ) {
		$system = "You design web forms. Reply with ONE JSON object and nothing else, no code fences.\n"
			. "Shape: {\"title\": string (the form's heading), \"subject\": string (notification email subject; you may use {text_name} if there is a name field with slug name), "
			. "\"button\": string (submit button text, 1-3 words), \"success\": string (thank-you message, one sentence), "
			. "\"fields\": [ { \"type\": one of " . implode( ', ', self::TYPES ) . ", \"slug\": short lowercase id like name or phone, "
			. "\"label\": string, \"required\": boolean, \"width\": \"1_1\" or \"1_2\", \"choices\": [strings] (only for select, radio, checkboxes), "
			. "\"mode\": \"date\"|\"time\"|\"datetime\" (only for date), \"scale\": \"stars5\"|\"nps\"|\"scale10\" (only for rating), "
			. "\"purpose\": \"terms\"|\"newsletter\" (only for consent) } ] }.\n"
			. "Rules: 3 to 14 fields; include an email field when the form needs a reply; pair short fields at width 1_2; "
			. "use a consent field with purpose terms for a privacy agreement, and only add a newsletter consent if asked; "
			. "never ask for passwords, card numbers or health details; use plain, friendly labels in the language of the description.";

		try {
			$text = wp_ai_client_prompt( $description )
				->using_system_instruction( $system )
				->using_max_tokens( 1500 )
				->generate_text();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'fw_forms_ai', __( 'The AI provider could not be reached. Try again, or start from a form type.', 'fw' ) );
		}
		if ( is_wp_error( $text ) ) {
			return new WP_Error( 'fw_forms_ai', __( 'The AI provider could not answer right now. Try again, or start from a form type.', 'fw' ) );
		}

		return self::shape( (string) $text );
	}

	/**
	 * The model's reply → a checked, normalised form. Public for tests.
	 *
	 * @param string $text
	 * @return array|WP_Error
	 */
	public static function shape( $text ) {
		$text = trim( preg_replace( '/^```(?:json)?|```$/m', '', trim( (string) $text ) ) );
		$a    = strpos( $text, '{' );
		$b    = strrpos( $text, '}' );
		$data = ( false !== $a && false !== $b ) ? json_decode( substr( $text, $a, $b - $a + 1 ), true ) : null;
		if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			return new WP_Error( 'fw_forms_ai_shape', __( 'The AI reply was not a form. Try describing it differently.', 'fw' ) );
		}

		$clean = function ( $v, $max = 160 ) {
			return trim( mb_substr( sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ), 0, $max ) );
		};

		$title = $clean( $data['title'] ?? '', 120 );
		$items = array(
			array( 'type' => 'form-header-title', 'shortcode' => 'form_header_title', 'width' => '', 'options' => array( 'title' => '' !== $title ? $title : __( 'Get in touch', 'fw' ), 'subtitle' => '' ) ),
		);
		$used  = array();

		foreach ( array_slice( $data['fields'], 0, 20 ) as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$type = isset( $f['type'] ) ? (string) $f['type'] : '';
			if ( ! in_array( $type, self::TYPES, true ) ) {
				continue;
			}
			$label = $clean( $f['label'] ?? '' );
			if ( '' === $label ) {
				continue;
			}
			$slug = sanitize_key( str_replace( '-', '_', (string) ( $f['slug'] ?? '' ) ) );
			$slug = '' !== $slug ? $slug : sanitize_key( substr( preg_replace( '/[^a-z0-9]+/', '_', strtolower( $label ) ), 0, 24 ) );
			$code = 'email' === $type && ! isset( $used['email_1'] ) ? 'email_1' : $type . '_' . ( '' !== $slug ? $slug : 'field' );
			$base = $code;
			$n    = 2;
			while ( isset( $used[ $code ] ) ) {
				$code = $base . '_' . $n++;
			}
			$used[ $code ] = true;

			$o = array( 'label' => $label, 'required' => ! empty( $f['required'] ) );
			if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
				$choices = array_values( array_filter( array_map( function ( $c ) use ( $clean ) {
					return $clean( $c, 80 );
				}, (array) ( $f['choices'] ?? array() ) ), 'strlen' ) );
				if ( count( $choices ) < 2 ) {
					continue; // A choice field with fewer than two choices is not a question.
				}
				$o['choices'] = array_slice( $choices, 0, 12 );
			}
			if ( 'date' === $type ) {
				$o['mode']          = in_array( $f['mode'] ?? '', array( 'date', 'time', 'datetime' ), true ) ? $f['mode'] : 'date';
				$o['min']           = '';
				$o['max']           = '';
				$o['blackout_days'] = array();
			}
			if ( 'rating' === $type ) {
				$o['scale']      = in_array( $f['scale'] ?? '', array( 'stars5', 'nps', 'scale10' ), true ) ? $f['scale'] : 'stars5';
				$o['low_label']  = '';
				$o['high_label'] = '';
			}
			if ( 'consent' === $type ) {
				$o['purpose'] = in_array( $f['purpose'] ?? '', array( 'terms', 'newsletter', 'other' ), true ) ? $f['purpose'] : 'terms';
				if ( 'newsletter' === $o['purpose'] ) {
					$o['required'] = false; // An opt-in is a free choice; the item class enforces it too.
				}
			}

			$width   = in_array( $f['width'] ?? '', array( '1_1', '1_2' ), true ) ? $f['width'] : '1_1';
			$width   = in_array( $type, array( 'textarea', 'radio', 'checkboxes', 'consent', 'rating', 'file-upload' ), true ) ? '1_1' : $width;
			$items[] = array( 'type' => $type, 'shortcode' => $code, 'width' => $width, 'options' => $o );
		}

		if ( count( $items ) < 2 ) {
			return new WP_Error( 'fw_forms_ai_shape', __( 'The AI reply had no usable fields. Try describing it differently.', 'fw' ) );
		}

		$items = class_exists( 'FW_Forms_Starters' ) ? FW_Forms_Starters::normalize( $items ) : $items;

		return array(
			'title'    => $title,
			'items'    => $items,
			'settings' => array(
				'subject_message'    => $clean( $data['subject'] ?? '', 140 ),
				'submit_button_text' => $clean( $data['button'] ?? '', 40 ),
				'success_message'    => $clean( $data['success'] ?? '', 240 ),
			),
		);
	}
}
