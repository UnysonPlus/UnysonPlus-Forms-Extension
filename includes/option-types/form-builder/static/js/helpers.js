var fwFormBuilder = {};

/**
 * @param {String} [prefix]
 */
fwFormBuilder.uniqueShortcode = function(prefix) {
	prefix = prefix || 'shortcode_';

	var shortcode = prefix + fw.randomMD5().substring(0, 7);

	shortcode = shortcode.replace(/-/g, '_');

	return shortcode;
};
/**
 * Field Name (tag) in a field's settings: moved up under Label, filled from the label until the
 * user types their own, cleaned to letters/numbers/_ and kept unique within the form.
 */
(function ($) {
	var tagInput = function ($modal) { return $modal.find('input[name$="[tag]"]').first(); };

	fwFormBuilder.slugTag = function (text) {
		return String(text || '').toLowerCase()
			.normalize('NFD').replace(/[̀-ͯ]/g, '')
			.replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').substring(0, 40);
	};

	// Every tag already used in the builder on this page (from its saved JSON).
	fwFormBuilder.usedTags = function () {
		var used = {};
		var walk = function (node) {
			if (!node || typeof node !== 'object') { return; }
			if (node.options && node.options.tag) { used[node.options.tag] = (used[node.options.tag] || 0) + 1; }
			if (node.shortcode) { used[node.shortcode] = (used[node.shortcode] || 0) + 1; }
			$.each(node, function (k, v) { if (v && typeof v === 'object') { walk(v); } });
		};
		$('.fw-option-type-form-builder input[type="hidden"]').each(function () {
			try { walk(JSON.parse(this.value)); } catch (e) {}
		});
		return used;
	};

	var unique = function (tag, own) {
		var used = fwFormBuilder.usedTags(), out = tag, n = 2;
		if (own) { used[own] = (used[own] || 1) - 1; }
		while (out && used[out] > 0) { out = tag + '_' + (n++); }
		return out;
	};

	var prepare = function ($modal) {
		var $tag = tagInput($modal);
		if (!$tag.length || $tag.data('fwTagReady')) { return; }
		$tag.data('fwTagReady', 1).data('fwTagOwn', $tag.val());
		var $row = $tag.closest('.fw-backend-option');
		var $label = $modal.find('input[name$="[label]"]').first();
		if ($label.length) { $label.closest('.fw-backend-option').after($row); }
		// Empty (a new field, or one saved before Field Name existed): fill it from the label now and keep
		// following the label until the user types their own name.
		$tag.data('fwTagAuto', !$tag.val());
		if (!$tag.val() && $label.length && $label.val()) {
			$tag.val(unique(fwFormBuilder.slugTag($label.val()), ''));
		}
	};

	$(document).on('focusin', '.fw-modal input[name$="[label]"], .fw-modal input[name$="[tag]"]', function () {
		prepare($(this).closest('.fw-modal'));
	});
	$(document).on('fw:options:init', function (e, data) {
		if (data && data.$elements) { setTimeout(function () { data.$elements.each(function () { prepare($(this).closest('.fw-modal').length ? $(this).closest('.fw-modal') : $(this)); }); }, 0); }
	});
	$(document).on('input', '.fw-modal input[name$="[label]"]', function () {
		var $modal = $(this).closest('.fw-modal'), $tag = tagInput($modal);
		prepare($modal);
		if ($tag.length && $tag.data('fwTagAuto')) {
			$tag.val(unique(fwFormBuilder.slugTag(this.value), $tag.data('fwTagOwn')));
		}
	});
	$(document).on('input', '.fw-modal input[name$="[tag]"]', function () {
		$(this).data('fwTagAuto', false);
		var clean = this.value.toLowerCase().replace(/[{}]/g, '').replace(/[^a-z0-9_]/g, '_');
		if (clean !== this.value) { this.value = clean; }
	});
	$(document).on('change', '.fw-modal input[name$="[tag]"]', function () {
		var $t = $(this), before = $t.data('fwTagOwn');
		$t.val(unique(fwFormBuilder.slugTag($t.val()), before));
		// A renamed tag: update the form's own settings that used the old name.
		if (before && $t.val() && before !== $t.val()) {
			var from = '{' + before + '}', to = '{' + $t.val() + '}';
			$('.fw-modal input[type="text"], .fw-modal textarea').not($t).each(function () {
				if (this.value.indexOf(from) !== -1) { this.value = this.value.split(from).join(to); $(this).trigger('change'); }
			});
			$t.data('fwTagOwn', $t.val());
		}
	});
})(jQuery);

/**
 * Clickable tag list under every setting that takes answer tags (its description mentions them):
 * one chip per form field plus the general tags; a click inserts the tag where the cursor is.
 */
(function ($) {
	var GENERAL = ['all_fields', 'site_name', 'page_title', 'page_url', 'date'];
	var SKIP = /^(form-header-title|honeypot|recaptcha|turnstile|divider|html|step|section)/;

	fwFormBuilder.fieldTags = function () {
		var out = [], seen = {};
		var walk = function (node) {
			if (!node || typeof node !== 'object') { return; }
			if (node.shortcode && node.type && !SKIP.test(node.type)) {
				var tag = (node.options && node.options.tag) || node.shortcode;
				if (!seen[tag]) { seen[tag] = 1; out.push({ tag: tag, label: (node.options && node.options.label) || tag }); }
			}
			$.each(node, function (k, v) { if (v && typeof v === 'object') { walk(v); } });
		};
		$('.fw-option-type-form-builder input[type="hidden"]').each(function () {
			try { walk(JSON.parse(this.value)); } catch (e) {}
		});
		return out;
	};

	var chip = function (tag, title) {
		return $('<button type="button" class="button button-small fw-forms-tag-chip"></button>')
			.text('{' + tag + '}').attr('title', title || '').attr('data-tag', tag);
	};

	var paint = function ($scope) {
		if (!$('.fw-option-type-form-builder').length) { return; }
		$scope.find('.fw-backend-option-desc').each(function () {
			var $desc = $(this);
			if (!/\{[a-z_]+\}|answer tag/i.test($desc.text())) { return; }
			var $row = $desc.closest('.fw-backend-option');
			if ($row.find('.fw-option-type-form-builder').length) { return; }
			$row.find('.fw-forms-tag-list').remove();
			var $list = $('<div class="fw-forms-tag-list" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px"></div>');
			$.each(fwFormBuilder.fieldTags(), function (i, f) { $list.append(chip(f.tag, f.label)); });
			$.each(GENERAL, function (i, t) { $list.append(chip(t).css('opacity', .75)); });
			$desc.after($list);
		});
	};

	$(document).on('fw:options:init', function (e, data) {
		if (data && data.$elements) { setTimeout(function () { paint(data.$elements); }, 0); }
	});
	// Field names change while the form is edited; refresh the lists when a setting row is focused.
	$(document).on('focusin', '.fw-backend-option input, .fw-backend-option textarea', function () {
		var $row = $(this).closest('.fw-backend-option');
		if ($row.find('.fw-forms-tag-list').length) { paint($row.parent()); }
		$row.data('fwLastField', this);
	});
	$(document).on('mousedown', '.fw-forms-tag-chip', function (e) { e.preventDefault(); });
	$(document).on('click', '.fw-forms-tag-chip', function () {
		var $row = $(this).closest('.fw-backend-option');
		var el = $row.data('fwLastField') || $row.find('textarea, input[type="text"]').get(0);
		if (!el) { return; }
		var text = '{' + $(this).data('tag') + '}';
		var a = el.selectionStart != null ? el.selectionStart : el.value.length;
		var b = el.selectionEnd != null ? el.selectionEnd : a;
		el.value = el.value.slice(0, a) + text + el.value.slice(b);
		el.focus();
		el.selectionStart = el.selectionEnd = a + text.length;
		$(el).trigger('input').trigger('change');
	});
})(jQuery);
