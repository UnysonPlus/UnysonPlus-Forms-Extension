<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Notifications routed by answer (contact form Settings → Notifications).
 *
 *   email_routes         "When [field] [is …] [value], send it to [addresses]" — instead of the main
 *                        recipient (the first such rule that matches wins) or as well as it (every match adds).
 *   extra_notifications  more emails, each with its own recipients, subject and message (answer tags work),
 *                        optionally only when an answer matches. Sent whenever the submission is not spam,
 *                        even with the main notification switched off.
 *   confirmation_routes  a different "after sending" when an answer matches: its own message, a page, or an
 *                        address. The first match wins over Settings → Options.
 *
 * Every rule names its field by LABEL and compares like conditional fields do (FW_Forms_Conditions):
 * trimmed, tag-stripped, case-insensitive; is / is not / contains / is empty / is not empty.
 */
class FW_Forms_Notifications {

	const KEYS = array( 'email_routes', 'extra_notifications', 'confirmation_routes' );

	private static function rule_options() {
		return array(
			'field' => array( 'type' => 'text', 'label' => __( 'When the field', 'fw' ), 'desc' => __( 'Its label, exactly as written, e.g. "Department".', 'fw' ), 'value' => '' ),
			'op'    => array(
				'type'    => 'select',
				'label'   => __( 'Condition', 'fw' ),
				'value'   => 'is',
				'choices' => array( 'is' => __( 'is', 'fw' ), 'is_not' => __( 'is not', 'fw' ), 'contains' => __( 'contains', 'fw' ), 'empty' => __( 'is empty', 'fw' ), 'not_empty' => __( 'is not empty', 'fw' ) ),
			),
			'value' => array( 'type' => 'text', 'label' => __( 'Value', 'fw' ), 'value' => '' ),
		);
	}

	public static function options() {
		return array(
			'type'    => 'group',
			'options' => array(
				'email_routes'        => array(
					'type'            => 'addable-box',
					'label'           => __( 'Send it to someone else, by answer', 'fw' ),
					'desc'            => __( 'For example: when "Department" is "Sales", send it to sales@…. "Instead" replaces the main recipient (the first match wins); "As well" adds them.', 'fw' ),
					'box-options'     => self::rule_options() + array(
						'to'   => array( 'type' => 'text', 'label' => __( 'Send it to', 'fw' ), 'desc' => __( 'Email addresses, separated by commas.', 'fw' ), 'value' => '' ),
						'mode' => array( 'type' => 'select', 'label' => __( 'Main recipient', 'fw' ), 'value' => 'replace', 'choices' => array( 'replace' => __( 'Instead of the main recipient', 'fw' ), 'add' => __( 'As well as the main recipient', 'fw' ) ) ),
					),
					'template'        => '{{- field }} {{- op }} {{- value }} → {{- to }}',
					'add-button-text' => __( 'Add a rule', 'fw' ),
					'value'           => array(),
				),
				'extra_notifications' => array(
					'type'            => 'addable-box',
					'label'           => __( 'More notifications', 'fw' ),
					'desc'            => __( 'Extra emails with their own recipients and wording, e.g. a short summary for a manager. Answer tags work: {all_fields}, {text_name}…. Leave "When the field" empty to always send it.', 'fw' ),
					'box-options'     => array(
						'title'   => array( 'type' => 'text', 'label' => __( 'Name (for you)', 'fw' ), 'value' => '' ),
						'to'      => array( 'type' => 'text', 'label' => __( 'Send it to', 'fw' ), 'desc' => __( 'Email addresses, separated by commas. An answer tag works too, e.g. {email_1}.', 'fw' ), 'value' => '' ),
						'subject' => array( 'type' => 'text', 'label' => __( 'Subject', 'fw' ), 'value' => '' ),
						'message' => array( 'type' => 'textarea', 'label' => __( 'Message', 'fw' ), 'desc' => __( 'Empty = all the answers.', 'fw' ), 'value' => '' ),
					) + self::rule_options(),
					'template'        => '{{- title }} → {{- to }}',
					'add-button-text' => __( 'Add a notification', 'fw' ),
					'value'           => array(),
				),
				'confirmation_routes' => array(
					'type'            => 'addable-box',
					'label'           => __( 'A different "after sending", by answer', 'fw' ),
					'desc'            => __( 'The first rule that matches replaces Settings → Options → After Sending for that submission.', 'fw' ),
					'box-options'     => self::rule_options() + array(
						'type'    => array( 'type' => 'select', 'label' => __( 'Then', 'fw' ), 'value' => 'message', 'choices' => array( 'message' => __( 'Show this message', 'fw' ), 'url' => __( 'Go to this address', 'fw' ) ) ),
						'message' => array( 'type' => 'textarea', 'label' => __( 'Message', 'fw' ), 'desc' => __( 'Answer tags work.', 'fw' ), 'value' => '' ),
						'url'     => array( 'type' => 'text', 'label' => __( 'Address', 'fw' ), 'desc' => __( 'A full address, or /path on this site (e.g. a thank-you page).', 'fw' ), 'value' => '' ),
					),
					'template'        => '{{- field }} {{- op }} {{- value }} → {{- type }}',
					'add-button-text' => __( 'Add a rule', 'fw' ),
					'value'           => array(),
				),
			),
		);
	}

	/**
	 * Does a rule's condition hold for this submission? A rule without a field always holds (used by
	 * extra notifications); a field that cannot be found never does (a typo must not reroute mail).
	 */
	public static function holds( array $rule, array $values, array $items ) {
		$field = FW_Forms_Conditions::norm( $rule['field'] ?? '' );
		if ( '' === $field ) {
			return true;
		}
		foreach ( $items as $sc => $it ) {
			if ( FW_Forms_Conditions::norm( $it['options']['label'] ?? '' ) === $field ) {
				$op = in_array( $rule['op'] ?? '', FW_Forms_Conditions::OPS, true ) ? $rule['op'] : 'is';
				return FW_Forms_Conditions::matches( array( 'op' => $op, 'value' => FW_Forms_Conditions::norm( $rule['value'] ?? '' ) ), $values[ $it['shortcode'] ?? $sc ] ?? null );
			}
		}
		return false;
	}

	private static function rows( array $form, $key ) {
		$v = $form[ $key ] ?? array();
		return is_array( $v ) ? array_values( array_filter( $v, 'is_array' ) ) : array();
	}

	/** "a@x.com, {email_1}" → valid addresses, answer tags filled in. */
	public static function addresses( $list, array $values, array $items, $post_id = 0 ) {
		$list = class_exists( 'FW_Forms_Merge_Tags' ) ? FW_Forms_Merge_Tags::replace( (string) $list, $values, $items, 'text', $post_id ) : (string) $list;
		$out  = array();
		foreach ( preg_split( '/[\s,;]+/', $list ) as $e ) {
			if ( is_email( $e ) ) {
				$out[ strtolower( $e ) ] = $e;
			}
		}
		return array_values( $out );
	}

	/**
	 * The main notification's recipients after the routing rules.
	 *
	 * @param string[] $main
	 * @return string[]
	 */
	public static function route( array $form, array $main, array $values, array $items ) {
		$to       = $main;
		$replaced = false;
		$extra    = array();
		foreach ( self::rows( $form, 'email_routes' ) as $r ) {
			if ( ! self::holds( $r, $values, $items ) ) {
				continue;
			}
			$addr = self::addresses( $r['to'] ?? '', $values, $items );
			if ( ! $addr ) {
				continue;
			}
			if ( 'add' === ( $r['mode'] ?? 'replace' ) ) {
				$extra = array_merge( $extra, $addr );
			} elseif ( ! $replaced ) {
				$to       = $addr;
				$replaced = true;
			}
		}
		$all = array();
		foreach ( array_merge( $to, $extra ) as $e ) {
			$all[ strtolower( $e ) ] = $e;
		}
		return array_values( $all );
	}

	/** Send the extra notifications that apply. @return int how many were sent */
	public static function send_extra( array $form, array $values, array $items, array $data = array(), $post_id = 0 ) {
		if ( ! function_exists( 'fw_ext_mailer_send_mail' ) ) {
			return 0;
		}
		$n   = 0;
		$tag = function ( $t, $mode ) use ( $values, $items, $post_id ) {
			return class_exists( 'FW_Forms_Merge_Tags' ) ? FW_Forms_Merge_Tags::replace( (string) $t, $values, $items, $mode, $post_id ) : (string) $t;
		};
		foreach ( self::rows( $form, 'extra_notifications' ) as $r ) {
			if ( ! self::holds( $r, $values, $items ) ) {
				continue;
			}
			$to = self::addresses( $r['to'] ?? '', $values, $items, $post_id );
			if ( ! $to ) {
				continue;
			}
			$template = trim( (string) ( $r['message'] ?? '' ) );
			$template = '' !== $template ? $template : '{all_fields}';
			$is_html  = $template !== wp_strip_all_tags( $template );
			$message  = trim( preg_replace( '/\{[a-zA-Z0-9_\-]+\}/', '', $tag( $template, $is_html ? 'html' : 'text' ) ) );
			$subject  = trim( preg_replace( '/\{[a-zA-Z0-9_\-]+\}/', '', $tag( '' !== trim( (string) ( $r['subject'] ?? '' ) ) ? $r['subject'] : ( $form['subject_message'] ?? __( 'New form submission', 'fw' ) ), 'text' ) ) );
			$body     = $is_html ? wp_kses_post( $message ) : wpautop( esc_html( $message ) );
			$res      = fw_ext_mailer_send_mail( implode( ',', $to ), wp_strip_all_tags( $subject ), $body, array_filter( array( 'reply_to' => $data['reply_to'] ?? '' ) ) );
			if ( ! empty( $res['status'] ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * The "after sending" for this submission when a rule matches.
	 *
	 * @return array|null { type: message|url, message, url }
	 */
	public static function confirmation( array $form, array $values, array $items ) {
		foreach ( self::rows( $form, 'confirmation_routes' ) as $r ) {
			if ( '' !== trim( (string) ( $r['field'] ?? '' ) ) && self::holds( $r, $values, $items ) ) {
				return array( 'type' => 'url' === ( $r['type'] ?? '' ) ? 'url' : 'message', 'message' => (string) ( $r['message'] ?? '' ), 'url' => (string) ( $r['url'] ?? '' ) );
			}
		}
		return null;
	}
}
