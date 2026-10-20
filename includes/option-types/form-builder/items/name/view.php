<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr    name, id
 * @var array  $options
 * @var string $format  full | first_last
 * @var array  $parts   submitted values (after a failed submit)
 */
$name     = $attr['name'];
$id       = $attr['id'];
$required = ! empty( $options['required'] );
$info_id  = ! empty( $options['info'] ) ? $id . '-info' : '';
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<?php if ( 'first_last' === $format ) : ?>
		<fieldset class="field-name field-name--split">
			<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( $required ) : ?><sup>*</sup><?php endif; ?></legend>
			<div class="field-name__parts">
				<label class="field-name__part" for="<?php echo esc_attr( $id ); ?>-first">
					<span><?php echo esc_html( $options['first_label'] ?? __( 'First name', 'fw' ) ); ?></span>
					<input type="text" id="<?php echo esc_attr( $id ); ?>-first" name="<?php echo esc_attr( $name ); ?>[first]" value="<?php echo esc_attr( $parts['first'] ?? '' ); ?>" autocomplete="given-name" maxlength="100"<?php echo $required ? ' required' : ''; ?><?php echo $info_id ? ' aria-describedby="' . esc_attr( $info_id ) . '"' : ''; ?>>
				</label>
				<label class="field-name__part" for="<?php echo esc_attr( $id ); ?>-last">
					<span><?php echo esc_html( $options['last_label'] ?? __( 'Last name', 'fw' ) ); ?></span>
					<input type="text" id="<?php echo esc_attr( $id ); ?>-last" name="<?php echo esc_attr( $name ); ?>[last]" value="<?php echo esc_attr( $parts['last'] ?? '' ); ?>" autocomplete="family-name" maxlength="100"<?php echo $required ? ' required' : ''; ?>>
				</label>
			</div>
			<?php if ( $info_id ) : ?><p class="field-info" id="<?php echo esc_attr( $info_id ); ?>"><em><?php echo esc_html( $options['info'] ); ?></em></p><?php endif; ?>
		</fieldset>
	<?php else : ?>
		<div class="field-text field-name">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( $required ) : ?><sup>*</sup><?php endif; ?></label>
			<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $parts['full'] ?? '' ); ?>" autocomplete="name" maxlength="100"<?php echo $required ? ' required' : ''; ?><?php echo $info_id ? ' aria-describedby="' . esc_attr( $info_id ) . '"' : ''; ?>>
			<?php if ( $info_id ) : ?><p class="field-info" id="<?php echo esc_attr( $info_id ); ?>"><em><?php echo esc_html( $options['info'] ); ?></em></p><?php endif; ?>
		</div>
	<?php endif; ?>
</div>
