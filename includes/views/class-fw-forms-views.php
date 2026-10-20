<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Entry View — a form's entries shown on the site (the `entry_view` element): a table, a grid of cards,
 * a list, a map or a month calendar, with search, a filter and a page per entry.
 *
 * Privacy first:
 *   - only forms that allow it (Settings → On the site → "Entries can be shown on the site");
 *   - only approved entries when the form approves first; never spam, unfinished or archived ones;
 *   - only the fields chosen on the element, and search / filter look only at those fields, so a
 *     search can never reveal what a hidden field holds; uploaded files are never shown;
 *   - "Only the visitor's own entries" shows a logged-in person what they sent (with Edit links,
 *     when the form lets senders edit).
 *
 * Request parameters are prefixed per element (`fwv<n>_q`, `_f`, `_p`, `_e`, `_m`), so two views on one
 * page do not disturb each other.
 */
class FW_Forms_Views {

	/** @var int */
	private static $n = 0;

	/** Forms whose entries may be shown (for the element's dropdown). */
	public static function form_choices() {
		$out = array( '' => __( '— Choose a form —', 'fw' ) );
		if ( ! class_exists( 'FW_Forms_Entries' ) ) {
			return $out;
		}
		foreach ( FW_Forms_Entries::forms() as $id => $title ) {
			$out[ $id ] = $title . ( 'yes' === ( FW_Forms_Approval::form( $id )['views_allowed'] ?? 'no' ) ? '' : ' ' . __( '(not allowed yet)', 'fw' ) );
		}
		return $out;
	}

	private static function names( $csv ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ), 'strlen' ) );
	}

	private static function value( $entry, $field ) {
		foreach ( (array) $entry->fields as $f ) {
			if ( (string) ( $f['id'] ?? '' ) === $field ) {
				if ( ! empty( $f['file'] ) ) {
					return ''; // uploaded files stay private
				}
				return is_array( $f['value'] ) ? implode( ', ', array_map( 'strval', $f['value'] ) ) : (string) $f['value'];
			}
		}
		return '';
	}

	private static function label( $entry, $field ) {
		foreach ( (array) $entry->fields as $f ) {
			if ( (string) ( $f['id'] ?? '' ) === $field ) {
				return (string) $f['label'];
			}
		}
		return $field;
	}

	/**
	 * The entries a view may show, newest first (at most 1000).
	 */
	public static function entries( $form_id, array $form, array $atts ) {
		$args = array( 'form_id' => (string) $form_id, 'per_page' => 1000, 'page' => 1, 'order' => 'oldest' === ( $atts['order'] ?? '' ) ? 'ASC' : 'DESC' );
		$mine = 'yes' === ( $atts['only_mine'] ?? 'no' );
		if ( $mine ) {
			// A sender sees their own entries whatever their approval (each marked), except rejected ones.
			$args['user_id'] = max( 1, get_current_user_id() );
		} elseif ( FW_Forms_Approval::needs_approval( $form ) ) {
			$args['approval'] = 'approved';
		}
		return array_values( array_filter( FW_Forms_Entries::query( $args )['rows'], function ( $e ) use ( $mine ) {
			return ! in_array( $e->status, array( 'spam', 'partial', 'archived' ), true ) && ! ( $mine && 'rejected' === $e->approval );
		} ) );
	}

	public static function render( array $atts ) {
		$n       = ++self::$n;
		$px      = 'fwv' . $n . '_';
		$form_id = (string) ( $atts['form_id'] ?? '' );
		$form    = '' !== $form_id ? FW_Forms_Approval::form( $form_id ) : array();
		if ( ! $form || 'yes' !== ( $form['views_allowed'] ?? 'no' ) ) {
			return current_user_can( 'edit_posts' ) ? '<p class="fw-entry-view__note"><em>' . esc_html__( 'Entry View: choose a form whose entries may be shown (the form\'s Settings → On the site).', 'fw' ) . '</em></p>' : '';
		}
		if ( 'yes' === ( $atts['only_mine'] ?? 'no' ) && ! is_user_logged_in() ) {
			return '<p class="fw-entry-view__note">' . sprintf( esc_html__( '%s to see what you have sent.', 'fw' ), '<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in', 'fw' ) . '</a>' ) . '</p>';
		}
		$fields  = self::names( $atts['fields'] ?? '' );
		$title_f = trim( (string) ( $atts['title_field'] ?? '' ) );
		$layout  = in_array( $atts['layout'] ?? 'table', array( 'table', 'grid', 'list', 'map', 'calendar' ), true ) ? $atts['layout'] : 'table';
		$all     = self::entries( $form_id, $form, $atts );
		$get     = function ( $k ) use ( $px ) {
			return isset( $_GET[ $px . $k ] ) ? sanitize_text_field( wp_unslash( $_GET[ $px . $k ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		};
		$url     = function ( array $set ) use ( $px ) {
			$args = array();
			foreach ( $set as $k => $v ) {
				$args[ $px . $k ] = $v;
			}
			return add_query_arg( $args, remove_query_arg( array( $px . 'e' ) ) );
		};
		$show    = array_values( array_unique( array_filter( array_merge( '' !== $title_f ? array( $title_f ) : array(), $fields ) ) ) );

		// One entry.
		$one = (int) $get( 'e' );
		if ( $one && 'yes' === ( $atts['detail'] ?? 'yes' ) ) {
			foreach ( $all as $e ) {
				if ( (int) $e->id === $one ) {
					return self::detail( $e, $title_f, $fields, $form, remove_query_arg( $px . 'e' ) );
				}
			}
		}

		// Search and filter — on the shown fields only.
		$q      = $get( 'q' );
		$filter = trim( (string) ( $atts['filter_field'] ?? '' ) );
		$fv     = $get( 'f' );
		$opts   = array();
		foreach ( $all as $e ) {
			if ( '' !== $filter ) {
				foreach ( array_map( 'trim', explode( ',', self::value( $e, $filter ) ) ) as $v ) {
					if ( '' !== $v ) {
						$opts[ $v ] = true;
					}
				}
			}
		}
		ksort( $opts );
		$list = array_values( array_filter( $all, function ( $e ) use ( $q, $show, $filter, $fv ) {
			if ( '' !== $fv && ! in_array( $fv, array_map( 'trim', explode( ',', self::value( $e, $filter ) ) ), true ) ) {
				return false;
			}
			if ( '' === $q ) {
				return true;
			}
			foreach ( $show as $f ) {
				if ( false !== stripos( self::value( $e, $f ), $q ) ) {
					return true;
				}
			}
			return false;
		} ) );

		ob_start();
		echo '<div class="fw-entry-view fw-entry-view--' . esc_attr( $layout ) . '">';
		if ( 'yes' === ( $atts['search'] ?? 'yes' ) || '' !== $filter ) {
			echo '<form class="fw-entry-view__tools" method="get" role="search">';
			foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( is_string( $v ) && 0 !== strpos( (string) $k, $px ) ) {
					echo '<input type="hidden" name="' . esc_attr( (string) $k ) . '" value="' . esc_attr( wp_unslash( $v ) ) . '">';
				}
			}
			if ( 'yes' === ( $atts['search'] ?? 'yes' ) ) {
				echo '<label><span class="screen-reader-text">' . esc_html__( 'Search', 'fw' ) . '</span><input type="search" name="' . esc_attr( $px ) . 'q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Search…', 'fw' ) . '"></label>';
			}
			if ( '' !== $filter && $opts ) {
				$lab = $all ? self::label( $all[0], $filter ) : $filter;
				echo '<label><span class="screen-reader-text">' . esc_html( $lab ) . '</span><select name="' . esc_attr( $px ) . 'f"><option value="">' . esc_html( sprintf( __( 'Any %s', 'fw' ), mb_strtolower( $lab ) ) ) . '</option>';
				foreach ( array_keys( $opts ) as $o ) {
					echo '<option value="' . esc_attr( (string) $o ) . '"' . selected( (string) $o, $fv, false ) . '>' . esc_html( (string) $o ) . '</option>';
				}
				echo '</select></label>';
			}
			echo '<button type="submit">' . esc_html__( 'Show', 'fw' ) . '</button></form>';
		}

		if ( ! $list ) {
			echo '<p class="fw-entry-view__empty">' . esc_html( '' !== trim( (string) ( $atts['empty_text'] ?? '' ) ) ? $atts['empty_text'] : __( 'Nothing to show yet.', 'fw' ) ) . '</p></div>';
			return ob_get_clean();
		}

		if ( 'map' === $layout ) {
			self::map( $list, $atts, $title_f, $fields, $url );
		} elseif ( 'calendar' === $layout ) {
			self::calendar( $list, $atts, $title_f, $get( 'm' ), $url );
		} else {
			$per   = max( 1, min( 100, (int) ( $atts['per_page'] ?? 12 ) ) );
			$pages = (int) ceil( count( $list ) / $per );
			$page  = max( 1, min( $pages, (int) $get( 'p' ) ) );
			$slice = array_slice( $list, ( $page - 1 ) * $per, $per );
			$link  = function ( $e ) use ( $url, $atts ) {
				return 'yes' === ( $atts['detail'] ?? 'yes' ) ? $url( array( 'e' => (int) $e->id ) ) : '';
			};
			if ( 'table' === $layout ) {
				echo '<div class="fw-entry-view__scroll"><table class="fw-entry-view__table"><thead><tr>';
				foreach ( $show as $f ) {
					echo '<th scope="col">' . esc_html( self::label( $slice[0], $f ) ) . '</th>';
				}
				echo '</tr></thead><tbody>';
				foreach ( $slice as $e ) {
					echo '<tr>';
					foreach ( $show as $i => $f ) {
						$v = esc_html( self::value( $e, $f ) );
						$h = 0 === $i && $link( $e ) ? '<a href="' . esc_url( $link( $e ) ) . '">' . ( '' !== $v ? $v : esc_html__( 'Open', 'fw' ) ) . '</a>' : $v;
						echo 0 === $i ? '<th scope="row">' . $h . '</th>' : '<td>' . $h . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
					}
					echo '</tr>';
				}
				echo '</tbody></table></div>';
			} else {
				echo '<ul class="fw-entry-view__cards">';
				foreach ( $slice as $e ) {
					$t = '' !== $title_f ? self::value( $e, $title_f ) : '';
					echo '<li class="fw-entry-view__card">';
					if ( '' !== $t ) {
						echo '<h3 class="fw-entry-view__title">' . ( $link( $e ) ? '<a href="' . esc_url( $link( $e ) ) . '">' . esc_html( $t ) . '</a>' : esc_html( $t ) ) . '</h3>';
					}
					echo '<dl>';
					foreach ( $fields as $f ) {
						if ( $f === $title_f || '' === ( $v = self::value( $e, $f ) ) ) {
							continue;
						}
						echo '<dt>' . esc_html( self::label( $e, $f ) ) . '</dt><dd>' . nl2br( esc_html( $v ) ) . '</dd>';
					}
					echo '</dl>';
					if ( '' === $t && $link( $e ) ) {
						echo '<p><a href="' . esc_url( $link( $e ) ) . '">' . esc_html__( 'Open', 'fw' ) . '</a></p>';
					}
					echo self::edit_link( $e, $form ); // phpcs:ignore WordPress.Security.EscapeOutput
					echo '</li>';
				}
				echo '</ul>';
			}
			if ( $pages > 1 ) {
				echo '<nav class="fw-entry-view__pages" aria-label="' . esc_attr__( 'Pages', 'fw' ) . '">';
				for ( $i = 1; $i <= $pages; $i++ ) {
					echo $i === $page ? '<span aria-current="page">' . (int) $i . '</span>' : '<a href="' . esc_url( $url( array( 'p' => $i, 'q' => $q, 'f' => $fv ) ) ) . '">' . (int) $i . '</a>';
				}
				echo '</nav>';
			}
		}
		echo '</div>';
		return ob_get_clean();
	}

	/** The Edit link for the sender, when the form allows it (filled in by the edit module). */
	public static function edit_link( $entry, array $form ) {
		/** Filters the "Edit" link HTML shown with an entry in a view ('' = none). */
		return (string) apply_filters( 'fw_ext_forms_view_edit_link', '', $entry, $form );
	}

	private static function detail( $e, $title_f, array $fields, array $form, $back ) {
		$t = '' !== $title_f ? self::value( $e, $title_f ) : '';
		ob_start();
		echo '<article class="fw-entry-view fw-entry-view--detail">';
		echo '<p><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'Back', 'fw' ) . '</a></p>';
		if ( '' !== $t ) {
			echo '<h2 class="fw-entry-view__title">' . esc_html( $t ) . '</h2>';
		}
		echo '<dl>';
		foreach ( $fields as $f ) {
			if ( $f === $title_f || '' === ( $v = self::value( $e, $f ) ) ) {
				continue;
			}
			echo '<dt>' . esc_html( self::label( $e, $f ) ) . '</dt><dd>' . nl2br( esc_html( $v ) ) . '</dd>';
		}
		echo '</dl>';
		echo self::edit_link( $e, $form ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</article>';
		return ob_get_clean();
	}

	private static function map( array $list, array $atts, $title_f, array $fields, $url ) {
		$loc = trim( (string) ( $atts['map_field'] ?? '' ) );
		$pts = array();
		foreach ( $list as $e ) {
			if ( '' !== $loc && preg_match( '/(-?\d{1,2}(?:\.\d+)?)\s*[,; ]\s*(-?\d{1,3}(?:\.\d+)?)/', self::value( $e, $loc ), $m ) && abs( $m[1] ) <= 90 && abs( $m[2] ) <= 180 ) {
				$pts[] = array( (float) $m[1], (float) $m[2], '' !== $title_f ? self::value( $e, $title_f ) : '', 'yes' === ( $atts['detail'] ?? 'yes' ) ? $url( array( 'e' => (int) $e->id ) ) : '' );
			}
		}
		if ( ! $pts ) {
			echo '<p class="fw-entry-view__empty">' . esc_html__( 'No entry has a location to show on the map yet ("latitude, longitude").', 'fw' ) . '</p>';
			return;
		}
		$v = class_exists( 'FW_Shortcode_Map' ) ? FW_Shortcode_Map::LEAFLET_VERSION : '1.9.4';
		wp_enqueue_style( 'leaflet', 'https://unpkg.com/leaflet@' . $v . '/dist/leaflet.css', array(), $v );
		wp_enqueue_script( 'leaflet', 'https://unpkg.com/leaflet@' . $v . '/dist/leaflet.js', array(), $v, true );
		$id = 'fw-entry-map-' . self::$n;
		echo '<div class="fw-entry-view__map" id="' . esc_attr( $id ) . '" style="height:' . esc_attr( (string) max( 240, min( 900, (int) ( $atts['map_height'] ?? 420 ) ) ) ) . 'px" role="region" aria-label="' . esc_attr__( 'Map of the entries', 'fw' ) . '"></div>';
		// A text list too, for anyone who cannot use the map.
		echo '<ul class="fw-entry-view__maplist">';
		foreach ( $pts as $p ) {
			echo '<li>' . ( $p[3] ? '<a href="' . esc_url( $p[3] ) . '">' . esc_html( $p[2] ? $p[2] : __( 'Open', 'fw' ) ) . '</a>' : esc_html( $p[2] ) ) . '</li>';
		}
		echo '</ul>';
		wp_add_inline_script( 'leaflet', '(function(){var pts=' . wp_json_encode( $pts ) . ',el=document.getElementById(' . wp_json_encode( $id ) . ');if(!el||!window.L)return;var m=L.map(el,{scrollWheelZoom:false});L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",{maxZoom:19,attribution:"&copy; OpenStreetMap"}).addTo(m);var b=[];pts.forEach(function(p){var mk=L.marker([p[0],p[1]]).addTo(m);var t=document.createElement(p[3]?"a":"span");t.textContent=p[2]||"";if(p[3])t.href=p[3];mk.bindPopup(t);b.push([p[0],p[1]]);});if(b.length>1){m.fitBounds(b,{padding:[30,30]});}else{m.setView(b[0],13);}})();' );
	}

	private static function calendar( array $list, array $atts, $title_f, $month, $url ) {
		$df = trim( (string) ( $atts['calendar_field'] ?? '' ) );
		$by = array();
		foreach ( $list as $e ) {
			$raw = '' !== $df ? self::value( $e, $df ) : (string) $e->created_at;
			$ts  = strtotime( $raw );
			if ( $ts ) {
				$by[ gmdate( 'Y-m-d', $ts ) ][] = $e;
			}
		}
		$month = preg_match( '/^\d{4}-\d{2}$/', $month ) ? $month : wp_date( 'Y-m' );
		$first = strtotime( $month . '-01 12:00:00' );
		$days  = (int) gmdate( 't', $first );
		$lead  = ( (int) gmdate( 'N', $first ) ) - 1;
		$prev  = gmdate( 'Y-m', strtotime( '-1 month', $first ) );
		$next  = gmdate( 'Y-m', strtotime( '+1 month', $first ) );
		echo '<div class="fw-entry-view__cal">';
		echo '<div class="fw-entry-view__calnav"><a href="' . esc_url( $url( array( 'm' => $prev ) ) ) . '">‹ ' . esc_html( date_i18n( 'F', strtotime( $prev . '-01' ) ) ) . '</a><h3>' . esc_html( date_i18n( 'F Y', $first ) ) . '</h3><a href="' . esc_url( $url( array( 'm' => $next ) ) ) . '">' . esc_html( date_i18n( 'F', strtotime( $next . '-01' ) ) ) . ' ›</a></div>';
		echo '<table class="fw-entry-view__month"><thead><tr>';
		foreach ( array( __( 'Mon', 'fw' ), __( 'Tue', 'fw' ), __( 'Wed', 'fw' ), __( 'Thu', 'fw' ), __( 'Fri', 'fw' ), __( 'Sat', 'fw' ), __( 'Sun', 'fw' ) ) as $d ) {
			echo '<th scope="col">' . esc_html( $d ) . '</th>';
		}
		echo '</tr></thead><tbody><tr>';
		for ( $i = 0; $i < $lead; $i++ ) {
			echo '<td></td>';
		}
		for ( $d = 1; $d <= $days; $d++ ) {
			$key = $month . '-' . str_pad( (string) $d, 2, '0', STR_PAD_LEFT );
			echo '<td><span class="fw-entry-view__day">' . (int) $d . '</span>';
			foreach ( $by[ $key ] ?? array() as $e ) {
				$t = '' !== $title_f ? self::value( $e, $title_f ) : __( 'Entry', 'fw' );
				echo 'yes' === ( $atts['detail'] ?? 'yes' ) ? '<a href="' . esc_url( $url( array( 'e' => (int) $e->id, 'm' => $month ) ) ) . '">' . esc_html( $t ) . '</a>' : '<span>' . esc_html( $t ) . '</span>';
			}
			echo '</td>';
			if ( 0 === ( $lead + $d ) % 7 && $d < $days ) {
				echo '</tr><tr>';
			}
		}
		$rest = ( 7 - ( $lead + $days ) % 7 ) % 7;
		for ( $i = 0; $i < $rest; $i++ ) {
			echo '<td></td>';
		}
		echo '</tr></tbody></table></div>';
	}
}
