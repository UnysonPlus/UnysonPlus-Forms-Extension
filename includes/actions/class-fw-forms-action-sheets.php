<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * "Send to a spreadsheet" — adds each submission as a row in a Google Sheets spreadsheet.
 *
 * Signs in as a Google service account (Forms Settings → Google Sheets: the account's JSON key; share
 * the spreadsheet with the account's email as an editor). No SDK: an RS256 JWT (openssl_sign) is
 * exchanged for a token (cached 50 minutes), then the Sheets v4 REST API:
 *   - the first row is the header: "Submitted", "Entry", then each answer's label in form order; labels
 *     the sheet does not have yet are added at the end, so columns never move under existing data;
 *   - the row is appended with valueInputOption=RAW, so an answer starting with "=" stays text and can
 *     never run as a formula.
 * A failure is logged (`fw_ext_forms_action_failed`), never shown to the visitor.
 */
class FW_Forms_Action_Sheets extends FW_Forms_Action {

	const API   = 'https://sheets.googleapis.com/v4/spreadsheets/';
	const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

	public function get_id() {
		return 'sheets';
	}

	public function get_title() {
		return __( 'Send to a spreadsheet', 'fw' );
	}

	public function get_options() {
		return array(
			'action_sheets'             => $this->switch_option(
				__( 'Add each submission to a Google Sheets spreadsheet', 'fw' ),
				__( 'Needs a Google service account key in Forms Settings → Google Sheets, and the spreadsheet shared with that account as an editor.', 'fw' )
			),
			'action_sheets_spreadsheet' => array( 'type' => 'text', 'label' => __( 'Spreadsheet', 'fw' ), 'desc' => __( 'Its address (https://docs.google.com/spreadsheets/d/…) or its ID.', 'fw' ), 'value' => '' ),
			'action_sheets_tab'         => array( 'type' => 'text', 'label' => __( 'Sheet (tab) name', 'fw' ), 'value' => 'Sheet1' ),
		);
	}

	/** @return array|null The service account { client_email, private_key, token_uri }. */
	public static function account() {
		$json = json_decode( (string) fw_ext( 'forms' )->get_db_settings_option( 'sheets_service_account', '' ), true );
		if ( ! is_array( $json ) || empty( $json['client_email'] ) || empty( $json['private_key'] ) ) {
			return null;
		}
		return array(
			'client_email' => (string) $json['client_email'],
			'private_key'  => (string) $json['private_key'],
			'token_uri'    => ! empty( $json['token_uri'] ) ? (string) $json['token_uri'] : 'https://oauth2.googleapis.com/token',
		);
	}

	private static function b64( $s ) {
		return rtrim( strtr( base64_encode( $s ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/** @return string|WP_Error An access token. */
	public static function token() {
		$a = self::account();
		if ( ! $a ) {
			return new WP_Error( 'fw_forms_sheets_key', 'No Google service account key in Forms Settings.' );
		}
		$cache = 'fw_forms_gsheets_' . md5( $a['client_email'] );
		$t     = get_transient( $cache );
		if ( $t ) {
			return $t;
		}
		$now   = time();
		$unsig = self::b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) ) . '.' . self::b64( wp_json_encode( array( 'iss' => $a['client_email'], 'scope' => self::SCOPE, 'aud' => $a['token_uri'], 'iat' => $now, 'exp' => $now + 3600 ) ) );
		$key   = openssl_pkey_get_private( $a['private_key'] );
		if ( ! $key || ! openssl_sign( $unsig, $sig, $key, 'sha256WithRSAEncryption' ) ) {
			return new WP_Error( 'fw_forms_sheets_sign', 'The service account key could not sign.' );
		}
		$res = wp_remote_post( $a['token_uri'], array( 'timeout' => 15, 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsig . '.' . self::b64( $sig ) ) ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['access_token'] ) ) {
			return new WP_Error( 'fw_forms_sheets_token', 'Google refused the service account: ' . (string) ( $body['error_description'] ?? $body['error'] ?? wp_remote_retrieve_response_code( $res ) ) );
		}
		set_transient( $cache, (string) $body['access_token'], 50 * MINUTE_IN_SECONDS );
		return (string) $body['access_token'];
	}

	private static function api( $method, $url, $token, array $body = null ) {
		$args = array( 'method' => $method, 'timeout' => 15, 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ) );
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$res = wp_remote_request( $url, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$out  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code >= 400 ) {
			return new WP_Error( 'fw_forms_sheets_http', 'Google Sheets answered HTTP ' . $code . ': ' . (string) ( $out['error']['message'] ?? '' ) );
		}
		return is_array( $out ) ? $out : array();
	}

	/** The spreadsheet ID from an address or an ID. */
	public static function sheet_id( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '#/spreadsheets/d/([A-Za-z0-9_\-]+)#', $v, $m ) ) {
			return $m[1];
		}
		return preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $v ) ? $v : '';
	}

	/** "'My sheet'!A1" for the API. */
	private static function range( $tab, $cells ) {
		return rawurlencode( "'" . str_replace( "'", "''", $tab ) . "'!" . $cells );
	}

	public function run( array $ctx ) {
		$id  = self::sheet_id( self::opt( $ctx['form'], 'action_sheets_spreadsheet', '' ) );
		$tab = trim( (string) self::opt( $ctx['form'], 'action_sheets_tab', 'Sheet1' ) );
		if ( '' === $id ) {
			return new WP_Error( 'fw_forms_sheets_id', 'No spreadsheet set on the form.' );
		}
		$token = self::token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$base = self::API . rawurlencode( $id ) . '/values/';

		// What this submission has, in form order.
		$cells = array( __( 'Submitted', 'fw' ) => wp_date( 'Y-m-d H:i:s' ), __( 'Entry', 'fw' ) => (string) (int) ( $ctx['entry_id'] ?? 0 ) );
		foreach ( (array) $ctx['fields'] as $f ) {
			$label = trim( wp_strip_all_tags( (string) ( $f['label'] ?? $f['id'] ?? '' ) ) );
			if ( '' === $label ) {
				continue;
			}
			$v               = $f['value'] ?? '';
			$cells[ $label ] = is_array( $v ) ? implode( ', ', array_map( 'strval', $v ) ) : (string) $v;
		}

		// The header row: keep what is there, add new labels at the end.
		$head = self::api( 'GET', $base . self::range( $tab, '1:1' ), $token );
		if ( is_wp_error( $head ) ) {
			return $head;
		}
		$header = array_map( 'strval', (array) ( $head['values'][0] ?? array() ) );
		$grown  = false;
		foreach ( array_keys( $cells ) as $label ) {
			if ( ! in_array( (string) $label, $header, true ) ) {
				$header[] = (string) $label;
				$grown    = true;
			}
		}
		if ( $grown ) {
			$r = self::api( 'PUT', $base . self::range( $tab, 'A1' ) . '?valueInputOption=RAW', $token, array( 'values' => array( $header ) ) );
			if ( is_wp_error( $r ) ) {
				return $r;
			}
		}
		$row = array();
		foreach ( $header as $label ) {
			$row[] = (string) ( $cells[ $label ] ?? '' );
		}
		$r = self::api( 'POST', $base . self::range( $tab, 'A1' ) . ':append?valueInputOption=RAW&insertDataOption=INSERT_ROWS', $token, array( 'values' => array( $row ) ) );
		return is_wp_error( $r ) ? $r : null;
	}
}
