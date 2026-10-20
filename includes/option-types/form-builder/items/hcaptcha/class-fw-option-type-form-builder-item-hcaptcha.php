<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * hCaptcha human check. See _shared/class-fw-option-type-form-builder-item-captcha.php.
 */
class FW_Option_Type_Form_Builder_Item_Hcaptcha extends FW_Option_Type_Form_Builder_Item_Captcha {

	public function get_type() {
		return 'hcaptcha';
	}

	protected function title() {
		return __( 'hCaptcha', 'fw' );
	}

	protected function tip() {
		return __( 'Add an hCaptcha check (stops spam bots)', 'fw' );
	}

	protected function provider() {
		return 'hcaptcha';
	}

	protected function script_url() {
		return 'https://js.hcaptcha.com/1/api.js?onload=fwFormsCaptchaReady&render=explicit';
	}

	protected function response_field() {
		return 'h-captcha-response';
	}

	protected function verify_url() {
		return 'https://api.hcaptcha.com/siteverify';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Hcaptcha' );
