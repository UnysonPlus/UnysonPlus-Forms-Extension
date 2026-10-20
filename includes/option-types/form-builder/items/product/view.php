<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr     name, id
 * @var array  $options
 * @var string $mode     single | choices
 * @var float  $price
 * @var array  $choices  [ { name, price } ]
 * @var string $display  radio | select
 * @var string $quantity fixed | checkbox | box
 * @var int    $qty_min
 * @var int    $qty_max
 * @var array  $value    { choice, qty, buy }
 * @var string $per      "/ month" for a recurring product, else ''
 * @var string $interval '' | week | month | year
 */
$name   = $attr['name'];
$id     = $attr['id'];
$req    = ! empty( $options['required'] );
$money  = function ( $n ) use ( $per ) {
	return trim( FW_Forms_Money::format( $n ) . ' ' . $per );
};
$legend = fw_htmlspecialchars( $options['label'] ?? '' ) . ( $req ? '<sup>*</sup>' : '' );
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-product" id="<?php echo esc_attr( $id ); ?>" data-fw-field="<?php echo esc_attr( $name ); ?>" data-fw-product data-mode="<?php echo esc_attr( $mode ); ?>" data-quantity="<?php echo esc_attr( $quantity ); ?>" data-price="<?php echo esc_attr( $price ); ?>" data-interval="<?php echo esc_attr( $interval ); ?>" data-per="<?php echo esc_attr( $per ); ?>"<?php echo ! empty( $options['info'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-info"' : ''; ?>>
		<legend><?php echo $legend; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?></legend>

		<?php if ( 'single' === $mode ) : ?>
			<p class="field-product__price"><?php echo esc_html( call_user_func( $money, $price ) ); ?></p>
		<?php elseif ( 'select' === $display ) : ?>
			<select name="<?php echo esc_attr( $name ); ?>[choice]" aria-label="<?php echo esc_attr( wp_strip_all_tags( $options['label'] ?? '' ) ); ?>"<?php echo $req ? ' required' : ''; ?>>
				<option value=""><?php esc_html_e( 'Choose…', 'fw' ); ?></option>
				<?php foreach ( $choices as $c ) : ?>
					<option value="<?php echo esc_attr( $c['name'] ); ?>" data-price="<?php echo esc_attr( $c['price'] ); ?>"<?php selected( $c['name'], $value['choice'] ); ?>><?php echo esc_html( $c['name'] . ' — ' . call_user_func( $money, $c['price'] ) ); ?></option>
				<?php endforeach; ?>
			</select>
		<?php else : ?>
			<div class="field-product__choices">
				<?php foreach ( $choices as $i => $c ) : ?>
					<label class="field-product__choice" for="<?php echo esc_attr( $id . '-' . $i ); ?>">
						<input type="radio" id="<?php echo esc_attr( $id . '-' . $i ); ?>" name="<?php echo esc_attr( $name ); ?>[choice]" value="<?php echo esc_attr( $c['name'] ); ?>" data-price="<?php echo esc_attr( $c['price'] ); ?>"<?php checked( $c['name'], $value['choice'] ); ?><?php echo $req ? ' required' : ''; ?>>
						<span class="field-product__name"><?php echo esc_html( $c['name'] ); ?></span>
						<span class="field-product__each"><?php echo esc_html( call_user_func( $money, $c['price'] ) ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'box' === $quantity ) : ?>
			<label class="field-product__qty" for="<?php echo esc_attr( $id ); ?>-qty"><span><?php esc_html_e( 'Quantity', 'fw' ); ?></span>
				<input type="number" id="<?php echo esc_attr( $id ); ?>-qty" name="<?php echo esc_attr( $name ); ?>[qty]" min="<?php echo (int) $qty_min; ?>" max="<?php echo (int) $qty_max; ?>" step="1" inputmode="numeric" value="<?php echo (int) $value['qty']; ?>"></label>
		<?php elseif ( 'checkbox' === $quantity ) : ?>
			<label class="field-product__add" for="<?php echo esc_attr( $id ); ?>-buy">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>-buy" name="<?php echo esc_attr( $name ); ?>[buy]" value="1"<?php checked( $value['buy'] ); ?>>
				<span><?php esc_html_e( 'Add to my order', 'fw' ); ?></span></label>
		<?php else : ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[buy]" value="1">
		<?php endif; ?>

		<?php if ( 'box' === $quantity ) : // the line total adds something only when a quantity is typed ?>
			<p class="field-product__line" data-fw-line aria-live="polite"></p>
		<?php endif; ?>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info" id="<?php echo esc_attr( $id ); ?>-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
