<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var array $attr
 */
?>
<input <?php echo fw_attr_to_html( $attr ); ?>>
