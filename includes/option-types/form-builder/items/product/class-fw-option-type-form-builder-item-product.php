<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Product — something with a price: one item ("Registration, $50") or a choice of options, each
 * with its own price ("Half day $90 / Full day $160"), bought once, when a box is ticked, or in a
 * typed quantity. Posts `<shortcode>[choice]`, `[qty]`, `[buy]`. The price is never taken from the
 * browser: FW_Forms_Order works the line out from the field's options. Stored as
 * "Full day × 2 = $320".
 */
class FW_Option_Type_Form_Builder_Item_Product extends FW_Option_Type_Form_Builder_Item_Simple {

	public function get_type() {
		return 'product';
	}

	protected function title() {
		return __( 'Product', 'fw' );
	}

	protected function tip() {
		return __( 'Add something with a price: one item, or a choice of options with their own prices, with a quantity', 'fw' );
	}

	public function get_options() {
		return array(
			$this->label_group( __( 'Product', 'fw' ), false ),
			array(
				'g_product' => array(
					'type'    => 'group',
					'options' => array(
						array(
							'mode' => array(
								'type'    => 'select',
								'label'   => __( 'Sold as', 'fw' ),
								'value'   => 'single',
								'choices' => array( 'single' => __( 'One item with one price', 'fw' ), 'choices' => __( 'A choice of options, each with a price', 'fw' ) ),
							),
						),
						array( 'price' => array( 'type' => 'text', 'label' => __( 'Price', 'fw' ), 'desc' => __( 'For "One item". A number, e.g. 25 or 12.50, in the currency set in Forms Settings → Payments.', 'fw' ), 'value' => '25' ) ),
						array(
							'choices' => array(
								'type'            => 'addable-box',
								'label'           => __( 'Options', 'fw' ),
								'desc'            => __( 'For "A choice of options".', 'fw' ),
								'box-options'     => array(
									'name'  => array( 'type' => 'text', 'label' => __( 'Name', 'fw' ), 'value' => '' ),
									'price' => array( 'type' => 'text', 'label' => __( 'Price', 'fw' ), 'value' => '' ),
								),
								'template'        => '{{- name }} — {{- price }}',
								'add-button-text' => __( 'Add option', 'fw' ),
								'sortable'        => true,
								'value'           => array(
									array( 'name' => __( 'Half day', 'fw' ), 'price' => '90' ),
									array( 'name' => __( 'Full day', 'fw' ), 'price' => '160' ),
								),
							),
						),
						array(
							'display' => array(
								'type'    => 'select',
								'label'   => __( 'Options shown as', 'fw' ),
								'value'   => 'radio',
								'choices' => array( 'radio' => __( 'A list to pick from', 'fw' ), 'select' => __( 'A dropdown', 'fw' ) ),
							),
						),
						array(
							'quantity' => array(
								'type'    => 'select',
								'label'   => __( 'Quantity', 'fw' ),
								'value'   => 'fixed',
								'choices' => array(
									'fixed'    => __( 'Always one (a fee, or the option picked)', 'fw' ),
									'checkbox' => __( 'One, when the visitor ticks "Add"', 'fw' ),
									'box'      => __( 'A number the visitor types', 'fw' ),
								),
							),
						),
						array(
							'billing' => array(
								'type'    => 'select',
								'label'   => __( 'Billing', 'fw' ),
								'desc'    => __( 'Charge it again every week, month or year (a subscription; needs Card or PayPal on the Take payment action). One form can hold one-off products and recurring ones of ONE billing period.', 'fw' ),
								'value'   => '',
								'choices' => array( '' => __( 'Once', 'fw' ), 'week' => __( 'Every week', 'fw' ), 'month' => __( 'Every month', 'fw' ), 'year' => __( 'Every year', 'fw' ) ),
							),
						),
						array( 'qty_min' => array( 'type' => 'text', 'label' => __( 'Quantity at least', 'fw' ), 'value' => '0' ) ),
						array( 'qty_max' => array( 'type' => 'text', 'label' => __( 'Quantity at most', 'fw' ), 'value' => '10' ) ),
					),
				),
			),
			$this->info_group(),
			$this->get_extra_options(),
		);
	}

	public function frontend_render( array $item, $input_value ) {
		$o   = $item['options'];
		$v   = is_array( $input_value ) ? $input_value : array();
		$id  = 'id-' . fw_unique_increment();
		list( $min, $max ) = FW_Forms_Order::qty_bounds( $o );
		return $this->view( $item, array( 'name' => $item['shortcode'] ?? '', 'id' => $id ), array(
			'mode'     => 'choices' === ( $o['mode'] ?? 'single' ) ? 'choices' : 'single',
			'price'    => FW_Forms_Money::parse( $o['price'] ?? 0 ),
			'choices'  => FW_Forms_Order::product_choices( $o ),
			'display'  => 'select' === ( $o['display'] ?? 'radio' ) ? 'select' : 'radio',
			'quantity' => in_array( $o['quantity'] ?? 'fixed', array( 'fixed', 'checkbox', 'box' ), true ) ? $o['quantity'] : 'fixed',
			'qty_min'  => $min,
			'qty_max'  => $max,
			'per'      => FW_Forms_Order::per( FW_Forms_Order::interval( $o ) ),
			'interval' => FW_Forms_Order::interval( $o ),
			'value'    => array(
				'choice' => isset( $v['choice'] ) && is_scalar( $v['choice'] ) ? (string) $v['choice'] : '',
				'qty'    => isset( $v['qty'] ) && is_numeric( $v['qty'] ) ? (int) $v['qty'] : max( 1, $min ),
				'buy'    => ! empty( $v['buy'] ),
			),
		) );
	}

	public function frontend_validate( array $item, $input_value ) {
		$o = $item['options'];
		$v = is_array( $input_value ) ? $input_value : array();
		if ( 'box' === ( $o['quantity'] ?? 'fixed' ) && isset( $v['qty'] ) && '' !== (string) $v['qty'] ) {
			list( $min, $max ) = FW_Forms_Order::qty_bounds( $o );
			if ( ! preg_match( '/^\d+$/', (string) $v['qty'] ) || (int) $v['qty'] < $min || (int) $v['qty'] > $max ) {
				return $this->msg( $item, sprintf( __( 'The {label} quantity must be between %1$d and %2$d', 'fw' ), $min, $max ) );
			}
		}
		if ( 'choices' === ( $o['mode'] ?? 'single' ) && isset( $v['choice'] ) && '' !== (string) $v['choice'] ) {
			if ( ! in_array( (string) $v['choice'], wp_list_pluck( FW_Forms_Order::product_choices( $o ), 'name' ), true ) ) {
				return $this->msg( $item, __( 'Please choose one of the {label} options', 'fw' ) );
			}
		}
		if ( ! empty( $o['required'] ) && ! FW_Forms_Order::product_line( $item, $v ) ) {
			return $this->msg( $item, 'choices' === ( $o['mode'] ?? 'single' ) ? __( 'Please choose a {label} option', 'fw' ) : __( 'Please add {label}', 'fw' ) );
		}
		return null;
	}

	public function get_value_from_item( $value, $item = array(), $all = array() ) {
		$l = $item ? FW_Forms_Order::product_line( $item, $value ) : null;
		return $l ? FW_Forms_Order::answer_text( $l ) : '';
	}
}

FW_Option_Type_Builder::register_item_type( 'FW_Option_Type_Form_Builder_Item_Product' );
