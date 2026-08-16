<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var int $form_id
 * @var string $submit_button_text
 * @var array $extra_data
 */

// Style-tab button options (Contact Form) arrive via $extra_data. Reuse the same
// theme button classes the Button shortcode emits: base `.btn` + a color-preset
// style (`btn-primary`, `btn-outline-primary`, …) + an optional size preset
// (`btn-{slug}`) + shape override + full-width. When nothing is set the input
// falls back to the theme's bare `.btn` styling.
$cf_bd    = isset( $extra_data ) && is_array( $extra_data ) ? $extra_data : array();
$cf_btn   = array( 'btn' );
if ( ! empty( $cf_bd['button_style'] ) ) { $cf_btn[] = (string) $cf_bd['button_style']; }
if ( ! empty( $cf_bd['button_size'] ) )  { $cf_btn[] = (string) $cf_bd['button_size']; }
if ( ! empty( $cf_bd['button_shape'] ) && in_array( $cf_bd['button_shape'], array( 'pill', 'rounded', 'square' ), true ) ) {
	$cf_btn[] = 'btn-shape-' . $cf_bd['button_shape'];
}
$cf_full  = ! empty( $cf_bd['button_full'] );
if ( $cf_full ) { $cf_btn[] = 'w-100'; }
$cf_btn   = implode( ' ', array_values( array_unique( array_filter( array_map( 'sanitize_html_class', $cf_btn ) ) ) ) );

// Alignment wrapper (ignored when full-width — a w-100 button already fills the row).
$cf_align = isset( $cf_bd['button_align'] ) ? (string) $cf_bd['button_align'] : 'left';
$cf_wrap_style = ( ! $cf_full && in_array( $cf_align, array( 'center', 'right' ), true ) )
	? ' style="text-align:' . esc_attr( $cf_align ) . ';"'
	: '';
?>
<div class="sc-cf-submit"<?php echo $cf_wrap_style; ?>>
	<input type="submit" class="<?php echo esc_attr( $cf_btn ); ?>" value="<?php echo esc_attr( $submit_button_text ) ?>"/>
</div>