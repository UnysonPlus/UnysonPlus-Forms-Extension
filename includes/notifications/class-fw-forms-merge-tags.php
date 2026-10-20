<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Answer tags: `{field_name}` placeholders in a form's subject, success message, thank-you
 * address and confirmation email, replaced with what the visitor sent.
 *
 *   {<shortcode>}  one answer, by the field's name in the builder (e.g. {text_name}, {email_1})
 *   {all_fields}   every answer as "Label: value" lines
 *   {site_name} {page_title} {page_url} {date}
 *
 * Values are escaped for where they land ($mode): plain text (emails), HTML (an on-page message)
 * or a URL query. An unknown tag is left as written, so a typo is visible rather than silently
 * blank. Secret fields (a password) never fill a tag.
 */
class FW_Forms_Merge_Tags {

	/**
	 * @param string $text
	 * @param array  $values  shortcode => submitted value
	 * @param array  $items   shortcode => builder item (for labels and types)
	 * @param string $mode    'text' | 'html' | 'url'
	 * @param int    $post_id The page the form was sent from (0 = unknown).
	 * @return string
	 */
	public static function replace( $text, array $values, array $items = array(), $mode = 'text', $post_id = 0 ) {
		$text = (string) $text;
		if ( false === strpos( $text, '{' ) ) {
			return $text;
		}

		$secret = class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::secret_types() : array();
		$tags   = array();

		foreach ( $values as $key => $value ) {
			$type = isset( $items[ $key ]['type'] ) ? $items[ $key ]['type'] : '';
			if ( $type !== '' && in_array( $type, $secret, true ) ) {
				continue;
			}
			$tags[ (string) $key ] = self::flatten( $value );
		}

		$lines = array();
		if ( class_exists( 'FW_Forms_Entries_Capture' ) ) {
			foreach ( FW_Forms_Entries_Capture::fields_from_submission( $values, $items ) as $row ) {
				$v = self::flatten( isset( $row['value'] ) ? $row['value'] : '' );
				if ( $v !== '' ) {
					$lines[] = ( isset( $row['label'] ) && $row['label'] !== '' ? wp_strip_all_tags( $row['label'] ) . ': ' : '' ) . $v;
				}
			}
		}
		$tags['all_fields'] = implode( "\n", $lines );

		$tags['site_name'] = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$post_id           = (int) $post_id;
		$tags['page_title'] = $post_id ? wp_specialchars_decode( get_the_title( $post_id ), ENT_QUOTES ) : '';
		$tags['page_url']   = $post_id ? (string) get_permalink( $post_id ) : '';
		$tags['date']       = wp_date( get_option( 'date_format' ) );

		/**
		 * Add or change answer tags.
		 *
		 * @param array $tags   name => plain-text value
		 * @param array $values The submitted values.
		 */
		$tags = (array) apply_filters( 'fw_ext_forms_merge_tags', $tags, $values );

		return preg_replace_callback( '/\{([a-zA-Z0-9_\-]+)\}/', function ( $m ) use ( $tags, $mode ) {
			if ( ! array_key_exists( $m[1], $tags ) ) {
				return $m[0];
			}
			$v = (string) $tags[ $m[1] ];
			if ( 'html' === $mode ) {
				return nl2br( esc_html( $v ) );
			}
			if ( 'url' === $mode ) {
				return rawurlencode( $v );
			}
			return $v;
		}, $text );
	}

	/** A submitted value as one line of text. */
	private static function flatten( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( array( __CLASS__, 'flatten' ), $value ), 'strlen' ) );
		}
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}
