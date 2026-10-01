<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Hidden field — a value that travels with the submission without being shown.
 *
 * Sources:
 *  - static      a fixed value set in the builder;
 *  - url_param   a query-string parameter on the page (utm_source, ref, …);
 *  - page_url    the page the form was on;
 *  - page_title  its title.
 *
 * Anything a browser sends can be edited by the person sending it, so a hidden
 * value is never TRUSTED — it is context, not authority. Treat it like any
 * other field the visitor typed: useful for reporting and routing, never as a
 * basis for a security decision. It is sanitised on the way in and that is all.
 */
class FW_Option_Type_Form_Builder_Item_Hidden extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'hidden';
	}

	protected function title() {
		return __( 'Hidden', 'fw' );
	}

	protected function tip() {
		return __( 'Add a Hidden field (tracking, source, campaign…)', 'fw' );
	}

	public function get_options() {
		return array(
			array(
				'g_hidden' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'label' => array(
								'type'  => 'text',
								'label' => __( 'Name', 'fw' ),
								'desc'  => __( 'Only you see this — it is the column heading in Entries and the email.', 'fw' ),
								'value' => __( 'Source', 'fw' ),
							),
						),
						array(
							'source' => array(
								'type'    => 'radio',
								'label'   => __( 'Value comes from', 'fw' ),
								'value'   => 'static',
								'choices' => array(
									'static'     => __( 'A fixed value', 'fw' ),
									'url_param'  => __( 'A URL parameter on the page (e.g. utm_source)', 'fw' ),
									'page_url'   => __( 'The page the form is on', 'fw' ),
									'page_title' => __( 'The title of the page the form is on', 'fw' ),
								),
							),
						),
						array(
							'value' => array(
								'type'  => 'text',
								'label' => __( 'Fixed value / parameter name', 'fw' ),
								'desc'  => __( 'For a fixed value: the value itself. For a URL parameter: its name, e.g. utm_source.', 'fw' ),
								'value' => '',
							),
						),
					),
				),
			),
			$this->get_extra_options(),
		);
	}

	/**
	 * The value this field should carry right now.
	 *
	 * @param array $o Item options.
	 *
	 * @return string
	 */
	private function resolve( array $o ) {
		$source = $o['source'] ?? 'static';
		$value  = (string) ( $o['value'] ?? '' );

		switch ( $source ) {
			case 'url_param':
				$key = sanitize_key( $value );

				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a tracking parameter, read only.
				return '' !== $key && isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';

			case 'page_url':
				return esc_url_raw( $this->page_url() );

			case 'page_title':
				$id = url_to_postid( $this->page_url() );

				return $id ? wp_strip_all_tags( get_the_title( $id ) ) : '';

			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * @return string
	 */
	private function page_url() {
		// On render this is the page itself; on submit the form posted back to
		// the same page, so the request URI is still it.
		return isset( $_SERVER['REQUEST_URI'] ) ? home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : home_url( '/' );
	}

	public function frontend_render( array $item, $input_value ) {
		$attr = array(
			'type'  => 'hidden',
			'name'  => $item['shortcode'] ?? '',
			'value' => $this->resolve( $item['options'] ),
		);

		return $this->view( $item, $attr );
	}

	public function frontend_validate( array $item, $input_value ) {
		return null; // Nothing a hidden field can fail.
	}

	public function get_value_from_item( $value ) {
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Hidden' );
