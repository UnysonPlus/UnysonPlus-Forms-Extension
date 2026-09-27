<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * AI Assistant abilities for Forms (the contact-form builder).
 *
 *   forms-describe  the field types a form can use (with their options) and the ready-made starters
 *   forms-add       put a contact form on a page — from a starter and / or a list of simple field
 *                   definitions — with its email settings; every field is normalised by its own
 *                   form-builder item class (the same path the starters use), and the change goes
 *                   through the AI Assistant's page store (a revision first; the builder panel's
 *                   sandbox when used from the builder)
 *
 * Personal data stays out of reach: there is no ability that reads form ENTRIES.
 */

if ( ! function_exists( 'fw_ext_forms_ai_register' ) ) :

	/** @return array type => item-type object (the form builder's registered field types). */
	function fw_ext_forms_ai_types() {
		$builder = fw()->backend->option_type( 'form-builder' );
		if ( ! $builder ) {
			return array();
		}
		$r = new ReflectionMethod( $builder, 'get_item_types' );
		if ( PHP_VERSION_ID < 80100 ) {
			$r->setAccessible( true );
		}
		return (array) $r->invoke( $builder );
	}

	/**
	 * One normalised form-builder item.
	 *
	 * @param array $f      { type, label?, required?, width?, choices?, …other options }
	 * @param int   $i
	 * @param array $errors
	 * @return array|null
	 */
	function fw_ext_forms_ai_item( array $f, $i, array &$errors ) {
		$types = fw_ext_forms_ai_types();
		$type  = (string) ( $f['type'] ?? '' );
		if ( ! isset( $types[ $type ] ) || in_array( $type, array( 'honeypot' ), true ) ) {
			$errors[] = sprintf( 'fields[%d].type "%s" is not a form field type (see forms_describe).', $i, $type );
			return null;
		}
		if ( in_array( $type, array( 'select', 'radio', 'checkboxes' ), true ) && empty( $f['choices'] ) ) {
			$errors[] = sprintf( 'fields[%d] (%s) needs choices.', $i, $type );
		}
		$width = (string) ( $f['width'] ?? '1_1' );
		if ( ! in_array( $width, array( '1_1', '1_2', '1_3', '2_3', '1_4', '3_4' ), true ) ) {
			$errors[] = sprintf( 'fields[%d].width "%s" — use 1_1, 1_2, 1_3, 2_3, 1_4 or 3_4.', $i, $width );
		}
		$options = $f;
		unset( $options['type'], $options['width'] );
		if ( isset( $options['choices'] ) ) {
			$options['choices'] = array_values( array_map( 'strval', (array) $options['choices'] ) );
		}
		$slug  = sanitize_key( (string) ( $f['label'] ?? $type ) ) ?: $type;
		$item  = array(
			'type'      => $type,
			'shortcode' => $type === 'form-header-title' ? 'form_header_title' : str_replace( '-', '_', $type ) . '_' . substr( $slug, 0, 20 ) . '_' . $i,
			'width'     => $type === 'form-header-title' ? '' : $width,
			'options'   => $options,
		);
		$fixed              = $types[ $type ]->get_value_from_attributes( $item );
		$fixed['shortcode'] = $item['shortcode'];
		$fixed['width']     = $item['width'];
		return $fixed;
	}

	function fw_ext_forms_ai_register() {
		if ( ! class_exists( 'FW_Forms_Starters' ) && file_exists( dirname( __FILE__ ) . '/class-fw-forms-starters.php' ) ) {
			require_once dirname( __FILE__ ) . '/class-fw-forms-starters.php';
		}
		if ( ! function_exists( 'fw_ai_register_ability' ) || ! fw_ext( 'shortcodes' ) || ! fw_ext( 'shortcodes' )->get_shortcode( 'contact_form' ) ) {
			return;
		}

		fw_ai_register_ability( 'forms-describe', array(
			'label'       => __( 'Describe form fields', 'fw' ),
			'description' => 'The field types a contact form can use (text, email, textarea, select, radio, checkboxes, date, number, website, rating, file-upload, hidden, consent, form-header-title …) with their options, and the ready-made starters (contact, booking, quote, registration, support, feedback, nps, signup) with their fields.',
			'permission'  => 'edit_posts',
			'readonly'    => true,
			'panel'       => true,
			'execute'     => function () {
				$types = array();
				foreach ( fw_ext_forms_ai_types() as $type => $obj ) {
					if ( $type === 'honeypot' ) {
						continue;
					}
					$sample  = $obj->get_value_from_attributes( array( 'type' => $type, 'shortcode' => $type . '_x', 'width' => '1_1', 'options' => array() ) );
					$types[] = array( 'type' => (string) $type, 'options' => array_keys( (array) ( $sample['options'] ?? array() ) ) );
				}
				$starters = array();
				if ( class_exists( 'FW_Forms_Starters' ) ) {
					foreach ( FW_Forms_Starters::all() as $id => $s ) {
						$starters[] = array(
							'starter' => (string) $id,
							'title'   => (string) $s['title'],
							'fields'  => array_map( static function ( $it ) {
								return trim( $it['type'] . ': ' . ( $it['options']['label'] ?? $it['options']['title'] ?? '' ) );
							}, (array) $s['items'] ),
						);
					}
				}
				return array(
					'field_types' => $types,
					'starters'    => $starters,
					'notes'       => 'Every field takes label and required; select / radio / checkboxes take choices (a list of strings); width is 1_1, 1_2, 1_3 … (two 1_2 fields sit side by side). consent takes purpose (terms | newsletter). Entries (the people who submit) are not available to the AI.',
				);
			},
		) );

		fw_ai_register_ability( 'forms-add', array(
			'label'       => __( 'Add a contact form to a page', 'fw' ),
			'description' => 'Adds a contact form to a page: start from a starter (see forms_describe) and / or give fields [{ type, label, required?, width?, choices?, …type options }]; title adds a heading field at the top. email_to receives submissions (default: the site admin email), subject / submit_text / success_message set the texts. Placed at the page root in its own section unless parent_path names a layout item. Works in the builder panel (unsaved until Update) and on saved pages (a revision first; undo restores). The form starts working once its page has been viewed.',
			'input'       => array(
				'post_id'         => array( 'type' => 'integer' ),
				'starter'         => array( 'type' => 'string' ),
				'fields'          => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
				'title'           => array( 'type' => 'string' ),
				'email_to'        => array( 'type' => 'string' ),
				'subject'         => array( 'type' => 'string' ),
				'submit_text'     => array( 'type' => 'string' ),
				'success_message' => array( 'type' => 'string' ),
				'parent_path'     => array( 'type' => 'string' ),
				'position'        => array( 'type' => 'integer', 'minimum' => 0 ),
			),
			'required'    => array( 'post_id' ),
			'permission'  => 'edit_post',
			'panel'       => true,
			'execute'     => 'fw_ext_forms_ai_add',
		) );
	}
	add_action( 'fw_ai_assistant_register_abilities', 'fw_ext_forms_ai_register' );

	/**
	 * @param array $in
	 * @return array|WP_Error
	 */
	function fw_ext_forms_ai_add( $in ) {
		$post_id = (int) $in['post_id'];
		$errors  = array();
		$items   = array();

		if ( ! empty( $in['title'] ) ) {
			$items[] = fw_ext_forms_ai_item( array( 'type' => 'form-header-title', 'title' => (string) $in['title'], 'subtitle' => '' ), 0, $errors );
		}
		if ( ! empty( $in['starter'] ) ) {
			$all = class_exists( 'FW_Forms_Starters' ) ? FW_Forms_Starters::all() : array();
			if ( ! isset( $all[ $in['starter'] ] ) ) {
				$errors[] = sprintf( 'Unknown starter "%s" (see forms_describe).', $in['starter'] );
			} else {
				$types = fw_ext_forms_ai_types();
				foreach ( (array) $all[ $in['starter'] ]['items'] as $it ) {
					if ( ! empty( $in['title'] ) && $it['type'] === 'form-header-title' ) {
						continue; // The given title replaces the starter's heading.
					}
					if ( isset( $types[ $it['type'] ] ) ) {
						$fixed              = $types[ $it['type'] ]->get_value_from_attributes( $it );
						$fixed['shortcode'] = $it['shortcode'];
						$fixed['width']     = $it['width'];
						$items[]            = $fixed;
					}
				}
			}
		}
		foreach ( array_values( (array) ( $in['fields'] ?? array() ) ) as $i => $f ) {
			$items[] = fw_ext_forms_ai_item( (array) json_decode( wp_json_encode( $f ), true ), $i + 1, $errors );
		}
		$items = array_values( array_filter( $items ) );
		if ( ! $items && ! $errors ) {
			$errors[] = 'Give a starter and / or fields.';
		}
		if ( ! empty( $in['email_to'] ) && ! is_email( (string) $in['email_to'] ) ) {
			$errors[] = 'email_to is not a valid email address.';
		}
		if ( $errors ) {
			return new WP_Error( 'fw_forms_ai_invalid', 'Nothing was changed: ' . implode( ' | ', array_slice( $errors, 0, 20 ) ) );
		}

		// The contact-form element, with the shortcode's own defaults.
		$sc   = fw_ext( 'shortcodes' )->get_shortcode( 'contact_form' );
		$atts = fw_get_options_values_from_input( (array) $sc->get_options(), array() );
		$atts['id']   = substr( md5( uniqid( 'cf', true ) ), 0, 20 );
		$atts['form'] = array( 'json' => wp_json_encode( $items ) );
		$map          = array( 'email_to' => 'email_to', 'subject' => 'subject_message', 'submit_text' => 'submit_button_text', 'success_message' => 'success_message' );
		foreach ( $map as $k => $opt ) {
			if ( isset( $in[ $k ] ) && $in[ $k ] !== '' ) {
				$atts[ $opt ] = $k === 'email_to' ? sanitize_email( (string) $in[ $k ] ) : sanitize_text_field( (string) $in[ $k ] );
			}
		}
		if ( empty( $atts['email_to'] ) ) {
			$atts['email_to'] = get_option( 'admin_email' );
		}
		$form = array( 'type' => 'contact-form', 'atts' => $atts, '_items' => array() );

		$tree   = FW_AI_Store::get_tree( $post_id );
		$parent = array();
		if ( ! empty( $in['parent_path'] ) ) {
			$parent = FW_AI_Store::resolve( $tree, (string) $in['parent_path'] );
			if ( is_wp_error( $parent ) ) {
				return $parent;
			}
			$ptype = (string) ( FW_AI_Store::node( $tree, $parent )['type'] ?? '' );
			if ( ! in_array( $ptype, array( 'flexbox', 'column', 'container' ), true ) ) {
				return new WP_Error( 'fw_forms_ai_parent', 'parent_path must be a flexbox, column or container (a section holds columns).' );
			}
			$node = $form;
		} else {
			$node = array(
				'type'   => 'flexbox',
				'atts'   => array( 'html_tag' => 'section', 'display' => 'block', 'unique_id' => FW_AI_Schema::unique_id() ),
				'_items' => array( $form ),
			);
		}
		$list =& FW_AI_Store::children( $tree, $parent );
		$pos  = isset( $in['position'] ) ? max( 0, min( (int) $in['position'], count( $list ) ) ) : count( $list );
		array_splice( $list, $pos, 0, array( $node ) );
		unset( $list );

		$rev = FW_AI_Store::save_tree( $post_id, $tree, 'unysonplus/forms-add', sprintf( 'Added a contact form (%d fields)', count( $items ) ) );
		if ( is_wp_error( $rev ) ) {
			return $rev;
		}
		return array(
			'ok'               => true,
			'message'          => sprintf( 'Added a contact form with %d field(s); submissions go to %s.', count( $items ), $atts['email_to'] ),
			'post_id'          => $post_id,
			'path'             => FW_AI_Store::path_str( array_merge( $parent, array( $pos ) ) ),
			'fields'           => array_map( static function ( $it ) {
				return trim( $it['type'] . ': ' . ( $it['options']['label'] ?? $it['options']['title'] ?? '' ) );
			}, $items ),
			'undo_revision_id' => (int) $rev,
			'outline'          => FW_AI_Store::outline( FW_AI_Store::get_tree( $post_id ) ),
		);
	}

endif;
