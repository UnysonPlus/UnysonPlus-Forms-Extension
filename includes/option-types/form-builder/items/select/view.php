<?php if (!defined('FW')) die('Forbidden');
/**
 * @var array $item
 * @var array $choices
 * @var array $attr
 * @var string $value
 */

$options = $item['options'];
?>
<?php if (empty($choices)): ?>
	<!-- select not displayed: no choices -->
<?php else: ?>
	<div class="<?php echo esc_attr(fw_ext_builder_get_item_width('form-builder', $item['width'] .'/frontend_class')) ?>">
		<div class="field-select select-styled">
			<?php if ($options['label']): ?>
			<label for="<?php echo esc_attr($attr['id']) ?>"><?php echo fw_htmlspecialchars($options['label']) ?>
				<?php if ($options['required']): ?><sup>*</sup><?php endif; ?>
			</label>
			<?php endif; ?>
			<?php if ( ! empty( $options['info'] ) && ! empty( $attr['id'] ) ) { $attr['aria-describedby'] = $attr['id'] . '-info'; } ?>
			<select <?php echo fw_attr_to_html($attr) ?> >
				<?php foreach ($choices as $choice): ?>
					<option <?php echo fw_attr_to_html($choice) ?> ><?php echo $choice['value'] ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ($options['info']): ?>
				<p class="field-info" id="<?php echo esc_attr( $attr['id'] ) ?>-info"><em><?php echo esc_html( $options['info'] ) ?></em></p>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>