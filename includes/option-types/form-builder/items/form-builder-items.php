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

if (apply_filters('fw:ext:forms:builder:load-item:form-header-title', true)) {
	require $dir . '/form-header-title/class-fw-option-type-form-builder-item-form-header-title.php';
}