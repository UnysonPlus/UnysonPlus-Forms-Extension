<?php if ( ! defined( 'FW' ) ) die( 'Forbidden' );
/**
 * @var array $item
 * @var array $attr
 */

$options = $item['options'];
?>
<div class="fw-form-honeypot" aria-hidden="true" style="position:absolute !important;left:-9999px !important;top:auto !important;width:1px !important;height:1px !important;overflow:hidden !important;">
	<label for="<?php echo esc_attr( $attr['id'] ); ?>"><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?></label>
	<input <?php echo fw_attr_to_html( $attr ); ?>>
</div>
