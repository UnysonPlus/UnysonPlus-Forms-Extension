<?php if (!defined('FW')) die('Forbidden');
/**
 * @var array $form_values
 * @var array $shortcode_to_item
 */

?>

<table border="0" cellpadding="10">
	<tbody>
	<?php foreach ($form_values as $shortcode => $form_value): ?>
		<?php

		if ( ! isset( $shortcode_to_item[ $shortcode ] ) ) {
			continue;
		}

		$item = &$shortcode_to_item[$shortcode];

		if ( ! isset( $item['options'] ) || ! isset( $item['type'] ) ) {
			continue;
		}

		$item_options = &$item['options'];

		$title = isset( $item_options['label'] ) ? fw_htmlspecialchars( (string) $item_options['label'] ) : '';
		$value = '';

		switch ( $item['type'] ) {
			case 'checkboxes':
				if ( ! is_array( $form_value ) || empty( $form_value ) ) {
					break;
				}

				$value = implode( ', ', array_map( 'strval', $form_value ) );
				break;
			case 'textarea':
				$value = '<pre style="font-family:arial,sans-serif;font-size:100%;">'. fw_htmlspecialchars( (string) ( $form_value ?? '' ) ) .'</pre>';
				break;
			case 'recaptcha':
			case 'honeypot':
				continue 2;
			case 'file-upload':
				$value = ( $form_value !== '' && $form_value !== null )
					? fw_htmlspecialchars( (string) $form_value ) . ' <em>(' . esc_html__( 'attached', 'fw' ) . ')</em>'
					: '&mdash;';
				break;
			default:
				if ( is_array( $form_value ) ) {
					$value = '<pre>'. fw_htmlspecialchars( print_r( $form_value, true ) ) .'</pre>';
				} else {
					$value = fw_htmlspecialchars( (string) ( $form_value ?? '' ) );
				}
		}
		?>
		<tr>
			<td valign="top"><b><?php echo $title ?></b></td>
			<td valign="top"><?php echo $value ?></td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>