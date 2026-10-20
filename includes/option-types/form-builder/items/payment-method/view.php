<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr    name, id
 * @var array  $options
 * @var array  $methods id => label (offered and ready)
 * @var string $value
 */
$id = $attr['id'];
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-payment-method" id="<?php echo esc_attr( $id ); ?>"<?php echo ! empty( $options['info'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-info"' : ''; ?>>
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></legend>
		<div class="field-payment-method__choices">
			<?php foreach ( $methods as $mid => $label ) : ?>
				<label class="field-payment-method__choice field-payment-method__choice--<?php echo esc_attr( $mid ); ?>" for="<?php echo esc_attr( $id . '-' . $mid ); ?>">
					<input type="radio" id="<?php echo esc_attr( $id . '-' . $mid ); ?>" name="<?php echo esc_attr( $attr['name'] ); ?>" value="<?php echo esc_attr( $mid ); ?>"<?php checked( $mid, $value ); ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $id ); ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
