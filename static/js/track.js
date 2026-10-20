/**
 * Form analytics and unfinished entries.
 *
 *   analytics  a view once per page load when half the form is on screen, a start at the first answer,
 *              and — when the page is left with a started, unsent form — an abandon with the last field's
 *              name. Counts only: nothing typed is sent. (fwFormsTrack.on)
 *   unfinished on forms that keep unfinished entries (data-fw-partial), what was typed is sent when the
 *              page is hidden or left, and a few seconds after typing stops; a real submission deletes it.
 *              Passwords, files and drawn signatures are never sent.
 */
(function () {
	'use strict';

	var C = window.fwFormsTrack || {};

	function beacon(url, data) {
		var body = JSON.stringify(data);
		try {
			if (navigator.sendBeacon && navigator.sendBeacon(url, new Blob([body], { type: 'text/plain' }))) { return; }
		} catch (e) {}
		try { fetch(url, { method: 'POST', body: body, keepalive: true, credentials: 'same-origin', headers: { 'Content-Type': 'text/plain' } }); } catch (e) {}
	}

	function token() {
		var s = '', a = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		var r = (window.crypto && crypto.getRandomValues) ? crypto.getRandomValues(new Uint8Array(24)) : null;
		for (var i = 0; i < 24; i++) { s += a[(r ? r[i] : Math.floor(Math.random() * 256)) % a.length]; }
		return s;
	}

	function collect(form) {
		var out = {};
		Array.prototype.forEach.call(form.elements, function (el) {
			var n = el.name;
			if (!n || el.disabled || /^(fw_|_wp|_nonce|fwf$)/.test(n) || /\[image\]$/.test(n)) { return; }
			if (['file', 'password', 'submit', 'button', 'reset', 'hidden'].indexOf(el.type) !== -1) { return; }
			if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { return; }
			var list = /\[\]$/.test(n) || el.type === 'checkbox' || (el.tagName === 'SELECT' && el.multiple);
			var vals = el.tagName === 'SELECT' && el.multiple ? Array.prototype.map.call(el.selectedOptions, function (o) { return o.value; }) : [el.value];
			if (list) { out[n] = (out[n] || []).concat(vals); } else if (vals[0] !== '') { out[n] = vals[0]; }
		});
		return out;
	}

	function init(form) {
		if (form.__fwTrack) { return; }
		form.__fwTrack = true;
		var idEl = form.querySelector('[name="fw_ext_forms_form_id"]');
		var id = idEl ? idEl.value : '';
		if (!/^[A-Za-z0-9_\-]{1,64}$/.test(id)) { return; }
		var wrap = form.closest('[data-fw-partial]');
		var started = false, sent = false, last = '', tk = '', timer = 0;
		var on = !!C.on;

		if (on && 'IntersectionObserver' in window) {
			var io = new IntersectionObserver(function (es) {
				if (es.some(function (e) { return e.isIntersecting; })) { beacon(C.endpoint, { f: id, e: 'v' }); io.disconnect(); }
			}, { threshold: 0.5 });
			io.observe(form);
		}

		var savePartial = function () {
			if (!wrap || !started || sent) { return; }
			beacon(C.partial, { form_id: id, token: tk, page_url: location.href, answers: collect(form) });
		};

		var touched = function (e) {
			if (!e.isTrusted || sent) { return; }
			var t = e.target;
			if (!t || !t.name || /^(fw_|_wp)/.test(t.name)) { return; }
			last = t.name.replace(/\[.*$/, '');
			if (!started) {
				started = true;
				if (on) { beacon(C.endpoint, { f: id, e: 's' }); }
				if (wrap) {
					tk = token();
					var h = document.createElement('input');
					h.type = 'hidden'; h.name = 'fw_partial_token'; h.value = tk;
					form.appendChild(h);
				}
			}
			if (wrap) { clearTimeout(timer); timer = setTimeout(savePartial, 8000); }
		};
		form.addEventListener('input', touched);
		form.addEventListener('change', touched);
		form.addEventListener('focusin', function (e) { if (started && e.target && e.target.name) { last = e.target.name.replace(/\[.*$/, ''); } });
		form.addEventListener('submit', function () { sent = true; clearTimeout(timer); });
		form.addEventListener('fw-forms:errors', function () { sent = false; });
		form.addEventListener('fw-forms:sent', function () { sent = true; clearTimeout(timer); });

		var leaving = function () {
			if (!started || sent) { return; }
			savePartial();
			if (on) { beacon(C.endpoint, { f: id, e: 'a', l: last }); }
			started = false; // once per visit
		};
		window.addEventListener('pagehide', leaving);
		document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { savePartial(); } });
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('form[data-fw-ext-forms-type]'), init); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
