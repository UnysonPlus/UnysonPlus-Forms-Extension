<?php if ( ! defined( 'FW' ) ) {
	die( 'Forbidden' );
}

$cfg = array();

$cfg['page_builder'] = array(
	'title'       => __( 'Contact form', 'fw' ),
	'description' => __( 'Add a Contact Form', 'fw' ),
	'tab'         => __( 'Interactive Elements', 'fw' ),
	'popup_size'  => 'large',
	// 'simple' -- the ONLY item type the page builder implements. This read 'special', which no item type
	// handles: the builder's simple-item JS looks the shortcode up in page_builder_item_type_simple_data,
	// did not find it, and every converted contact form opened as "The shortcode contact_form not found."
	// The shortcode rendered correctly on the front end the whole time -- it was only uneditable in the
	// builder, which is exactly the breakage a converted page must not ship. It was the only shortcode in
	// the framework declaring 'special'.
	'type'        => 'simple',

	// Builder preview: a schematic of the form — each field's label over a box that matches its kind
	// (single-line, textarea, dropdown, checkboxes, rating), laid out at the field's real width, with a
	// required marker and the submit button. Parses the form-builder value (o.form.json). Falls back to
	// the plain title when there are no fields (or a saved form is used).
	'title_template' => <<<'TPL'
		{{ if ( o ) {
			var items = [];
			try {
				var raw = '[]';
				if ( o.form ) { raw = ( typeof o.form === 'string' ) ? o.form : ( o.form.json != null ? o.form.json : '[]' ); }
				items = ( typeof raw === 'string' ) ? JSON.parse( raw ) : ( raw || [] );
				if ( ! _.isArray( items ) ) { items = []; }
			} catch ( e ) { items = []; }
			var usingLibrary = o.library_form && String( o.library_form ).length;
			var submit = ( o.submit_button_text && String( o.submit_button_text ).trim() ) || 'Send';
			var prettify = function ( t ) { return String( t || 'field' ).replace( /[-_]/g, ' ' ).replace( /\b\w/g, function ( c ) { return c.toUpperCase(); } ); };
			var pct = function ( w ) { if ( ! w ) { return 100; } var m = String( w ).split( '/' ); if ( m.length === 2 ) { var a = parseFloat( m[0] ), b = parseFloat( m[1] ); if ( b ) { return Math.max( 20, Math.round( a / b * 100 ) ); } } return 100; };
			var HEAD = ['form-header-title'], DIV = ['section','page-break'], HID = ['hidden','honeypot','recaptcha','hcaptcha','turnstile'],
				MULTI = ['checkboxes','radio','image-choice','consent','likert','ranking'], ROUND = ['radio','image-choice'],
				TALL = ['textarea','signature','file-upload','repeater','address'], DROP = ['select','date','phone','payment-method','product','country','number','range'];
			var shown = 0, hiddenCount = 0;
		}}
		<div style="margin-top:.4rem;">
			{{ if ( usingLibrary ) { }}<div style="font-size:.8em; opacity:.6; margin-bottom:.4rem;">Saved form</div>{{ } }}
			<div style="display:flex; flex-wrap:wrap; gap:7px 6px; align-items:flex-end;">
				{{ _.each( items, function ( it ) {
					var type = it && it.type ? it.type : '';
					var opts = ( it && it.options ) ? it.options : {};
					if ( _.contains( HID, type ) ) { hiddenCount++; return; }
					if ( shown >= 7 ) { return; }
					shown++;
					var w = pct( it ? it.width : '' );
					var basis = 'flex:1 1 ' + ( w >= 100 ? 100 : ( w - 2 ) ) + '%; min-width:' + ( w >= 100 ? '100%' : '92px' ) + ';';
					var req = ( opts.required === 'yes' || opts.required === true || opts.required === 'true' );
				}}
					{{ if ( _.contains( HEAD, type ) ) { }}
						<div style="flex:1 1 100%;">
							<div style="font-weight:600; font-size:.95em; line-height:1.3;">{{- opts.title || prettify( type ) }}</div>
							{{ if ( opts.subtitle ) { }}<div style="font-size:.82em; opacity:.6;">{{- opts.subtitle }}</div>{{ } }}
						</div>
					{{ } else if ( _.contains( DIV, type ) ) { }}
						<div style="flex:1 1 100%; border-top:1px dashed rgba(0,0,0,.18); margin:.15rem 0; padding-top:.15rem; font-size:.74em; opacity:.5;">{{- type === 'page-break' ? 'Page break' : ( opts.label || 'Section' ) }}</div>
					{{ } else { }}
						<div style="{{= basis }}">
							<div style="font-size:.78em; opacity:.72; margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{- opts.label || prettify( type ) }}{{ if ( req ) { }}<span style="color:#d63638;"> *</span>{{ } }}</div>
							{{ if ( _.contains( MULTI, type ) ) { }}
								<div style="display:flex; flex-direction:column; gap:3px;">
									<span style="display:flex; align-items:center; gap:5px;"><span style="width:11px; height:11px; border:1px solid rgba(0,0,0,.3); border-radius:{{= _.contains( ROUND, type ) ? '50%' : '3px' }};"></span><span style="height:7px; width:48px; max-width:70%; background:rgba(0,0,0,.1); border-radius:3px;"></span></span>
									<span style="display:flex; align-items:center; gap:5px;"><span style="width:11px; height:11px; border:1px solid rgba(0,0,0,.3); border-radius:{{= _.contains( ROUND, type ) ? '50%' : '3px' }};"></span><span style="height:7px; width:36px; max-width:55%; background:rgba(0,0,0,.1); border-radius:3px;"></span></span>
								</div>
							{{ } else if ( type === 'rating' ) { }}
								<div style="letter-spacing:2px; color:rgba(0,0,0,.35); font-size:1.05em; line-height:1;">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
							{{ } else if ( _.contains( TALL, type ) ) { }}
								<div style="height:34px; border:1px solid rgba(0,0,0,.2); border-radius:4px; background:rgba(0,0,0,.02);"></div>
							{{ } else { }}
								<div style="height:18px; border:1px solid rgba(0,0,0,.2); border-radius:4px; background:rgba(0,0,0,.02); display:flex; align-items:center; justify-content:flex-end; padding-right:6px;">{{ if ( _.contains( DROP, type ) ) { }}<span style="width:0; height:0; border-left:4px solid transparent; border-right:4px solid transparent; border-top:5px solid rgba(0,0,0,.35);"></span>{{ } }}</div>
							{{ } }}
						</div>
					{{ } }}
				{{ } ); }}
			</div>
			{{ var more = items.length - shown - hiddenCount; if ( more > 0 ) { }}
				<div style="font-size:.75em; opacity:.5; margin-top:.4rem;">+{{= more }} more field{{= more === 1 ? '' : 's' }}</div>
			{{ } }}
			{{ if ( ! usingLibrary && shown === 0 ) { }}<div style="font-size:.8em; opacity:.55;">No fields yet</div>{{ } }}
			<div style="margin-top:.55rem;"><span style="display:inline-block; background:rgba(0,0,0,.72); color:#fff; font-size:.8em; padding:3px 14px; border-radius:4px;">{{- submit }}</span></div>
		</div>
		{{ } }}
TPL
);