<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array   $item
 * @var array   $attr      name, id
 * @var array   $options
 * @var float[] $suggested
 * @var bool    $other_ok
 * @var float   $min
 * @var float   $max       0 = no limit
 * @var array   $value     { choice, other }
 */
$name = $attr['name'];
$id   = $attr['id'];
$req  = ! empty( $options['required'] );
$sym  = FW_Forms_Money::symbol();
$is_other = 'other' === $value['choice'] || ( '' === $value['choice'] && '' !== $value['other'] );
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-amount" id="<?php echo esc_attr( $id ); ?>" data-fw-field="<?php echo esc_attr( $name ); ?>" data-fw-amount<?php echo ! empty( $options['info'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-info"' : ''; ?>>
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( $req ) : ?><sup>*</sup><?php endif; ?></legend>
		<?php if ( $suggested ) : ?>
			<div class="field-amount__choices">
				<?php foreach ( $suggested as $i => $s ) : ?>
					<label class="field-amount__choice" for="<?php echo esc_attr( $id . '-' . $i ); ?>">
						<input type="radio" id="<?php echo esc_attr( $id . '-' . $i ); ?>" name="<?php echo esc_attr( $name ); ?>[choice]" value="<?php echo esc_attr( $s ); ?>"<?php checked( ! $is_other && (float) $value['choice'] === (float) $s ); ?>>
						<span><?php echo esc_html( FW_Forms_Money::format( $s ) ); ?></span>
					</label>
				<?php endforeach; ?>
				<?php if ( $other_ok ) : ?>
					<label class="field-amount__choice" for="<?php echo esc_attr( $id ); ?>-o">
						<input type="radio" id="<?php echo esc_attr( $id ); ?>-o" name="<?php echo esc_attr( $name ); ?>[choice]" value="other"<?php checked( $is_other ); ?>>
						<span><?php esc_html_e( 'Other', 'fw' ); ?></span>
					</label>
				<?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $other_ok ) : ?>
			<label class="field-amount__other" for="<?php echo esc_attr( $id ); ?>-amount"<?php echo $suggested && ! $is_other ? ' hidden' : ''; ?>>
				<span class="screen-reader-text"><?php esc_html_e( 'Your amount', 'fw' ); ?></span>
				<span class="field-amount__symbol" aria-hidden="true"><?php echo esc_html( trim( $sym ) ); ?></span>
				<input type="text" id="<?php echo esc_attr( $id ); ?>-amount" name="<?php echo esc_attr( $name ); ?>[other]" inputmode="decimal" autocomplete="off" value="<?php echo esc_attr( $value['other'] ); ?>"
					data-min="<?php echo esc_attr( $min ); ?>"<?php echo $max ? ' data-max="' . esc_attr( $max ) . '"' : ''; ?>>
			</label>
		<?php endif; ?>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $id ); ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
