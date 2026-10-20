<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}
/**
 * @var string $form_id
 * @var string $form_html
 * @var array $extra_data
 */
?>
<?php $fw_confirm = ( isset( $extra_data['confirmation_type'] ) && 'hide' === $extra_data['confirmation_type'] ) ? 'hide' : 'message'; ?>
<?php $fw_layout = ( isset( $extra_data['form_layout'] ) && 'conversational' === $extra_data['form_layout'] ) ? 'conversational' : 'standard'; ?>
<?php $fw_seats = ! empty( $extra_data['_seats'] ) && is_array( $extra_data['_seats'] ) ? ' data-fw-seats="' . esc_attr( wp_json_encode( $extra_data['_seats'] ) ) . '"' : ''; ?>
<?php $fw_editing = class_exists( 'FW_Forms_Entry_Edit' ) && FW_Forms_Entry_Edit::editing( $form_id ); ?>
<?php $fw_seats .= ! $fw_editing && ( isset( $extra_data['drafts_enabled'] ) && 'yes' === $extra_data['drafts_enabled'] ) ? ' data-fw-drafts' : ''; ?>
<?php $fw_partial = ! $fw_editing && isset( $extra_data['partial_enabled'] ) && 'yes' === $extra_data['partial_enabled']; ?>
<?php $fw_seats .= $fw_partial ? ' data-fw-partial' : ''; ?>
<?php $fw_seats .= $fw_editing ? ' data-fw-editing' : ''; ?>
<div class="form-wrapper fw-contact-form contact-form" data-fw-confirm="<?php echo esc_attr( $fw_confirm ); ?>" data-fw-layout="<?php echo esc_attr( $fw_layout ); ?>"<?php echo $fw_seats; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
	<?php if ( $fw_editing ) : ?>
		<p class="fw-form-editing-notice" role="status"><?php esc_html_e( 'You are editing the entry you sent. Sending the form saves your changes.', 'fw' ); ?></p>
	<?php endif; ?>
	<?php echo $form_html; ?>
	<?php if ( $fw_partial ) : ?>
		<p class="fw-form-partial-notice"><?php echo esc_html( '' !== trim( (string) ( $extra_data['partial_notice'] ?? '' ) ) ? (string) $extra_data['partial_notice'] : __( 'We keep what you type here, even if you do not send it, so we can help you finish.', 'fw' ) ); ?></p>
	<?php endif; ?>
</div>