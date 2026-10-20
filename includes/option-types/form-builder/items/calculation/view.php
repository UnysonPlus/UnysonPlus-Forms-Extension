<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr    name, id
 * @var array  $options
 * @var bool   $shown
 * @var string $display the figure before the script works it out
 */
$o = $options;
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>"<?php echo $shown ? '' : ' data-fw-calc-hidden hidden'; ?>>
	<div class="field-calculation" id="<?php echo esc_attr( $attr['id'] ); ?>" data-fw-field="<?php echo esc_attr( $attr['name'] ); ?>" data-fw-calc
		data-formula="<?php echo esc_attr( (string) ( $o['formula'] ?? '' ) ); ?>"
		data-format="<?php echo esc_attr( 'number' === ( $o['format'] ?? 'money' ) ? 'number' : 'money' ); ?>"
		data-decimals="<?php echo esc_attr( (int) ( $o['decimals'] ?? 0 ) ); ?>"
		data-prefix="<?php echo esc_attr( (string) ( $o['prefix'] ?? '' ) ); ?>"
		data-suffix="<?php echo esc_attr( (string) ( $o['suffix'] ?? '' ) ); ?>"
		data-add="<?php echo esc_attr( 'yes' === ( $o['add_to_total'] ?? 'no' ) ? 'yes' : 'no' ); ?>">
		<span class="field-calculation__label" id="<?php echo esc_attr( $attr['id'] ); ?>-l"><?php echo fw_htmlspecialchars( $o['label'] ?? '' ); ?></span>
		<output class="field-calculation__value" aria-labelledby="<?php echo esc_attr( $attr['id'] ); ?>-l" aria-live="polite"><?php echo esc_html( $display ); ?></output>
		<input type="hidden" name="<?php echo esc_attr( $attr['name'] ); ?>" value="">
		<?php if ( ! empty( $o['info'] ) ) : ?>
			<p class="field-info"><em><?php echo esc_html( $o['info'] ); ?></em></p>
		<?php endif; ?>
	</div>
</div>
