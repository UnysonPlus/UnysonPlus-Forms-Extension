<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr     name, id
 * @var array  $options
 * @var bool   $typed_ok
 * @var string $typed
 */
$id   = $attr['id'];
$name = $attr['name'];
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-signature" id="<?php echo esc_attr( $id ); ?>" data-fw-signature<?php echo ! empty( $options['info'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-info"' : ''; ?>>
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></legend>
		<div class="field-signature__pad" hidden data-fw-signature-pad>
			<canvas width="600" height="180" role="img" aria-label="<?php esc_attr_e( 'Signature drawing area', 'fw' ); ?>"></canvas>
			<button type="button" class="field-signature__clear" data-fw-signature-clear><?php esc_html_e( 'Clear', 'fw' ); ?></button>
		</div>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>[image]" value="" data-fw-signature-image>
		<?php if ( $typed_ok ) : ?>
			<label class="field-signature__typed" for="<?php echo esc_attr( $id ); ?>-typed">
				<span data-fw-signature-typed-label><?php esc_html_e( 'Type your full name to sign', 'fw' ); ?></span>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-typed" name="<?php echo esc_attr( $name ); ?>[typed]" value="<?php echo esc_attr( $typed ); ?>" autocomplete="name" maxlength="100">
			</label>
		<?php endif; ?>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $id ); ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
