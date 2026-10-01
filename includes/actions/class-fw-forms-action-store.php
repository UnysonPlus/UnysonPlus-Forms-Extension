<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Store entry" as an Action.
 *
 * The actual write is done by FW_Forms_Entries_Capture on the submit hook at
 * priority 10 (before this runner), so this action's job is the per-form
 * switch and its place on the Actions tab: `is_enabled()` is what the capture
 * consults. Default ON — a form that stores nothing is the failure mode we are
 * moving away from — and the global Forms setting stays the master switch
 * above it.
 */
class FW_Forms_Action_Store extends FW_Forms_Action {

	public function get_id() {
		return 'store';
	}

	public function get_title() {
		return __( 'Store entry', 'fw' );
	}

	protected function default_enabled() {
		return true;
	}

	public function get_options() {
		return array(
			'action_store' => $this->switch_option(
				__( 'Keep every submission as an entry', 'fw' ),
				__( 'Listed under Unyson+ → Form Entries, searchable and exportable. Stored even if the email fails to send. The global switch under Forms → Settings can turn this off for the whole site.', 'fw' ),
				true
			),
		);
	}

	public function run( array $ctx ) {
		// Nothing to do: the capture already stored it before this runner ran.
	}
}
