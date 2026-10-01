<?php if (!defined('FW')) die('Forbidden');

if (!is_admin()) {
	wp_enqueue_style('fw-ext-builder-frontend-grid');

	// The forms CSS/JS used to load on EVERY front-end page. They're now only
	// registered here, and enqueued where a form actually renders (the
	// contact-form shortcode's static.php in <head>, render_form() as a fallback).
	// The grid above stays global: the theme layout relies on it.
	fw()->extensions->get('forms')->register_frontend_static();
}
