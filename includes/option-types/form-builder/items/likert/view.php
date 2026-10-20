<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var array $item
 * @var array $attr    name, id
 * @var array $options
 * @var array $rows    statements
 * @var array $scale   points
 * @var array $chosen  statement => point (after a failed submit)
 */
$required = ! empty( $options['required'] );
?>
<div class="<?php echo esc_attr( fw_ext_builder_get_item_width( 'form-builder', $item['width'] . '/frontend_class' ) ); ?>">
	<fieldset class="field-likert">
		<legend><?php echo fw_htmlspecialchars( $options['label'] ?? '' ); ?><?php if ( $required ) : ?><sup>*</sup><?php endif; ?></legend>
		<table class="field-likert__table">
			<thead>
				<tr>
					<td></td>
					<?php foreach ( $scale as $point ) : ?><th scope="col"><?php echo esc_html( $point ); ?></th><?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $r => $row ) : ?>
					<tr>
						<th scope="row" id="<?php echo esc_attr( $attr['id'] . '-r' . $r ); ?>"><?php echo esc_html( $row ); ?></th>
						<?php foreach ( $scale as $c => $point ) : $rid = $attr['id'] . '-' . $r . '-' . $c; ?>
							<td data-point="<?php echo esc_attr( $point ); ?>">
								<input type="radio" id="<?php echo esc_attr( $rid ); ?>" name="<?php echo esc_attr( $attr['name'] . '[' . $row . ']' ); ?>" value="<?php echo esc_attr( $point ); ?>"
									<?php checked( isset( $chosen[ $row ] ) && (string) $chosen[ $row ] === $point ); ?><?php echo $required ? ' required' : ''; ?>>
								<label for="<?php echo esc_attr( $rid ); ?>" class="field-likert__label"><span class="screen-reader-text"><?php echo esc_html( $row . ': ' ); ?></span><span class="field-likert__point"><?php echo esc_html( $point ); ?></span></label>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( ! empty( $options['info'] ) ) : ?>
			<p class="field-info"><em><?php echo esc_html( $options['info'] ); ?></em></p>
		<?php endif; ?>
	</fieldset>
</div>
