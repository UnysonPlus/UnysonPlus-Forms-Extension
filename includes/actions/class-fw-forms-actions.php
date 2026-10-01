<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * The action registry and runner.
 *
 *  - `all()` collects actions through `fw_ext_forms_actions` (Forms' own first,
 *    then whatever other extensions add).
 *  - `options_tab()` builds the Settings → Actions tab for the contact-form
 *    element from every action's options.
 *  - The runner listens on `fw_ext_forms_frontend_submit` at priority 20 —
 *    AFTER the entry capture at 10 — so actions see the stored entry's id.
 *
 * Actions are isolated from each other and from the visitor: one action
 * throwing or returning a WP_Error never stops the next, and never surfaces as
 * a form error. The visitor already succeeded; what happens next is the site's
 * business, and is reported through `fw_ext_forms_action_failed`.
 */
class FW_Forms_Actions {

	/** @var FW_Forms_Action[]|null */
	private static $cache = null;

	public function __construct() {
		add_action( 'fw_ext_forms_frontend_submit', array( $this, '_action_submit' ), 20 );
		add_action( 'fw_ext_forms_entry_stored', array( __CLASS__, '_remember_entry' ), 10, 1 );
	}

	/* ---------------------------------------------------------------------- *
	 * Registry
	 * ---------------------------------------------------------------------- */

	/**
	 * @return FW_Forms_Action[] id => action
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		require_once dirname( __FILE__ ) . '/class-fw-forms-action-store.php';

		$actions = array( new FW_Forms_Action_Store() );

		/**
		 * Register form actions. Append instances of FW_Forms_Action.
		 *
		 * @param FW_Forms_Action[] $actions
		 */
		$actions = apply_filters( 'fw_ext_forms_actions', $actions );

		$out = array();

		foreach ( (array) $actions as $a ) {
			if ( $a instanceof FW_Forms_Action && preg_match( '/^[a-z0-9_]+$/', $a->get_id() ) ) {
				$out[ $a->get_id() ] = $a;
			}
		}

		self::$cache = $out;

		return $out;
	}

	/**
	 * Settings → Actions: one titled group per action, its switch first.
	 *
	 * @return array A tab definition, ready to drop into the contact-form options.
	 */
	public static function options_tab() {
		$groups = array();

		foreach ( self::all() as $id => $action ) {
			$options = $action->get_options();

			if ( ! $options ) {
				continue;
			}

			$groups[ 'action_group_' . $id ] = array(
				'type'    => 'group',
				'options' => array_merge(
					array(
						'action_title_' . $id => array(
							'type'  => 'html-fixed',
							'label' => false,
							'html'  => '<strong>' . esc_html( $action->get_title() ) . '</strong>',
						),
					),
					$options
				),
			);
		}

		return array(
			'title'   => __( 'Actions', 'fw' ),
			'type'    => 'tab',
			'options' => $groups ? $groups : array(
				'actions_none' => array(
					'type'  => 'html-fixed',
					'label' => false,
					'html'  => esc_html__( 'No actions are available.', 'fw' ),
				),
			),
		);
	}

	/* ---------------------------------------------------------------------- *
	 * Runner
	 * ---------------------------------------------------------------------- */

	/** @var int The id of the entry stored during THIS request, if any. */
	private static $entry_id = 0;

	/**
	 * @internal
	 */
	public static function _remember_entry( $id ) {
		self::$entry_id = (int) $id;
	}

	/**
	 * @internal
	 *
	 * @param array $data The submit-hook payload.
	 */
	public function _action_submit( $data ) {
		if ( ! is_array( $data ) ) {
			return;
		}

		$form = self::form_settings( $data );

		if ( ! $form ) {
			return;
		}

		$values = isset( $data['form_values'] ) && is_array( $data['form_values'] ) ? $data['form_values'] : array();
		$items  = isset( $data['shortcode_to_item'] ) && is_array( $data['shortcode_to_item'] ) ? $data['shortcode_to_item'] : array();
		$fields = class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::fields_from_submission( $values, $items ) : array();

		$ctx = array(
			'form_id'   => (string) ( $data['id'] ?? '' ),
			'form_type' => (string) ( $data['type'] ?? '' ),
			'form'      => $form,
			'fields'    => $fields,
			'email'     => class_exists( 'FW_Forms_Entries_Capture' ) ? FW_Forms_Entries_Capture::primary_email( $fields ) : '',
			'entry_id'  => self::$entry_id,
			'payload'   => $data,
		);

		self::$entry_id = 0;

		foreach ( self::all() as $id => $action ) {
			if ( ! $action->is_enabled( $form ) ) {
				continue;
			}

			try {
				$result = $action->run( $ctx );
			} catch ( \Throwable $e ) {
				$result = new WP_Error( 'fw_forms_action_exception', $e->getMessage() );
			}

			if ( is_wp_error( $result ) ) {
				/**
				 * Fires when an action fails. The visitor never sees this.
				 *
				 * @param string   $id
				 * @param WP_Error $result
				 * @param array    $ctx
				 */
				do_action( 'fw_ext_forms_action_failed', $id, $result, $ctx );
			} else {
				/**
				 * Fires after an action ran.
				 *
				 * @param string $id
				 * @param array  $ctx
				 */
				do_action( 'fw_ext_forms_action_ran', $id, $ctx );
			}
		}
	}

	/**
	 * The form's saved settings (its element atts). Forms core does not know
	 * where a form type keeps them, so each type answers a filter — the same
	 * seam the entry title uses.
	 *
	 * @param array $data Submit-hook payload.
	 *
	 * @return array
	 */
	public static function form_settings( array $data ) {
		/**
		 * Filters the saved settings of the form being submitted. A form type
		 * (e.g. contact-forms) returns the stored element atts for `$data['id']`.
		 *
		 * @param array $settings Default: empty.
		 * @param array $data     The submit-hook payload.
		 */
		$settings = apply_filters( 'fw_ext_forms_form_settings', array(), $data );

		return is_array( $settings ) ? $settings : array();
	}
}
