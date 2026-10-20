<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/** Sub extensions will extend this class */
require dirname( __FILE__ ) . '/includes/extends/class-fw-extension-forms-form.php';

/**
 * Build frontend forms
 */
class FW_Extension_Forms extends FW_Extension {

	/**
	 * Via this form will be rendered, validated and saved forms on frontend
	 * @var FW_Form
	 */
	private $frontend_form;

	/**
	 * @internal
	 */
	protected function _init() {
		// AI Assistant abilities (only registered while that extension is active).
		require_once dirname( __FILE__ ) . '/includes/ai-abilities.php';

		// Entries: stored submissions. Lives on the submit hook, so the pipeline
		// below never learns it exists.
		$entries = dirname( __FILE__ ) . '/includes/entries';
		require_once $entries . '/class-fw-forms-entries-installer.php';
		require_once $entries . '/class-fw-forms-entries.php';
		require_once $entries . '/class-fw-forms-entries-capture.php';
		FW_Forms_Entries_Installer::maybe_install();
		new FW_Forms_Entries_Capture();

		if ( is_admin() ) {
			require_once $entries . '/class-fw-forms-entries-admin.php';
			new FW_Forms_Entries_Admin();
		}

		// Conditional visibility — one rule per field, enforced server-side.
		require_once dirname( __FILE__ ) . '/includes/conditions/class-fw-forms-conditions.php';

		// Actions: what a form does after a successful submit, per form. Other
		// extensions add theirs through `fw_ext_forms_actions`.
		$actions = dirname( __FILE__ ) . '/includes/actions';
		require_once $actions . '/class-fw-forms-action.php';
		require_once $actions . '/class-fw-forms-actions.php';
		new FW_Forms_Actions();

		// Autofill hints (autocomplete tokens) for name / email / phone / address fields.
		require_once dirname( __FILE__ ) . '/includes/class-fw-forms-autocomplete.php';

		// The form library: saved forms (post type fw_form) placed by any Contact Form element.
		require_once dirname( __FILE__ ) . '/includes/library/class-fw-forms-library.php';
		FW_Forms_Library::init();
		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/includes/library/class-fw-forms-library-admin.php';
			FW_Forms_Library_Admin::init();
		}

		// The form-type picker on an empty form builder, and "describe a form" through the site's AI provider.
		require_once dirname( __FILE__ ) . '/includes/picker/class-fw-forms-picker.php';
		require_once dirname( __FILE__ ) . '/includes/ai/class-fw-forms-ai-generate.php';
		FW_Forms_Picker::init();
		FW_Forms_AI_Generate::init();

		// Content spam filtering: caught submissions are kept as Spam entries, nothing is sent.
		require_once dirname( __FILE__ ) . '/includes/spam/class-fw-forms-spam-filter.php';
		FW_Forms_Spam_Filter::init();

		// Pre-filled answers (logged-in user, the page address).
		require_once dirname( __FILE__ ) . '/includes/prefill/class-fw-forms-prefill.php';

		// Answer tags ({text_name}, {all_fields}, …) for subjects, messages, addresses and emails.
		require_once dirname( __FILE__ ) . '/includes/notifications/class-fw-forms-merge-tags.php';
		// More than one notification, recipients and "after sending" chosen by an answer.
		require_once dirname( __FILE__ ) . '/includes/notifications/class-fw-forms-notifications.php';

		// Prices, totals and payments: Product / Amount / Calculated fields, the order, the
		// "Take payment" action and the payment records on entries.
		require_once dirname( __FILE__ ) . '/includes/payments/class-fw-forms-money.php';
		require_once dirname( __FILE__ ) . '/includes/payments/class-fw-forms-formula.php';
		require_once dirname( __FILE__ ) . '/includes/payments/class-fw-forms-order.php';
		require_once dirname( __FILE__ ) . '/includes/payments/class-fw-forms-payments.php';
		FW_Forms_Payments::init();
		require_once dirname( __FILE__ ) . '/includes/payments/class-fw-forms-coupons.php';
		FW_Forms_Coupons::init();
		require_once dirname( __FILE__ ) . '/includes/payments/class-fw-forms-subscriptions.php';
		FW_Forms_Subscriptions::init();

		// Entry / date / seat limits on a form.
		require_once dirname( __FILE__ ) . '/includes/limits/class-fw-forms-limits.php';
		FW_Forms_Limits::init();

		// PDF copies of entries (download, email attachments) and "Save and continue later".
		require_once dirname( __FILE__ ) . '/includes/pdf/class-fw-forms-pdf.php';
		FW_Forms_PDF::init();
		require_once dirname( __FILE__ ) . '/includes/drafts/class-fw-forms-drafts.php';
		FW_Forms_Drafts::init();

		// Views / starts / abandons per form, and unfinished entries.
		require_once dirname( __FILE__ ) . '/includes/analytics/class-fw-forms-analytics.php';
		FW_Forms_Analytics::init();
		require_once dirname( __FILE__ ) . '/includes/entries/class-fw-forms-partial-entries.php';
		FW_Forms_Partial_Entries::init();

		// Working with entries (star, assign, notes, reply, corrections) and the scheduled CSV email.
		require_once dirname( __FILE__ ) . '/includes/entries/class-fw-forms-entry-notes.php';
		FW_Forms_Entry_Notes::init();
		require_once dirname( __FILE__ ) . '/includes/entries/class-fw-forms-entries-schedule.php';
		FW_Forms_Entries_Schedule::init();

		// Entries on the site: the Entry View element, approval before showing, senders editing their entry.
		require_once dirname( __FILE__ ) . '/includes/workflow/class-fw-forms-approval.php';
		FW_Forms_Approval::init();
		require_once dirname( __FILE__ ) . '/includes/views/class-fw-forms-views.php';
		require_once dirname( __FILE__ ) . '/includes/views/class-fw-forms-entry-edit.php';
		FW_Forms_Entry_Edit::init();

		// Quiz scoring, and the survey results screen.
		require_once dirname( __FILE__ ) . '/includes/quiz/class-fw-forms-quiz.php';
		FW_Forms_Quiz::init();
		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/includes/reports/class-fw-forms-reports.php';
			FW_Forms_Reports::init();
		}

		// Form Styles presets: Theme Settings → Components → Form Styles, and their CSS in the
		// shared presets stylesheet. Both are filters, so they exist only while Forms is active.
		require_once dirname( __FILE__ ) . '/includes/styles/class-fw-forms-style-presets.php';
		FW_Forms_Style_Presets::init();

		$this->frontend_form = new FW_Form( 'fw_form', array(
			'render'   => array( $this, '_frontend_form_render' ),
			'validate' => array( $this, '_frontend_form_validate' ),
			'save'     => array( $this, '_frontend_form_save' ),
		) );

		add_filter('fw:form:nonce-name-data', array($this, '_filter_frontend_nonce_name_date'), 10, 3);
	}

	/**
	 * Register the front-end form stylesheet + conditions script (idempotent).
	 */
	public function register_frontend_static() {
		if ( wp_style_is( 'fw-ext-forms-default-styles', 'registered' ) ) {
			return;
		}
		wp_register_style(
			'fw-ext-forms-default-styles',
			fw_min_uri( $this->get_declared_URI( '/static/css/frontend.css' ) ),
			array(),
			fw()->manifest->get_version()
		);
		// "Show only when…" rules: toggles fields live; the server enforces the same rules on submit.
		wp_register_script(
			'fw-ext-forms-conditions',
			fw_min_uri( $this->get_declared_URI( '/static/js/conditions.js' ) ),
			array(),
			$this->manifest->get_version(),
			true
		);
		// Background sending + accessible error messages; forms still submit normally without it.
		wp_register_script(
			'fw-ext-forms-submit',
			fw_min_uri( $this->get_declared_URI( '/static/js/submit.js' ) ),
			array(),
			$this->manifest->get_version(),
			true
		);
		// "One question at a time": splits the form into one step per field, before steps.js runs.
		wp_register_script(
			'fw-ext-forms-conversational',
			fw_min_uri( $this->get_declared_URI( '/static/js/conversational.js' ) ),
			array( 'fw-ext-forms-submit' ),
			$this->manifest->get_version(),
			true
		);
		// Page Break steps: progress, Back / Next, opening the step with a problem.
		wp_register_script(
			'fw-ext-forms-steps',
			fw_min_uri( $this->get_declared_URI( '/static/js/steps.js' ) ),
			array( 'fw-ext-forms-submit', 'fw-ext-forms-conversational' ),
			$this->manifest->get_version(),
			true
		);
		// Range readouts, ranking Move up / Move down.
		wp_register_script(
			'fw-ext-forms-fields',
			fw_min_uri( $this->get_declared_URI( '/static/js/fields.js' ) ),
			array( 'fw-ext-forms-submit' ),
			$this->manifest->get_version(),
			true
		);
		// Live prices, totals and Calculated fields (the server works them out again on submit).
		wp_register_script(
			'fw-ext-forms-calc',
			fw_min_uri( $this->get_declared_URI( '/static/js/calc.js' ) ),
			array(),
			$this->manifest->get_version(),
			true
		);
		wp_localize_script( 'fw-ext-forms-calc', 'fwFormsMoney', FW_Forms_Money::js() );
		// "Save and continue later" (only acts on forms that switch it on).
		wp_register_script(
			'fw-ext-forms-drafts',
			fw_min_uri( $this->get_declared_URI( '/static/js/drafts.js' ) ),
			array( 'fw-ext-forms-submit' ),
			$this->manifest->get_version(),
			true
		);
		// Analytics counts and unfinished entries.
		wp_register_script(
			'fw-ext-forms-track',
			fw_min_uri( $this->get_declared_URI( '/static/js/track.js' ) ),
			array(),
			$this->manifest->get_version(),
			true
		);
		wp_localize_script( 'fw-ext-forms-track', 'fwFormsTrack', array(
			'on'       => class_exists( 'FW_Forms_Analytics' ) && FW_Forms_Analytics::enabled() && ! ( is_user_logged_in() && current_user_can( 'edit_posts' ) ),
			'endpoint' => rest_url( 'fw-forms/v1/track' ),
			'partial'  => rest_url( 'fw-forms/v1/partial' ),
		) );
		wp_localize_script( 'fw-ext-forms-drafts', 'fwFormsDrafts', array(
			'endpoint' => rest_url( 'fw-forms/v1/drafts' ),
			'i18n'     => array(
				'save'    => __( 'Save and continue later', 'fw' ),
				'email'   => __( 'Email me a link to finish later', 'fw' ),
				'send'    => __( 'Send the link', 'fw' ),
				'failed'  => __( 'The answers could not be saved. Please try again.', 'fw' ),
				'welcome' => __( 'Welcome back: your saved answers are filled in.', 'fw' ),
			),
		) );
		wp_localize_script( 'fw-ext-forms-submit', 'fwFormsSubmit', array(
			'i18n' => array(
				'one'  => __( 'There is a problem with this form:', 'fw' ),
				/* translators: %d: number of problems */
				'many' => __( 'There are %d problems with this form:', 'fw' ),
				'sent' => __( 'Thank you, your message has been sent.', 'fw' ),
				/* translators: 1: current step, 2: number of steps */
				'step' => __( 'Step %1$d of %2$d', 'fw' ),
				/* translators: 1: current question, 2: number of questions */
				'question' => __( 'Question %1$d of %2$d', 'fw' ),
				'next' => __( 'Next', 'fw' ),
				'back' => __( 'Back', 'fw' ),
				/* translators: %s: an option being ranked */
				'up'    => __( 'Move %s up', 'fw' ),
				/* translators: %s: an option being ranked */
				'down'  => __( 'Move %s down', 'fw' ),
				/* translators: 1: an option, 2: its new position */
				'moved' => __( '%1$s is now number %2$d', 'fw' ),
				/* translators: %d: places left for a choice */
				'left'    => __( '%d places left', 'fw' ),
				'oneLeft' => __( '1 place left', 'fw' ),
				'full'    => __( 'full', 'fw' ),
				'orType'  => __( 'Or type your full name to sign', 'fw' ),
			),
		) );
	}

	/**
	 * Enqueue the front-end form assets. Called only where a form is on the page,
	 * so pages without a form don't download them.
	 */
	public function enqueue_frontend_static() {
		if ( is_admin() ) {
			return;
		}
		$this->register_frontend_static();
		wp_enqueue_style( 'fw-ext-forms-default-styles' );
		wp_enqueue_script( 'fw-ext-forms-conditions' );
		wp_enqueue_script( 'fw-ext-forms-submit' );
		wp_enqueue_script( 'fw-ext-forms-steps' );
		wp_enqueue_script( 'fw-ext-forms-fields' );
		wp_enqueue_script( 'fw-ext-forms-calc' );
		wp_enqueue_script( 'fw-ext-forms-drafts' );
		wp_enqueue_script( 'fw-ext-forms-track' );
	}

	/**
	 * Render from items
	 *
	 * @param string $form_id
	 * @param array $form
	 * @param string $form_type
	 * @param string $submit_button
	 *
	 * @return string
	 */
	public final function render_form( $form_id, $form, $form_type, $submit_button = null ) {

		if ( empty( $form['json'] ) ) {
			return '';
		}

		// No-op when the form's shortcode already enqueued these in <head>; otherwise
		// (a form rendered some other way) WordPress prints them in the footer.
		$this->enqueue_frontend_static();

		ob_start();
		{
			$this->frontend_form->render( array(
				'builder_value' => json_decode( $form['json'], true ),
				'form_type'     => $form_type,
				'form_id'       => $form_id,
				'submit'        => $submit_button,
			) );
		}

		return ob_get_clean();
	}

	/**
	 * @internal
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function _frontend_form_render( $data ) {
		$form_id              = $data['data']['form_id']   ?? '';
		$form_type            = $data['data']['form_type'] ?? '';
		$submit_button        = $data['data']['submit']    ?? null;
		$form_type_input_name = 'fw_ext_forms_form_type';
		$form_type_input_id   = 'fw_ext_forms_form_id';

		if ( ! isset( $data['attr'] ) || ! is_array( $data['attr'] ) ) {
			$data['attr'] = array();
		}

		$data['attr']['data-fw-ext-forms-type'] = $form_type;
		// Always multipart so the File Upload field works. Harmless for text-only
		// forms — PHP still populates $_POST identically for multipart submissions.
		$data['attr']['enctype'] = 'multipart/form-data';
		/** Filters the CSS class string applied to the rendered form element. */
		$data['attr']['class'] = apply_filters( 'fw:ext:forms:attr:class', $data['attr']['class'] ?? '' );

		echo '<input type="hidden" name="' . esc_attr( $form_type_input_name ) . '" value="' . esc_attr( $form_type ) . '" />';
		echo '<input type="hidden" name="' . esc_attr( $form_type_input_id )   . '" value="' . esc_attr( $form_id )   . '" />';

		/**
		 * @var FW_Ext_Forms_Type $form_type_instance
		 */
		$form_type_instance = fw_ext( $form_type );

		if ( ! $form_type_instance ) {
			return $data;
		}

		/**
		 * @var FW_Option_Type_Form_Builder $builder
		 */
		$builder = fw()->backend->option_type( $form_type_instance->get_form_builder_type() );

		$items = isset( $data['data']['builder_value'] ) && is_array( $data['data']['builder_value'] )
			? $data['data']['builder_value']
			: array();

		// Every form of this type on a page is the same FW_Form, so "was it submitted" is not enough:
		// only the form that was actually sent gets the posted values back, and its errors.
		$is_sent = ( (string) FW_Request::POST( $form_type_input_id ) === (string) $form_id )
			&& (string) FW_Request::POST( $form_type_input_name ) === (string) $form_type;

		// A form not being re-shown after its own submission starts from its pre-filled values
		// (logged-in user, ?<field>= in the address, the fw_ext_forms_prefill_values filter).
		$start = ( ! $is_sent && class_exists( 'FW_Forms_Prefill' ) ) ? FW_Forms_Prefill::values( $items ) : array();

		echo $builder->frontend_render( $items, $is_sent ? FW_Request::POST() : $start );

		if ( $this->frontend_form->is_submitted() && ! $this->frontend_form->is_valid() ) {
			// Reading the errors stops FW_Form printing its own list, which lands above whichever form
			// renders first on the page. The form that was sent prints them instead, inside itself (also
			// without JavaScript); static/js/submit.js then moves each message next to its field.
			$errors = $this->frontend_form->get_errors();

			if ( $is_sent && $errors ) {
				echo '<ul class="fw-form-errors" role="alert">';
				foreach ( $errors as $input_name => $message ) {
					echo '<li data-input-name="' . esc_attr( $input_name ) . '">' . wp_kses_post( $message ) . '</li>';
				}
				echo '</ul>';
			}
		}

		// The form's "show only when…" rules, for the front-end script. Emitted
		// INSIDE the form so the script scopes them to this form alone; the
		// server enforces the same rules regardless of what the script does.
		if ( class_exists( 'FW_Forms_Conditions' ) ) {
			$rules = FW_Forms_Conditions::rules_for_client( $items );

			if ( $rules ) {
				echo '<script type="application/json" class="fw-form-conditions">' . wp_json_encode( $rules ) . '</script>';
			}
		}

		if ( ! is_null( $submit_button ) ) {
			$data['submit']['html'] = $submit_button;
		}

		return $data;
	}

	/**
	 * @internal
	 *
	 * @param array $errors
	 *
	 * @return array
	 */
	public function _frontend_form_validate( $errors ) {
		// Public submissions usually send mail; a nonce does not bound how often
		// one visitor can replay the form. 10 per 10 minutes leaves room to fix
		// validation errors and resubmit.
		if ( function_exists( 'fw_rate_limit_exceeded' ) && fw_rate_limit_exceeded( 'fw_ext_forms_submit', 10, 600 ) ) {
			return array(
				'rate-limit' => __( 'Too many submissions. Please wait a few minutes and try again.', 'fw' ),
			);
		}

		$form_id   = FW_Request::POST( 'fw_ext_forms_form_id' );
		$form_type = FW_Request::POST( 'fw_ext_forms_form_type' );

		if ( empty( $form_id ) || empty( $form_type ) ) {
			return array(
				'invalid-form-id' => __( 'Unable to process the form', 'fw' )
			);
		}

		/**
		 * @var FW_Ext_Forms_Type $form_instance
		 */
		$form_instance = fw_ext( $form_type );

		if ( ! $this->_child_extension_is_valid( $form_instance ) ) {
			return array(
				'invalid-form-id' => __( 'Unable to process the form', 'fw' )
			);
		}

		$form = $form_instance->get_form_builder_value( $form_id );

		if ( empty( $form ) || empty( $form['json'] ) ) {
			return array(
				'invalid-form-id' => __( 'Unable to process the form', 'fw' )
			);
		}

		/**
		 * @var FW_Option_Type_Form_Builder $builder
		 */
		$builder = fw()->backend->option_type( $form_instance->get_form_builder_type() );

		if ( ! $builder instanceof FW_Option_Type_Form_Builder ) {
			return array(
				'invalid-form-id' => __( 'Unable to process the form', 'fw' )
			);
		}

		$items = json_decode( (string) $form['json'], true );

		if ( ! is_array( $items ) ) {
			return array(
				'invalid-form-id' => __( 'Unable to process the form', 'fw' )
			);
		}

		// Prices, formulas and the order are worked out from the whole form (FW_Forms_Order).
		FW_Forms_Order::use_items( $items );

		$items_errors = $builder->frontend_validate( $items, FW_Request::POST() );

		if ( ! empty( $items_errors ) ) {
			$errors = array_merge( $errors, $items_errors );
		}

		if ( empty( $errors ) ) {
			$shortcode_to_item = array();
			$this->extract_shortcode_item( $shortcode_to_item, $items );

			/**
			 * Lets a form action (or anything else) refuse a submission that passed field
			 * validation, with a message the visitor sees — a registration whose email
			 * already has an account, for one. Return the errors array, adding
			 * `key => message` to refuse. Runs only when the fields themselves are valid.
			 *
			 * @param array $errors Empty.
			 * @param array $data   { id, type, shortcode_to_item, form_values }
			 */
			$errors = (array) apply_filters( 'fw_ext_forms_validate_submission', $errors, array(
				'id'                => $form_id,
				'type'              => $form_type,
				'shortcode_to_item' => $shortcode_to_item,
				'form_values'       => $builder->frontend_get_value_from_items( $items, FW_Request::POST() ),
			) );
		}

		return $errors;
	}

	/**
	 * @param array $fw_form_data
	 *
	 * @return array
	 *
	 * @internal
	 */
	public function _frontend_form_save( $fw_form_data ) {
		$form_id   = FW_Request::POST( 'fw_ext_forms_form_id' );
		$form_type = FW_Request::POST( 'fw_ext_forms_form_type' );

		/**
		 * By default redirect to the same page
		 * to prevent form submit alert on page refresh
		 */
		$fw_form_data['redirect'] = fw_current_url();

		// Remember which form was sent for the page that follows the redirect, so its success or
		// error message is shown next to THAT form rather than the first form on the page.
		if ( ! headers_sent() && is_string( $form_id ) && preg_match( '/^[A-Za-z0-9_-]{1,64}$/', $form_id ) ) {
			setcookie( 'fw_ext_forms_last', $form_id, time() + 120, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), false );
		}

		/**
		 * @var FW_Ext_Forms_Type $form_instance
		 */
		$form_instance = fw_ext( $form_type );

		if ( ! $this->_child_extension_is_valid( $form_instance ) ) {
			return $fw_form_data;
		}

		/**
		 * @var FW_Option_Type_Form_Builder $builder
		 */
		$builder = fw()->backend->option_type( $form_instance->get_form_builder_type() );

		if ( ! $builder instanceof FW_Option_Type_Form_Builder ) {
			return $fw_form_data;
		}

		/**
		 * {json: '...', ...}
		 */
		$builder_value = $form_instance->get_form_builder_value( $form_id );

		if ( empty( $builder_value ) || empty( $builder_value['json'] ) ) {
			return $fw_form_data;
		}

		/**
		 * {[item], [item], ...}
		 */
		$builder_value_json_array = json_decode( (string) $builder_value['json'], true );

		if ( ! is_array( $builder_value_json_array ) || empty( $builder_value_json_array ) ) {
			return $fw_form_data;
		}

		/**
		 * {shortcode => item}
		 */
		$shortcode_to_item = array();
		$this->extract_shortcode_item( $shortcode_to_item, $builder_value_json_array );

		FW_Forms_Order::use_items( $builder_value_json_array );

		$form_values = $builder->frontend_get_value_from_items(
			$builder_value_json_array,
			FW_Request::POST()
		);

		/**
		 * File-upload fields live in $_FILES (not $_POST), so they bypass the value
		 * pipeline above. Now that validation has passed, move them via the
		 * collect-uploads filter, then merge each uploaded filename into the form
		 * values (for the email body) and its absolute path into the attachments
		 * (the mailer attaches them). No-op for forms without file fields.
		 */
		$attachments = array();
		/** Filters the collected uploaded files (from File Upload fields) mapped by shortcode, after validation passes. */
		$uploads     = apply_filters( 'fw:ext:forms:collect-uploads', array(), $shortcode_to_item );
		if ( is_array( $uploads ) ) {
			foreach ( $uploads as $upload_shortcode => $upload ) {
				if ( ! is_array( $upload ) ) {
					continue;
				}
				$form_values[ $upload_shortcode ] = $upload['name'] ?? '';
				if ( ! empty( $upload['file'] ) ) {
					$attachments[] = $upload['file'];
				}
			}
		}

		/**
		 * Lets a listener handle a submission itself, before the form type processes it (the notification,
		 * the actions, the stored entry). Return array( 'redirect' => url ) to stop there; null to go on.
		 * Used by "senders edit their entry".
		 *
		 * @since 2.0.83
		 *
		 * @param array|null $result
		 * @param array      $data form_id, form_type, form_values, shortcode_to_item, uploads
		 */
		$handled = apply_filters( 'fw_ext_forms_pre_process', null, array(
			'form_id'           => $form_id,
			'form_type'         => $form_type,
			'form_values'       => $form_values,
			'shortcode_to_item' => $shortcode_to_item,
			'uploads'           => is_array( $uploads ) ? $uploads : array(),
		) );
		if ( is_array( $handled ) ) {
			if ( ! empty( $handled['redirect'] ) ) {
				$fw_form_data['redirect'] = $handled['redirect'];
			}
			return $fw_form_data;
		}

		$process_data = $form_instance->process_form(
			$form_values,
			array(
				'shortcode_to_item' => $shortcode_to_item,
				'builder_value'     => $builder_value,
				'attachments'       => $attachments,
				/** @since 2.0.79 Uploaded files by field: { name, file, url, type }. */
				'uploads'           => is_array( $uploads ) ? $uploads : array(),
			)
		);

		if ( is_array( $process_data ) ) {
			if ( isset( $process_data['redirect'] ) ) {
				$fw_form_data['redirect'] = $process_data['redirect'];
			}
		}

		/** Fires after a frontend form is submitted, passing form id, type, instance, and process data for post-submit handling. */
		do_action('fw_ext_forms_frontend_submit', array(
			'id' => $form_id,
			'type' => $form_type,
			'instance' => $form_instance,
			'process_data' => $process_data,
			/** @since 2.0.28 */
			'shortcode_to_item' => $shortcode_to_item,
			/** @since 2.0.28 */
			'builder_value'     => $builder_value,
			/**
			 * @since 2.0.50 The submitted values (shortcode => value) and uploaded
			 * file paths. Without these a listener could see THAT a form was
			 * submitted but not WHAT was submitted — which is why entries could
			 * not be stored from this hook before.
			 */
			'form_values'       => $form_values,
			'attachments'       => $attachments,
			/** Uploaded files by field shortcode: { name, file, url, type }. */
			'uploads'           => is_array( $uploads ) ? $uploads : array(),
		));

		// A card payment waits on its service's page: go there instead, and keep the form's own
		// redirect for after paying. The success message waits too (the return shows a notice).
		$pay_page = FW_Forms_Payments::take_redirect( isset( $fw_form_data['redirect'] ) ? (string) $fw_form_data['redirect'] : '' );
		if ( '' !== $pay_page ) {
			FW_Flash_Messages::_clear();
			$fw_form_data['redirect'] = $pay_page;
		}

		return $fw_form_data;
	}

	/**
	 * @internal
	 *
	 * @param mixed $child_extension_instance
	 *
	 * @return bool
	 */
	public function _child_extension_is_valid( $child_extension_instance ) {
		return is_subclass_of( $child_extension_instance, 'FW_Extension_Forms_Form' );
	}

	/**
	 * Extract recursive all items in one level array
	 * @param array $extracted {shortcode => item}
	 * @param $items array, some items can have sub-items in the '_items' key
	 */
	private function extract_shortcode_item(&$extracted, &$items)
	{
		if (!is_array($items)) {
			return;
		}
		foreach ($items as &$item) {
			$extracted[ $item['shortcode'] ] = $item;
			if (!empty($item['_items'])) {
				$this->extract_shortcode_item($extracted, $item['_items']);
			}
		}
	}

	/**
	 * @param string $val
	 * @param FW_Form $form
	 * @param array $render_data
	 * @return string
	 */
	public function _filter_frontend_nonce_name_date($val, $form, $render_data) {
		if ($form->get_id() === $this->frontend_form->get_id()) {
			if (isset($render_data['data']['form_id'])) {
				return $render_data['data']['form_id'];
			} else {
				return FW_Request::POST('fw_ext_forms_form_id', '');
			}
		}

		return $val;
	}
}
