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

				/**
				 * Escape each submitted choice, like every other branch here.
				 *
				 * $value is echoed WITHOUT escaping below, because the branches
				 * build HTML (<pre>, <em>) — so escaping is each branch's own
				 * responsibility. This one did not, making it the single
				 * asymmetric branch in the file.
				 *
				 * Not currently exploitable: the checkboxes item validates the
				 * submitted array against its declared choices server-side
				 * (frontend_validate() rejects "not existing choices"), so an
				 * attacker cannot get arbitrary markup in here today. It is
				 * escaped anyway because that guarantee lives in a different
				 * file, nothing at this line signals the dependency, and the
				 * consequence if it is ever relaxed is HTML injected into an
				 * email sent to the site owner.
				 */
				$value = implode(
					', ',
					array_map(
						'fw_htmlspecialchars',
						array_map( 'strval', $form_value )
					)
				);
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