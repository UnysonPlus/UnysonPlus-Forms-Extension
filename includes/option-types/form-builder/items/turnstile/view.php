<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr
 * @var string $label
 */
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<div class="field-captcha">
		<?php if ( '' !== $label ) : ?><p class="field-captcha__label"><?php echo esc_html( $label ); ?></p><?php endif; ?>
		<div <?php echo fw_attr_to_html( $attr ); ?>></div>
	</div>
</div>
