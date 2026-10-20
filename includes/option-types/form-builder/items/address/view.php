<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var array $attr    name, id
 * @var array $options
 * @var array $shown   part keys, in order
 * @var array $parts   submitted values
 * @var array $labels  part => label
 */
$name     = $attr['name'];
$id       = $attr['id'];
$required = ! empty( $options['required'] );
$must     = array( 'street', 'city', 'country' );
$tokens   = FW_Option_Type_Form_Builder_Item_Address::PARTS;
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-address">
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( $required ) : ?><sup>*</sup><?php endif; ?></legend>
		<div class="field-address__parts">
			<?php foreach ( $shown as $k ) : ?>
				<label class="field-address__part field-address__part--<?php echo esc_attr( $k ); ?>" for="<?php echo esc_attr( $id . '-' . $k ); ?>">
					<span><?php echo esc_html( $labels[ $k ] ); ?></span>
					<input type="text" id="<?php echo esc_attr( $id . '-' . $k ); ?>" name="<?php echo esc_attr( $name . '[' . $k . ']' ); ?>"
						value="<?php echo esc_attr( $parts[ $k ] ?? '' ); ?>" autocomplete="<?php echo esc_attr( $tokens[ $k ] ); ?>" maxlength="200"
						<?php echo ( $required && in_array( $k, $must, true ) ) ? 'required' : ''; ?>>
				</label>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
