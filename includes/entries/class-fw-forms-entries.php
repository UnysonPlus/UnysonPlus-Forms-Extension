<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * The entries repository — the only code that writes SQL against the entries
 * table. Everything above it (capture, admin, privacy) calls these methods.
 *
 * Same layering rule as the Newsletter CRM: nothing above the repository writes
 * SQL, and nothing in the repository fires a hook.
 */
class FW_Forms_Entries {

	/** The states an entry can be in. A whitelist, so an unknown value never reaches SQL. */
	const STATUSES = array( 'new', 'read', 'archived' );

	/**
	 * @return string
	 */
	public static function table() {
		return FW_Forms_Entries_Installer::table();
	}

	/* ---------------------------------------------------------------------- *
	 * Write
	 * ---------------------------------------------------------------------- */

	/**
	 * @param array $data form_id, form_type, form_title, post_id, email, fields (array), ip, user_agent
	 *
	 * @return int The new id, or 0.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$row = array(
			'form_id'    => substr( sanitize_text_field( (string) ( $data['form_id'] ?? '' ) ), 0, 64 ),
			'form_type'  => substr( sanitize_key( (string) ( $data['form_type'] ?? '' ) ), 0, 64 ),
			'form_title' => substr( sanitize_text_field( (string) ( $data['form_title'] ?? '' ) ), 0, 200 ),
			'post_id'    => (int) ( $data['post_id'] ?? 0 ),
			'email'      => substr( sanitize_email( (string) ( $data['email'] ?? '' ) ), 0, 190 ),
			'fields'     => wp_json_encode( is_array( $data['fields'] ?? null ) ? $data['fields'] : array() ),
			'status'     => 'new',
			'ip'         => substr( sanitize_text_field( (string) ( $data['ip'] ?? '' ) ), 0, 45 ),
			'user_agent' => substr( sanitize_text_field( (string) ( $data['user_agent'] ?? '' ) ), 0, 255 ),
			'created_at' => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert( self::table(), $row );

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * @param int|int[] $ids
	 * @param string    $status
	 *
	 * @return int Rows changed.
	 */
	public static function set_status( $ids, $status ) {
		global $wpdb;

		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return 0;
		}

		$ids = array_filter( array_map( 'intval', (array) $ids ) );

		if ( ! $ids ) {
			return 0;
		}

		$in = implode( ',', $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
		return (int) $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . " SET status = %s WHERE id IN ({$in})", $status ) );
	}

	/**
	 * @param int|int[] $ids
	 *
	 * @return int Rows deleted.
	 */
	public static function delete( $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'intval', (array) $ids ) );

		if ( ! $ids ) {
			return 0;
		}

		$in = implode( ',', $ids );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- ids are cast to int above.
		return (int) $wpdb->query( 'DELETE FROM ' . self::table() . " WHERE id IN ({$in})" );
	}

	/**
	 * Every entry that carries this email — the eraser's unit of work.
	 *
	 * @param string $email
	 *
	 * @return int Rows deleted.
	 */
	public static function delete_by_email( $email ) {
		global $wpdb;

		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return 0;
		}

		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE email = %s', $email ) );
	}

	/**
	 * Retention: drop everything older than N days.
	 *
	 * @param int $days
	 *
	 * @return int Rows deleted.
	 */
	public static function delete_older_than( $days ) {
		global $wpdb;

		$days = (int) $days;

		if ( $days < 1 ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		return (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', $cutoff ) );
	}

	/* ---------------------------------------------------------------------- *
	 * Read
	 * ---------------------------------------------------------------------- */

	/**
	 * @param int $id
	 *
	 * @return object|null Row with `fields` decoded.
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', (int) $id ) );

		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Filtered, paged list.
	 *
	 * @param array $args form_id, status, search, email, date_from, date_to, orderby, order, per_page, page
	 *
	 * @return array { rows: object[], total: int }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$args = array_merge( array(
			'form_id'   => '',
			'status'    => '',
			'search'    => '',
			'email'     => '',
			'date_from' => '',
			'date_to'   => '',
			'orderby'   => 'created_at',
			'order'     => 'DESC',
			'per_page'  => 20,
			'page'      => 1,
		), $args );

		list( $where, $params ) = self::where( $args );

		$orderby = in_array( $args['orderby'], array( 'id', 'created_at', 'email', 'form_title', 'status' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per     = max( 1, min( 500, (int) $args['per_page'] ) );
		$offset  = max( 0, ( (int) $args['page'] - 1 ) * $per );

		$table = self::table();
		$sql   = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$rows  = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = $params
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'rows'  => array_map( array( __CLASS__, 'hydrate' ), (array) $rows ),
			'total' => $total,
		);
	}

	/**
	 * Iterate every matching row in chunks — the export path, which must never
	 * load a whole table into memory.
	 *
	 * @param array    $args     Same filters as query().
	 * @param callable $callback Receives one hydrated row.
	 * @param int      $chunk
	 */
	public static function each( array $args, $callback, $chunk = 500 ) {
		$page = 1;

		do {
			$batch = self::query( array_merge( $args, array( 'per_page' => $chunk, 'page' => $page, 'orderby' => 'id', 'order' => 'ASC' ) ) );

			foreach ( $batch['rows'] as $row ) {
				call_user_func( $callback, $row );
			}

			$page++;
		} while ( count( $batch['rows'] ) === $chunk );
	}

	/**
	 * Counts per status, for the list-table views.
	 *
	 * @param string $form_id Optional narrowing.
	 *
	 * @return array status => count, plus 'all'.
	 */
	public static function counts( $form_id = '' ) {
		global $wpdb;

		$table = self::table();
		$rows  = '' !== $form_id
			? $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$table} WHERE form_id = %s GROUP BY status", $form_id ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			: $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array_fill_keys( self::STATUSES, 0 );
		$all = 0;

		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
			$all              += (int) $r->n;
		}

		$out['all'] = $all;

		return $out;
	}

	/**
	 * The distinct forms that have entries, for the filter dropdown. The title
	 * is whatever the most recent entry recorded, so a renamed form shows its
	 * new name without touching old rows.
	 *
	 * @return array form_id => title
	 */
	public static function forms() {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT form_id, form_title, MAX(created_at) AS latest FROM {$table} GROUP BY form_id, form_title ORDER BY latest DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = array();

		foreach ( (array) $rows as $r ) {
			if ( ! isset( $out[ $r->form_id ] ) ) {
				$out[ $r->form_id ] = '' !== $r->form_title ? $r->form_title : $r->form_id;
			}
		}

		return $out;
	}

	/**
	 * Every entry for one email — the privacy exporter's and the CRM's
	 * activity panel's unit of work.
	 *
	 * @param string $email
	 * @param int    $limit
	 *
	 * @return object[]
	 */
	public static function by_email( $email, $limit = 100 ) {
		$email = sanitize_email( $email );

		if ( '' === $email ) {
			return array();
		}

		$r = self::query( array( 'email' => $email, 'per_page' => $limit ) );

		return $r['rows'];
	}

	/* ---------------------------------------------------------------------- *
	 * Internals
	 * ---------------------------------------------------------------------- */

	/**
	 * @param array $args
	 *
	 * @return array [ where-sql, params ]
	 */
	private static function where( array $args ) {
		$w = array();
		$p = array();

		if ( '' !== $args['form_id'] ) {
			$w[] = 'form_id = %s';
			$p[] = $args['form_id'];
		}

		if ( '' !== $args['status'] && in_array( $args['status'], self::STATUSES, true ) ) {
			$w[] = 'status = %s';
			$p[] = $args['status'];
		}

		if ( '' !== $args['email'] ) {
			$w[] = 'email = %s';
			$p[] = sanitize_email( $args['email'] );
		}

		if ( '' !== $args['search'] ) {
			global $wpdb;
			$like = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			// Search the raw JSON too: it is the only place a field value lives,
			// and a LIKE over longtext is fine at the scale a contact form reaches.
			$w[] = '(email LIKE %s OR form_title LIKE %s OR fields LIKE %s)';
			$p[] = $like;
			$p[] = $like;
			$p[] = $like;
		}

		if ( '' !== $args['date_from'] ) {
			$w[] = 'created_at >= %s';
			$p[] = $args['date_from'] . ' 00:00:00';
		}

		if ( '' !== $args['date_to'] ) {
			$w[] = 'created_at <= %s';
			$p[] = $args['date_to'] . ' 23:59:59';
		}

		return array( $w ? 'WHERE ' . implode( ' AND ', $w ) : '', $p );
	}

	/**
	 * @param object $row
	 *
	 * @return object
	 */
	private static function hydrate( $row ) {
		$decoded     = json_decode( (string) $row->fields, true );
		$row->fields = is_array( $decoded ) ? $decoded : array();
		$row->id     = (int) $row->id;
		$row->post_id = (int) $row->post_id;

		return $row;
	}
}
