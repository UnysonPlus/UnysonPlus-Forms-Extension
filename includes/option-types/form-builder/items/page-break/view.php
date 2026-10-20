<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var array $attr    class, data-title, data-next, data-back
 * @var array $options
 */
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', '1_1/frontend_class' ) ); ?>">
	<div <?php echo fw_attr_to_html( $attr ); ?>>
		<?php if ( '' !== trim( (string) ( $options['label'] ?? '' ) ) ) : ?>
			<h3 class="fw-form-step-break__title"><?php echo esc_html( $options['label'] ); ?></h3>
		<?php endif; ?>
	</div>
</div>
