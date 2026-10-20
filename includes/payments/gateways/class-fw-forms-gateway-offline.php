<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Pay later: a bank transfer, cash or card on arrival, an invoice. The payment is recorded as
 * waiting, with the amount owed and a reference; the payer gets the form's instructions
 * ({payment_instructions}), and the site owner marks it paid on the entry.
 */
class FW_Forms_Gateway_Offline extends FW_Forms_Gateway {

	public function get_id() {
		return 'offline';
	}

	public function get_title() {
		return __( 'Pay later', 'fw' );
	}

	public function title( array $form ) {
		$t = trim( (string) FW_Forms_Action::opt( $form, 'action_payment_offline_title', '' ) );
		return '' !== $t ? $t : __( 'Bank transfer', 'fw' );
	}

	public function instructions( array $pending, array $order ) {
		$text = (string) FW_Forms_Action::opt( $pending['form'] ?? array(), 'action_payment_offline_instructions', '' );
		return strtr( $text, array(
			'{payment_ref}' => (string) $pending['reference'],
			'{order_total}' => FW_Forms_Money::format( $order['total'], $order['currency'] ),
		) );
	}
}
