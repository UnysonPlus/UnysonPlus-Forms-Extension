<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * The form-type picker: what a new Contact Form shows before its first field.
 *
 * The starters used to be reachable only through the builder's Templates panel, which most people
 * never open. Now an empty form builder (nothing but the header) opens on a grid of form types,
 * grouped, plus "Blank" and, when the site has an AI provider, a box to describe the form in a
 * sentence. Picking a type fills the builder exactly like loading the template (same normalised JSON)
 * and fills the subject, button and thank-you text — only where they are still the defaults, and
 * never the form's actions. "Choose a form type" in the builder's header reopens it later.
 *
 * Assets ride the builder's own enqueue (`fw_ext_builder:option_type:builder:enqueue`) for
 * form-builder options only; the script is
 * extensions/contact-forms/shortcodes/contact-form/static/js/starter-picker.js.
 */
class FW_Forms_Picker {

	public static function init() {
		add_action( 'fw_ext_builder:option_type:builder:enqueue', array( __CLASS__, '_enqueue' ) );
	}

	/** Group id => label, in display order. */
	public static function groups() {
		return array(
			'enquiries'    => __( 'Enquiries', 'fw' ),
			'bookings'     => __( 'Bookings', 'fw' ),
			'events'       => __( 'Events', 'fw' ),
			'applications' => __( 'Applications', 'fw' ),
			'surveys'      => __( 'Surveys and feedback', 'fw' ),
			'marketing'    => __( 'Marketing', 'fw' ),
			'payments'     => __( 'Orders and payments', 'fw' ),
			'other'        => __( 'Other', 'fw' ),
		);
	}

	/** The starters as the picker needs them. */
	public static function starters() {
		$out = array();
		if ( ! class_exists( 'FW_Forms_Starters' ) ) {
			return $out;
		}
		foreach ( FW_Forms_Starters::all() as $id => $s ) {
			if ( empty( $s['items'] ) || ! is_array( $s['items'] ) ) {
				continue;
			}
			$items  = FW_Forms_Starters::normalize( $s['items'] );
			$fields = 0;
			foreach ( $items as $it ) {
				if ( ! in_array( $it['type'] ?? '', array( 'form-header-title', 'hidden', 'honeypot' ), true ) ) {
					$fields++;
				}
			}
			$out[] = array(
				'id'       => (string) $id,
				'title'    => (string) ( $s['title'] ?? $id ),
				'desc'     => (string) ( $s['desc'] ?? '' ),
				'icon'     => preg_replace( '/[^a-z0-9-]/', '', (string) ( $s['icon'] ?? 'forms' ) ),
				'group'    => isset( self::groups()[ $s['group'] ?? '' ] ) ? $s['group'] : 'other',
				'fields'   => $fields,
				'items'    => $items,
				'settings' => isset( $s['settings'] ) && is_array( $s['settings'] ) ? $s['settings'] : array(),
			);
		}
		return $out;
	}

	/** Saved forms for the picker: id, title, fields. */
	private static function library_forms() {
		$out = array();
		if ( ! class_exists( 'FW_Forms_Library' ) ) {
			return $out;
		}
		foreach ( FW_Forms_Library::choices() as $id => $title ) {
			$out[] = array( 'id' => (int) $id, 'title' => (string) $title, 'fields' => FW_Forms_Library::field_count( $id ) );
		}
		return $out;
	}

	/** @internal */
	public static function _enqueue( $data ) {
		static $done = false;

		// The page builder runs the builder enqueue many times while it prepares element options
		// (once per element, and inside its own AJAX data request). The picker's data — every starter,
		// normalised — must be built and printed ONCE per page, never per call: repeating it here once
		// pushed that request past PHP's memory limit. And an AJAX reply has no page to print it on.
		if ( $done || wp_doing_ajax() || ( $data['option']['type'] ?? '' ) !== 'form-builder' || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$done = true;
		$forms = fw_ext( 'forms' );
		$uri   = fw_get_framework_directory_uri( '/extensions/forms/extensions/contact-forms/shortcodes/contact-form/static' );
		$ver   = $forms ? $forms->manifest->get_version() : null;

		wp_enqueue_style( 'fw-ext-forms-starter-picker', fw_min_uri( $uri . '/css/starter-picker.css' ), array(), $ver );
		wp_enqueue_script( 'fw-ext-forms-starter-picker', fw_min_uri( $uri . '/js/starter-picker.js' ), array( 'jquery', 'fw-events' ), $ver, true );

		$ai = class_exists( 'FW_Forms_AI_Generate' ) && FW_Forms_AI_Generate::available();

		wp_localize_script( 'fw-ext-forms-starter-picker', 'fwFormsPicker', array(
			'starters' => self::starters(),
			'groups'   => self::groups(),
			// The contact form's own defaults: the picker only replaces a setting still at its default.
			'defaults' => array(
				'subject_message'    => array( '', __( 'Contact Form', 'fw' ) ),
				'submit_button_text' => array( '', __( 'Send', 'fw' ), __( 'Submit', 'fw' ) ),
				'success_message'    => array( '', __( 'Message sent!', 'fw' ) ),
			),
			// The site's saved forms (Unyson+ → Forms): picking one links the element to it.
			'library'  => array(
				'forms'   => self::library_forms(),
				'nonce'   => wp_create_nonce( 'fw_forms_library' ),
				'url'     => admin_url( 'admin-ajax.php' ),
				'listUrl' => admin_url( 'edit.php?post_type=fw_form' ),
				'editUrl' => admin_url( 'post.php?action=edit&post=' ),
			),
			'ai'       => array(
				'available' => $ai,
				'action'    => class_exists( 'FW_Forms_AI_Generate' ) ? FW_Forms_AI_Generate::AJAX : '',
				'nonce'     => class_exists( 'FW_Forms_AI_Generate' ) ? wp_create_nonce( FW_Forms_AI_Generate::NONCE ) : '',
				'url'       => admin_url( 'admin-ajax.php' ),
			),
			'l10n'     => array(
				'heading'      => __( 'What kind of form is this?', 'fw' ),
				'intro'        => __( 'Start from a ready-made form and change anything afterwards, or start blank.', 'fw' ),
				'blank'        => __( 'Blank form', 'fw' ),
				'blankDesc'    => __( 'Start empty and add the fields yourself.', 'fw' ),
				'fields'       => __( '%d fields', 'fw' ),
				'describe'     => __( 'Or describe it', 'fw' ),
				'placeholder'  => __( 'e.g. A waiver for a surf school with an emergency contact and a signature-style consent', 'fw' ),
				'build'        => __( 'Build it', 'fw' ),
				'building'     => __( 'Building…', 'fw' ),
				'aiOff'        => __( 'Describing a form needs an AI provider. Connect one under Settings → Connectors, or start from a form type.', 'fw' ),
				'aiFailed'     => __( 'That did not work. Try again, or start from a form type.', 'fw' ),
				'reopen'       => __( 'Choose a form type', 'fw' ),
				'confirm'      => __( 'Replace the fields of this form with the form type you pick?', 'fw' ),
				'close'        => __( 'Close', 'fw' ),
				'saved'        => __( 'Your saved forms', 'fw' ),
				'savedDesc'    => __( 'Shows this saved form; edit it once in Unyson+ → Forms.', 'fw' ),
				'linked'       => __( 'This element shows the saved form "%s". Its fields and settings come from Unyson+ → Forms; this element keeps its own Style.', 'fw' ),
				'editLinked'   => __( 'Edit the saved form', 'fw' ),
				'unlink'       => __( 'Use own fields instead', 'fw' ),
				'saveLib'      => __( 'Save to the library', 'fw' ),
				'saveTitle'    => __( 'Name for the saved form:', 'fw' ),
				'savedOk'      => __( 'Saved. This element now shows the saved form.', 'fw' ),
				'saveFailed'   => __( 'The form could not be saved.', 'fw' ),
			),
		) );
	}
}
