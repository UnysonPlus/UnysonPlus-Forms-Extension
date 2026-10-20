<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Take payment" — charges the form's total (Product, Amount and "add to the total" Calculated
 * fields, worked out on the server by FW_Forms_Order).
 *
 *   validate()  before the submission is accepted: refuses an empty order when an amount is
 *               required, checks the method is ready, and reserves the payment's reference so the
 *               success message and emails can show {payment_ref} / {payment_instructions}.
 *   run()       records the payment against the entry and starts it with the method: Pay later waits
 *               until you mark it paid; Card (Stripe) and PayPal send the payer to their page, and the
 *               payer's return or the service's notification marks it paid.
 */
class FW_Forms_Action_Payment extends FW_Forms_Action {

	public function get_id() {
		return 'payment';
	}

	public function get_title() {
		return __( 'Take payment', 'fw' );
	}

	public function get_options() {
		$methods = array();
		foreach ( FW_Forms_Payments::gateways() as $id => $g ) {
			$methods[ $id ] = $g->get_title();
		}
		return array(
			'action_payment'                      => $this->switch_option(
				__( 'Take payment', 'fw' ),
				__( 'Charge the total of the form\'s Product, Amount and "add to the total" Calculated fields. Each payment is kept with its entry under Form Entries. Answer tags for messages and emails: {order_total}, {order_summary}, {payment_ref}, {payment_method}, {payment_instructions}.', 'fw' )
			),
			'action_payment_method'               => array(
				'type'    => 'select',
				'label'   => __( 'Payment method', 'fw' ),
				'desc'    => __( 'Card and PayPal need their keys in Forms Settings → Payments (or in Membership → Payments). To let the payer choose, add a Payment Method field to the form.', 'fw' ),
				'value'   => 'offline',
				'choices' => $methods,
			),
			'action_payment_require'              => $this->switch_option(
				__( 'Something must be bought', 'fw' ),
				__( 'Refuse a submission whose total is zero. Off: a zero total is sent without a payment.', 'fw' ),
				true
			),
			'action_payment_offline_title'        => array(
				'type'  => 'text',
				'label' => __( 'Pay later: name', 'fw' ),
				'desc'  => __( 'What the payer sees, e.g. Bank transfer, Pay on arrival, Invoice.', 'fw' ),
				'value' => __( 'Bank transfer', 'fw' ),
			),
			'action_payment_offline_instructions' => array(
				'type'  => 'textarea',
				'label' => __( 'Pay later: instructions', 'fw' ),
				'desc'  => __( 'Shown through {payment_instructions} in the success message and emails. {payment_ref} and {order_total} fill in.', 'fw' ),
				'value' => __( 'Please transfer {order_total} to our account, quoting the reference {payment_ref}. We will confirm once it arrives.', 'fw' ),
			),
		);
	}

	/**
	 * The method to use: the payer's choice in a Payment Method field (already checked by the field),
	 * else the action's own method.
	 *
	 * @return array [ FW_Forms_Gateway|null, string $title ]
	 */
	private function gateway( array $ctx ) {
		foreach ( (array) ( $ctx['payload']['shortcode_to_item'] ?? array() ) as $sc => $item ) {
			if ( 'payment-method' !== ( $item['type'] ?? '' ) ) {
				continue;
			}
			$v       = FW_Request::POST( $sc );
			$methods = FW_Option_Type_Form_Builder_Item_Payment_Method::available( $item['options'] ?? array() );
			if ( is_scalar( $v ) && isset( $methods[ (string) $v ] ) ) {
				return array( FW_Forms_Payments::gateway( (string) $v ), $methods[ (string) $v ] );
			}
		}
		$g = FW_Forms_Payments::gateway( (string) self::opt( $ctx['form'], 'action_payment_method', 'offline' ) );
		$g = $g ? $g : FW_Forms_Payments::gateway( 'offline' );
		return array( $g, $g ? $g->title( $ctx['form'] ) : '' );
	}

	public function validate( array $ctx ) {
		$order = FW_Forms_Order::current();
		if ( $order['total'] <= 0 ) {
			return 'yes' === self::opt( $ctx['form'], 'action_payment_require', 'yes' )
				? __( 'Please choose what you would like to pay for.', 'fw' )
				: null;
		}
		if ( count( $order['intervals'] ) > 1 ) {
			return __( 'Products billed weekly, monthly and yearly cannot be paid together. Please choose products with one billing period.', 'fw' );
		}
		list( $g, $title ) = $this->gateway( $ctx );
		if ( ! $g || ! $g->is_ready() ) {
			return __( 'Sorry, payments cannot be taken right now. Please try again later.', 'fw' );
		}
		if ( $order['recurring'] && ! $g->supports_recurring() ) {
			return __( 'A subscription can be paid by card or PayPal. Please choose one of those.', 'fw' );
		}
		$pending = FW_Forms_Payments::prepare( $g, $ctx['form'], $title );
		if ( $order['recurring'] && class_exists( 'FW_Forms_Subscriptions' ) ) {
			FW_Forms_Payments::add_to_pending( 'subscription', array(
				'reference' => FW_Forms_Subscriptions::new_reference(),
				'price'     => trim( FW_Forms_Money::format( $order['recurring']['amount'], $order['currency'] ) . ' ' . FW_Forms_Order::per( $order['recurring']['interval'] ) ),
			) );
		}
		return null;
	}

	public function run( array $ctx ) {
		$pending = FW_Forms_Payments::pending();
		$order   = FW_Forms_Order::current();
		if ( ! $pending || $order['total'] <= 0 ) {
			return null; // nothing to pay
		}
		$g  = $pending['gateway_o'];
		$id = FW_Forms_Payments::create( array(
			'entry_id'  => (int) $ctx['entry_id'],
			'form_id'   => (string) $ctx['form_id'],
			'email'     => (string) $ctx['email'],
			'reference' => $pending['reference'],
			'gateway'   => $g->get_id(),
			'amount'    => $order['total'],
			'currency'  => $order['currency'],
			'lines'     => $order['lines'],
			'meta'      => array(
				'method_title' => $pending['title'],
				'key'          => $pending['key'],
				'page_url'     => $pending['page_url'],
				'coupon'       => $order['coupon'],
				'coupon_def'   => '' !== $order['coupon'] && class_exists( 'FW_Forms_Coupons' ) ? FW_Forms_Coupons::check( $order['coupon'] ) : null,
				'discount'     => $order['discount'],
				'recurring'    => $order['recurring'],
			),
		) );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( $order['recurring'] && ! empty( $pending['subscription'] ) ) {
			$sid = FW_Forms_Subscriptions::create( array(
				'reference' => $pending['subscription']['reference'],
				'entry_id'  => (int) $ctx['entry_id'],
				'form_id'   => (string) $ctx['form_id'],
				'email'     => (string) $ctx['email'],
				'gateway'   => $g->get_id(),
				'amount'    => $order['recurring']['amount'],
				'currency'  => $order['currency'],
				'interval'  => $order['recurring']['interval'],
				'lines'     => array_values( array_filter( $order['lines'], function ( $l ) {
					return ! empty( $l['interval'] );
				} ) ),
				'meta'      => array( 'key' => $pending['key'], 'page_url' => $pending['page_url'], 'method_title' => $pending['title'], 'first_payment' => $id ),
			) );
			if ( ! is_wp_error( $sid ) ) {
				FW_Forms_Payments::update( $id, array( 'meta' => array( 'subscription_id' => $sid ) ) );
			}
		}
		$r = $g->start( FW_Forms_Payments::find( $id ), $ctx );
		if ( is_wp_error( $r ) ) {
			FW_Forms_Payments::set_status( $id, 'failed', array( 'note' => $r->get_error_message() ) );
			return $r;
		}
		if ( 'paid' === ( $r['status'] ?? '' ) ) {
			FW_Forms_Payments::set_status( $id, 'paid', array( 'gateway_ref' => (string) ( $r['gateway_ref'] ?? '' ) ) );
		} elseif ( 'redirect' === ( $r['status'] ?? '' ) && ! empty( $r['redirect'] ) ) {
			// Forms sends the payer there instead of showing the success message (FW_Forms_Payments::take_redirect).
			FW_Forms_Payments::set_redirect( $r['redirect'] );
		}
		return null;
	}
}
