<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr
 * @var array  $options
 * @var string $button
 */
$id = $attr['id'];
$attr['aria-describedby'] = $id . '-status' . ( ! empty( $options['info'] ) ? ' ' . $id . '-info' : '' );
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<div class="field-coupon" data-fw-field="<?php echo esc_attr( $attr['name'] ); ?>" data-fw-coupon data-endpoint="<?php echo esc_url( rest_url( 'fw-forms/v1/coupon' ) ); ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></label>
		<div class="field-coupon__row">
			<input <?php echo fw_attr_to_html( $attr ); // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<button type="button" class="field-coupon__apply" data-fw-coupon-apply><?php echo esc_html( $button ); ?></button>
		</div>
		<p class="field-coupon__status" id="<?php echo esc_attr( $id ); ?>-status" role="status" aria-live="polite"></p>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $id ); ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</div>
</div>
