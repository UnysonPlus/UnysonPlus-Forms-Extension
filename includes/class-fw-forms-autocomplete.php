<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Autofill hints (WCAG 2.2 success criterion 1.3.5, "Identify Input Purpose").
 *
 * A field that asks for the visitor's own name, email, phone or address declares it with the
 * HTML `autocomplete` token, so browsers and password managers can fill it in and assistive
 * tools can recognise it. Email fields always say `email`; a text field is matched by the START
 * of its label ("Your name", "Phone number", "Postcode"…, English), so "Event name" or "Email
 * address" is never taken for a person's name or a street address. Anything not recognised is
 * left without a hint: a wrong hint is worse than none, because the browser would fill the wrong value.
 */
class FW_Forms_Autocomplete {

	/**
	 * The autofill hint for a text field, from its label.
	 *
	 * @param string $label
	 * @return array { autocomplete?: string, inputmode?: string }
	 */
	public static function for_label( $label ) {
		$l   = strtolower( trim( wp_strip_all_tags( (string) $label ) ) );
		$out = array();

		// Order matters: the more specific phrase first.
		$map = array(
			'/\be-?mail\b/'                                                         => 'email',
			'/^(your\s+)?(first|given|fore)\s*name\b/'                              => 'given-name',
			'/^(your\s+)?((last|family)\s*name|surname)\b/'                         => 'family-name',
			'/^(your\s+)?(company|organi[sz]ation|business)(\s+name)?\b/'           => 'organization',
			'/^(your\s+)?job\s*title\b/'                                            => 'organization-title',
			'/\b(phone|telephone|mobile|cell)\b|^tel\b/'                            => 'tel',
			'/^(your\s+)?(post\s*code|postal\s*code|zip(\s*code)?)\b/'              => 'postal-code',
			'/^(your\s+)?(city|town)\b/'                                            => 'address-level2',
			'/^(your\s+)?(state|province|county)\b/'                                => 'address-level1',
			'/^(your\s+)?country\b/'                                                => 'country-name',
			'/^(your\s+|street\s+|home\s+|postal\s+|billing\s+|delivery\s+)*address\b/' => 'street-address',
			'/^(your\s+|full\s+|contact\s+)*name\s*(\(.*\))?$/'                     => 'name',
		);

		foreach ( $map as $re => $token ) {
			if ( preg_match( $re, $l ) ) {
				$out['autocomplete'] = $token;
				break;
			}
		}

		if ( isset( $out['autocomplete'] ) && 'tel' === $out['autocomplete'] ) {
			$out['inputmode'] = 'tel';
		}

		/**
		 * Filter the autofill hint for a text field.
		 *
		 * @param array  $out   { autocomplete?, inputmode? }
		 * @param string $label The field's label.
		 */
		return (array) apply_filters( 'fw_ext_forms_autocomplete_hint', $out, (string) $label );
	}
}
