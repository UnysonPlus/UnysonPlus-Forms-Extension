<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** @var array $atts */

if ( class_exists( 'FW_Forms_Views' ) ) {
	$atts = is_array( $atts ) ? $atts : array();
	$html = FW_Forms_Views::render( $atts );
	if ( '' !== $html && function_exists( 'sc_build_wrapper_attr' ) ) {
		$atts['css_class'] = trim( 'fw-entry-view-wrap ' . ( $atts['css_class'] ?? '' ) );
		echo '<div ' . fw_attr_to_html( sc_build_wrapper_attr( $atts ) ) . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	} else {
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
	}
}
