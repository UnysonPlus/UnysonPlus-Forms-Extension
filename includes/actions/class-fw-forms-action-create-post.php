<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Create a post" — turns each submission into a post of any type (a testimonial, a listing, an
 * event), as a draft for review by default.
 *
 *   title / content  answer tags ({text_name}, {all_fields}, …); the content is cleaned with
 *                    wp_kses_post, so a visitor cannot inject scripts
 *   featured image   a File Upload (or Signature) field's file, copied into the Media Library
 *                    (images only)
 *   custom fields    one line each: meta_key = {answer tag}
 *   author           the logged-in visitor, when there is one
 *
 * Fires `fw_ext_forms_post_created( $post_id, $ctx )`.
 */
class FW_Forms_Action_Create_Post extends FW_Forms_Action {

	const SKIP_TYPES = array( 'attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'wp_font_family', 'wp_font_face', 'fw_form' );

	public function get_id() {
		return 'create_post';
	}

	public function get_title() {
		return __( 'Create a post', 'fw' );
	}

	public function get_options() {
		$types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $t ) {
			if ( ! in_array( $t->name, self::SKIP_TYPES, true ) ) {
				$types[ $t->name ] = $t->labels->singular_name;
			}
		}
		return array(
			'action_create_post'         => $this->switch_option(
				__( 'Create a post', 'fw' ),
				__( 'Turn each submission into a post: a testimonial, a listing, an event. Answer tags fill the title and content.', 'fw' )
			),
			'action_create_post_type'    => array( 'type' => 'select', 'label' => __( 'Post type', 'fw' ), 'value' => 'post', 'choices' => $types ? $types : array( 'post' => __( 'Post', 'fw' ) ) ),
			'action_create_post_status'  => array(
				'type'    => 'select',
				'label'   => __( 'Status', 'fw' ),
				'value'   => 'draft',
				'choices' => array( 'draft' => __( 'Draft (you review and publish)', 'fw' ), 'pending' => __( 'Pending review', 'fw' ), 'publish' => __( 'Published at once', 'fw' ) ),
			),
			'action_create_post_title'   => array( 'type' => 'text', 'label' => __( 'Title', 'fw' ), 'desc' => __( 'Answer tags work, e.g. Review from {text_name}.', 'fw' ), 'value' => __( 'Submission from {text_name}', 'fw' ) ),
			'action_create_post_content' => array( 'type' => 'textarea', 'label' => __( 'Content', 'fw' ), 'desc' => __( 'Answer tags work; {all_fields} lists every answer. Basic formatting is kept, scripts are removed.', 'fw' ), 'value' => '{all_fields}' ),
			'action_create_post_image'   => array( 'type' => 'text', 'label' => __( 'Featured image from', 'fw' ), 'desc' => __( 'The name of a File Upload field (e.g. file_upload_photo). Its image is added to the Media Library.', 'fw' ), 'value' => '' ),
			'action_create_post_meta'    => array( 'type' => 'textarea', 'label' => __( 'Custom fields', 'fw' ), 'desc' => __( 'One per line: meta_key = {answer tag}, e.g. rating = {rating_stars}', 'fw' ), 'value' => '' ),
		);
	}

	private static function tags( $text, array $ctx, $mode = 'text' ) {
		return class_exists( 'FW_Forms_Merge_Tags' )
			? FW_Forms_Merge_Tags::replace( (string) $text, (array) ( $ctx['payload']['form_values'] ?? array() ), (array) ( $ctx['payload']['shortcode_to_item'] ?? array() ), $mode, class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::current_post_id() : 0 )
			: (string) $text;
	}

	public function run( array $ctx ) {
		$form = $ctx['form'];
		$type = (string) self::opt( $form, 'action_create_post_type', 'post' );
		if ( ! post_type_exists( $type ) || in_array( $type, self::SKIP_TYPES, true ) ) {
			return new WP_Error( 'fw_forms_post_type', 'Unknown post type ' . $type );
		}
		$status = in_array( self::opt( $form, 'action_create_post_status', 'draft' ), array( 'draft', 'pending', 'publish' ), true ) ? self::opt( $form, 'action_create_post_status', 'draft' ) : 'draft';
		$title  = sanitize_text_field( self::tags( self::opt( $form, 'action_create_post_title', '' ), $ctx ) );
		$body   = wp_kses_post( wpautop( self::tags( self::opt( $form, 'action_create_post_content', '{all_fields}' ), $ctx ) ) );
		$id     = wp_insert_post( array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => '' !== $title ? $title : __( 'Form submission', 'fw' ),
			'post_content' => $body,
			'post_author'  => get_current_user_id(),
		), true );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_fw_form_source', array( 'form_id' => (string) $ctx['form_id'], 'entry_id' => (int) ( $ctx['entry_id'] ?? 0 ) ) );

		foreach ( self::lines( self::opt( $form, 'action_create_post_meta', '' ) ) as $line ) {
			$p = array_map( 'trim', explode( '=', $line, 2 ) );
			if ( 2 === count( $p ) && '' !== sanitize_key( $p[0] ) && '_' !== substr( sanitize_key( $p[0] ), 0, 1 ) ) {
				update_post_meta( $id, sanitize_key( $p[0] ), sanitize_text_field( self::tags( $p[1], $ctx ) ) );
			}
		}

		$field = trim( (string) self::opt( $form, 'action_create_post_image', '' ) );
		$up    = '' !== $field ? ( $ctx['payload']['uploads'][ $field ] ?? null ) : null;
		if ( is_array( $up ) && ! empty( $up['file'] ) && is_file( $up['file'] ) && 0 === strpos( (string) ( $up['type'] ?? '' ), 'image/' ) ) {
			$att = self::to_media( $up, $id );
			if ( $att ) {
				set_post_thumbnail( $id, $att );
			}
		}

		/** Fires after a form created a post. */
		do_action( 'fw_ext_forms_post_created', $id, $ctx );
		return null;
	}

	/** Copy a privately stored upload into the Media Library. @return int attachment id or 0 */
	private static function to_media( array $up, $post_id ) {
		$bits = wp_upload_bits( sanitize_file_name( (string) $up['name'] ), null, file_get_contents( $up['file'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! empty( $bits['error'] ) ) {
			return 0;
		}
		$type = wp_check_filetype( $bits['file'] );
		if ( 0 !== strpos( (string) $type['type'], 'image/' ) ) {
			wp_delete_file( $bits['file'] );
			return 0;
		}
		$att = wp_insert_attachment( array( 'post_mime_type' => $type['type'], 'post_title' => sanitize_file_name( pathinfo( $bits['file'], PATHINFO_FILENAME ) ), 'post_status' => 'inherit' ), $bits['file'], $post_id );
		if ( ! $att || is_wp_error( $att ) ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $bits['file'] ) );
		return (int) $att;
	}
}
