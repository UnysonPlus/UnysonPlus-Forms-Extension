<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * A way to pay for a form. Register more on `fw_ext_forms_gateways`.
 *
 *   start()   called by the Take payment action once the payment row exists. Return
 *             { status: pending | paid | redirect, redirect?: url, gateway_ref? }. A redirect (a hosted
 *             card page) is followed after the form is sent.
 *   refund()  only when refunds_online() is true; otherwise the admin "Mark refunded" just records it.
 *   confirm_return() / webhook()  for services with a hosted page: the payer's return and the
 *             service's own notification both mark the payment paid (whichever comes first).
 */
abstract class FW_Forms_Gateway {

	/** @return string [a-z0-9_] */
	abstract public function get_id();

	/** @return string The method's name in the admin ("Pay later"). */
	abstract public function get_title();

	/** @return string What the payer sees for this form ("Bank transfer"). */
	public function title( array $form ) {
		return $this->get_title();
	}

	/** Ready to take payments (keys set…). */
	public function is_ready() {
		return true;
	}

	/**
	 * Text for {payment_instructions}.
	 *
	 * @param array $pending { reference, title, form }
	 * @param array $order   FW_Forms_Order::build()
	 */
	public function instructions( array $pending, array $order ) {
		return '';
	}

	/**
	 * @param object $payment The stored payment.
	 * @param array  $ctx     The Forms action context.
	 * @return array|WP_Error
	 */
	public function start( $payment, array $ctx ) {
		return array( 'status' => 'pending' );
	}

	/** Check a payment with the service when the payer comes back (the notification may be late). */
	public function confirm_return( $payment ) {
	}

	/**
	 * A notification from the service (POST /wp-json/fw-forms/v1/webhooks/{id}). Verify it first.
	 *
	 * @return true|WP_Error
	 */
	public function webhook( WP_REST_Request $request ) {
		return new WP_Error( 'fw_forms_no_webhook', 'This payment method takes no notifications.', array( 'status' => 404 ) );
	}

	/** An amount in the currency's smallest unit (cents; yen as is). */
	public static function minor_units( $amount, $currency ) {
		return 0 === FW_Forms_Money::decimals( strtoupper( $currency ) ) ? (int) round( $amount ) : (int) round( $amount * 100 );
	}

	/** What the payment is for, as one line ("Order F-7K3QD: Tour, Airport pickup"). */
	public static function description( $payment ) {
		$names = array();
		foreach ( (array) $payment->lines as $l ) {
			$names[] = trim( $l['label'] . ( '' !== ( $l['detail'] ?? '' ) ? ': ' . $l['detail'] : '' ) );
		}
		return substr( sprintf( __( 'Order %1$s: %2$s', 'fw' ), $payment->reference, implode( ', ', array_filter( $names ) ) ), 0, 120 );
	}

	/** Can charge a subscription (Products billed every week / month / year). */
	public function supports_recurring() {
		return false;
	}

	/**
	 * Stop a subscription's renewals with the service (it runs to the end of the paid period).
	 *
	 * @param object $subscription FW_Forms_Subscriptions row
	 * @return true|WP_Error
	 */
	public function cancel_subscription( $subscription ) {
		return new WP_Error( 'fw_forms_no_subscriptions', __( 'This payment method has no subscriptions.', 'fw' ) );
	}

	/** The one-off part of an order (lines without a billing period, before the discount). */
	public static function one_off( $payment ) {
		$sum = 0.0;
		foreach ( (array) $payment->lines as $l ) {
			if ( empty( $l['interval'] ) && 'discount' !== ( $l['kind'] ?? '' ) ) {
				$sum += (float) $l['total'];
			}
		}
		return $sum;
	}

	public function refunds_online() {
		return false;
	}

	/** @return true|WP_Error */
	public function refund( $payment ) {
		return new WP_Error( 'fw_forms_no_refund', __( 'This payment method cannot refund online.', 'fw' ) );
	}
}
