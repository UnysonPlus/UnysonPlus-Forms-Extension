<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Starter forms for the form builder's Templates panel.
 *
 * The panel itself is the framework's (`template_saving` on the form-builder
 * option, same as the email builder). We only supply the starters, through the
 * framework's own `fw_ext_builder:predefined_templates:form-builder:full`
 * filter.
 *
 * Authored as PHP, not frozen JSON — a template stores option VALUES, so it rots
 * silently when a field's options change. As code, a renamed option is a
 * visible edit in a reviewed file. Every starter runs through
 * `normalize()`, which fixes each item with its own item class (the same code
 * that loads a saved form), so the starters follow the option schema instead of
 * becoming the one shape nothing else produces.
 *
 * Rules the starters keep: a newsletter opt-in is always a separate, optional
 * Consent box with purpose=newsletter — never bundled into the terms box, and
 * never pre-ticked. Nothing here sets a form's Actions; that stays a decision.
 */
class FW_Forms_Starters {

	/**
	 * @internal
	 */
	public static function _filter_predefined( $templates ) {
		foreach ( self::all() as $id => $tpl ) {
			$templates[ 'fw-forms-' . $id ] = array(
				'title' => $tpl['title'],
				'json'  => wp_json_encode( self::normalize( $tpl['items'] ) ),
			);
		}

		return $templates;
	}

	/**
	 * @return array id => { title, items }
	 */
	public static function all() {
		$t = array(
			'contact'      => array( 'title' => __( 'Contact', 'fw' ),           'items' => self::contact() ),
			'booking'      => array( 'title' => __( 'Booking / Appointment', 'fw' ), 'items' => self::booking() ),
			'quote'        => array( 'title' => __( 'Quote request', 'fw' ),     'items' => self::quote() ),
			'registration' => array( 'title' => __( 'Event registration', 'fw' ), 'items' => self::registration() ),
			'support'      => array( 'title' => __( 'Support ticket', 'fw' ),    'items' => self::support() ),
			'feedback'     => array( 'title' => __( 'Feedback (CSAT)', 'fw' ),   'items' => self::feedback() ),
			'nps'          => array( 'title' => __( 'NPS survey', 'fw' ),        'items' => self::nps() ),
			'signup'       => array( 'title' => __( 'Newsletter signup', 'fw' ), 'items' => self::signup() ),
		);

		/**
		 * Add or remove form starters.
		 *
		 * @param array $t
		 */
		return apply_filters( 'fw_ext_forms_starters', $t );
	}

	/* ---------------------------------------------------------------------- *
	 * The starters
	 * ---------------------------------------------------------------------- */

	private static function contact() {
		return array(
			self::header( __( 'Get in touch', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::textarea( 'message', __( 'Message', 'fw' ), true ),
			self::consent_terms(),
		);
	}

	private static function booking() {
		return array(
			self::header( __( 'Book a table', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'phone', __( 'Phone', 'fw' ), false, '1_2' ),
			self::select( 'party', __( 'Party size', 'fw' ), array( '1', '2', '3', '4', '5', '6', '7', '8+' ), '1_2' ),
			self::date( 'date', __( 'Date', 'fw' ), 'date', '1_2', array( 'min' => 'today' ) ),
			self::date( 'time', __( 'Time', 'fw' ), 'time', '1_2', array( 'min' => '09:00', 'max' => '22:00' ) ),
			self::textarea( 'notes', __( 'Anything we should know? (allergies, occasion, access needs)', 'fw' ), false ),
			self::consent_terms(),
			self::consent_newsletter(),
		);
	}

	private static function quote() {
		return array(
			self::header( __( 'Request a quote', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::text( 'company', __( 'Company', 'fw' ), false, '1_2' ),
			self::email( '1_2' ),
			self::text( 'phone', __( 'Phone', 'fw' ), false, '1_2' ),
			self::select( 'service', __( 'What do you need?', 'fw' ), array( __( 'Option A', 'fw' ), __( 'Option B', 'fw' ), __( 'Something else', 'fw' ) ), '1_2' ),
			self::select( 'budget', __( 'Budget', 'fw' ), array( __( 'Under 1,000', 'fw' ), __( '1,000 – 5,000', 'fw' ), __( '5,000 – 20,000', 'fw' ), __( 'Over 20,000', 'fw' ), __( 'Not sure yet', 'fw' ) ), '1_2' ),
			self::date( 'deadline', __( 'When do you need it by?', 'fw' ), 'date', '1_2', array( 'min' => 'today' ), false ),
			self::textarea( 'details', __( 'Tell us about the project', 'fw' ), true ),
			self::hidden( 'source', __( 'Source', 'fw' ) ),
			self::consent_terms(),
		);
	}

	private static function registration() {
		return array(
			self::header( __( 'Register for the event', 'fw' ) ),
			self::text( 'name', __( 'Full name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'org', __( 'Organisation', 'fw' ), false, '1_2' ),
			self::text( 'role', __( 'Job title', 'fw' ), false, '1_2' ),
			self::radio( 'attendance', __( 'Attending', 'fw' ), array( __( 'In person', 'fw' ), __( 'Online', 'fw' ) ) ),
			self::checkboxes( 'sessions', __( 'Sessions you are interested in', 'fw' ), array( __( 'Morning keynote', 'fw' ), __( 'Workshop A', 'fw' ), __( 'Workshop B', 'fw' ), __( 'Evening social', 'fw' ) ) ),
			self::textarea( 'dietary', __( 'Dietary or access requirements', 'fw' ), false ),
			self::consent_terms(),
			self::consent_newsletter(),
		);
	}

	private static function support() {
		return array(
			self::header( __( 'Open a support ticket', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'order', __( 'Order number', 'fw' ), false, '1_2' ),
			self::select( 'category', __( 'What is this about?', 'fw' ), array( __( 'Delivery', 'fw' ), __( 'Returns & refunds', 'fw' ), __( 'Billing', 'fw' ), __( 'Technical problem', 'fw' ), __( 'Other', 'fw' ) ), '1_2' ),
			self::radio( 'urgency', __( 'How urgent is it?', 'fw' ), array( __( 'Low', 'fw' ), __( 'Normal', 'fw' ), __( 'High — I cannot use the product', 'fw' ) ) ),
			self::textarea( 'issue', __( 'Describe the issue', 'fw' ), true ),
			self::item( 'file-upload', 'screenshot', array( 'label' => __( 'Screenshot (optional)', 'fw' ), 'required' => false ) ),
			self::consent_terms(),
		);
	}

	private static function feedback() {
		return array(
			self::header( __( 'How did we do?', 'fw' ) ),
			self::rating( 'overall', __( 'Overall, how satisfied were you?', 'fw' ), 'stars5', true ),
			self::rating( 'speed', __( 'How quickly did we respond?', 'fw' ), 'stars5', false ),
			self::textarea( 'comment', __( 'What could we do better?', 'fw' ), false ),
			self::email( '1_1', false ),
			self::hidden( 'agent', __( 'Handled by', 'fw' ) ),
		);
	}

	private static function nps() {
		return array(
			self::header( __( 'One quick question', 'fw' ) ),
			self::rating( 'nps', __( 'How likely are you to recommend us to a friend or colleague?', 'fw' ), 'nps', true, __( 'Not at all likely', 'fw' ), __( 'Extremely likely', 'fw' ) ),
			self::textarea( 'why', __( 'What is the main reason for your score?', 'fw' ), false ),
			self::email( '1_1', false ),
		);
	}

	private static function signup() {
		return array(
			self::header( __( 'Join the newsletter', 'fw' ) ),
			self::text( 'name', __( 'First name', 'fw' ), false, '1_2' ),
			self::email( '1_2' ),
			self::consent_newsletter( __( 'Yes, send me the newsletter. I can unsubscribe at any time.', 'fw' ) ),
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Item shorthands
	 * ---------------------------------------------------------------------- */

	private static function item( $type, $slug, array $options, $width = '1_1' ) {
		return array( 'type' => $type, 'shortcode' => $type . '_' . $slug, 'width' => $width, 'options' => $options );
	}

	private static function header( $title ) {
		return array( 'type' => 'form-header-title', 'shortcode' => 'form_header_title', 'width' => '', 'options' => array( 'title' => $title, 'subtitle' => '' ) );
	}

	private static function text( $slug, $label, $required = true, $width = '1_1' ) {
		return self::item( 'text', $slug, array( 'label' => $label, 'required' => $required ), $width );
	}

	private static function email( $width = '1_1', $required = true ) {
		return self::item( 'email', '1', array( 'label' => __( 'Email', 'fw' ), 'required' => $required ), $width );
	}

	private static function textarea( $slug, $label, $required = false ) {
		return self::item( 'textarea', $slug, array( 'label' => $label, 'required' => $required ) );
	}

	private static function select( $slug, $label, array $choices, $width = '1_1' ) {
		return self::item( 'select', $slug, array( 'label' => $label, 'required' => true, 'choices' => $choices ), $width );
	}

	private static function radio( $slug, $label, array $choices ) {
		return self::item( 'radio', $slug, array( 'label' => $label, 'required' => true, 'choices' => $choices ) );
	}

	private static function checkboxes( $slug, $label, array $choices ) {
		return self::item( 'checkboxes', $slug, array( 'label' => $label, 'required' => false, 'choices' => $choices ) );
	}

	private static function date( $slug, $label, $mode = 'date', $width = '1_1', array $bounds = array(), $required = true ) {
		return self::item( 'date', $slug, array_merge( array( 'label' => $label, 'required' => $required, 'mode' => $mode, 'min' => '', 'max' => '', 'blackout_days' => array() ), $bounds ), $width );
	}

	private static function rating( $slug, $label, $scale = 'stars5', $required = true, $low = '', $high = '' ) {
		return self::item( 'rating', $slug, array( 'label' => $label, 'required' => $required, 'scale' => $scale, 'low_label' => $low, 'high_label' => $high ) );
	}

	private static function hidden( $slug, $label ) {
		return self::item( 'hidden', $slug, array( 'label' => $label, 'source' => 'url_param', 'value' => 'utm_source' ) );
	}

	private static function consent_terms() {
		return self::item( 'consent', 'terms', array( 'label' => __( 'I agree to the privacy policy.', 'fw' ), 'required' => true, 'purpose' => 'terms' ) );
	}

	private static function consent_newsletter( $label = '' ) {
		return self::item( 'consent', 'newsletter', array( 'label' => '' !== $label ? $label : __( 'Also send me the newsletter.', 'fw' ), 'required' => false, 'purpose' => 'newsletter' ) );
	}

	/* ---------------------------------------------------------------------- */

	/**
	 * Run every item through its own class so the stored shape is canonical.
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	private static function normalize( array $items ) {
		$builder = fw()->backend->option_type( 'form-builder' );

		if ( ! $builder ) {
			return $items;
		}

		$r = new ReflectionMethod( $builder, 'get_item_types' );
		$r->setAccessible( true );
		$types = $r->invoke( $builder );
		$out   = array();

		foreach ( $items as $it ) {
			$type = $it['type'];

			if ( ! isset( $types[ $type ] ) ) {
				continue; // A starter must never reference a field that is not registered.
			}

			$fixed = $types[ $type ]->get_value_from_attributes( $it );
			$fixed['shortcode'] = $it['shortcode'];
			$fixed['width']     = $it['width'];
			$out[]              = $fixed;
		}

		return $out;
	}
}
