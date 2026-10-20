<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array  $item
 * @var array  $attr        name, id
 * @var array  $options
 * @var array  $fields      [ { key, label, type, required } ]
 * @var array  $rows        submitted copies (or empty ones)
 * @var int    $min
 * @var int    $max
 * @var string $item_title  "Guest"
 * @var string $add_label
 */
$types = array( 'text' => 'text', 'email' => 'email', 'phone' => 'tel', 'number' => 'number', 'date' => 'date' );
$auto  = array( 'email' => 'email', 'phone' => 'tel' );
$copy  = function ( $n, array $row, $id_base ) use ( $attr, $fields, $types, $auto, $item_title ) {
	ob_start();
	?>
	<div class="field-repeater__row" data-fw-repeat-row>
		<div class="field-repeater__head">
			<span class="field-repeater__title" data-fw-repeat-title><?php echo esc_html( trim( $item_title . ' ' . ( $n + 1 ) ) ); ?></span>
			<button type="button" class="field-repeater__remove" data-fw-repeat-remove hidden><?php esc_html_e( 'Remove', 'fw' ); ?></button>
		</div>
		<div class="field-repeater__fields">
			<?php foreach ( $fields as $i => $f ) : $fid = $id_base . '-' . $n . '-' . $i; ?>
				<label class="field-repeater__field" for="<?php echo esc_attr( $fid ); ?>">
					<span><?php echo esc_html( $f['label'] ); ?><?php if ( $f['required'] ) : ?><sup>*</sup><?php endif; ?></span>
					<input type="<?php echo esc_attr( $types[ $f['type'] ] ); ?>" id="<?php echo esc_attr( $fid ); ?>"
						name="<?php echo esc_attr( $attr['name'] . '[' . $n . '][' . $f['key'] . ']' ); ?>"
						value="<?php echo esc_attr( $row[ $f['key'] ] ?? '' ); ?>" maxlength="300"
						<?php echo isset( $auto[ $f['type'] ] ) ? 'autocomplete="' . esc_attr( $auto[ $f['type'] ] ) . '"' : ''; ?>
						<?php echo 'phone' === $f['type'] ? 'inputmode="tel"' : ''; ?>
						<?php echo $f['required'] ? 'required' : ''; ?>>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
};
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-repeater" data-fw-repeater data-min="<?php echo (int) $min; ?>" data-max="<?php echo (int) $max; ?>" data-name="<?php echo esc_attr( $attr['name'] ); ?>">
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( ! empty( $options['required'] ) ) : ?><sup>*</sup><?php endif; ?></legend>
		<div class="field-repeater__rows">
			<?php foreach ( $rows as $n => $row ) { echo $copy( $n, $row, $attr['id'] ); } // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
		</div>
		<template data-fw-repeat-template><?php echo $copy( 0, array(), $attr['id'] . '-t' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?></template>
		<button type="button" class="field-repeater__add" data-fw-repeat-add hidden>+ <?php echo esc_html( $add_label ); ?></button>
		<p class="screen-reader-text" aria-live="polite" data-fw-repeat-live></p>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
