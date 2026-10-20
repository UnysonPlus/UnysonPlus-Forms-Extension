<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Pre-filled answers — the starting values a form shows before anyone types.
 *
 * Three sources, the later winning:
 *  1. the logged-in user (email, name; phone, job title and address too when the CRM knows them),
 *  2. the page address: `?text_name=Ada&email_1=ada@example.com` fills those fields (by the field's
 *     name in the builder), so a link from an email or an ad can carry what is already known,
 *  3. the `fw_ext_forms_prefill_values` filter.
 *
 * These are only starting values: the visitor sees and can change every one of them, and the server
 * validates the submission as usual. A value that does not fit its field (a choice that is not one of
 * the options) simply shows nothing. Hidden fields keep their own source and are never pre-filled here.
 *
 * Answer piping (repeating an answer later in the form, "Thanks, {name_1}") is client-side, in
 * static/js/fields.js.
 */
class FW_Forms_Prefill {

	/**
	 * @param array $items The form's builder items.
	 * @return array shortcode => value, in the shape each field posts.
	 */
	public static function values( array $items ) {
		$out  = array();
		$user = is_user_logged_in() ? wp_get_current_user() : null;
		$crm  = null;
		if ( $user && class_exists( 'FW_CRM_Contacts' ) && is_callable( array( 'FW_CRM_Contacts', 'find_by_user' ) ) ) {
			try {
				$crm = FW_CRM_Contacts::find_by_user( $user->ID );
			} catch ( \Throwable $e ) {
				$crm = null;
			}
		}

		foreach ( $items as $item ) {
			$code = isset( $item['shortcode'] ) ? (string) $item['shortcode'] : '';
			$type = isset( $item['type'] ) ? (string) $item['type'] : '';
			if ( '' === $code || in_array( $type, array( 'hidden', 'honeypot', 'recaptcha', 'turnstile', 'hcaptcha', 'form-header-title', 'section', 'page-break', 'file-upload', 'consent' ), true ) ) {
				continue;
			}

			// 1. The logged-in person.
			if ( $user ) {
				$first = $crm && '' !== ( $crm['first_name'] ?? '' ) ? $crm['first_name'] : (string) $user->first_name;
				$last  = $crm && '' !== ( $crm['last_name'] ?? '' ) ? $crm['last_name'] : (string) $user->last_name;
				$full  = trim( $first . ' ' . $last );
				$full  = '' !== $full ? $full : (string) $user->display_name;
				switch ( $type ) {
					case 'email':
						$out[ $code ] = (string) $user->user_email;
						break;
					case 'name':
						$out[ $code ] = ( $item['options']['format'] ?? 'full' ) === 'first_last'
							? array( 'first' => $first, 'last' => $last )
							: $full;
						break;
					case 'phone':
						if ( $crm && '' !== ( $crm['phone'] ?? '' ) ) {
							$out[ $code ] = (string) $crm['phone'];
						}
						break;
					case 'address':
						if ( $crm && ! empty( $crm['address'] ) && is_array( $crm['address'] ) ) {
							$a = $crm['address'];
							$out[ $code ] = array(
								'street' => (string) ( $a['line1'] ?? '' ), 'line2' => (string) ( $a['line2'] ?? '' ), 'city' => (string) ( $a['city'] ?? '' ),
								'region' => (string) ( $a['region'] ?? '' ), 'postcode' => (string) ( $a['postcode'] ?? '' ), 'country' => (string) ( $a['country'] ?? '' ),
							);
						}
						break;
					case 'text':
						$hint = class_exists( 'FW_Forms_Autocomplete' ) ? FW_Forms_Autocomplete::for_label( $item['options']['label'] ?? '' ) : array();
						$map  = array(
							'name'               => $full,
							'given-name'         => $first,
							'family-name'        => $last,
							'email'              => (string) $user->user_email,
							'tel'                => $crm ? (string) ( $crm['phone'] ?? '' ) : '',
							'organization-title' => $crm ? (string) ( $crm['job_title'] ?? '' ) : '',
						);
						$k = $hint['autocomplete'] ?? '';
						if ( isset( $map[ $k ] ) && '' !== $map[ $k ] ) {
							$out[ $code ] = $map[ $k ];
						}
						break;
				}
			}

			// 2. The page address (?<field name>=…). Read-only, sanitised, and only for this field.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- starting values only.
			if ( isset( $_GET[ $code ] ) ) {
				$raw = wp_unslash( $_GET[ $code ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
				$out[ $code ] = is_array( $raw )
					? array_map( function ( $v ) {
						return is_scalar( $v ) ? sanitize_text_field( (string) $v ) : '';
					}, $raw )
					: sanitize_textarea_field( (string) $raw );
			}
		}

		/**
		 * Change the starting values of a form's fields.
		 *
		 * @param array $out   shortcode => value
		 * @param array $items The form's builder items.
		 */
		return (array) apply_filters( 'fw_ext_forms_prefill_values', $out, $items );
	}
}
