<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

/**
 * Form Styles presets — Theme Settings → Components → Form Styles.
 *
 * A library of reusable looks for form fields, the same idea as Button and Box presets: each preset
 * becomes a `.formp-{slug}` class, the Contact Form element picks one on its Style tab, and editing a
 * preset restyles every form wearing it. The element keeps only what belongs to one form (width,
 * alignment, spacing, and its legacy one-off field colours, which still win when set).
 *
 * Everything here is owned by the Forms extension and plugs into two existing seams, so the tab and
 * its CSS exist only while Forms is active:
 *   - `unysonplus_components_settings_options` adds the tab (stored under `form_style_presets` in the
 *     theme-scoped Theme Settings store, read back with unysonplus_preset_store_get());
 *   - `unysonplus_global_css` appends the generated rules to the shared presets stylesheet. That
 *     filter is also part of the presets file's cache hash, so a preset edit rebuilds the file.
 *
 * Specificity: the base field rules in static/css/frontend.css are `.wrap-forms input:not()×4`
 * (0,5,1). A preset rule is `.formp-x .wrap-forms input:not()×4` (0,6,1), so it wins wherever the
 * two stylesheets load. The element's one-off overrides are emitted at (0,7,1) and win over both.
 *
 * Preset value (one addable-box row):
 *   id, style_name, field_bg, field_text, field_border, focus_color, placeholder_color, label_color,
 *   help_color, error_color, choice_color (compact colour values { predefined, custom }),
 *   border_width, border_radius, field_height, field_padding, font_size, label_size (unit-input),
 *   border_sides (all|bottom), focus_ring (none|subtle|medium|strong), label_weight, label_case,
 *   label_position ('' above | 'floating'),
 *   dark_field_bg, dark_field_text, dark_field_border, dark_placeholder_color, dark_label_color,
 *   dark_help_color (colours applied when the site is in dark mode), custom_css ({{SELECTOR}}).
 */
class FW_Forms_Style_Presets {

	/** Theme Settings key the presets are saved under. */
	const OPTION = 'form_style_presets';

	/** Class prefix for a preset (`.formp-{slug}`). */
	const PREFIX = 'formp-';

	/** @var string|null Generated CSS, once per request. */
	private static $css = null;

	public static function init() {
		add_filter( 'unysonplus_components_settings_options', array( __CLASS__, '_filter_tab' ), 20 );
		add_filter( 'unysonplus_global_css', array( __CLASS__, '_filter_css' ), 20 );
	}

	/* ------------------------------------------------------------------ data */

	/**
	 * The shipped presets. "Standard" reproduces the stylesheet's own field look exactly, so a form
	 * that picks it (every existing form does, by default) renders the same as before.
	 *
	 * @return array
	 */
	public static function defaults() {
		$c  = function ( $hex ) {
			return array( 'predefined' => '', 'custom' => $hex );
		};
		$p  = function ( $slug ) {
			return array( 'predefined' => 'text-' . $slug, 'custom' => '' );
		};
		$u  = function ( $v, $unit = 'px' ) {
			return array( 'value' => (string) $v, 'unit' => $unit );
		};
		$no = array( 'value' => '', 'unit' => 'px' );
		$e  = array( 'predefined' => '', 'custom' => '' );

		$base = array(
			'field_bg' => $e, 'field_text' => $e, 'field_border' => $e, 'focus_color' => $e,
			'placeholder_color' => $e, 'label_color' => $e, 'help_color' => $e, 'error_color' => $e, 'choice_color' => $e,
			'border_width' => $no, 'border_radius' => $no, 'field_height' => $no, 'field_padding' => $no,
			'font_size' => $no, 'label_size' => $no, 'border_sides' => 'all', 'focus_ring' => 'subtle',
			'label_weight' => '', 'label_case' => '', 'label_position' => '',
			'dark_field_bg' => $e, 'dark_field_text' => $e, 'dark_field_border' => $e,
			'dark_placeholder_color' => $e, 'dark_label_color' => $e, 'dark_help_color' => $e,
			'custom_css' => '',
		);

		$presets = array(
			array_merge( $base, array(
				'id' => 'fsp-standard', 'style_name' => 'Standard',
				'field_bg' => $c( '#ffffff' ), 'field_text' => $c( '#14161a' ), 'field_border' => $c( '#dbdfe4' ),
				'focus_color' => $c( '#14161a' ), 'placeholder_color' => $c( '#9aa2ab' ),
				'border_width' => $u( 1 ), 'border_radius' => $u( 10 ), 'field_height' => $u( 48 ), 'field_padding' => $u( 16 ),
				'dark_field_bg' => $c( '#1b1e23' ), 'dark_field_text' => $c( '#f1f3f5' ), 'dark_field_border' => $c( '#3a3f47' ),
				'dark_placeholder_color' => $c( '#8a929b' ),
			) ),
			array_merge( $base, array(
				'id' => 'fsp-soft', 'style_name' => 'Soft',
				'field_bg' => $c( '#f3f4f6' ), 'field_text' => $c( '#14161a' ), 'field_border' => $c( '#f3f4f6' ),
				'focus_color' => $p( 'primary' ), 'placeholder_color' => $c( '#8a929b' ), 'choice_color' => $p( 'primary' ),
				'border_width' => $u( 1 ), 'border_radius' => $u( 12 ), 'field_height' => $u( 50 ), 'field_padding' => $u( 18 ),
				'focus_ring' => 'medium', 'label_weight' => '600',
				'dark_field_bg' => $c( '#22262c' ), 'dark_field_text' => $c( '#f1f3f5' ), 'dark_field_border' => $c( '#22262c' ),
				'dark_placeholder_color' => $c( '#8a929b' ),
			) ),
			array_merge( $base, array(
				'id' => 'fsp-underline', 'style_name' => 'Underline',
				'field_bg' => $c( 'transparent' ), 'field_text' => $c( 'inherit' ), 'field_border' => $c( '#c4c9cf' ), 'focus_color' => $p( 'primary' ),
				'placeholder_color' => $c( '#9aa2ab' ), 'choice_color' => $p( 'primary' ),
				'border_width' => $u( 1 ), 'border_radius' => $u( 0 ), 'field_height' => $u( 46 ), 'field_padding' => $u( 0 ),
				'border_sides' => 'bottom', 'label_size' => $u( 12 ), 'label_weight' => '600', 'label_case' => 'upper',
				'dark_field_border' => $c( '#4a5058' ),
			) ),
			array_merge( $base, array(
				'id' => 'fsp-rounded', 'style_name' => 'Rounded',
				'field_bg' => $c( '#ffffff' ), 'field_text' => $c( '#14161a' ), 'field_border' => $c( '#d0d4da' ),
				'focus_color' => $p( 'primary' ), 'placeholder_color' => $c( '#9aa2ab' ), 'choice_color' => $p( 'primary' ),
				'border_width' => $u( 1 ), 'border_radius' => $u( 999 ), 'field_height' => $u( 52 ), 'field_padding' => $u( 22 ),
				'focus_ring' => 'medium',
				'dark_field_bg' => $c( '#1b1e23' ), 'dark_field_text' => $c( '#f1f3f5' ), 'dark_field_border' => $c( '#3a3f47' ),
			) ),
			array_merge( $base, array(
				'id' => 'fsp-floating', 'style_name' => 'Floating Labels',
				'field_bg' => $c( '#ffffff' ), 'field_text' => $c( '#14161a' ), 'field_border' => $c( '#d0d4da' ),
				'focus_color' => $p( 'primary' ), 'placeholder_color' => $c( '#9aa2ab' ), 'choice_color' => $p( 'primary' ),
				'border_width' => $u( 1 ), 'border_radius' => $u( 10 ), 'field_height' => $u( 56 ), 'field_padding' => $u( 16 ),
				'focus_ring' => 'medium', 'label_position' => 'floating',
				'dark_field_bg' => $c( '#1b1e23' ), 'dark_field_text' => $c( '#f1f3f5' ), 'dark_field_border' => $c( '#3a3f47' ),
			) ),
			array_merge( $base, array(
				'id' => 'fsp-on-dark', 'style_name' => 'On Dark',
				'field_bg' => $c( 'rgba(255,255,255,.08)' ), 'field_text' => $c( '#ffffff' ), 'field_border' => $c( 'rgba(255,255,255,.24)' ),
				'focus_color' => $c( '#ffffff' ), 'placeholder_color' => $c( 'rgba(255,255,255,.55)' ),
				'label_color' => $c( '#ffffff' ), 'help_color' => $c( 'rgba(255,255,255,.72)' ), 'error_color' => $c( '#ffb4a8' ),
				'choice_color' => $c( '#ffffff' ),
				'border_width' => $u( 1 ), 'border_radius' => $u( 10 ), 'field_height' => $u( 48 ), 'field_padding' => $u( 16 ),
				'focus_ring' => 'medium',
			) ),
		);

		/** Filters the shipped Form Styles presets (used until the site saves its own). */
		return apply_filters( 'unysonplus_default_form_style_presets', $presets );
	}

	/**
	 * The site's saved presets, or the shipped ones.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = function_exists( 'unysonplus_preset_store_get' ) ? unysonplus_preset_store_get( self::OPTION, null ) : null;
		$list  = ( is_array( $saved ) && ! empty( $saved ) ) ? array_values( $saved ) : self::defaults();
		/** Filters the Form Styles presets, saved or shipped. */
		return (array) apply_filters( 'unysonplus_form_style_presets', $list );
	}

	/**
	 * [ preset id => css slug ]. Readable slugs ("On Dark" → `on-dark`); collisions get -2, -3; an
	 * empty name falls back to the id. Shared by the CSS, the element's dropdown and the renderer.
	 *
	 * @return array
	 */
	public static function slug_map() {
		$map  = array();
		$seen = array();
		foreach ( self::all() as $sp ) {
			if ( ! is_array( $sp ) || empty( $sp['id'] ) ) {
				continue;
			}
			$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $sp['id'] );
			if ( $id === '' ) {
				continue;
			}
			$name = isset( $sp['style_name'] ) ? (string) $sp['style_name'] : '';
			$slug = trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $name ) ), '-' );
			if ( $slug === '' ) {
				$slug = strtolower( $id );
			}
			$base = $slug;
			$n    = 1;
			while ( isset( $seen[ $slug ] ) ) {
				$n++;
				$slug = $base . '-' . $n;
			}
			$seen[ $slug ] = true;
			$map[ $id ]    = $slug;
		}
		return $map;
	}

	/**
	 * Choices for the element's "Form Style" dropdown: class => name, plus None (the stylesheet's
	 * own look with no preset).
	 *
	 * @return array
	 */
	public static function choices() {
		$out = array();
		$map = self::slug_map();
		foreach ( self::all() as $sp ) {
			$id = isset( $sp['id'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $sp['id'] ) : '';
			if ( $id === '' || ! isset( $map[ $id ] ) ) {
				continue;
			}
			$name = ( isset( $sp['style_name'] ) && $sp['style_name'] !== '' ) ? (string) $sp['style_name'] : $map[ $id ];
			$out[ self::PREFIX . $map[ $id ] ] = $name;
		}
		$out[''] = __( 'None (plain fields)', 'fw' );
		return $out;
	}

	/** The dropdown's default: the first preset, or None when the library is empty. */
	public static function default_choice() {
		foreach ( array_keys( self::choices() ) as $k ) {
			if ( $k !== '' ) {
				return $k;
			}
		}
		return '';
	}

	/**
	 * Is $class a preset that exists right now? A form whose stored style was deleted or renamed
	 * falls back to no preset instead of carrying a class nothing styles.
	 */
	public static function is_valid( $class ) {
		$class = (string) $class;
		return $class !== '' && preg_match( '/^formp-[a-z0-9_-]+$/', $class ) && isset( self::choices()[ $class ] );
	}

	/**
	 * The form containers a preset styles, inside the element that carries the `.formp-{slug}` class.
	 * Forms' own is `.wrap-forms`; the Newsletter element and Membership's login form add theirs, so one
	 * preset gives every form on the site the same fields. Class selectors only.
	 *
	 * @return string[]
	 */
	public static function targets() {
		/**
		 * Filters the form containers Form Styles presets reach (class selectors, e.g. '.fw-nl__form').
		 *
		 * @param string[] $targets
		 */
		// Forms' own fields, the Newsletter element's form and Membership's login form. Their classes are
		// listed here, not added by each one, because the presets stylesheet is generated before every
		// element has loaded; the classes only take effect inside an element wearing a `.formp-*` class.
		$list = (array) apply_filters( 'unysonplus_forms_style_targets', array( '.wrap-forms', '.fw-nl__form', '.fw-mbr-login' ) );
		$out  = array();
		foreach ( $list as $t ) {
			$t = trim( (string) $t );
			if ( preg_match( '/^\.[a-zA-Z][a-zA-Z0-9_-]*$/', $t ) ) {
				$out[ $t ] = true;
			}
		}
		return $out ? array_keys( $out ) : array( '.wrap-forms' );
	}

	/** `.wrap-forms`, or `:is(.wrap-forms,.fw-nl__form,…)` — the same specificity either way. */
	private static function scope() {
		$t = self::targets();
		return 1 === count( $t ) ? $t[0] : ':is(' . implode( ',', $t ) . ')';
	}

	/**
	 * The class for a stored choice: a preset class that exists, 'default' = the site's default style (the
	 * first preset), anything else = '' (no preset).
	 */
	public static function resolve( $choice ) {
		$choice = (string) $choice;
		if ( 'default' === $choice ) {
			return self::default_choice();
		}
		return self::is_valid( $choice ) ? $choice : '';
	}

	/* ------------------------------------------------------------------ Theme Settings tab */

	/** @internal Adds Components → Form Styles right after Buttons. */
	public static function _filter_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			return $tabs;
		}
		$tab = array(
			'title'   => __( 'Form Styles', 'fw' ),
			'type'    => 'tab',
			'options' => array( self::OPTION => self::schema() ),
		);
		$out = array();
		foreach ( $tabs as $k => $v ) {
			$out[ $k ] = $v;
			if ( $k === 'tab_buttons' ) {
				$out['tab_form_styles'] = $tab;
			}
		}
		if ( ! isset( $out['tab_form_styles'] ) ) {
			$out['tab_form_styles'] = $tab;
		}
		return $out;
	}

	/** The addable-box schema for one preset. */
	private static function schema() {
		$color = function ( $label, $desc = '', $kind = 'text' ) {
			if ( function_exists( 'sc_color_field_compact' ) ) {
				return sc_color_field_compact( array( 'label' => $label, 'desc' => $desc, 'kind' => $kind, 'picker' => 'rgba-color-picker' ) );
			}
			return array( 'label' => $label, 'desc' => $desc, 'type' => 'rgba-color-picker', 'value' => '' );
		};
		$len = function ( $label, $desc = '', $units = array( 'px', 'rem', 'em' ) ) {
			return array( 'label' => $label, 'desc' => $desc, 'type' => 'unit-input', 'units' => $units, 'min' => 0, 'value' => array( 'value' => '', 'unit' => 'px' ) );
		};

		return array(
			'label'           => __( 'Form Styles', 'fw' ),
			'type'            => 'addable-box',
			'width'           => 'full',
			'value'           => self::defaults(),
			'desc'            => __( 'Reusable looks for form fields. Each becomes a <code>.formp-{name}</code> class you pick on a Contact Form (Style → Form Style), so every form on the site matches and one edit here restyles them all. The submit button keeps using your Button presets. "Standard" is the original field look.', 'fw' ),
			'sortable'        => true,
			'add-button-text' => __( 'Add Form Style', 'fw' ),
			'box-options'     => array(
				'id'                => array( 'type' => 'unique' ),
				'style_name'        => array(
					'label' => __( 'Name', 'fw' ),
					'type'  => 'text',
					'value' => '',
					'desc'  => __( 'Shown in the Contact Form\'s Form Style dropdown, and becomes the class suffix (e.g. <code>On Dark</code> → <code>.formp-on-dark</code>).', 'fw' ),
				),
				'field_bg'          => $color( __( 'Field background', 'fw' ), '', 'bg' ),
				'field_text'        => $color( __( 'Field text', 'fw' ) ),
				'field_border'      => $color( __( 'Field border', 'fw' ) ),
				'focus_color'       => $color( __( 'Focus colour', 'fw' ), __( 'Border and ring colour while a field is being typed in.', 'fw' ) ),
				'placeholder_color' => $color( __( 'Placeholder text', 'fw' ) ),
				'label_color'       => $color( __( 'Label colour', 'fw' ), __( 'Field labels, and the text beside checkboxes and radio buttons.', 'fw' ) ),
				'help_color'        => $color( __( 'Help text colour', 'fw' ), __( 'The small hint under a field.', 'fw' ) ),
				'error_color'       => $color( __( 'Error colour', 'fw' ), __( 'Error messages and the required-field star.', 'fw' ) ),
				'choice_color'      => $color( __( 'Choice colour', 'fw' ), __( 'Ticked checkboxes and radio buttons, and the chosen score in a rating.', 'fw' ) ),
				'border_width'      => $len( __( 'Border width', 'fw' ) ),
				'border_sides'      => array(
					'label'   => __( 'Border', 'fw' ),
					'type'    => 'select',
					'value'   => 'all',
					'choices' => array(
						'all'    => __( 'All sides (a box)', 'fw' ),
						'bottom' => __( 'Bottom only (an underline)', 'fw' ),
					),
				),
				'border_radius'     => $len( __( 'Corner radius', 'fw' ), __( 'Long text boxes are capped at 20px so a pill radius still reads well on them.', 'fw' ), array( 'px', 'rem', 'em', '%' ) ),
				'field_height'      => $len( __( 'Field height', 'fw' ), __( 'One-line fields and dropdowns.', 'fw' ) ),
				'field_padding'     => $len( __( 'Side padding', 'fw' ), __( 'Space between the field edge and the text.', 'fw' ) ),
				'font_size'         => $len( __( 'Field text size', 'fw' ) ),
				'focus_ring'        => array(
					'label'   => __( 'Focus ring', 'fw' ),
					'type'    => 'select',
					'value'   => 'subtle',
					'desc'    => __( 'A soft halo in the focus colour around the field being typed in. An underline style gets a thicker underline instead.', 'fw' ),
					'choices' => array(
						'none'   => __( 'None', 'fw' ),
						'subtle' => __( 'Subtle', 'fw' ),
						'medium' => __( 'Medium', 'fw' ),
						'strong' => __( 'Strong', 'fw' ),
					),
				),
				'label_size'        => $len( __( 'Label size', 'fw' ) ),
				'label_weight'      => array(
					'label'   => __( 'Label weight', 'fw' ),
					'type'    => 'select',
					'value'   => '',
					'choices' => array( '' => __( 'Default', 'fw' ), '400' => __( 'Regular', 'fw' ), '500' => __( 'Medium', 'fw' ), '600' => __( 'Semibold', 'fw' ), '700' => __( 'Bold', 'fw' ) ),
				),
				'label_case'        => array(
					'label'   => __( 'Label case', 'fw' ),
					'type'    => 'select',
					'value'   => '',
					'choices' => array( '' => __( 'As written', 'fw' ), 'upper' => __( 'Uppercase', 'fw' ) ),
				),
				'label_position'    => array(
					'label'   => __( 'Label position', 'fw' ),
					'type'    => 'select',
					'value'   => '',
					'desc'    => __( 'Floating: the label sits inside the empty field and moves up, smaller, once the field is focused or filled. Applies to one-line fields and text boxes.', 'fw' ),
					'choices' => array( '' => __( 'Above the field', 'fw' ), 'floating' => __( 'Floating (inside the field)', 'fw' ) ),
				),
				'dark_field_bg'          => $color( __( 'Dark mode: field background', 'fw' ), __( 'Used when the site is shown in dark mode. Leave the dark-mode colours empty to keep the colours above.', 'fw' ), 'bg' ),
				'dark_field_text'        => $color( __( 'Dark mode: field text', 'fw' ) ),
				'dark_field_border'      => $color( __( 'Dark mode: field border', 'fw' ) ),
				'dark_placeholder_color' => $color( __( 'Dark mode: placeholder text', 'fw' ) ),
				'dark_label_color'       => $color( __( 'Dark mode: label colour', 'fw' ) ),
				'dark_help_color'        => $color( __( 'Dark mode: help text colour', 'fw' ) ),
				'custom_css'        => array(
					'type'        => 'code-editor',
					'label'       => __( 'Custom CSS (advanced)', 'fw' ),
					'mode'        => 'css',
					'height'      => 140,
					'placeholder' => "{{SELECTOR}} .wrap-forms textarea {\n    /* your rules */\n}",
					'desc'        => __( 'Use {{SELECTOR}} for this preset (becomes .formp-&lt;name&gt;). Fields sit inside <code>.wrap-forms</code>.', 'fw' ),
				),
			),
			'template'        => '{{- style_name }}',
		);
	}

	/* ------------------------------------------------------------------ CSS */

	/** @internal Appends the preset rules to the shared presets stylesheet. */
	public static function _filter_css( $css ) {
		$own = self::css();
		return $own === '' ? $css : ( (string) $css . "\n" . $own );
	}

	/**
	 * Every preset's rules.
	 *
	 * @return string
	 */
	public static function css() {
		if ( self::$css !== null ) {
			return self::$css;
		}
		$map = self::slug_map();
		$out = '';
		foreach ( self::all() as $sp ) {
			$id = isset( $sp['id'] ) ? preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $sp['id'] ) : '';
			if ( $id === '' || ! isset( $map[ $id ] ) ) {
				continue;
			}
			$out .= self::preset_css( '.' . self::PREFIX . $map[ $id ], $sp );
		}
		self::$css = $out;
		return $out;
	}

	/** Resolve a compact colour value to a CSS colour, or ''. */
	private static function color( $v ) {
		if ( is_array( $v ) ) {
			$custom = isset( $v['custom'] ) ? trim( (string) $v['custom'] ) : '';
			if ( $custom !== '' ) {
				return self::safe_color( $custom );
			}
			$v = isset( $v['predefined'] ) ? (string) $v['predefined'] : '';
		}
		$v = trim( (string) $v );
		if ( $v === '' ) {
			return '';
		}
		if ( function_exists( 'unysonplus_resolve_preset_color' ) ) {
			$r = unysonplus_resolve_preset_color( $v );
			if ( $r !== '' ) {
				return self::safe_color( $r );
			}
		}
		return self::safe_color( $v );
	}

	/** Allow hex, rgb()/rgba()/hsl()/hsla(), var() and plain keywords; drop anything else. */
	private static function safe_color( $c ) {
		$c = trim( (string) $c );
		if ( preg_match( '/^#[0-9a-fA-F]{3,8}$/', $c )
			|| preg_match( '/^(rgba?|hsla?)\(\s*[0-9.,%\s\/-]+\)$/i', $c )
			|| preg_match( '/^var\(--[a-zA-Z0-9_-]+\)$/', $c )
			|| preg_match( '/^[a-zA-Z]+$/', $c ) ) {
			return $c;
		}
		return '';
	}

	/** Resolve a unit-input value to a CSS length, or ''. */
	private static function len( $v ) {
		if ( is_array( $v ) ) {
			$num  = isset( $v['value'] ) ? trim( (string) $v['value'] ) : '';
			$unit = isset( $v['unit'] ) ? trim( (string) $v['unit'] ) : 'px';
			if ( $num === '' || ! is_numeric( $num ) ) {
				return '';
			}
			if ( ! in_array( $unit, array( 'px', 'rem', 'em', '%' ), true ) ) {
				$unit = 'px';
			}
			return ( (float) $num == 0 ) ? '0' : ( $num . $unit );
		}
		$v = trim( (string) $v );
		return preg_match( '/^\d+(\.\d+)?(px|rem|em|%)?$/', $v ) ? $v : '';
	}

	/**
	 * One preset's rules, scoped to $s.
	 *
	 * @param string $s  Preset selector, e.g. ".formp-standard".
	 * @param array  $sp Preset value.
	 * @return string
	 */
	private static function preset_css( $s, array $sp ) {
		$g     = function ( $k ) use ( $sp ) {
			return isset( $sp[ $k ] ) ? $sp[ $k ] : '';
		};
		$input = 'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"])';
		$w     = $s . ' ' . self::scope() . ' ';
		$all   = $w . $input . ',' . $w . 'textarea,' . $w . 'select';
		$line  = $w . $input . ',' . $w . 'select';
		$rules = array();
		$add   = function ( $sel, $decls ) use ( &$rules ) {
			$decls = array_filter( $decls, 'strlen' );
			if ( $decls ) {
				$rules[] = $sel . '{' . implode( ';', $decls ) . '}';
			}
		};

		$bw     = self::len( $g( 'border_width' ) );
		$radius = self::len( $g( 'border_radius' ) );
		$bottom = ( $g( 'border_sides' ) === 'bottom' );
		$focus  = self::color( $g( 'focus_color' ) );

		// Shape and type.
		$shape = array(
			$bw !== '' ? ( $bottom ? 'border-width:0 0 ' . $bw . ' 0' : 'border-width:' . $bw ) : ( $bottom ? 'border-width:0 0 1px 0' : '' ),
			$bw !== '' || $bottom ? 'border-style:solid' : '',
			$radius !== '' ? 'border-radius:' . $radius : '',
			( $f = self::len( $g( 'font_size' ) ) ) !== '' ? 'font-size:' . $f : '',
		);
		$add( $all, $shape );

		$h   = self::len( $g( 'field_height' ) );
		$pad = self::len( $g( 'field_padding' ) );
		$add( $line, array(
			$h !== '' ? 'height:' . $h : '',
			$pad !== '' ? 'padding:0 ' . $pad : '',
		) );
		$add( $w . 'textarea', array(
			$pad !== '' ? 'padding:14px ' . $pad : '',
			$radius !== '' ? 'border-radius:min(' . $radius . ',20px)' : '',
		) );

		// Labels.
		$main  = $w . 'div[class^="field-"]:not(.field-consent)>label,' . $w . 'fieldset[class^="field-"]>legend';
		foreach ( self::targets() as $t ) {
			if ( '.wrap-forms' !== $t ) {
				$main .= ',' . $s . ' ' . $t . ' label:not(:has(input[type="checkbox"],input[type="radio"]))';
			}
		}
		$ls    = self::len( $g( 'label_size' ) );
		$lw    = (string) $g( 'label_weight' );
		$upper = ( $g( 'label_case' ) === 'upper' );
		$add( $main, array(
			$ls !== '' ? 'font-size:' . $ls : '',
			preg_match( '/^[1-9]00$/', $lw ) ? 'font-weight:' . $lw : '',
			$upper ? 'text-transform:uppercase' : '',
			$upper ? 'letter-spacing:.06em' : '',
		) );

		// Floating labels: the label sits in the empty field and moves up once it is focused or filled.
		// Needs a (blank) placeholder for :placeholder-shown; static/js/fields.js adds one where missing,
		// and without it the label simply stays in its raised position.
		if ( 'floating' === $g( 'label_position' ) ) {
			$fw   = $s . $s . ' .wrap-forms div:is(.field-text,.field-textarea)';
			$hh   = '' !== $h ? $h : '48px';
			$left = '' !== $pad ? $pad : '16px';
			$add( $fw, array( 'position:relative' ) );
			$add( $fw . '>label', array(
				'position:absolute', 'z-index:1', 'left:calc(' . $left . ' + ' . ( '' !== $bw ? $bw : '1px' ) . ')', 'top:calc(' . $hh . ' / 2)',
				'transform:translateY(-50%)', 'margin:0', 'width:auto', 'max-width:calc(100% - 2 * ' . $left . ')', 'pointer-events:none',
				'font-weight:400', 'opacity:.7', 'white-space:nowrap', 'overflow:hidden', 'text-overflow:ellipsis',
				'transition:top .15s ease,font-size .15s ease,opacity .15s ease,transform .15s ease',
			) );
			$add( $s . $s . ' .wrap-forms div.field-textarea>label', array( 'top:22px' ) );
			$up = $fw . ':has(input:focus,input:not(:placeholder-shown),textarea:focus,textarea:not(:placeholder-shown))>label';
			$add( $up, array( 'top:6px', 'transform:none', 'font-size:11px', 'font-weight:600', 'opacity:1' ) );
			// Must outrank the preset's own `input:not()×4 { padding }` rule (0,6,1).
			$add( $fw . '>input:not([type="checkbox"]):not([type="radio"])', array( 'padding-top:14px' ) );
			$add( $fw . '>textarea', array( 'padding-top:24px' ) );
			// A real placeholder would sit under the label: show it only while typing.
			$add( $fw . ' :is(input,textarea)::placeholder', array( 'color:transparent' ) );
			$add( $fw . ' :is(input,textarea):focus::placeholder', array( 'color:inherit', 'opacity:.45' ) );
			$add( '@media (prefers-reduced-motion:reduce){' . $fw . '>label', array( 'transition:none' ) );
			$rules[ count( $rules ) - 1 ] .= '}';
		}

		// Focus.
		$ring = (string) $g( 'focus_ring' );
		$pct  = array( 'subtle' => 8, 'medium' => 18, 'strong' => 32 );
		$fsel = $w . $input . ':focus,' . $w . 'textarea:focus,' . $w . 'select:focus';
		if ( $focus !== '' || $ring === 'none' ) {
			$shadow = '';
			if ( $ring === 'none' ) {
				$shadow = 'box-shadow:none';
			} elseif ( $focus !== '' && $bottom ) {
				$shadow = 'box-shadow:inset 0 -1px 0 0 ' . $focus;
			} elseif ( $focus !== '' && isset( $pct[ $ring ] ) ) {
				$shadow = 'box-shadow:0 0 0 3px color-mix(in srgb,' . $focus . ' ' . $pct[ $ring ] . '%,transparent)';
			}
			$add( $fsel, array( $focus !== '' ? 'border-color:' . $focus : '', $shadow ) );
		}

		// Colours (light), then the same set again for dark mode.
		$rules = array_merge( $rules, self::color_rules( $s, $sp, '' ) );
		$dark  = self::color_rules( ':root[data-bs-theme="dark"] ' . $s, $sp, 'dark_' );
		$rules = array_merge( $rules, $dark );

		// A dropdown's option list is drawn by the system on its own surface, so it must not inherit a
		// light field text colour (white text on the system's white list).
		$rules[] = $w . 'select option{color:CanvasText;background-color:Canvas}';

		// Choices, errors.
		$choice = self::color( $g( 'choice_color' ) );
		if ( $choice !== '' ) {
			$add( $w . 'input[type="checkbox"],' . $w . 'input[type="radio"]', array( 'accent-color:' . $choice ) );
			$add( $w . '.fw-rating--pills .fw-rating__input:checked+.fw-rating__point', array( 'background:' . $choice, 'border-color:' . $choice ) );
			$add( $w . '.fw-rating--pills .fw-rating__point:hover', array( 'border-color:' . $choice ) );
		}
		$err = self::color( $g( 'error_color' ) );
		if ( $err !== '' ) {
			// The validation script writes its message colour inline, so only !important reaches it.
			$add( $s, array( '--fw-form-error:' . $err ) );
			$add( $s . ' .form-error,' . $s . ' .fw-form-errors li', array( 'color:' . $err . ' !important' ) );
			$add( $w . 'sup', array( 'color:' . $err ) );
		}

		// Free-form CSS, sanitised the same way as the Button and Box presets.
		$custom = (string) $g( 'custom_css' );
		if ( trim( $custom ) !== '' ) {
			$custom  = preg_replace( '#</?(style|script)[^>]*>#i', '', $custom );
			$custom  = str_replace( array( '<', '>' ), '', $custom );
			$rules[] = str_replace( '{{SELECTOR}}', $s, $custom );
		}

		return implode( "\n", $rules ) . "\n";
	}

	/**
	 * Colour rules for one preset. $prefix '' reads the light keys; 'dark_' reads the dark-mode keys
	 * (only those that are set), scoped under $s which then already carries the dark-mode root.
	 *
	 * @return string[]
	 */
	private static function color_rules( $s, array $sp, $prefix ) {
		$g = function ( $k ) use ( $sp, $prefix ) {
			return self::color( isset( $sp[ $prefix . $k ] ) ? $sp[ $prefix . $k ] : '' );
		};
		$input = 'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"])';
		$w     = $s . ' ' . self::scope() . ' ';
		$all   = $w . $input . ',' . $w . 'textarea,' . $w . 'select';
		$out   = array();

		$bg = $g( 'field_bg' );
		$tx = $g( 'field_text' );
		$bd = $g( 'field_border' );
		$d  = array_filter( array(
			$bg !== '' ? 'background:' . $bg : '',
			$tx !== '' ? 'color:' . $tx : '',
			$bd !== '' ? 'border-color:' . $bd : '',
		), 'strlen' );
		if ( $d ) {
			$out[] = $all . '{' . implode( ';', $d ) . '}';
			// Rating pills sit beside the fields and should match them.
			$out[] = $w . '.fw-rating--pills .fw-rating__point{' . implode( ';', $d ) . '}';
		}
		if ( ( $ph = $g( 'placeholder_color' ) ) !== '' ) {
			$out[] = $w . $input . '::placeholder,' . $w . 'textarea::placeholder{color:' . $ph . '}';
		}
		if ( ( $lc = $g( 'label_color' ) ) !== '' ) {
			$out[] = $w . 'label,' . $w . 'legend{color:' . $lc . '}';
		}
		if ( ( $hc = $g( 'help_color' ) ) !== '' ) {
			$out[] = $w . '.field-info,' . $w . '.fw-rating__ends{color:' . $hc . '}';
		}
		return $out;
	}
}
