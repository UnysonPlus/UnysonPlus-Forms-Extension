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
		// title, items; plus, for the form-type picker: desc (one line), icon (dashicon), group, and the
		// suggested subject / button / thank-you text a new form starts with. Never actions.
		$s = function ( $title, $items, $desc, $icon, $group, $subject, $button, $success ) {
			return array(
				'title'    => $title,
				'items'    => $items,
				'desc'     => $desc,
				'icon'     => $icon,
				'group'    => $group,
				'settings' => array( 'subject_message' => $subject, 'submit_button_text' => $button, 'success_message' => $success ),
			);
		};
		$t = array(
			'contact'      => $s( __( 'Contact', 'fw' ), self::contact(), __( 'Name, email and a message.', 'fw' ), 'email-alt', 'enquiries', __( 'New message from {text_name}', 'fw' ), __( 'Send message', 'fw' ), __( 'Thanks, {text_name}. We will get back to you soon.', 'fw' ) ),
			'quote'        => $s( __( 'Quote request', 'fw' ), self::quote(), __( 'Service, budget, deadline and project details.', 'fw' ), 'money-alt', 'enquiries', __( 'Quote request from {text_name}', 'fw' ), __( 'Request a quote', 'fw' ), __( 'Thanks, {text_name}. We will send your quote shortly.', 'fw' ) ),
			'callback'     => $s( __( 'Callback request', 'fw' ), self::callback(), __( 'A phone number and the best time to call.', 'fw' ), 'phone', 'enquiries', __( 'Callback request from {text_name}', 'fw' ), __( 'Call me back', 'fw' ), __( 'Thanks, {text_name}. We will call you at the time you chose.', 'fw' ) ),
			'product'      => $s( __( 'Product enquiry', 'fw' ), self::product(), __( 'Which product, how many, and any questions.', 'fw' ), 'cart', 'enquiries', __( 'Product enquiry from {text_name}', 'fw' ), __( 'Send enquiry', 'fw' ), __( 'Thanks, {text_name}. We will reply with the details.', 'fw' ) ),
			'support'      => $s( __( 'Support ticket', 'fw' ), self::support(), __( 'Order number, category, urgency and a screenshot.', 'fw' ), 'sos', 'enquiries', __( 'Support request from {text_name}', 'fw' ), __( 'Open ticket', 'fw' ), __( 'Thanks, {text_name}. Your ticket is open; we will be in touch.', 'fw' ) ),
			'appointment'  => $s( __( 'Appointment request', 'fw' ), self::appointment(), __( 'Service, preferred date and time. No free-text box.', 'fw' ), 'calendar-alt', 'bookings', __( 'Appointment request from {text_name}', 'fw' ), __( 'Request appointment', 'fw' ), __( 'Thanks, {text_name}. We will confirm your appointment.', 'fw' ) ),
			'booking'      => $s( __( 'Table booking (restaurant)', 'fw' ), self::booking(), __( 'Party size, date and time.', 'fw' ), 'food', 'bookings', __( 'Table booking from {text_name}', 'fw' ), __( 'Book a table', 'fw' ), __( 'Thanks, {text_name}. We will confirm your table.', 'fw' ) ),
			'registration' => $s( __( 'Event registration', 'fw' ), self::registration(), __( 'In person or online, sessions and access needs.', 'fw' ), 'tickets-alt', 'events', __( 'Event registration: {text_name}', 'fw' ), __( 'Register', 'fw' ), __( 'Thanks, {text_name}. You are registered.', 'fw' ) ),
			'rsvp'         => $s( __( 'RSVP', 'fw' ), self::rsvp(), __( 'Attending or not, number of guests, dietary needs.', 'fw' ), 'groups', 'events', __( 'RSVP from {text_name}', 'fw' ), __( 'Send RSVP', 'fw' ), __( 'Thanks, {text_name}. Your reply is in.', 'fw' ) ),
			'job'          => $s( __( 'Job application', 'fw' ), self::job(), __( 'Position, CV upload, links and availability.', 'fw' ), 'id-alt', 'applications', __( 'Job application: {text_name}', 'fw' ), __( 'Apply', 'fw' ), __( 'Thanks, {text_name}. We have your application.', 'fw' ) ),
			'volunteer'    => $s( __( 'Volunteer sign-up', 'fw' ), self::volunteer(), __( 'Areas of interest and when they are free.', 'fw' ), 'heart', 'applications', __( 'New volunteer: {text_name}', 'fw' ), __( 'Sign up', 'fw' ), __( 'Thanks, {text_name}. We will be in touch about volunteering.', 'fw' ) ),
			'feedback'     => $s( __( 'Feedback (CSAT)', 'fw' ), self::feedback(), __( 'Star ratings and what could be better.', 'fw' ), 'star-filled', 'surveys', __( 'New feedback', 'fw' ), __( 'Send feedback', 'fw' ), __( 'Thank you for your feedback.', 'fw' ) ),
			'nps'          => $s( __( 'NPS survey', 'fw' ), self::nps(), __( 'The 0 to 10 "would you recommend us" question.', 'fw' ), 'chart-bar', 'surveys', __( 'New NPS response', 'fw' ), __( 'Send', 'fw' ), __( 'Thank you for your answer.', 'fw' ) ),
			'signup'       => $s( __( 'Newsletter signup', 'fw' ), self::signup(), __( 'First name, email and an opt-in.', 'fw' ), 'megaphone', 'marketing', __( 'New newsletter signup', 'fw' ), __( 'Subscribe', 'fw' ), __( 'Thanks for subscribing.', 'fw' ) ),
			'download'     => $s( __( 'Download (lead magnet)', 'fw' ), self::download(), __( 'A guide or file in return for an email address.', 'fw' ), 'download', 'marketing', __( 'Download request from {text_name}', 'fw' ), __( 'Get the download', 'fw' ), __( 'Thanks, {text_name}. Your download is on its way.', 'fw' ) ),
			// Turn on Settings → Actions → Take payment to charge the total.
			'order'        => $s( __( 'Order form', 'fw' ), self::order(), __( 'Products with prices and quantities, a live total.', 'fw' ), 'cart', 'payments', __( 'New order from {text_name}: {order_total}', 'fw' ), __( 'Place order', 'fw' ), __( 'Thanks, {text_name}. Your order comes to {order_total}. {payment_instructions}', 'fw' ) ),
			'donation'     => $s( __( 'Donation', 'fw' ), self::donation(), __( 'Suggested amounts or their own, and a note.', 'fw' ), 'heart', 'payments', __( 'New donation from {text_name}: {order_total}', 'fw' ), __( 'Donate', 'fw' ), __( 'Thank you, {text_name}, for your gift of {order_total}. {payment_instructions}', 'fw' ) ),
		);

		/**
		 * Add or remove form starters. Each: title, items, and optionally desc, icon (dashicon name),
		 * group (enquiries | bookings | events | applications | surveys | marketing) and settings
		 * (subject_message, submit_button_text, success_message).
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

	/**
	 * A request for an appointment, consultation or visit: the person asks, the business confirms.
	 * No free-text "anything we should know" box, so it suits clinics, salons and law firms, where
	 * the form must not invite health or confidential details.
	 */
	private static function appointment() {
		return array(
			self::header( __( 'Request an appointment', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'phone', __( 'Phone', 'fw' ), true, '1_2' ),
			self::select( 'service', __( 'What is it for?', 'fw' ), array( __( 'Option A', 'fw' ), __( 'Option B', 'fw' ), __( 'Something else', 'fw' ) ), '1_2' ),
			self::date( 'date', __( 'Preferred date', 'fw' ), 'date', '1_2', array( 'min' => 'today' ) ),
			self::select( 'time', __( 'Preferred time', 'fw' ), array( __( 'Morning', 'fw' ), __( 'Afternoon', 'fw' ), __( 'Evening', 'fw' ), __( 'Any time', 'fw' ) ), '1_2' ),
			self::radio( 'customer', __( 'Have you been to us before?', 'fw' ), array( __( 'Yes', 'fw' ), __( 'No, this is my first time', 'fw' ) ) ),
			self::consent_terms(),
			self::consent_newsletter(),
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

	private static function order() {
		return array(
			self::header( __( 'Place an order', 'fw' ) ),
			self::item( 'product', 'main', array(
				'label' => __( 'Choose your product', 'fw' ), 'required' => true, 'mode' => 'choices', 'display' => 'radio', 'quantity' => 'box', 'qty_min' => '1', 'qty_max' => '10',
				'choices' => array( array( 'name' => __( 'Standard', 'fw' ), 'price' => '25' ), array( 'name' => __( 'Deluxe', 'fw' ), 'price' => '45' ) ),
			) ),
			self::item( 'product', 'gift', array( 'label' => __( 'Gift wrapping', 'fw' ), 'required' => false, 'mode' => 'single', 'price' => '5', 'quantity' => 'checkbox' ) ),
			self::item( 'calculation', 'total', array( 'label' => __( 'Total', 'fw' ), 'formula' => '{product_main} + {product_gift}', 'format' => 'money', 'add_to_total' => 'no', 'visible' => 'yes' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::textarea( 'notes', __( 'Delivery notes', 'fw' ) ),
		);
	}

	private static function donation() {
		return array(
			self::header( __( 'Make a donation', 'fw' ) ),
			self::item( 'amount', 'gift', array( 'label' => __( 'Your gift', 'fw' ), 'required' => true, 'suggested' => '10, 25, 50, 100', 'allow_other' => 'yes', 'min' => '1', 'max' => '' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::textarea( 'message', __( 'A message (optional)', 'fw' ) ),
		);
	}

	private static function callback() {
		return array(
			self::header( __( 'Request a callback', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::text( 'phone', __( 'Phone number', 'fw' ), true, '1_2' ),
			self::select( 'when', __( 'Best time to call', 'fw' ), array( __( 'Morning', 'fw' ), __( 'Lunchtime', 'fw' ), __( 'Afternoon', 'fw' ), __( 'Evening', 'fw' ), __( 'Any time', 'fw' ) ), '1_2' ),
			self::text( 'topic', __( 'What is it about?', 'fw' ), false, '1_2' ),
			self::consent_terms(),
		);
	}

	private static function product() {
		return array(
			self::header( __( 'Ask about a product', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'product', __( 'Product', 'fw' ), true, '1_2' ),
			self::item( 'number', 'quantity', array( 'label' => __( 'Quantity', 'fw' ), 'required' => false ), '1_2' ),
			self::textarea( 'question', __( 'Your question', 'fw' ), true ),
			self::hidden( 'source', __( 'Source', 'fw' ) ),
			self::consent_terms(),
		);
	}

	private static function rsvp() {
		return array(
			self::header( __( 'Will you join us?', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::radio( 'attending', __( 'Will you attend?', 'fw' ), array( __( 'Yes, I will be there', 'fw' ), __( 'Sorry, I cannot make it', 'fw' ) ) ),
			self::select( 'guests', __( 'Number of guests (including you)', 'fw' ), array( '1', '2', '3', '4', '5+' ), '1_2' ),
			self::textarea( 'dietary', __( 'Dietary or access needs', 'fw' ), false ),
			self::consent_terms(),
		);
	}

	private static function job() {
		return array(
			self::header( __( 'Apply for the role', 'fw' ) ),
			self::text( 'name', __( 'Full name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'phone', __( 'Phone number', 'fw' ), false, '1_2' ),
			self::select( 'position', __( 'Position', 'fw' ), array( __( 'Role A', 'fw' ), __( 'Role B', 'fw' ), __( 'Open application', 'fw' ) ), '1_2' ),
			self::item( 'file-upload', 'cv', array( 'label' => __( 'CV (PDF or Word)', 'fw' ), 'required' => true ) ),
			self::item( 'website', 'portfolio', array( 'label' => __( 'Portfolio or LinkedIn (optional)', 'fw' ), 'required' => false ) ),
			self::date( 'start', __( 'Earliest start date', 'fw' ), 'date', '1_2', array( 'min' => 'today' ), false ),
			self::textarea( 'cover', __( 'Why this role?', 'fw' ), false ),
			self::consent_terms(),
		);
	}

	private static function volunteer() {
		return array(
			self::header( __( 'Volunteer with us', 'fw' ) ),
			self::text( 'name', __( 'Your name', 'fw' ), true, '1_2' ),
			self::email( '1_2' ),
			self::text( 'phone', __( 'Phone number', 'fw' ), false, '1_2' ),
			self::checkboxes( 'areas', __( 'What would you like to help with?', 'fw' ), array( __( 'Events', 'fw' ), __( 'Fundraising', 'fw' ), __( 'Admin', 'fw' ), __( 'Outreach', 'fw' ) ) ),
			self::checkboxes( 'when', __( 'When are you free?', 'fw' ), array( __( 'Weekday mornings', 'fw' ), __( 'Weekday evenings', 'fw' ), __( 'Weekends', 'fw' ) ) ),
			self::textarea( 'experience', __( 'Any relevant experience?', 'fw' ), false ),
			self::consent_terms(),
			self::consent_newsletter(),
		);
	}

	private static function download() {
		return array(
			self::header( __( 'Get the free guide', 'fw' ) ),
			self::text( 'name', __( 'First name', 'fw' ), false, '1_2' ),
			self::email( '1_2' ),
			self::consent_newsletter( __( 'Also send me the newsletter. I can unsubscribe at any time.', 'fw' ) ),
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
	public static function normalize( array $items ) {
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
