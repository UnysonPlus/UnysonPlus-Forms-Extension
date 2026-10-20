<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr     name, id
 * @var array  $options
 * @var array  $choices  [ { caption, url } ]
 * @var bool   $multiple
 * @var string $columns  2 | 3 | 4
 * @var array  $chosen   submitted captions
 */
$name     = $attr['name'] . ( $multiple ? '[]' : '' );
$type     = $multiple ? 'checkbox' : 'radio';
$required = ! empty( $options['required'] ) && ! $multiple;
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-image-choice">
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></legend>
		<div class="field-image-choice__grid field-image-choice__grid--<?php echo esc_attr( $columns ); ?>">
			<?php foreach ( $choices as $i => $c ) : $cid = $attr['id'] . '-' . $i; ?>
				<div class="field-image-choice__item">
					<input type="<?php echo esc_attr( $type ); ?>" class="field-image-choice__input" id="<?php echo esc_attr( $cid ); ?>" name="<?php echo esc_attr( $name ); ?>"
						value="<?php echo esc_attr( $c['caption'] ); ?>" <?php checked( in_array( $c['caption'], $chosen, true ) ); ?><?php echo $required ? ' required' : ''; ?>>
					<label class="field-image-choice__card" for="<?php echo esc_attr( $cid ); ?>">
						<?php if ( '' !== $c['url'] ) : ?>
							<img src="<?php echo esc_url( $c['url'] ); ?>" alt="" loading="lazy" decoding="async">
						<?php endif; ?>
						<span class="field-image-choice__caption"><?php echo esc_html( $c['caption'] ); ?></span>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
