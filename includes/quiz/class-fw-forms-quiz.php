<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Quiz scoring (contact form Settings → Quiz). Knowledge quizzes and "which one suits you" finders.
 *
 *   quiz_enabled   on / off
 *   quiz_points    one line per scored answer:  Paris = 1   or, for one field only,
 *                  radio_capital: Paris = 1   (answers are compared without case; a typed answer
 *                  scores too, so "type the answer" questions work)
 *   quiz_outcomes  score ranges { from, to, title, message, redirect, crm_tag }
 *
 * The score is worked out on the server from the posted answers. Answer tags {quiz_score},
 * {quiz_max}, {quiz_percent}, {quiz_result} (the range's title) and {quiz_message}; a range with a
 * page sends the visitor there; a range with a CRM tag tags the sender's CRM contact (when the CRM is
 * active and the contact exists, e.g. with the "Add the sender to the CRM" action on); the entry
 * gets a "Quiz score" row.
 */
class FW_Forms_Quiz {

	const KEYS = array( 'quiz_enabled', 'quiz_points', 'quiz_outcomes' );

	/** @var array|null The result for this request. */
	private static $result = null;

	public static function init() {
		add_filter( 'fw_ext_forms_merge_tags', array( __CLASS__, '_tags' ), 30, 2 );
		add_filter( 'fw_ext_forms_confirmation_url', array( __CLASS__, '_redirect' ), 10, 2 );
		add_filter( 'fw_ext_forms_entry_before_insert', array( __CLASS__, '_entry_row' ), 10, 2 );
		add_action( 'fw_ext_forms_frontend_submit', array( __CLASS__, '_crm_tag' ), 30 );
	}

	/** The contact form's Settings → Quiz tab. */
	public static function options() {
		return array(
			'type'    => 'group',
			'options' => array(
				'quiz_enabled'  => array(
					'type'         => 'switch',
					'label'        => __( 'Score this form as a quiz', 'fw' ),
					'value'        => 'no',
					'left-choice'  => array( 'value' => 'no', 'label' => __( 'Off', 'fw' ) ),
					'right-choice' => array( 'value' => 'yes', 'label' => __( 'On', 'fw' ) ),
				),
				'quiz_points'   => array(
					'type'  => 'textarea',
					'label' => __( 'Points', 'fw' ),
					'desc'  => __( 'One scored answer per line: Paris = 1. To score an answer in one question only, start with the field name: radio_capital: Paris = 1. Answers that are not listed score 0; case does not matter.', 'fw' ),
					'value' => '',
				),
				'quiz_outcomes' => array(
					'type'            => 'addable-box',
					'label'           => __( 'Results by score', 'fw' ),
					'desc'            => __( 'The first range the score falls in is the result. Show it with {quiz_result} and {quiz_message} in the success message; {quiz_score}, {quiz_max} and {quiz_percent} give the score.', 'fw' ),
					'box-options'     => array(
						'from'     => array( 'type' => 'text', 'label' => __( 'From score', 'fw' ), 'desc' => __( 'Empty = no lower limit.', 'fw' ), 'value' => '' ),
						'to'       => array( 'type' => 'text', 'label' => __( 'To score', 'fw' ), 'desc' => __( 'Empty = no upper limit.', 'fw' ), 'value' => '' ),
						'title'    => array( 'type' => 'text', 'label' => __( 'Result', 'fw' ), 'value' => '' ),
						'message'  => array( 'type' => 'textarea', 'label' => __( 'Message', 'fw' ), 'value' => '' ),
						'redirect' => array( 'type' => 'text', 'label' => __( 'Go to page (optional)', 'fw' ), 'desc' => __( 'An address or /path on this site.', 'fw' ), 'value' => '' ),
						'crm_tag'  => array( 'type' => 'text', 'label' => __( 'CRM tag (optional)', 'fw' ), 'value' => '' ),
					),
					'template'        => '{{- title }} ({{- from }}–{{- to }})',
					'add-button-text' => __( 'Add a result', 'fw' ),
					'value'           => array(),
				),
			),
		);
	}

	private static function norm( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $s ) ) : strtolower( trim( (string) $s ) );
	}

	/** @return array { global: answer => points, fields: field => { answer => points } } */
	public static function rules( array $form ) {
		$out = array( 'global' => array(), 'fields' => array() );
		foreach ( FW_Forms_Action::lines( $form['quiz_points'] ?? '' ) as $line ) {
			if ( ! preg_match( '/^(.+?)\s*=\s*(-?\d+(?:\.\d+)?)$/', $line, $m ) ) {
				continue;
			}
			$answer = $m[1];
			$points = (float) $m[2];
			if ( preg_match( '/^([a-z0-9_\-]+):\s*(.+)$/i', $answer, $f ) ) {
				$out['fields'][ $f[1] ][ self::norm( $f[2] ) ] = $points;
			} else {
				$out['global'][ self::norm( $answer ) ] = $points;
			}
		}
		return $out;
	}

	/** The answer texts a field can give (for the best possible score). */
	private static function choices( array $item ) {
		$o = $item['options'] ?? array();
		$c = array();
		foreach ( (array) ( $o['choices'] ?? array() ) as $ch ) {
			$c[] = is_array( $ch ) ? (string) ( $ch['caption'] ?? $ch['name'] ?? '' ) : (string) $ch;
		}
		return array_filter( $c, 'strlen' );
	}

	/**
	 * Score a submission.
	 *
	 * @param array $form  Settings.
	 * @param array $items shortcode => item
	 * @param array $input Posted values.
	 * @return array { score, max, percent, outcome|null }
	 */
	public static function score( array $form, array $items, array $input ) {
		$rules = self::rules( $form );
		$score = 0.0;
		$max   = 0.0;
		foreach ( $items as $sc => $item ) {
			if ( class_exists( 'FW_Forms_Conditions' ) && ! FW_Forms_Conditions::is_visible( $item, array_values( $items ), $input ) ) {
				continue;
			}
			$own   = $rules['fields'][ $sc ] ?? array();
			$value = $input[ $sc ] ?? null;
			foreach ( is_array( $value ) ? $value : array( $value ) as $v ) {
				if ( ! is_scalar( $v ) || '' === trim( (string) $v ) ) {
					continue;
				}
				$n = self::norm( $v );
				if ( isset( $own[ $n ] ) ) {
					$score += $own[ $n ];
				} elseif ( isset( $rules['global'][ $n ] ) ) {
					$score += $rules['global'][ $n ];
				}
			}
			// The best this field could score: one answer for a single choice, every positive one for
			// checkboxes; a typed question counts its best rule of its own.
			$pts = array();
			foreach ( self::choices( $item ) as $ch ) {
				$n = self::norm( $ch );
				if ( isset( $own[ $n ] ) || isset( $rules['global'][ $n ] ) ) {
					$pts[] = $own[ $n ] ?? $rules['global'][ $n ];
				}
			}
			if ( ! $pts && $own ) {
				$pts = array_values( $own );
			}
			if ( $pts ) {
				$multi = 'checkboxes' === ( $item['type'] ?? '' ) || ! empty( $item['options']['multiple'] );
				$max  += $multi ? array_sum( array_filter( $pts, function ( $p ) {
					return $p > 0;
				} ) ) : max( 0, max( $pts ) );
			}
		}
		$outcome = null;
		foreach ( (array) ( $form['quiz_outcomes'] ?? array() ) as $o ) {
			$from = '' !== trim( (string) ( $o['from'] ?? '' ) ) ? (float) $o['from'] : -INF;
			$to   = '' !== trim( (string) ( $o['to'] ?? '' ) ) ? (float) $o['to'] : INF;
			if ( $score >= $from && $score <= $to ) {
				$outcome = $o;
				break;
			}
		}
		$fmt = function ( $n ) {
			return rtrim( rtrim( number_format( (float) $n, 2, '.', '' ), '0' ), '.' );
		};
		return array(
			'score'   => $fmt( $score ),
			'max'     => $fmt( $max ),
			'percent' => $max > 0 ? (int) round( 100 * $score / $max ) : 0,
			'outcome' => $outcome,
		);
	}

	/** The result for the form being sent in this request, or null when it is not a quiz. */
	public static function current( array $form = null ) {
		if ( null !== self::$result ) {
			return self::$result ? self::$result : null;
		}
		if ( null === $form || 'yes' !== ( $form['quiz_enabled'] ?? 'no' ) || ! class_exists( 'FW_Forms_Order' ) || ! FW_Forms_Order::items() ) {
			return null;
		}
		self::$result = self::score( $form, FW_Forms_Order::items(), (array) FW_Request::POST() );
		return self::$result;
	}

	/** The settings of the form being sent (from Forms' submit context). */
	private static function form_of( $data ) {
		return is_array( $data ) && class_exists( 'FW_Forms_Actions' ) ? FW_Forms_Actions::form_settings( $data ) : array();
	}

	private static function submitted_form() {
		$id = FW_Request::POST( 'fw_ext_forms_form_id' );
		return is_string( $id ) && '' !== $id ? self::form_of( array( 'id' => $id, 'type' => (string) FW_Request::POST( 'fw_ext_forms_form_type' ) ) ) : array();
	}

	/** @internal */
	public static function _tags( $tags, $values ) {
		$r = self::current( self::submitted_form() );
		if ( $r ) {
			$tags['quiz_score']   = $r['score'];
			$tags['quiz_max']     = $r['max'];
			$tags['quiz_percent'] = $r['percent'] . '%';
			$tags['quiz_result']  = (string) ( $r['outcome']['title'] ?? '' );
			$tags['quiz_message'] = (string) ( $r['outcome']['message'] ?? '' );
		}
		return $tags;
	}

	/** @internal A result range with a page sends the visitor there. */
	public static function _redirect( $url, $form ) {
		$r = self::current( (array) $form );
		$to = $r ? trim( (string) ( $r['outcome']['redirect'] ?? '' ) ) : '';
		return '' !== $to ? $to : $url;
	}

	/** @internal The score on the entry. */
	public static function _entry_row( $entry, $data ) {
		$r = self::current( self::form_of( $data ) );
		if ( $r && is_array( $entry ) && isset( $entry['fields'] ) ) {
			$entry['fields'][] = array(
				'id'    => '_quiz',
				'type'  => 'quiz',
				'label' => __( 'Quiz score', 'fw' ),
				'value' => sprintf( '%s / %s (%d%%)', $r['score'], $r['max'], $r['percent'] ) . ( ! empty( $r['outcome']['title'] ) ? ' · ' . $r['outcome']['title'] : '' ),
			);
		}
		return $entry;
	}

	/** @internal Tag the sender's CRM contact with the result's tag. */
	public static function _crm_tag( $data ) {
		$r   = self::current( self::form_of( $data ) );
		$tag = $r ? sanitize_title( (string) ( $r['outcome']['crm_tag'] ?? '' ) ) : '';
		if ( '' === $tag || ! class_exists( 'FW_CRM_Subscribers' ) || ! class_exists( 'FW_CRM_Tags' ) ) {
			return;
		}
		$email = '';
		foreach ( (array) ( $data['shortcode_to_item'] ?? array() ) as $sc => $item ) {
			if ( 'email' === ( $item['type'] ?? '' ) && is_email( (string) ( $data['form_values'][ $sc ] ?? '' ) ) ) {
				$email = (string) $data['form_values'][ $sc ];
				break;
			}
		}
		$c = '' !== $email ? FW_CRM_Subscribers::find_by_email( $email ) : null;
		if ( $c ) {
			FW_CRM_Tags::add( (int) $c->id, array( $tag => (string) ( $r['outcome']['crm_tag'] ?? $tag ) ), 'form-quiz' );
		}
	}
}
