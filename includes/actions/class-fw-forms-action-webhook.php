<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Send to a webhook" — POSTs each submission to a URL (an automation tool, another system).
 *
 * Body (JSON, or form-encoded): { event: "form.submitted", form_id, entry_id, submitted_at (ISO 8601),
 * page_url, answers: { field_name: text }, fields: [ { id, type, label, value } ], order?: { total,
 * currency, lines } }. With a secret, the header X-Unysonplus-Signature carries
 * "sha256=" + HMAC-SHA256 of the raw body, so the receiver can check it came from this site.
 * Sent with wp_safe_remote_post (no addresses on the local network); a failure is logged
 * (`fw_ext_forms_action_failed`), never shown to the visitor.
 */
class FW_Forms_Action_Webhook extends FW_Forms_Action {

	public function get_id() {
		return 'webhook';
	}

	public function get_title() {
		return __( 'Send to a webhook', 'fw' );
	}

	public function get_options() {
		return array(
			'action_webhook'        => $this->switch_option(
				__( 'Send to a webhook', 'fw' ),
				__( 'Post each submission to an address, for automation tools (Zapier, Make, n8n) and other systems.', 'fw' )
			),
			'action_webhook_url'    => array( 'type' => 'text', 'label' => __( 'Webhook URL', 'fw' ), 'desc' => __( 'https://… Addresses on your own network are refused.', 'fw' ), 'value' => '' ),
			'action_webhook_format' => array(
				'type'    => 'select',
				'label'   => __( 'Send as', 'fw' ),
				'value'   => 'json',
				'choices' => array( 'json' => __( 'JSON', 'fw' ), 'form' => __( 'Form fields (application/x-www-form-urlencoded)', 'fw' ) ),
			),
			'action_webhook_secret' => array( 'type' => 'text', 'label' => __( 'Signing secret (optional)', 'fw' ), 'desc' => __( 'With a secret, each request carries X-Unysonplus-Signature: sha256=<HMAC of the body>.', 'fw' ), 'value' => '' ),
		);
	}

	/** The data sent for a submission. */
	public static function payload( array $ctx ) {
		$answers = array();
		foreach ( (array) ( $ctx['payload']['form_values'] ?? array() ) as $k => $v ) {
			$answers[ (string) $k ] = is_array( $v ) ? array_values( array_map( 'strval', $v ) ) : ( null === $v ? '' : (string) $v );
		}
		$out = array(
			'event'        => 'form.submitted',
			'form_id'      => (string) $ctx['form_id'],
			'entry_id'     => (int) ( $ctx['entry_id'] ?? 0 ),
			'submitted_at' => gmdate( 'c' ),
			'page_url'     => class_exists( 'FW_Forms_Payments' ) ? FW_Forms_Payments::form_page_url() : home_url( '/' ),
			'answers'      => $answers,
			'fields'       => array_values( (array) $ctx['fields'] ),
		);
		if ( class_exists( 'FW_Forms_Order' ) && FW_Forms_Order::items() ) {
			$order = FW_Forms_Order::current();
			if ( $order['lines'] ) {
				$out['order'] = array( 'total' => $order['total'], 'currency' => $order['currency'], 'lines' => $order['lines'] );
			}
		}
		/** Filters the data a webhook sends. */
		return apply_filters( 'fw_ext_forms_webhook_payload', $out, $ctx );
	}

	public function run( array $ctx ) {
		$url = trim( (string) self::opt( $ctx['form'], 'action_webhook_url', '' ) );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'fw_forms_webhook_url', 'No valid webhook URL.' );
		}
		$data = self::payload( $ctx );
		$form = 'form' === self::opt( $ctx['form'], 'action_webhook_format', 'json' );
		$body = $form ? http_build_query( array( 'payload' => wp_json_encode( $data ) ) + $data['answers'] ) : wp_json_encode( $data );
		$head = array(
			'Content-Type'          => $form ? 'application/x-www-form-urlencoded' : 'application/json',
			'X-Unysonplus-Event'    => 'form.submitted',
			'User-Agent'            => 'Unysonplus-Forms/' . fw_ext( 'forms' )->manifest->get_version() . '; ' . home_url( '/' ),
		);
		$secret = (string) self::opt( $ctx['form'], 'action_webhook_secret', '' );
		if ( '' !== $secret ) {
			$head['X-Unysonplus-Signature'] = 'sha256=' . hash_hmac( 'sha256', $body, $secret );
		}
		$res = wp_safe_remote_post( $url, array( 'timeout' => 10, 'redirection' => 2, 'headers' => $head, 'body' => $body ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		return $code >= 200 && $code < 300 ? null : new WP_Error( 'fw_forms_webhook_http', 'The webhook answered HTTP ' . $code );
	}
}
