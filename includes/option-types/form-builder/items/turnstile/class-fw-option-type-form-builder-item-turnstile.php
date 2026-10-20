<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Turnstile human check. See _shared/class-fw-option-type-form-builder-item-captcha.php.
 */
class FW_Option_Type_Form_Builder_Item_Turnstile extends FW_Option_Type_Form_Builder_Item_Captcha {

	public function get_type() {
		return 'turnstile';
	}

	protected function title() {
		return __( 'Turnstile', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Cloudflare Turnstile check (stops spam bots, usually invisible to people)', 'fw' );
	}

	protected function provider() {
		return 'turnstile';
	}

	protected function script_url() {
		return 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=fwFormsCaptchaReady&render=explicit';
	}

	protected function response_field() {
		return 'cf-turnstile-response';
	}

	protected function verify_url() {
		return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Turnstile' );
