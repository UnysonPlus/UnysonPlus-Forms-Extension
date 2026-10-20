/**
 * Save and continue later: a button next to "Send" on forms that allow it (data-fw-drafts on the
 * form wrapper). The answers so far are posted with an email address; the email's link
 * (?fw_draft=<token>) fills the form in again. Passwords, files and drawn signatures are not saved.
 */
(function () {
	'use strict';

	var C = window.fwFormsDrafts || {};
	function t(key, fallback) { return (C.i18n && C.i18n[key]) || fallback; }

	function formIdOf(form) {
		var el = form.querySelector('[name="fw_ext_forms_form_id"]');
		return el ? el.value : '';
	}

	function collect(form) {
		var out = {};
		Array.prototype.forEach.call(form.elements, function (el) {
			var n = el.name;
			if (!n || el.disabled || /^(fw_|_wp|fwf$)/.test(n) || /\[image\]$/.test(n)) { return; }
			if (['file', 'password', 'submit', 'button', 'reset'].indexOf(el.type) !== -1) { return; }
			if (/^_nonce/.test(n)) { return; }
			var list = /\[\]$/.test(n) || el.type === 'checkbox' || (el.tagName === 'SELECT' && el.multiple);
			if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { if (list && !(n in out)) { out[n] = []; } return; }
			var vals = el.tagName === 'SELECT' && el.multiple ? Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }) : [el.value];
			if (list) { out[n] = (out[n] || []).concat(vals); } else { out[n] = vals[0]; }
		});
		return out;
	}

	function fill(form, answers) {
		Object.keys(answers).forEach(function (name) {
			var value = answers[name];
			var find = function () { return form.querySelectorAll('[name="' + name.replace(/"/g, '\\"') + '"]'); };
			var els = find();
			// A repeating group's extra copies: press "Add" until the copy exists.
			var m = !els.length && name.match(/^([^\[]+)\[(\d+)\]/);
			if (m) {
				var box = form.querySelector('[data-fw-repeater][data-name="' + m[1] + '"]');
				var add = box && box.querySelector('[data-fw-repeat-add]');
				for (var i = 0; add && !add.hidden && !find().length && i < 50; i++) { add.click(); }
				els = find();
			}
			Array.prototype.forEach.call(els, function (el) {
				if (el.type === 'checkbox' || el.type === 'radio') {
					el.checked = Array.isArray(value) ? value.indexOf(el.value) !== -1 : el.value === value;
				} else if (el.tagName === 'SELECT' && el.multiple) {
					Array.prototype.forEach.call(el.options, function (o) { o.selected = Array.isArray(value) && value.indexOf(o.value) !== -1; });
				} else if (el.type !== 'file' && el.type !== 'hidden') {
					el.value = Array.isArray(value) ? value[0] || '' : value;
				} else if (el.type === 'hidden' && !/^(fw_|_wp)/.test(name)) {
					el.value = Array.isArray(value) ? value[0] || '' : value;
				}
				el.dispatchEvent(new Event('input', { bubbles: true }));
				el.dispatchEvent(new Event('change', { bubbles: true }));
			});
		});
	}

	function emailOf(form) {
		var e = form.querySelector('input[type="email"]');
		return e ? e.value : '';
	}

	function init(wrap) {
		var form = wrap.querySelector('form');
		if (!form || form.__fwDrafts) { return; }
		form.__fwDrafts = true;
		var submit = form.querySelector('[type="submit"]');
		var bar = document.createElement('div');
		bar.className = 'fw-form-drafts';
		bar.innerHTML = '<button type="button" class="fw-form-drafts__open">' + t('save', 'Save and continue later') + '</button>' +
			'<div class="fw-form-drafts__panel" hidden>' +
			'<label><span>' + t('email', 'Email me a link to finish later') + '</span><input type="email" autocomplete="email"></label>' +
			'<button type="button" class="fw-form-drafts__send">' + t('send', 'Send the link') + '</button>' +
			'</div><p class="fw-form-drafts__status" role="status" aria-live="polite"></p>';
		(submit && submit.parentNode ? submit.parentNode : form).appendChild(bar);
		var panel = bar.querySelector('.fw-form-drafts__panel'), input = panel.querySelector('input'), st = bar.querySelector('.fw-form-drafts__status');
		bar.querySelector('.fw-form-drafts__open').addEventListener('click', function () {
			panel.hidden = !panel.hidden;
			if (!panel.hidden) { if (!input.value) { input.value = emailOf(form); } input.focus(); }
		});
		var send = bar.querySelector('.fw-form-drafts__send');
		var go = function () {
			st.textContent = '';
			send.disabled = true;
			fetch(C.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ form_id: formIdOf(form), email: input.value.trim(), page_url: location.href, answers: collect(form) }) })
				.then(function (r) { return r.json(); })
				.then(function (j) { st.textContent = j.message || ''; bar.classList.toggle('is-ok', !!j.ok); if (j.ok) { panel.hidden = true; } })
				.catch(function () { st.textContent = t('failed', 'The answers could not be saved. Please try again.'); })
				.then(function () { send.disabled = false; });
		};
		send.addEventListener('click', go);
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); go(); } });

		// Coming back from the email's link.
		var token = new URLSearchParams(location.search).get('fw_draft');
		if (token && /^[A-Za-z0-9]{32}$/.test(token)) {
			fetch(C.endpoint + '/' + token, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j.ok) { st.textContent = j.message || ''; return; }
					if (j.form_id !== formIdOf(form)) { return; }
					fill(form, j.answers || {});
					var h = document.createElement('input');
					h.type = 'hidden'; h.name = 'fw_draft_token'; h.value = token;
					form.appendChild(h);
					st.textContent = t('welcome', 'Welcome back: your saved answers are filled in.');
					bar.classList.add('is-ok');
					try { history.replaceState(null, '', location.href.replace(/([?&])fw_draft=[^&#]*&?/, '$1').replace(/[?&](#|$)/, '$1')); } catch (e) {}
				})
				.catch(function () {});
		}
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-fw-drafts]'), init); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
