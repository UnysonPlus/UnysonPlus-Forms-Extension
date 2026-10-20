/**
 * The form-type picker (see forms/includes/picker/class-fw-forms-picker.php).
 *
 * An empty form builder (nothing but the header item) opens on a grid of form types; picking one
 * fills the builder the way loading a template does — rootItems.reset() with the same normalised
 * JSON — and then triggers builder:change, because reset() alone never writes the hidden input.
 * The subject, button and thank-you text are set only where they still hold the defaults. A
 * "Choose a form type" link in the builder header reopens the picker (asking before it replaces
 * fields that are already there).
 */
(function ($, fwEvents, data) {
	'use strict';

	if (!data || !data.starters) { return; }

	var L = data.l10n || {};

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function isEmpty(builder) {
		var items = builder.rootItems;
		if (!items.length) { return true; }
		return items.length === 1 && items.at(0).get('type') === 'form-header-title';
	}

	/**
	 * Fill the settings that still hold their defaults (Settings → Options of the same modal). That
	 * tab is only built when it is first opened, so a setting whose field does not exist yet waits in
	 * `waiting` and is applied when the field appears (see the observer below).
	 */
	var waiting = [];

	function applySettings($builder, settings) {
		var $form = $builder.closest('form');
		var left = {};
		$.each(settings || {}, function (key, value) {
			if (!value) { return; }
			var $input = $form.find('[name$="[' + key + ']"]').first();
			if (!$input.length) { left[key] = value; return; }
			var defaults = (data.defaults && data.defaults[key]) || [''];
			if (defaults.indexOf($.trim($input.val())) === -1) { return; } // the person already wrote one
			$input.val(value).trigger('change');
		});
		waiting = waiting.filter(function (w) { return w.$builder[0] !== $builder[0]; });
		if (!$.isEmptyObject(left)) { waiting.push({ $builder: $builder, settings: left }); }
	}

	function applyWaiting() {
		waiting.slice().forEach(function (w) {
			if (!document.body.contains(w.$builder[0])) {
				waiting = waiting.filter(function (x) { return x !== w; }); // modal closed
				return;
			}
			applySettings(w.$builder, w.settings);
		});
	}

	function load(builder, $builder, items, settings) {
		builder.rootItems.reset(JSON.parse(JSON.stringify(items)));
		builder.rootItems.trigger('builder:change'); // reset() alone does not save the value
		applySettings($builder, settings);
	}

	function card(s) {
		return '<button type="button" class="fw-forms-picker__card" data-starter="' + esc(s.id) + '">' +
			'<span class="dashicons dashicons-' + esc(s.icon || 'forms') + '" aria-hidden="true"></span>' +
			'<span class="fw-forms-picker__card-title">' + esc(s.title) + '</span>' +
			'<span class="fw-forms-picker__card-desc">' + esc(s.desc) + '</span>' +
			'<span class="fw-forms-picker__card-meta">' + esc((L.fields || '%d fields').replace('%d', s.fields)) + '</span>' +
			'</button>';
	}

	/** The element's "Saved Form" select in the same modal (absent on a saved form's own editor). */
	function librarySelect($builder) {
		return $builder.closest('form').find('select[name$="[library_form]"]').first();
	}

	function render(withLibrary) {
		var byGroup = {};
		data.starters.forEach(function (s) { (byGroup[s.group] = byGroup[s.group] || []).push(s); });

		var html = '<div class="fw-forms-picker" role="region" aria-label="' + esc(L.heading) + '">' +
			'<div class="fw-forms-picker__head">' +
			'<h3 class="fw-forms-picker__title">' + esc(L.heading) + '</h3>' +
			'<p class="fw-forms-picker__intro">' + esc(L.intro) + '</p>' +
			'<button type="button" class="fw-forms-picker__close button-link" data-picker-close aria-label="' + esc(L.close) + '"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>' +
			'</div>';

		// Describe it (AI) — or how to switch it on.
		html += '<div class="fw-forms-picker__describe">';
		html += '<label class="fw-forms-picker__label" for="fw-forms-picker-describe">' + esc(L.describe) + '</label>';
		if (data.ai && data.ai.available) {
			html += '<div class="fw-forms-picker__describe-row">' +
				'<textarea id="fw-forms-picker-describe" rows="2" maxlength="1000" placeholder="' + esc(L.placeholder) + '"></textarea>' +
				'<button type="button" class="button button-primary" data-picker-ai>' + esc(L.build) + '</button>' +
				'</div><p class="fw-forms-picker__status" role="status"></p>';
		} else {
			html += '<p class="fw-forms-picker__note">' + esc(L.aiOff) + '</p>';
		}
		html += '</div>';

		html += '<div class="fw-forms-picker__grid">' +
			'<button type="button" class="fw-forms-picker__card fw-forms-picker__card--blank" data-starter="">' +
			'<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' +
			'<span class="fw-forms-picker__card-title">' + esc(L.blank) + '</span>' +
			'<span class="fw-forms-picker__card-desc">' + esc(L.blankDesc) + '</span></button>' +
			'</div>';

		var saved = (withLibrary && data.library && data.library.forms) || [];
		if (saved.length) {
			html += '<h4 class="fw-forms-picker__group">' + esc(L.saved) + '</h4><div class="fw-forms-picker__grid">' +
				saved.map(function (f) {
					return '<button type="button" class="fw-forms-picker__card fw-forms-picker__card--saved" data-library="' + esc(f.id) + '">' +
						'<span class="dashicons dashicons-saved" aria-hidden="true"></span>' +
						'<span class="fw-forms-picker__card-title">' + esc(f.title) + '</span>' +
						'<span class="fw-forms-picker__card-desc">' + esc(L.savedDesc) + '</span>' +
						'<span class="fw-forms-picker__card-meta">' + esc((L.fields || '%d fields').replace('%d', f.fields)) + '</span></button>';
				}).join('') + '</div>';
		}

		$.each(data.groups || {}, function (gid, label) {
			if (!byGroup[gid]) { return; }
			html += '<h4 class="fw-forms-picker__group">' + esc(label) + '</h4><div class="fw-forms-picker__grid">' +
				byGroup[gid].map(card).join('') + '</div>';
		});

		return $(html + '</div>');
	}

	function open(builder, $builder, confirmReplace) {
		$builder.find('.fw-forms-picker').remove();
		var $select = librarySelect($builder);
		var $picker = render($select.length > 0);
		$builder.find('.builder-root-items').first().before($picker);
		$builder.addClass('fw-forms-picker-open');

		var close = function () {
			$picker.remove();
			$builder.removeClass('fw-forms-picker-open');
		};

		$picker.on('click', '[data-picker-close]', close);

		$picker.on('click', '[data-library]', function () {
			$select.val($(this).attr('data-library')).trigger('change');
			close();
		});

		$picker.on('click', '[data-starter]', function () {
			var id = $(this).attr('data-starter');
			if (confirmReplace && !isEmpty(builder) && !window.confirm(L.confirm)) { return; }
			if (id) {
				var s = data.starters.filter(function (x) { return x.id === id; })[0];
				if (s) { load(builder, $builder, s.items, s.settings); }
			}
			close();
		});

		$picker.on('click', '[data-picker-ai]', function () {
			var $btn = $(this);
			var $status = $picker.find('.fw-forms-picker__status');
			var text = $.trim($picker.find('#fw-forms-picker-describe').val());
			if (text.length < 8) { $picker.find('#fw-forms-picker-describe').trigger('focus'); return; }
			if (confirmReplace && !isEmpty(builder) && !window.confirm(L.confirm)) { return; }
			$btn.prop('disabled', true).text(L.building);
			$status.text(L.building);
			$.post(data.ai.url, { action: data.ai.action, nonce: data.ai.nonce, description: text }, null, 'json')
				.done(function (r) {
					if (r && r.success && r.data && r.data.items) {
						load(builder, $builder, r.data.items, r.data.settings);
						close();
					} else {
						$status.text((r && r.data && r.data.message) || L.aiFailed);
					}
				})
				.fail(function () { $status.text(L.aiFailed); })
				.always(function () { $btn.prop('disabled', false).text(L.build); });
		});

		var $first = $picker.find('.fw-forms-picker__card').first();
		if ($first.length) { setTimeout(function () { $first.trigger('focus'); }, 50); }
	}

	function setup(builder) {
		var $builder = builder.$input.closest('.fw-option-type-builder');
		if (!$builder.length || $builder.data('fwFormsPicker')) { return; }
		$builder.data('fwFormsPicker', true);

		// "Choose a form type", always available in the builder header.
		var $tools = $builder.find('.fw-builder-header-tools').first();
		if ($tools.length && !$tools.find('.fw-forms-picker-reopen').length) {
			$tools.removeClass('fw-hidden').append(
				'<div class="fw-forms-picker-reopen fw-pull-right"><a href="#" onclick="return false;">' + esc(L.reopen) + '</a></div>'
			);
			$tools.find('.fw-forms-picker-reopen a').on('click', function () { open(builder, $builder, true); });
		}

		var $select = librarySelect($builder);
		if ($select.length) {
			// "Save to the library": these fields (and the settings already on screen) become a saved
			// form, and this element then shows it.
			if ($tools.length && !$tools.find('.fw-forms-library-save').length) {
				$tools.append('<div class="fw-forms-library-save fw-pull-right"><a href="#" onclick="return false;">' + esc(L.saveLib) + '</a></div>');
				$tools.find('.fw-forms-library-save a').on('click', function () { saveToLibrary(builder, $builder, $select); });
			}
			$select.on('change', function () { linkedNotice($builder, $select); });
			linkedNotice($builder, $select);
			if ($select.val()) { return; } // shows a saved form: nothing to pick
		}

		if (isEmpty(builder)) { open(builder, $builder, false); }
	}

	/** While a saved form is chosen, its fields replace the builder: say so, with a way back. */
	function linkedNotice($builder, $select) {
		$builder.find('.fw-forms-linked').remove();
		var id = $select.val();
		$builder.toggleClass('fw-forms-is-linked', !!id);
		if (!id) { return; }
		$builder.find('.fw-forms-picker').remove();
		$builder.removeClass('fw-forms-picker-open');
		var title = $.trim($select.find('option:selected').text());
		var $n = $('<div class="fw-forms-linked notice notice-info inline"><p>' + esc((L.linked || '').replace('%s', title)) + '</p><p>' +
			'<a class="button button-secondary" target="_blank" rel="noopener" href="' + esc((data.library && data.library.editUrl || '') + id) + '">' + esc(L.editLinked) + '</a> ' +
			'<button type="button" class="button-link" data-unlink>' + esc(L.unlink) + '</button></p></div>');
		$n.on('click', '[data-unlink]', function () { $select.val('').trigger('change'); });
		$builder.find('.builder-root-items').first().before($n);
	}

	function saveToLibrary(builder, $builder, $select) {
		var name = window.prompt(L.saveTitle, '');
		if (name === null) { return; }
		var $form = $builder.closest('form');
		var settings = {};
		$form.find('input[name], select[name], textarea[name]').each(function () {
			var m = /\[([a-z0-9_]+)\]$/.exec(this.name || '');
			if (!m) { return; }
			var key = m[1];
			if (/^(email_to|email_cc|email_bcc|subject_message|submit_button_text|success_message|failure_message|confirmation_type|confirmation_page|confirmation_url|action_[a-z0-9_]+)$/.test(key)) {
				if ((this.type === 'checkbox' || this.type === 'radio') && !this.checked) { return; }
				settings[key] = $(this).val();
			}
		});
		$.post(data.library.url, {
			action: 'fw_forms_library_save', nonce: data.library.nonce, title: name,
			fields: builder.$input.val() || '[]', settings: settings
		}, null, 'json').done(function (r) {
			if (r && r.success && r.data) {
				if (!$select.find('option[value="' + r.data.id + '"]').length) {
					$select.append($('<option>').val(r.data.id).text(r.data.title));
				}
				$select.val(String(r.data.id)).trigger('change');
				window.alert(L.savedOk);
			} else {
				window.alert((r && r.data && r.data.message) || L.saveFailed);
			}
		}).fail(function () { window.alert(L.saveFailed); });
	}

	// A form builder is created (and its items loaded) while an options modal builds its content
	// off-page — the page builder even prepares modals long before anyone opens one — so a DOM event
	// from it never reaches the document and there is no telling when it will be shown. Keep every
	// builder from its items-loaded event, and set each up the moment its element is on the page.
	var pending = [];

	function scan() {
		pending = pending.filter(function (builder) {
			if (!document.body.contains(builder.$input[0])) { return true; }
			setup(builder);
			return false;
		});
	}

	fwEvents.on('fw-builder:form-builder:items-loaded', function (builder) {
		if (!builder || !builder.$input) { return; }
		pending.push(builder);
		scan();
	});

	if (window.MutationObserver) {
		new MutationObserver(function () { if (pending.length) { scan(); } if (waiting.length) { applyWaiting(); } })
			.observe(document.body, { childList: true, subtree: true });
	}
})(jQuery, window.fwEvents, window.fwFormsPicker);
