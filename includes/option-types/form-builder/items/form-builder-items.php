<?php if (!defined('FW')) die('Forbidden');

$dir = dirname(__FILE__);


require $dir .'/text/class-fw-option-type-form-builder-item-text.php';
require $dir .'/textarea/class-fw-option-type-form-builder-item-textarea.php';
require $dir .'/number/class-fw-option-type-form-builder-item-number.php';
require $dir .'/checkboxes/class-fw-option-type-form-builder-item-checkboxes.php';
require $dir .'/radio/class-fw-option-type-form-builder-item-radio.php';
require $dir .'/select/class-fw-option-type-form-builder-item-select.php';
require $dir .'/email/class-fw-option-type-form-builder-item-email.php';
require $dir .'/website/class-fw-option-type-form-builder-item-website.php';
require $dir .'/recaptcha/class-fw-option-type-form-builder-item-recaptcha.php';
require $dir .'/honeypot/class-fw-option-type-form-builder-item-honeypot.php';
require $dir .'/file-upload/class-fw-option-type-form-builder-item-file-upload.php';

// Newer items share one base + one canvas factory (see _shared/), so each is
// only its own options, render and validate.
require $dir .'/_shared/class-fw-option-type-form-builder-item-simple.php';
require $dir .'/date/class-fw-option-type-form-builder-item-date.php';
require $dir .'/rating/class-fw-option-type-form-builder-item-rating.php';
require $dir .'/hidden/class-fw-option-type-form-builder-item-hidden.php';
require $dir .'/consent/class-fw-option-type-form-builder-item-consent.php';
require $dir .'/_shared/class-fw-option-type-form-builder-item-captcha.php';
require $dir .'/turnstile/class-fw-option-type-form-builder-item-turnstile.php';
require $dir .'/hcaptcha/class-fw-option-type-form-builder-item-hcaptcha.php';
require $dir .'/name/class-fw-option-type-form-builder-item-name.php';
require $dir .'/phone/class-fw-option-type-form-builder-item-phone.php';
require $dir .'/address/class-fw-option-type-form-builder-item-address.php';
require $dir .'/section/class-fw-option-type-form-builder-item-section.php';
require $dir .'/page-break/class-fw-option-type-form-builder-item-page-break.php';
require $dir .'/range/class-fw-option-type-form-builder-item-range.php';
require $dir .'/image-choice/class-fw-option-type-form-builder-item-image-choice.php';
require $dir .'/ranking/class-fw-option-type-form-builder-item-ranking.php';
require $dir .'/likert/class-fw-option-type-form-builder-item-likert.php';
require $dir .'/repeater/class-fw-option-type-form-builder-item-repeater.php';
require $dir .'/product/class-fw-option-type-form-builder-item-product.php';
require $dir .'/amount/class-fw-option-type-form-builder-item-amount.php';
require $dir .'/calculation/class-fw-option-type-form-builder-item-calculation.php';
require $dir .'/payment-method/class-fw-option-type-form-builder-item-payment-method.php';
require $dir .'/coupon/class-fw-option-type-form-builder-item-coupon.php';
require $dir .'/signature/class-fw-option-type-form-builder-item-signature.php';

if (apply_filters('fw:ext:forms:builder:load-item:form-header-title', true)) {
	require $dir . '/form-header-title/class-fw-option-type-form-builder-item-form-header-title.php';
}