<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Survey results: Unyson+ → Form Results (`admin.php?page=fw-form-results&form_id=…&period=…`).
 *
 * Built from the stored entries (spam left out), field by field, with what each kind of answer needs:
 *   choices (dropdown, single / multiple choice, picture choices, consent, payment method)
 *            counts and a bar per answer
 *   rating   the average, the spread per score and, for a 0–10 question, the NPS
 *            (promoters 9–10 minus detractors 0–6)
 *   survey grid  a table per statement
 *   number / range / calculated  average, lowest, highest
 *   quiz score   the average percentage and how many reached each result
 *   text / long text  how many answered, and the latest few answers
 * plus the number of entries per week. Bars are plain HTML (a table with the numbers in it), so
 * the page needs no chart library and reads well in a screen reader. Names, emails, phone numbers,
 * addresses and files are never summarised.
 */
class FW_Forms_Reports {

	const PAGE_SLUG = 'fw-form-results';
	const CHOICE    = array( 'radio', 'select', 'checkboxes', 'image-choice', 'consent', 'payment-method' );
	const NUMERIC   = array( 'number', 'range' );
	const TEXT      = array( 'text', 'textarea' );

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, '_menu' ), 20 );
	}

	public static function url( $form_id = '', $period = '' ) {
		return add_query_arg( array_filter( array( 'page' => self::PAGE_SLUG, 'form_id' => $form_id, 'period' => $period ) ), admin_url( 'admin.php' ) );
	}

	/** @internal */
	public static function _menu() {
		if ( ! current_user_can( FW_Forms_Entries_Admin::capability() ) ) {
			return;
		}
		add_submenu_page( FW_Forms_Entries_Admin::PARENT_SLUG, __( 'Form Results', 'fw' ), __( 'Form Results', 'fw' ), FW_Forms_Entries_Admin::capability(), self::PAGE_SLUG, array( __CLASS__, 'render' ) );
	}

	/**
	 * Gather the results of one form.
	 *
	 * @param string $form_id
	 * @param int    $days 0 = all time
	 * @return array { total, fields: [ id => { type, label, ... } ], weeks: [ 'Y-m-d' (Monday) => n ] }
	 */
	public static function collect( $form_id, $days = 0 ) {
		$args = array( 'form_id' => (string) $form_id );
		if ( $days > 0 ) {
			$args['date_from'] = wp_date( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		}
		$fields = array();
		$weeks  = array();
		$total  = 0;
		FW_Forms_Entries::each( $args, function ( $row ) use ( &$fields, &$weeks, &$total ) {
			if ( in_array( $row->status ?? '', array( 'spam', 'partial' ), true ) ) {
				return;
			}
			$total++;
			$ts  = strtotime( (string) $row->created_at );
			$mon = gmdate( 'Y-m-d', $ts - ( ( (int) gmdate( 'N', $ts ) - 1 ) * DAY_IN_SECONDS ) );
			$weeks[ $mon ] = ( $weeks[ $mon ] ?? 0 ) + 1;
			foreach ( (array) $row->fields as $f ) {
				$id   = (string) ( $f['id'] ?? '' );
				$type = (string) ( $f['type'] ?? '' );
				$val  = $f['value'] ?? '';
				if ( '' === $id || '' === $val || array() === $val ) {
					continue;
				}
				if ( ! isset( $fields[ $id ] ) ) {
					$fields[ $id ] = array( 'type' => $type, 'label' => (string) ( $f['label'] ?? $id ), 'answered' => 0, 'counts' => array(), 'nums' => array(), 'latest' => array(), 'grid' => array(), 'scale' => (string) ( $f['scale'] ?? '' ) );
				}
				$F = &$fields[ $id ];
				$F['answered']++;
				if ( in_array( $type, self::CHOICE, true ) ) {
					foreach ( (array) $val as $v ) {
						if ( is_scalar( $v ) && '' !== (string) $v ) {
							$F['counts'][ (string) $v ] = ( $F['counts'][ (string) $v ] ?? 0 ) + 1;
						}
					}
				} elseif ( 'rating' === $type ) {
					$F['nums'][] = (float) $val;
					$F['counts'][ (string) (int) $val ] = ( $F['counts'][ (string) (int) $val ] ?? 0 ) + 1;
				} elseif ( 'likert' === $type ) {
					foreach ( explode( '; ', (string) $val ) as $pair ) {
						$p = explode( ': ', $pair, 2 );
						if ( 2 === count( $p ) ) {
							$F['grid'][ $p[0] ][ $p[1] ] = ( $F['grid'][ $p[0] ][ $p[1] ] ?? 0 ) + 1;
						}
					}
				} elseif ( in_array( $type, self::NUMERIC, true ) || 'calculation' === $type ) {
					$F['nums'][] = class_exists( 'FW_Forms_Order' ) ? FW_Forms_Order::number_in( $val ) : (float) $val;
				} elseif ( 'quiz' === $type ) {
					if ( preg_match( '/\((\d+)%\)/', (string) $val, $m ) ) {
						$F['nums'][] = (float) $m[1];
					}
					$parts = explode( ' · ', (string) $val, 2 );
					$F['counts'][ $parts[1] ?? __( '(no result)', 'fw' ) ] = ( $F['counts'][ $parts[1] ?? __( '(no result)', 'fw' ) ] ?? 0 ) + 1;
				} elseif ( in_array( $type, self::TEXT, true ) ) {
					array_unshift( $F['latest'], (string) $val );
					$F['latest'] = array_slice( $F['latest'], 0, 5 );
				}
				unset( $F );
			}
		} );
		ksort( $weeks );
		return array( 'total' => $total, 'fields' => $fields, 'weeks' => $weeks );
	}

	/** NPS from 0–10 scores: { nps, promoters, passives, detractors } (shares in %). */
	public static function nps( array $scores ) {
		$n = count( $scores );
		if ( ! $n ) {
			return null;
		}
		$pro = count( array_filter( $scores, function ( $s ) {
			return $s >= 9;
		} ) );
		$det = count( array_filter( $scores, function ( $s ) {
			return $s <= 6;
		} ) );
		return array(
			'nps'        => (int) round( 100 * ( $pro - $det ) / $n ),
			'promoters'  => (int) round( 100 * $pro / $n ),
			'passives'   => (int) round( 100 * ( $n - $pro - $det ) / $n ),
			'detractors' => (int) round( 100 * $det / $n ),
		);
	}

	/**
	 * Forms with entries, labelled so two forms with the same title ("Contact Form") can be told
	 * apart: the page each was last sent from is added.
	 *
	 * @return array form_id => label
	 */
	public static function form_choices() {
		global $wpdb;
		$forms  = FW_Forms_Entries::forms();
		$counts = array_count_values( array_values( $forms ) );
		$table  = FW_Forms_Entries::table();
		foreach ( $forms as $id => $title ) {
			if ( $counts[ $title ] < 2 ) {
				continue;
			}
			$post  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE form_id = %s ORDER BY id DESC LIMIT 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$where = $post ? get_the_title( $post ) : '';
			$forms[ $id ] = $title . ' · ' . ( '' !== $where ? $where : substr( (string) $id, 0, 8 ) );
		}
		return $forms;
	}

	/** Bars as a table: [ label => count ]. */
	private static function bars( array $counts, $of, $caption ) {
		if ( ! $counts ) {
			return;
		}
		echo '<table class="fw-results__bars"><caption class="screen-reader-text">' . esc_html( $caption ) . '</caption><tbody>';
		foreach ( $counts as $label => $n ) {
			$pct = $of > 0 ? round( 100 * $n / $of ) : 0;
			echo '<tr><th scope="row">' . esc_html( (string) $label ) . '</th>'
				. '<td class="fw-results__bar"><span style="width:' . esc_attr( (string) max( 1, $pct ) ) . '%"></span></td>'
				. '<td class="fw-results__num">' . esc_html( number_format_i18n( $n ) ) . ' <span>(' . esc_html( $pct ) . '%)</span></td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function render() {
		if ( ! current_user_can( FW_Forms_Entries_Admin::capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to see this page.', 'fw' ) );
		}
		$forms   = self::form_choices();
		$form_id = isset( $_GET['form_id'] ) ? sanitize_text_field( wp_unslash( $_GET['form_id'] ) ) : (string) key( $forms ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$period  = isset( $_GET['period'] ) ? (int) $_GET['period'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$periods = array( 0 => __( 'All time', 'fw' ), 30 => __( 'Last 30 days', 'fw' ), 90 => __( 'Last 90 days', 'fw' ), 365 => __( 'Last 12 months', 'fw' ) );
		?>
		<div class="wrap fw-results">
			<style>
				.fw-results__head { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; margin: 1rem 0 1.5rem; }
				.fw-results__cards { display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); }
				.fw-results__card { background: #fff; border: 1px solid #dcdcde; border-radius: 8px; padding: 16px 18px; }
				.fw-results__card h2 { margin: 0 0 .25rem; font-size: 15px; }
				.fw-results__meta { margin: 0 0 .8rem; color: #50575e; }
				.fw-results__big { font-size: 28px; font-weight: 700; margin: .2rem 0 .6rem; }
				.fw-results__bars { width: 100%; border-collapse: collapse; }
				.fw-results__bars th { text-align: left; font-weight: 400; padding: 4px 8px 4px 0; width: 38%; vertical-align: middle; }
				.fw-results__bar { width: 46%; padding: 4px 0; }
				.fw-results__bar span { display: block; height: 14px; border-radius: 3px; background: #2271b1; }
				.fw-results__num { text-align: right; white-space: nowrap; padding-left: 8px; font-variant-numeric: tabular-nums; }
				.fw-results__num span { color: #646970; }
				.fw-results__grid { width: 100%; border-collapse: collapse; font-size: 12px; }
				.fw-results__grid th, .fw-results__grid td { border-bottom: 1px solid #f0f0f1; padding: 4px 6px; text-align: center; }
				.fw-results__grid th[scope="row"] { text-align: left; }
				.fw-results__latest { margin: .4rem 0 0; padding-left: 1.1rem; }
				.fw-results__latest li { margin-bottom: .3rem; }
				.fw-results__weeks { display: flex; align-items: flex-end; gap: 4px; height: 120px; margin-top: .5rem; }
				.fw-results__weeks div { flex: 1 1 0; background: #72aee6; min-height: 2px; border-radius: 2px 2px 0 0; }
			</style>
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Form Results', 'fw' ); ?></h1>
			<a class="page-title-action" href="<?php echo esc_url( add_query_arg( 'form_id', rawurlencode( $form_id ), FW_Forms_Entries_Admin::page_url() ) ); ?>"><?php esc_html_e( 'See the entries', 'fw' ); ?></a>
			<hr class="wp-header-end">
			<?php if ( ! $forms ) : ?>
				<p><?php esc_html_e( 'No entries yet. Results appear once a form has stored entries.', 'fw' ); ?></p></div>
				<?php
				return;
			endif;
			?>
			<form method="get" class="fw-results__head">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
				<label class="screen-reader-text" for="fw-results-form"><?php esc_html_e( 'Form', 'fw' ); ?></label>
				<select id="fw-results-form" name="form_id">
					<?php foreach ( $forms as $id => $title ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>"<?php selected( $id, $form_id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
				<label class="screen-reader-text" for="fw-results-period"><?php esc_html_e( 'Period', 'fw' ); ?></label>
				<select id="fw-results-period" name="period">
					<?php foreach ( $periods as $d => $label ) : ?>
						<option value="<?php echo (int) $d; ?>"<?php selected( $d, $period ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button"><?php esc_html_e( 'Show', 'fw' ); ?></button>
			</form>
			<?php
			$r = self::collect( $form_id, $period );
			?>
			<div class="fw-results__cards">
				<?php if ( class_exists( 'FW_Forms_Analytics' ) && FW_Forms_Analytics::enabled() ) { FW_Forms_Analytics::render_card( $form_id, $period ); } ?>
				<section class="fw-results__card">
					<h2><?php esc_html_e( 'Entries', 'fw' ); ?></h2>
					<p class="fw-results__big"><?php echo esc_html( number_format_i18n( $r['total'] ) ); ?></p>
					<?php if ( count( $r['weeks'] ) > 1 ) : ?>
						<p class="fw-results__meta"><?php esc_html_e( 'Per week', 'fw' ); ?></p>
						<?php $peak = max( $r['weeks'] ); ?>
						<div class="fw-results__weeks" role="img" aria-label="<?php echo esc_attr( implode( ', ', array_map( function ( $w, $n ) {
							return sprintf( __( 'week of %1$s: %2$d', 'fw' ), $w, $n );
						}, array_keys( $r['weeks'] ), $r['weeks'] ) ) ); ?>">
							<?php foreach ( array_slice( $r['weeks'], -16, null, true ) as $w => $n ) : ?>
								<div style="height:<?php echo esc_attr( (string) max( 2, round( 100 * $n / $peak ) ) ); ?>%" title="<?php echo esc_attr( $w . ': ' . $n ); ?>"></div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</section>
				<?php
				foreach ( $r['fields'] as $id => $f ) :
					$type = $f['type'];
					$show = in_array( $type, self::CHOICE, true ) || in_array( $type, self::NUMERIC, true ) || in_array( $type, self::TEXT, true ) || in_array( $type, array( 'rating', 'likert', 'calculation', 'quiz' ), true );
					if ( ! $show ) {
						continue;
					}
					?>
					<section class="fw-results__card">
						<h2><?php echo esc_html( $f['label'] ); ?></h2>
						<p class="fw-results__meta"><?php echo esc_html( sprintf( _n( '%d answer', '%d answers', $f['answered'], 'fw' ), $f['answered'] ) ); ?></p>
						<?php
						if ( in_array( $type, self::CHOICE, true ) ) {
							arsort( $f['counts'] );
							self::bars( $f['counts'], $f['answered'], $f['label'] );
						} elseif ( 'rating' === $type ) {
							$avg   = $f['nums'] ? array_sum( $f['nums'] ) / count( $f['nums'] ) : 0;
							$scale = $f['scale'] ? $f['scale'] : ( in_array( 0.0, $f['nums'], true ) ? 'nps' : ( max( $f['nums'] ) > 5 ? 'scale10' : 'stars5' ) );
							echo '<p class="fw-results__big">' . esc_html( number_format_i18n( $avg, 1 ) ) . ' <small>' . esc_html( 'stars5' === $scale ? __( 'of 5', 'fw' ) : __( 'of 10', 'fw' ) ) . '</small></p>';
							if ( 'nps' === $scale && ( $n = self::nps( $f['nums'] ) ) ) {
								echo '<p><strong>' . esc_html( sprintf( __( 'NPS %+d', 'fw' ), $n['nps'] ) ) . '</strong> · ' . esc_html( sprintf( __( '%1$d%% promoters, %2$d%% passives, %3$d%% detractors', 'fw' ), $n['promoters'], $n['passives'], $n['detractors'] ) ) . '</p>';
							}
							krsort( $f['counts'], SORT_NUMERIC );
							self::bars( $f['counts'], $f['answered'], $f['label'] );
						} elseif ( 'likert' === $type ) {
							$cols = array();
							foreach ( $f['grid'] as $answers ) {
								$cols = array_unique( array_merge( $cols, array_keys( $answers ) ) );
							}
							echo '<table class="fw-results__grid"><thead><tr><td></td>';
							foreach ( $cols as $c ) {
								echo '<th scope="col">' . esc_html( $c ) . '</th>';
							}
							echo '</tr></thead><tbody>';
							foreach ( $f['grid'] as $row => $answers ) {
								echo '<tr><th scope="row">' . esc_html( $row ) . '</th>';
								foreach ( $cols as $c ) {
									echo '<td>' . esc_html( (string) ( $answers[ $c ] ?? 0 ) ) . '</td>';
								}
								echo '</tr>';
							}
							echo '</tbody></table>';
						} elseif ( in_array( $type, self::NUMERIC, true ) || 'calculation' === $type ) {
							$nums = $f['nums'];
							echo '<p class="fw-results__big">' . esc_html( number_format_i18n( array_sum( $nums ) / max( 1, count( $nums ) ), 1 ) ) . ' <small>' . esc_html__( 'on average', 'fw' ) . '</small></p>';
							echo '<p>' . esc_html( sprintf( __( 'Lowest %1$s · highest %2$s', 'fw' ), number_format_i18n( min( $nums ), 1 ), number_format_i18n( max( $nums ), 1 ) ) ) . '</p>';
						} elseif ( 'quiz' === $type ) {
							echo '<p class="fw-results__big">' . esc_html( $f['nums'] ? round( array_sum( $f['nums'] ) / count( $f['nums'] ) ) : 0 ) . '% <small>' . esc_html__( 'average score', 'fw' ) . '</small></p>';
							arsort( $f['counts'] );
							self::bars( $f['counts'], $f['answered'], $f['label'] );
						} elseif ( $f['latest'] ) {
							echo '<p class="fw-results__meta">' . esc_html__( 'Latest answers', 'fw' ) . '</p><ul class="fw-results__latest">';
							foreach ( $f['latest'] as $t ) {
								echo '<li>' . esc_html( wp_html_excerpt( $t, 160, '…' ) ) . '</li>';
							}
							echo '</ul>';
						}
						?>
					</section>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}
