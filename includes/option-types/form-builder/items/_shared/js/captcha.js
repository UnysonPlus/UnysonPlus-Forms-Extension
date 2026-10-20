/**
 * Human-check widgets (Turnstile, hCaptcha) for front-end forms.
 *
 * The provider scripts load with explicit rendering and call fwFormsCaptchaReady when ready;
 * every [data-fw-captcha] element of that provider is then drawn once, and its widget id is kept
 * in data-fw-captcha-widget so the background sender (forms/static/js/submit.js) can reset it
 * after a failed attempt (a token is single-use).
 */
(function () {
	'use strict';

	function theme(el) {
		var t = el.getAttribute('data-theme') || 'auto';
		if (t !== 'auto') { return t; }
		var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark'
			|| (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
		return dark ? 'dark' : 'light';
	}

	function renderAll() {
		var els = document.querySelectorAll('[data-fw-captcha]:not([data-fw-captcha-widget])');
		Array.prototype.forEach.call(els, function (el) {
			var p = el.getAttribute('data-fw-captcha');
			var api = window[p];
			if (!api || typeof api.render !== 'function') { return; }
			try {
				var id = api.render(el, { sitekey: el.getAttribute('data-sitekey'), theme: theme(el) });
				el.setAttribute('data-fw-captcha-widget', id == null ? '' : String(id));
			} catch (e) {}
		});
	}

	// Both providers call the same onload name; each call draws whatever is now drawable.
	window.fwFormsCaptchaReady = renderAll;
	if (document.readyState !== 'loading') { renderAll(); } else { document.addEventListener('DOMContentLoaded', renderAll); }
})();
