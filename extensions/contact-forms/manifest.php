<?php if (!defined('FW')) die('Forbidden');

/**
 * Changelog ----
 *
 * 1.0.4 - Contact Form element reaches parity with the rest of the builder: two
 *         new tabs. STYLE adds per-instance layout (form width + alignment),
 *         field colours (background / text / border / focus) and shape (radius /
 *         border width / padding), a submit button styled with the theme's Button
 *         presets (style / size / shape / full-width / alignment) and an
 *         outer-margin control.
 *         ADVANCED wires the shared sc_get_advanced_tab() surface (CSS ID, extra
 *         class, custom CSS, position/overflow, responsive hide, display
 *         conditions, custom HTML attributes). The shortcode now wraps its output
 *         through sc_build_wrapper_attr() with a unique per-instance class, and
 *         emits a scoped <style> block so two forms on one page can look
 *         different. Both tabs are additive with defaults — no migration needed.
 */

$manifest = array();

$manifest['name'] = __('Contact Forms', 'fw');
$manifest['version'] = '1.0.5';
$manifest['standalone'] = true;
$manifest['display'] = false;
$manifest['requirements']  = array(
	'extensions' => array(
		'mailer' => array(),
	),
);