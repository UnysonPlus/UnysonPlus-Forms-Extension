<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var array $attr
 * @var array $options
 */
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<div class="field-consent">
		<label for="<?php echo esc_attr( $attr['id'] ); ?>">
			<input <?php echo fw_attr_to_html( $attr ); ?>>
			<span><?php echo wp_kses( (string) ( $options['label'] ?? '' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array(), 'em' => array(), 'br' => array() ) ); ?>
				<?php if ( ! empty( $attr['required'] ) ) : ?><sup>*</sup><?php endif; ?>
			</span>
		</label>
	</div>
</div>
