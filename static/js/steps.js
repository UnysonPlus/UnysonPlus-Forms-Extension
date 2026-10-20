/**
 * Multi-step forms: the Page Break field splits a form's rows into steps, with a progress bar and
 * Back / Next. Next checks the current step's fields with the browser's own validation first; the
 * server still validates everything on submit, and after a failed submit (background or reload) the
 * first step holding a problem is opened. Without this script the form shows whole, each break a
 * heading, so nothing depends on it.
 */
(function () {
	'use strict';

	function txt(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	function i18n(key) {
		var d = window.fwFormsSubmit && window.fwFormsSubmit.i18n;
		var defaults = { step: 'Step %1$d of %2$d', question: 'Question %1$d of %2$d', next: 'Next', back: 'Back' };
		return (d && d[key]) || defaults[key];
	}

	function controls(rows) {
		var out = [];
		rows.forEach(function (row) {
			Array.prototype.forEach.call(row.querySelectorAll('input, select, textarea'), function (el) {
				if (el.type === 'hidden' || el.disabled) { return; }
				// A field hidden by its "show only when…" rule is not asked for.
				var col = el.closest('[class*="fw-col-"]');
				if (col && (col.hidden || getComputedStyle(col).display === 'none')) { return; }
				out.push(el);
			});
		});
		return out;
	}

	function init(form) {
		if (form.__fwSteps) { return; }
		var wrap = form.querySelector('.wrap-forms');
		if (!wrap || !wrap.querySelector('.fw-form-step-break')) { return; }
		form.__fwSteps = true;

		var steps = [{ rows: [], title: '', next: '', back: '' }];
		Array.prototype.forEach.call(wrap.children, function (row) {
			var brk = row.querySelector('.fw-form-step-break');
			if (brk) {
				steps[steps.length - 1].next = brk.getAttribute('data-next') || '';
				steps.push({ rows: [], title: brk.getAttribute('data-title') || '', next: '', back: brk.getAttribute('data-back') || '' });
				row.hidden = true;
				return;
			}
			// The form's own heading stays above every step.
			if (row.querySelector('.header.title') && steps.length === 1 && !steps[0].rows.length) { return; }
			steps[steps.length - 1].rows.push(row);
		});
		steps = steps.filter(function (s, i) { return s.rows.length || i === 0; });
		if (steps.length < 2) { return; }

		var current = 0;
		var submit = form.querySelector('.sc-cf-submit') || (form.querySelector('[type="submit"]') || {}).parentNode;
		var submitBtn = form.querySelector('[type="submit"]');

		var progress = document.createElement('div');
		progress.className = 'fw-form-steps';
		progress.innerHTML = '<div class="fw-form-steps__bar" aria-hidden="true"><span></span></div>' +
			'<p class="fw-form-steps__label" tabindex="-1" aria-live="polite"></p>';
		var firstRow = steps[0].rows[0];
		wrap.insertBefore(progress, firstRow || wrap.firstChild);

		var nav = document.createElement('div');
		nav.className = 'fw-form-steps__nav';
		nav.innerHTML = '<button type="button" class="fw-form-steps__back btn"></button>' +
			'<button type="button" class="fw-form-steps__next ' + txt(submitBtn ? submitBtn.className : 'btn') + '"></button>';
		if (submit && submit.parentNode) { submit.parentNode.insertBefore(nav, submit); } else { form.appendChild(nav); }
		var back = nav.querySelector('.fw-form-steps__back');
		var next = nav.querySelector('.fw-form-steps__next');

		function show(i, focus) {
			current = Math.max(0, Math.min(steps.length - 1, i));
			steps.forEach(function (s, n) { s.rows.forEach(function (r) { r.hidden = n !== current; }); });
			var s = steps[current];
			var last = current === steps.length - 1;
			progress.querySelector('.fw-form-steps__bar span').style.width = Math.round(((current + 1) / steps.length) * 100) + '%';
			var label = i18n(form.hasAttribute('data-fw-conversational') ? 'question' : 'step').replace('%1$d', current + 1).replace('%2$d', steps.length);
			progress.querySelector('.fw-form-steps__label').innerHTML = txt(label) + (s.title ? ' <span class="fw-form-steps__title">' + txt(s.title) + '</span>' : '');
			back.hidden = current === 0;
			back.textContent = s.back || i18n('back');
			next.hidden = last;
			next.textContent = s.next || i18n('next');
			if (submit) { submit.hidden = !last; }
			form.setAttribute('data-step', String(current + 1));
			if (focus) {
				var target = progress.querySelector('.fw-form-steps__label');
				try { target.focus({ preventScroll: true }); } catch (e) { target.focus(); }
				if (progress.getBoundingClientRect().top < 0 && progress.scrollIntoView) { progress.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
			}
		}

		function stepOf(el) {
			for (var i = 0; i < steps.length; i++) {
				for (var r = 0; r < steps[i].rows.length; r++) {
					if (steps[i].rows[r].contains(el)) { return i; }
				}
			}
			return -1;
		}

		next.addEventListener('click', function () {
			var bad = controls(steps[current].rows).filter(function (el) { return el.checkValidity && !el.checkValidity(); })[0];
			if (bad) { bad.reportValidity(); return; }
			show(current + 1, true);
		});
		back.addEventListener('click', function () { show(current - 1, true); });

		// Server problems: open the first step that has one; a summary link opens its field's step.
		function toProblem() {
			var bad = form.querySelector('[aria-invalid="true"]');
			var i = bad ? stepOf(bad) : -1;
			if (i >= 0) { show(i, false); }
		}
		form.addEventListener('fw-forms:errors', toProblem);
		form.addEventListener('fw-forms:sent', function () { show(0, false); });
		form.addEventListener('click', function (e) {
			var a = e.target.closest && e.target.closest('.fw-form-error-summary a[href^="#"]');
			if (!a) { return; }
			var el = document.getElementById(a.getAttribute('href').slice(1));
			var i = el ? stepOf(el) : -1;
			if (i >= 0 && i !== current) { show(i, false); }
		});

		show(0, false);
		toProblem(); // errors already on the page after a reload
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('form[data-fw-ext-forms-type]'), init);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
