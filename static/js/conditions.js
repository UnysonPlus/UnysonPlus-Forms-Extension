/**
 * Conditional visibility for front-end forms: toggles fields as the visitor
 * types, from the rules the form emitted as JSON. Convenience only — the
 * server applies the same rules on submit.
 */
(function () {
	'use strict';

	function norm(v) { return String(v == null ? '' : v).trim().toLowerCase(); }

	function valuesOf(form, name) {
		var out = [];
		var els = form.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]');
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked) { out.push(norm(el.value)); } }
			else if (el.tagName === 'SELECT' && el.multiple) { for (var j = 0; j < el.options.length; j++) { if (el.options[j].selected) { out.push(norm(el.options[j].value)); } } }
			else { var v = norm(el.value); if (v !== '') { out.push(v); } }
		}
		return out;
	}

	function matches(rule, list) {
		var has = list.length > 0;
		switch (rule.op) {
			case 'empty': return !has;
			case 'not_empty': return has;
			case 'is': return list.indexOf(rule.value) !== -1;
			case 'is_not': return list.indexOf(rule.value) === -1;
			case 'contains': return rule.value !== '' && list.some(function (v) { return v.indexOf(rule.value) !== -1; });
		}
		return true;
	}

	function wrapperOf(form, name) {
		var el = form.querySelector('[name="' + name + '"], [name="' + name + '[]"]');
		if (!el) { return null; }
		// Every field view renders inside the builder's width column.
		return el.closest('[class*="fw-col-"]') || el.parentElement;
	}

	function init(form) {
		var tag = form.querySelector('script.fw-form-conditions');
		if (!tag) { return; }
		var rules;
		try { rules = JSON.parse(tag.textContent); } catch (e) { return; }
		var names = Object.keys(rules);
		if (!names.length) { return; }

		function apply() {
			// A few passes so a chain (A shows B shows C) settles.
			for (var pass = 0; pass < 3; pass++) {
				names.forEach(function (name) {
					var rule = rules[name], wrap = wrapperOf(form, name);
					if (!wrap) { return; }
					var ctrlWrap = wrapperOf(form, rule.field);
					var ctrlHidden = !!(ctrlWrap && ctrlWrap.hidden);
					var show = ctrlHidden ? (rule.op === 'is_not' || rule.op === 'empty') : matches(rule, valuesOf(form, rule.field));
					wrap.hidden = !show;
					wrap.setAttribute('aria-hidden', show ? 'false' : 'true');
				});
			}
		}

		form.addEventListener('input', apply);
		form.addEventListener('change', apply);
		apply();
	}

	function boot() {
		var forms = document.querySelectorAll('form');
		for (var i = 0; i < forms.length; i++) { init(forms[i]); }
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
