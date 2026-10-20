<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Shared base for the human-check fields (Cloudflare Turnstile, hCaptcha).
 *
 * Each provider is a script that draws a widget and puts a token in the form, and a server
 * endpoint that says whether the token is genuine. The subclass names them; this class renders the
 * widget explicitly (static/js/captcha.js keeps each widget's id, so the background sender can reset
 * it after a failed attempt), verifies the token on submit, and stays out of the way until the site's
 * keys are set: a missing key shows a note to administrators and never blocks a visitor.
 *
 * Keys are site-wide, under Forms → Settings, as `{provider}-keys` { site-key, secret-key }.
 */
abstract class FW_Option_Type_Form_Builder_Item_Captcha extends FW_Option_Type_Form_Builder_Item_Simple {

	/** @return string 'turnstile' | 'hcaptcha' — also the global the provider script defines. */
	abstract protected function provider();

	/** @return string The provider's script, loaded with explicit rendering and our onload callback. */
	abstract protected function script_url();

	/** @return string The POST field the provider fills with its token. */
	abstract protected function response_field();

	/** @return string The verification endpoint. */
	abstract protected function verify_url();

	public function get_options() {
		return array(
			array(
				'g_captcha' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Label', 'fw' ),
								'desc'  => __( 'Optional. Shown above the check; leave empty for none.', 'fw' ),
								'value' => '',
							),
						),
						array(
							'theme' => array(
								'type'    => 'select',
								'label'   => __( 'Appearance', 'fw' ),
								'value'   => 'auto',
								'choices' => array(
									'auto'  => __( 'Match the visitor\'s light or dark setting', 'fw' ),
									'light' => __( 'Light', 'fw' ),
									'dark'  => __( 'Dark', 'fw' ),
								),
							),
						),
					),
				),
			),
		);
	}

	/** @return array { site-key, secret-key } */
	protected function keys() {
		$forms = fw_ext( 'forms' );
		$keys  = $forms ? $forms->get_db_settings_option( $this->provider() . '-keys' ) : array();
		return array(
			'site-key'   => is_array( $keys ) && ! empty( $keys['site-key'] ) ? trim( (string) $keys['site-key'] ) : '',
			'secret-key' => is_array( $keys ) && ! empty( $keys['secret-key'] ) ? trim( (string) $keys['secret-key'] ) : '',
		);
	}

	private function configured() {
		$k = $this->keys();
		return $k['site-key'] !== '' && $k['secret-key'] !== '';
	}

	public function frontend_render( array $item, $input_value ) {
		if ( ! $this->configured() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<p class="fw-captcha-notice">' . esc_html( sprintf(
					/* translators: %s: Turnstile / hCaptcha */
					__( 'The %s check is not shown because its keys are not set yet (Unyson+ → Forms → Settings). Only administrators see this note.', 'fw' ),
					$this->title()
				) ) . '</p>';
			}
			return '';
		}

		$forms = fw_ext( 'forms' );
		wp_enqueue_script(
			'fw-ext-forms-captcha',
			fw_min_uri( $this->shared_uri( '/js/captcha.js' ) ),
			array(),
			$forms->manifest->get_version(),
			true
		);
		wp_enqueue_script( 'fw-ext-forms-captcha-' . $this->provider(), $this->script_url(), array( 'fw-ext-forms-captcha' ), null, true );

		$theme = isset( $item['options']['theme'] ) && in_array( $item['options']['theme'], array( 'auto', 'light', 'dark' ), true ) ? $item['options']['theme'] : 'auto';

		return $this->view( $item, array(
			'class'               => 'fw-captcha fw-captcha--' . $this->provider(),
			'data-fw-captcha'     => $this->provider(),
			'data-sitekey'        => $this->keys()['site-key'],
			'data-theme'          => $theme,
		), array( 'label' => isset( $item['options']['label'] ) ? (string) $item['options']['label'] : '' ) );
	}

	public function frontend_validate( array $item, $input_value ) {
		if ( ! $this->configured() ) {
			return null; // Not set up yet: never lock visitors out of the form.
		}

		$token = FW_Request::POST( $this->response_field() );
		if ( ! is_string( $token ) || '' === trim( $token ) ) {
			return __( 'Please complete the check that shows you are not a robot.', 'fw' );
		}

		$body = array(
			'secret'   => $this->keys()['secret-key'],
			'response' => $token,
		);
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$body['remoteip'] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		$res = wp_remote_post( $this->verify_url(), array( 'timeout' => 10, 'body' => $body ) );

		if ( is_wp_error( $res ) ) {
			// The provider could not be reached. Refusing would lose a real visitor's message because
			// of someone else's outage; the honeypot and rate limit still apply.
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( is_array( $data ) && ! empty( $data['success'] ) ) {
			return null;
		}

		return __( 'The check that shows you are not a robot did not pass. Please try it again.', 'fw' );
	}

	/** A human check is not an answer: nothing to store or email. */
	public function get_value_from_item( $value ) {
		return '';
	}
}
