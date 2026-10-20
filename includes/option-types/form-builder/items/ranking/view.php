<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var array $attr     name, id
 * @var array $options
 * @var array $choices  option texts
 * @var array $order    option texts in the current order
 */
$n = count( $choices );
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-ranking" data-fw-ranking>
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></legend>
		<ol class="field-ranking__list">
			<?php foreach ( $order as $pos => $text ) : $sid = $attr['id'] . '-' . $pos; ?>
				<li class="field-ranking__item">
					<label class="field-ranking__text" for="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $text ); ?></label>
					<select class="field-ranking__pos" id="<?php echo esc_attr( $sid ); ?>" name="<?php echo esc_attr( $attr['name'] . '[' . $text . ']' ); ?>">
						<?php for ( $i = 1; $i <= $n; $i++ ) : ?>
							<option value="<?php echo (int) $i; ?>" <?php selected( $pos + 1, $i ); ?>><?php echo (int) $i; ?></option>
						<?php endfor; ?>
					</select>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
