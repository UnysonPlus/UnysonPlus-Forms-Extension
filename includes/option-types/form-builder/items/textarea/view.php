<?php if (!defined('FW')) die('Forbidden');
/**
 * @var array $item
 * @var array $attr
 */

$options = $item['options'];
?>
<div class="<?php echo esc_attr(fw_ext_builder_get_item_width('form-builder', $item['width'] .'/frontend_class')) ?>">
	<div class="field-textarea">
		<?php if ($options['label']): ?>
		<label for="<?php echo esc_attr($attr['id']) ?>"><?php echo fw_htmlspecialchars($options['label']) ?>
			<?php if ($options['required']): ?><sup>*</sup><?php endif; ?>
		</label>
		<?php endif; ?>
		<?php if ( ! empty( $options['info'] ) && ! empty( $attr['id'] ) ) { $attr['aria-describedby'] = $attr['id'] . '-info'; } ?>
		<textarea <?php echo fw_attr_to_html($attr) ?>><?php echo fw_htmlspecialchars($value) ?></textarea>
		<?php if ($options['info']): ?>
			<p class="field-info" id="<?php echo esc_attr( $attr['id'] ) ?>-info"><em><?php echo esc_html( $options['info'] ) ?></em></p>
		<?php endif; ?>
	</div>
</div>