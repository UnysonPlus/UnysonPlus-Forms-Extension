<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array    $item
 * @var array    $attr    name, id
 * @var array    $options
 * @var int      $min
 * @var int      $max
 * @var string   $style   stars | pills
 * @var int|null $current
 */
$name = $attr['name'];
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-rating fw-rating fw-rating--<?php echo esc_attr( $style ); ?>" id="<?php echo esc_attr( $attr['id'] ); ?>">
		<?php if ( ! empty( $options['label'] ) ) : ?>
			<legend><?php echo fw_htmlspecialchars( $options['label'] ); ?>
				<?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?>
			</legend>
		<?php endif; ?>
		<?php
		// Stars are emitted HIGHEST FIRST and flipped back with flex row-reverse, so
		// "every star up to the chosen one" is expressible in CSS as the checked
		// input's LATER siblings. Pills read left-to-right and need no trick.
		$points = range( $min, $max );
		if ( 'stars' === $style ) { $points = array_reverse( $points ); }
		?>
		<div class="fw-rating__scale">
		<div class="fw-rating__points" role="radiogroup">
			<?php foreach ( $points as $i ) : $id = $attr['id'] . '-' . $i; ?>
				<input type="radio" class="fw-rating__input" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" value="<?php echo (int) $i; ?>"
					<?php checked( $current, $i ); ?> <?php echo ! empty( $options['required'] ) ? 'required' : ''; ?>>
				<label class="fw-rating__point" for="<?php echo esc_attr( $id ); ?>" title="<?php echo (int) $i; ?>">
					<?php if ( 'stars' === $style ) : ?>
						<span class="fw-rating__star" aria-hidden="true">&#9733;</span><span class="screen-reader-text"><?php echo (int) $i; ?></span>
					<?php else : ?>
						<?php echo (int) $i; ?>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php if ( ! empty( $options['low_label'] ) || ! empty( $options['high_label'] ) ) : ?>
			<div class="fw-rating__ends">
				<span><?php echo esc_html( $options['low_label'] ?? '' ); ?></span>
				<span><?php echo esc_html( $options['high_label'] ?? '' ); ?></span>
			</div>
		<?php endif; ?>
		</div>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $attr['id'] ) ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
