<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Unfinished entries (contact form Settings → Options: partial_enabled, partial_notice). Off by default.
 *
 * When a visitor leaves a form they started but did not send, static/js/track.js posts what they typed
 * to POST /wp-json/fw-forms/v1/partial with a token made for that page visit; it is stored as an entry
 * with the status "Unfinished" (one per visit, updated as they go). If they then send the form, the
 * unfinished copy is deleted. Unfinished entries stay out of "All", counts, limits, results and the CRM.
 *
 * The form shows the notice under it while this is on, so visitors know (privacy). Passwords, files and
 * drawn signatures are never kept.
 */
class FW_Forms_Partial_Entries {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, '_routes' ) );
		add_action( 'fw_ext_forms_frontend_submit', array( __CLASS__, '_finished' ), 45 );
	}

	public static function options() {
		return array(
			'type'    => 'group',
			'options' => array(
				'partial_enabled' => array(
					'type'         => 'switch',
					'label'        => __( 'Keep unfinished entries', 'fw' ),
					'desc'         => __( 'Keep what a visitor typed into this form even if they leave without sending it, so a half-finished request can be followed up. The notice below is shown under the form while this is on.', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
				'partial_notice'  => array( 'type' => 'text', 'label' => __( 'Notice under the form', 'fw' ), 'value' => __( 'We keep what you type here, even if you do not send it, so we can help you finish.', 'fw' ) ),
			),
		);
	}

	/** @internal */
	public static function _routes() {
		register_rest_route( 'fw-forms/v1', '/partial', array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'callback'            => array( __CLASS__, 'save' ),
		) );
	}

	/** Answers posted as flat name => value pairs, back into the nested shape of $_POST. */
	private static function to_input( array $answers ) {
		$pairs = array();
		foreach ( $answers as $name => $value ) {
			foreach ( is_array( $value ) ? $value : array( $value ) as $v ) {
				if ( is_scalar( $v ) ) {
					$pairs[] = rawurlencode( (string) $name ) . '=' . rawurlencode( substr( (string) $v, 0, 5000 ) );
				}
			}
		}
		parse_str( implode( '&', $pairs ), $out );
		return is_array( $out ) ? $out : array();
	}

	public static function save( WP_REST_Request $r ) {
		$done = new WP_REST_Response( null, 204 );
		$p    = json_decode( (string) $r->get_body(), true );
		if ( ! is_array( $p ) || strlen( (string) $r->get_body() ) > 65536 ) {
			return $done;
		}
		$form_id = isset( $p['form_id'] ) && is_string( $p['form_id'] ) && preg_match( '/^[A-Za-z0-9_\-]{1,64}$/', $p['form_id'] ) ? $p['form_id'] : '';
		$token   = isset( $p['token'] ) && is_string( $p['token'] ) && preg_match( '/^[A-Za-z0-9]{24}$/', $p['token'] ) ? $p['token'] : '';
		$form    = '' !== $form_id && class_exists( 'FW_Forms_Actions' ) ? FW_Forms_Actions::form_settings( array( 'id' => $form_id, 'type' => 'contact-forms' ) ) : array();
		if ( '' === $token || ! $form || 'yes' !== ( $form['partial_enabled'] ?? 'no' ) || ! FW_Forms_Entries_Capture::enabled() ) {
			return $done;
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'fw_forms_partial_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 30 ) {
			return $done;
		}
		set_transient( $key, $n + 1, 10 * MINUTE_IN_SECONDS );

		$items   = FW_Forms_Order::flatten( (array) json_decode( (string) ( $form['form']['json'] ?? '[]' ), true ) );
		$secret  = FW_Forms_Entries_Capture::secret_types();
		$input   = self::to_input( (array) ( $p['answers'] ?? array() ) );
		$builder = fw()->backend->option_type( 'form-builder' );
		if ( ! $builder instanceof FW_Option_Type_Form_Builder || ! $items ) {
			return $done;
		}
		foreach ( $items as $sc => $it ) {
			if ( in_array( $it['type'] ?? '', array_merge( $secret, array( 'signature', 'file-upload', 'recaptcha', 'turnstile', 'hcaptcha', 'honeypot' ) ), true ) ) {
				unset( $input[ $sc ], $items[ $sc ] );
			}
		}
		FW_Forms_Order::use_items( array_values( $items ) );
		$values = $builder->frontend_get_value_from_items( array_values( $items ), $input );
		$fields = array_values( array_filter( FW_Forms_Entries_Capture::fields_from_submission( array_filter( (array) $values, function ( $v ) {
			return null !== $v && '' !== $v && array() !== $v;
		} ), $items ), function ( $f ) {
			return '' !== $f['value'] && array() !== $f['value'];
		} ) );
		if ( ! $fields ) {
			return $done;
		}
		$email = FW_Forms_Entries_Capture::primary_email( $fields );
		$map   = 'fw_forms_partial_' . md5( $token );
		$id    = (int) get_transient( $map );
		if ( $id && FW_Forms_Entries::find( $id ) ) {
			FW_Forms_Entries::update_fields( $id, $fields, $email );
		} else {
			$id = FW_Forms_Entries::insert( array(
				'form_id'    => $form_id,
				'form_type'  => 'contact-forms',
				'form_title' => FW_Forms_Entries_Capture::form_title( array( 'id' => $form_id, 'type' => 'contact-forms' ) ),
				'post_id'    => (int) url_to_postid( (string) ( $p['page_url'] ?? '' ) ),
				'email'      => $email,
				'fields'     => $fields,
				'status'     => 'partial',
			) );
			if ( $id ) {
				set_transient( $map, $id, 2 * DAY_IN_SECONDS );
			}
		}
		return $done;
	}

	/** @internal The form was sent after all: the unfinished copy goes. */
	public static function _finished( $data ) {
		$token = FW_Request::POST( 'fw_partial_token' );
		if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9]{24}$/', $token ) ) {
			return;
		}
		$id = (int) get_transient( 'fw_forms_partial_' . md5( $token ) );
		$e  = $id ? FW_Forms_Entries::find( $id ) : null;
		if ( $e && 'partial' === $e->status ) {
			FW_Forms_Entries::delete( array( $id ) );
		}
		delete_transient( 'fw_forms_partial_' . md5( $token ) );
	}
}
