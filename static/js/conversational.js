/**
 * "One question at a time" layout (Contact Form → Style → Layout).
 *
 * Runs before steps.js: for a form inside [data-fw-layout="conversational"] it gives every field its
 * own row and puts a step break between them, so the steps engine shows one question per screen with
 * a progress bar and Back / Next. Fields with nothing to fill in (hidden values, the honeypot) ride
 * along with the question before them; any Page Breaks the form already had are dropped (each question
 * is a step anyway). Enter moves to the next question; in a text box it still adds a line. Without the
 * script the form is an ordinary form.
 */
(function () {
	'use strict';

	function i18n(key) {
		var d = window.fwFormsSubmit && window.fwFormsSubmit.i18n;
		var defaults = { next: 'Next', back: 'Back' };
		return (d && d[key]) || defaults[key];
	}

	function answerable(col) {
		return !!col.querySelector('input:not([type="hidden"]):not([name^="fw_"]), select, textarea, .field-section, .field-captcha');
	}

	function init(form) {
		if (form.__fwConv) { return; }
		var host = form.closest('[data-fw-layout="conversational"]');
		var wrap = form.querySelector('.wrap-forms');
		if (!host || !wrap) { return; }
		form.__fwConv = true;
		form.setAttribute('data-fw-conversational', '1');

		var rowClass = (wrap.firstElementChild && wrap.firstElementChild.className) || 'fw-row';
		var keep = [];
		var questions = [];
		Array.prototype.slice.call(wrap.children).forEach(function (row) {
			if (row.querySelector('.header.title') && !questions.length) { keep.push(row); return; }
			Array.prototype.slice.call(row.children).forEach(function (col) {
				if (col.querySelector('.fw-form-step-break')) { return; }
				if (!answerable(col) && questions.length) { questions[questions.length - 1].push(col); return; }
				questions.push([col]);
			});
			row.parentNode.removeChild(row);
		});

		questions.forEach(function (cols, i) {
			if (i > 0) {
				var br = document.createElement('div');
				br.className = rowClass;
				br.innerHTML = '<div class="fw-form-step-break" data-title="" data-next="" data-back=""></div>';
				br.firstChild.setAttribute('data-next', i18n('next'));
				br.firstChild.setAttribute('data-back', i18n('back'));
				wrap.appendChild(br);
			}
			var row = document.createElement('div');
			row.className = rowClass + ' fw-form-question';
			cols.forEach(function (c) { row.appendChild(c); });
			wrap.appendChild(row);
		});
		keep.reverse().forEach(function (r) { wrap.insertBefore(r, wrap.firstChild); });

		form.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' || e.isComposing) { return; }
			var t = e.target;
			if (!t || t.tagName === 'TEXTAREA' || t.tagName === 'BUTTON' || t.type === 'submit') { return; }
			var next = form.querySelector('.fw-form-steps__next');
			if (next && !next.hidden) {
				e.preventDefault();
				next.click();
			}
		});
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-fw-layout="conversational"] form[data-fw-ext-forms-type]'), init);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
