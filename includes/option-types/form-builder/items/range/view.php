<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr
 * @var array  $options
 * @var string $display the value as shown, with prefix / suffix
 */
if ( ! empty( $options['info'] ) ) {
	$attr['aria-describedby'] = $attr['id'] . '-info';
}
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<div class="field-range">
		<label for="<?php echo esc_attr( $attr['id'] ); ?>"><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></label>
		<div class="field-range__row">
			<input <?php echo fw_attr_to_html( $attr ); ?>>
			<output class="field-range__value" for="<?php echo esc_attr( $attr['id'] ); ?>" aria-hidden="true"><?php echo esc_html( $display ); ?></output>
		</div>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $attr['id'] ); ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</div>
</div>
