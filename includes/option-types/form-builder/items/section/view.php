<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $options
 * @var string $level h3 | h4 | h5
 */
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', '1_1/frontend_class' ) ); ?>">
	<div class="field-section">
		<?php if ( '' !== trim( (string) ( $options['label'] ?? '' ) ) ) : ?>
			<<?php echo tag_escape( $level ); ?> class="field-section__title"><?php echo esc_html( $options['label'] ); ?></<?php echo tag_escape( $level ); ?>>
		<?php endif; ?>
		<?php if ( '' !== trim( (string) ( $options['text'] ?? '' ) ) ) : ?>
			<p class="field-section__text"><?php echo nl2br( esc_html( $options['text'] ) ); ?></p>
		<?php endif; ?>
	</div>
</div>
