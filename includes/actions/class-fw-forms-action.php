<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * One thing a form does after a successful submission.
 *
 * Today "email the admin" is what a contact form does, hard-coded. An Action is
 * the general form of that: a named, per-form-configurable step that runs once
 * the submission has passed validation. Forms ships "store entry"; the
 * Newsletter CRM contributes "subscribe to list"; a webhook or a WooCommerce
 * action plug in the same way — through the `fw_ext_forms_actions` filter, so
 * Forms never has to know they exist.
 *
 * An action's options appear on the form's Settings → Actions tab, each action
 * in its own group. Option ids MUST be prefixed `action_{id}_` (and the on/off
 * switch is `action_{id}`) so they cannot collide with the form's own options
 * or with another action's.
 */
abstract class FW_Forms_Action {

	/** @return string Machine id, e.g. 'store', 'subscribe'. Lowercase, [a-z0-9_]. */
	abstract public function get_id();

	/** @return string Human title for the Actions tab. */
	abstract public function get_title();

	/**
	 * Run the action. Only called when `is_enabled()` said yes.
	 *
	 * @param array $ctx {
	 *     @type string $form_id
	 *     @type string $form_type
	 *     @type array  $form      The form's saved settings (its atts) — read your own options from here.
	 *     @type array  $fields    Entry-style rows [ { id, type, label, value[, purpose] } ], builder order.
	 *     @type string $email     The primary email, or ''.
	 *     @type int    $entry_id  The stored entry's id, or 0 if storing is off.
	 *     @type array  $payload   The raw submit-hook payload.
	 * }
	 *
	 * @return void|WP_Error A WP_Error is logged (via the `fw_ext_forms_action_failed` hook), never shown to the visitor.
	 */
	abstract public function run( array $ctx );

	/**
	 * The per-form options this action adds to the Actions tab. Return a flat
	 * option map (id => option); the registry wraps it in a titled group.
	 *
	 * The first option should be the on/off switch, id `action_{id}`.
	 *
	 * @return array
	 */
	abstract public function get_options();

	/**
	 * @param array $form The form's saved settings.
	 *
	 * @return bool
	 */
	public function is_enabled( array $form ) {
		return 'yes' === self::opt( $form, 'action_' . $this->get_id(), $this->default_enabled() ? 'yes' : 'no' );
	}

	/**
	 * Whether a form that has never seen this action should run it. Default off:
	 * an action that sends data somewhere must be a decision, not a surprise.
	 *
	 * @return bool
	 */
	protected function default_enabled() {
		return false;
	}

	/**
	 * Read one of this action's options from the form's settings.
	 *
	 * @param array  $form
	 * @param string $key     Full option id, e.g. 'action_subscribe_list'.
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public static function opt( array $form, $key, $default = '' ) {
		return isset( $form[ $key ] ) && '' !== $form[ $key ] && null !== $form[ $key ] ? $form[ $key ] : $default;
	}

	/**
	 * A standard on/off switch for the Actions tab.
	 *
	 * @param string $label
	 * @param string $desc
	 * @param bool   $default
	 *
	 * @return array
	 */
	protected function switch_option( $label, $desc, $default = false ) {
		return array(
			'type'         => 'switch',
			'label'        => $label,
			'desc'         => $desc,
			'value'        => $default ? 'yes' : 'no',
			'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
			'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
		);
	}
}
