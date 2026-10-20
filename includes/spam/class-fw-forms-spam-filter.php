<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Content spam filtering for form submissions.
 *
 * A submission that passed every field check is tested against the site's own rules (blocked
 * words, blocked email domains, too many links) and, when the site turns it on and the Akismet
 * plugin is set up, against Akismet. A caught submission is NOT refused: the sender sees the
 * normal success message (a refusal teaches a bot what to change), no notification or
 * confirmation email goes out, no action runs, and the entry is kept with status Spam under
 * Form Entries, where "Not spam" restores it. Nothing is lost to a false positive.
 *
 * The verdict is reached once, at validation (`fw_ext_forms_validate_submission`, before the
 * form type sends its email), and read later in the same request through is_spam().
 */
class FW_Forms_Spam_Filter {

	/** @var array form id => reason, for this request. */
	private static $flagged = array();

	public static function init() {
		add_filter( 'fw_ext_forms_validate_submission', array( __CLASS__, '_filter_validate' ), 5, 2 );
		add_filter( 'fw_ext_forms_entry_before_insert', array( __CLASS__, '_filter_entry' ), 5, 2 );
	}

	/** Was this form's submission, in this request, judged spam? */
	public static function is_spam( $form_id ) {
		return isset( self::$flagged[ (string) $form_id ] );
	}

	/** Why, in words (for the entry). */
	public static function reason( $form_id ) {
		return isset( self::$flagged[ (string) $form_id ] ) ? self::$flagged[ (string) $form_id ] : '';
	}

	private static function setting( $key, $default = '' ) {
		$forms = fw_ext( 'forms' );
		$v     = $forms ? $forms->get_db_settings_option( $key ) : null;
		return ( null === $v || '' === $v ) ? $default : $v;
	}

	private static function lines( $text ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', strtolower( (string) $text ) ) ), 'strlen' ) );
	}

	/**
	 * @internal Decide, never refuse: the errors array goes back unchanged.
	 */
	public static function _filter_validate( $errors, $data ) {
		if ( ! empty( $errors ) || ! is_array( $data ) ) {
			return $errors;
		}
		$values = isset( $data['form_values'] ) && is_array( $data['form_values'] ) ? $data['form_values'] : array();
		$items  = isset( $data['shortcode_to_item'] ) && is_array( $data['shortcode_to_item'] ) ? $data['shortcode_to_item'] : array();
		$fields = class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::fields_from_submission( $values, $items ) : array();
		$reason = self::check( $fields );
		if ( '' !== $reason ) {
			self::$flagged[ (string) ( $data['id'] ?? '' ) ] = $reason;
		}
		return $errors;
	}

	/**
	 * @param array $fields Entry-style rows.
	 * @return string The reason, or '' when it looks fine.
	 */
	public static function check( array $fields ) {
		$text  = '';
		$email = '';
		$name  = '';
		foreach ( $fields as $f ) {
			$v = isset( $f['value'] ) ? $f['value'] : '';
			$v = is_array( $v ) ? implode( ' ', array_map( 'strval', $v ) ) : (string) $v;
			$text .= ' ' . $v;
			if ( '' === $email && 'email' === ( $f['type'] ?? '' ) && is_email( $v ) ) {
				$email = $v;
			}
			if ( '' === $name && 'text' === ( $f['type'] ?? '' ) ) {
				$name = $v;
			}
		}
		$lower = strtolower( $text );

		foreach ( self::lines( self::setting( 'spam_blocked_words' ) ) as $word ) {
			if ( preg_match( '/(^|\W)' . preg_quote( $word, '/' ) . '($|\W)/u', $lower ) ) {
				return sprintf( __( 'Contains the blocked word "%s".', 'fw' ), $word );
			}
		}

		if ( '' !== $email ) {
			$domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );
			foreach ( self::lines( self::setting( 'spam_blocked_domains' ) ) as $d ) {
				$d = ltrim( $d, '@' );
				if ( $domain === $d || ( '' !== $d && substr( $domain, -strlen( '.' . $d ) ) === '.' . $d ) ) {
					return sprintf( __( 'Sent from the blocked email domain "%s".', 'fw' ), $d );
				}
			}
		}

		$max = (string) self::setting( 'spam_max_links', '' );
		if ( '' !== $max && ctype_digit( $max ) ) {
			$links = preg_match_all( '#(https?://|www\.)#i', $text );
			if ( $links > (int) $max ) {
				return sprintf( _n( 'Contains %d link (the limit is set lower).', 'Contains %d links (the limit is set lower).', $links, 'fw' ), $links );
			}
		}

		if ( 'yes' === self::setting( 'spam_akismet', 'no' ) && self::akismet_ready() && self::akismet_says_spam( $name, $email, trim( $text ) ) ) {
			return __( 'Akismet marked it as spam.', 'fw' );
		}

		/**
		 * Last word on a submission. Return a non-empty reason to mark it spam.
		 *
		 * @param string $reason ''.
		 * @param array  $fields Entry-style rows.
		 */
		return (string) apply_filters( 'fw_ext_forms_spam_check', '', $fields );
	}

	/** Is the Akismet plugin active with a key? */
	public static function akismet_ready() {
		return class_exists( 'Akismet' ) && is_callable( array( 'Akismet', 'get_api_key' ) ) && '' !== (string) Akismet::get_api_key();
	}

	private static function akismet_says_spam( $name, $email, $content ) {
		if ( ! is_callable( array( 'Akismet', 'http_post' ) ) ) {
			return false;
		}
		$query = array(
			'blog'                 => home_url(),
			'user_ip'              => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'referrer'             => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'comment_type'         => 'contact-form',
			'comment_author'       => $name,
			'comment_author_email' => $email,
			'comment_content'      => $content,
			'blog_lang'            => get_locale(),
			'blog_charset'         => get_option( 'blog_charset' ),
		);
		$response = Akismet::http_post( build_query( $query ), 'comment-check' );
		// Akismet answers "true" for spam. An unreachable service lets the message through.
		return is_array( $response ) && isset( $response[1] ) && 'true' === trim( (string) $response[1] );
	}

	/** @internal A caught submission is stored as Spam, with the reason. */
	public static function _filter_entry( $entry, $data ) {
		if ( ! is_array( $entry ) || ! is_array( $data ) || ! self::is_spam( $data['id'] ?? '' ) ) {
			return $entry;
		}
		$entry['status'] = 'spam';
		if ( isset( $entry['fields'] ) && is_array( $entry['fields'] ) ) {
			$entry['fields'][] = array(
				'id'    => '_spam',
				'type'  => 'notice',
				'label' => __( 'Marked as spam', 'fw' ),
				'value' => self::reason( $data['id'] ?? '' ),
			);
		}
		return $entry;
	}
}
