/**
 * Forms: send without reloading, and show problems so everyone can find and fix them.
 *
 * Sending: a form posts to its own page in the background (FW_Form answers an
 * X-Requested-With request with JSON instead of redirecting) and its result is shown
 * in place. Anything unexpected (no fetch, a network error, a non-JSON reply) falls
 * back to the ordinary submit, so a form always works without this script.
 *
 * Errors (WCAG 2.2 3.3.1, 3.3.3, 1.4.1): each message is written next to its field
 * and linked to it (aria-describedby), the field is marked aria-invalid, a form with
 * several problems gets a summary at the top, and focus moves to the summary or the
 * only invalid field. The same treatment is applied to the error list the server
 * prints after a reload, so both paths behave alike.
 */
(function () {
	'use strict';

	var SELECTOR = 'form[data-fw-ext-forms-type]';
	var uid = 0;

	function txt(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : String(s);
		return d.innerHTML;
	}

	/** Strip tags from a server message (they are plain sentences, sometimes with markup). */
	function plain(html) {
		var d = document.createElement('div');
		d.innerHTML = html == null ? '' : String(html);
		return (d.textContent || '').trim();
	}

	function fieldsByName(form, name) {
		var safe = String(name).replace(/(["\\])/g, '\\$1');
		var els = form.querySelectorAll('[name="' + safe + '"], [name="' + safe + '[]"], [name^="' + safe + '["]');
		return Array.prototype.filter.call(els, function (el) { return el.type !== 'hidden'; });
	}

	/** The field's own wrapper: the `div.field-*` (or rating fieldset) that holds label + control. */
	function containerOf(el) {
		// The nearest ancestor carrying a field-<type> class, skipping layout wrappers inside a field
		// (a radio or checkbox group's .field-column / .field-columns-N, the help line .field-info, and the
		// parts of a multi-box field — .field-name__part, .field-repeater__field — so the message belongs to the whole field).
		for (var n = el.parentElement; n && n.tagName !== 'FORM'; n = n.parentElement) {
			if (n.tagName === 'FIELDSET') { return n; }
			for (var i = 0; i < n.classList.length; i++) {
				var c = n.classList[i];
				if (c.indexOf('field-') === 0 && c.indexOf('__') === -1 && !/^field-(column|columns-\d+|info)$/.test(c)) { return n; }
			}
		}
		return el.parentElement;
	}

	function labelOf(container, el) {
		var l = container && container.querySelector(':scope > label, :scope > legend');
		if (!l && el && el.id) {
			l = document.querySelector('label[for="' + el.id + '"]');
		}
		return l ? l.textContent.replace(/\*/g, '').trim() : '';
	}

	function addDescribedBy(el, id) {
		var cur = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
		if (cur.indexOf(id) === -1) {
			cur.push(id);
			el.setAttribute('aria-describedby', cur.join(' '));
		}
	}

	function removeDescribedBy(el, id) {
		var cur = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function (x) { return x && x !== id; });
		if (cur.length) { el.setAttribute('aria-describedby', cur.join(' ')); } else { el.removeAttribute('aria-describedby'); }
	}

	function clearErrors(form) {
		var nodes = form.querySelectorAll('.form-error, .fw-form-error-summary');
		Array.prototype.forEach.call(nodes, function (n) {
			if (n.id) {
				Array.prototype.forEach.call(form.querySelectorAll('[aria-describedby~="' + n.id + '"]'), function (el) { removeDescribedBy(el, n.id); });
			}
			n.parentNode.removeChild(n);
		});
		Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid="true"]'), function (el) { el.removeAttribute('aria-invalid'); });
		Array.prototype.forEach.call(form.querySelectorAll('.has-error'), function (el) { el.classList.remove('has-error'); });
	}

	function clearResult(form) {
		var box = resultBoxOf(form, false);
		if (box) { box.parentNode.removeChild(box); }
	}

	/** The form asks for its messages under the Send button (Settings → Messages: below the form). */
	function messagesBelow(form) {
		var w = form.closest ? form.closest('[data-fw-messages]') : null;
		return !!(w && w.getAttribute('data-fw-messages') === 'below');
	}

	function resultBoxOf(form, create) {
		var below = messagesBelow(form);
		var near = below ? form.nextElementSibling : form.previousElementSibling;
		if (near && near.classList.contains('fw-form-result')) { return near; }
		if (!create) { return null; }
		var box = document.createElement('div');
		box.className = 'fw-form-result';
		box.setAttribute('tabindex', '-1');
		form.parentNode.insertBefore(box, below ? form.nextSibling : form);
		return box;
	}

	function showResult(form, kind, html) {
		var box = resultBoxOf(form, true);
		box.className = 'fw-form-result fw-form-result--' + kind;
		box.setAttribute('role', kind === 'error' ? 'alert' : 'status');
		box.innerHTML = html;
		return box;
	}

	/**
	 * @param {HTMLFormElement} form
	 * @param {Object} errors  name => message (HTML allowed, as the server sends it)
	 * @param {boolean} focus  move focus to the problem
	 */
	function showErrors(form, errors, focus) {
		clearErrors(form);
		var items = [];
		var loose = [];
		Object.keys(errors || {}).forEach(function (name) {
			var msg = errors[name];
			var els = fieldsByName(form, name);
			if (!els.length) {
				loose.push(msg);
				return;
			}
			var first = els[0];
			var container = containerOf(first);
			var id = 'fw-err-' + (++uid);
			var p = document.createElement('p');
			p.className = 'form-error';
			p.id = id;
			p.innerHTML = '<span class="form-error__icon" aria-hidden="true">!</span> ' + msg;
			container.appendChild(p);
			if (container.classList) { container.classList.add('has-error'); }
			els.forEach(function (el) {
				el.setAttribute('aria-invalid', 'true');
				addDescribedBy(el, id);
			});
			if (!first.id) { first.id = 'fw-fld-' + uid; }
			items.push({ el: first, label: labelOf(container, first), msg: plain(msg) });
		});

		var target = null;
		if (items.length + loose.length > 1 || loose.length) {
			var summary = document.createElement('div');
			summary.className = 'fw-form-error-summary';
			summary.id = 'fw-err-' + (++uid);
			summary.setAttribute('role', 'alert');
			summary.setAttribute('tabindex', '-1');
			var n = items.length + loose.length;
			var html = '<p class="fw-form-error-summary__title">' + (n === 1 ? txt(fwFormsI18n('one')) : txt(fwFormsI18n('many').replace('%d', n))) + '</p><ul>';
			items.forEach(function (it) {
				// Server messages usually name the field already ("The Email field is required").
				var named = it.label && it.msg.toLowerCase().indexOf(it.label.toLowerCase()) !== -1;
				html += '<li><a href="#' + txt(it.el.id) + '">' + (it.label && !named ? txt(it.label) + ': ' : '') + txt(it.msg) + '</a></li>';
			});
			loose.forEach(function (m) { html += '<li>' + m + '</li>'; });
			summary.innerHTML = html + '</ul>';
			if (messagesBelow(form)) { form.appendChild(summary); } else { form.insertBefore(summary, form.firstChild); }
			target = summary;
		} else if (items.length === 1) {
			// One problem: its message is announced through the field it describes.
			var only = form.querySelector('.form-error');
			if (only) { only.setAttribute('role', 'alert'); }
			target = items[0].el;
		}
		form.dispatchEvent(new CustomEvent('fw-forms:errors', { bubbles: true }));
		if (focus && target) {
			try { target.focus({ preventScroll: true }); } catch (e) { target.focus(); }
			(target.scrollIntoView && target.scrollIntoView({ block: 'center', behavior: 'smooth' }));
		}
	}

	/** Errors stay until the person changes that field. */
	function watchFixes(form) {
		form.addEventListener('input', fix, true);
		form.addEventListener('change', fix, true);
		function fix(e) {
			var el = e.target;
			if (!el || el.getAttribute('aria-invalid') !== 'true') { return; }
			var container = containerOf(el);
			Array.prototype.forEach.call(container.querySelectorAll('[aria-invalid="true"]'), function (x) {
				x.removeAttribute('aria-invalid');
			});
			var msg = container.querySelector('.form-error');
			if (msg) {
				Array.prototype.forEach.call(container.querySelectorAll('[aria-describedby~="' + msg.id + '"]'), function (x) { removeDescribedBy(x, msg.id); });
				msg.parentNode.removeChild(msg);
			}
			container.classList.remove('has-error');
		}
	}

	function resetWidgets(form) {
		var w = window;
		// A token is single-use, so every human check needs a fresh one after a failed attempt.
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-captcha-widget]'), function (el) {
			var api = w[el.getAttribute('data-fw-captcha')];
			if (api && typeof api.reset === 'function') {
				try { api.reset(el.getAttribute('data-fw-captcha-widget') || undefined); } catch (e) {}
			}
		});
		if (w.grecaptcha && typeof w.grecaptcha.reset === 'function' && form.querySelector('.form-builder-item-recaptcha, .g-recaptcha')) {
			try { w.grecaptcha.reset(); } catch (e) {}
		}
	}

	function flashHtml(flash, type) {
		var out = [];
		var group = flash && flash[type];
		if (group) {
			Object.keys(group).forEach(function (k) { out.push('<p>' + group[k] + '</p>'); });
		}
		return out.join('');
	}

	function setBusy(form, busy) {
		form.classList.toggle('fw-form--sending', busy);
		form.setAttribute('aria-busy', busy ? 'true' : 'false');
		Array.prototype.forEach.call(form.querySelectorAll('[type="submit"]'), function (b) { b.disabled = busy; });
	}

	function fallback(form) {
		form.setAttribute('data-fw-no-ajax', '1');
		HTMLFormElement.prototype.submit.call(form);
	}

	function samePage(url) {
		try {
			var a = new URL(url, window.location.href);
			var b = new URL(window.location.href);
			a.hash = ''; b.hash = '';
			return a.href === b.href;
		} catch (e) {
			return true;
		}
	}

	function send(form, submitter) {
		var body = new FormData(form);
		if (submitter && submitter.name) { body.append(submitter.name, submitter.value); }
		clearErrors(form);
		clearResult(form);
		setBusy(form, true);

		fetch(form.getAttribute('action') || window.location.href, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
		}).then(function (r) {
			return r.json();
		}).then(function (r) {
			setBusy(form, false);
			if (!r || typeof r !== 'object' || !('success' in r)) {
				throw new Error('unexpected reply');
			}
			var data = r.data || {};
			var flash = data.flash_messages || {};
			if (!r.success) {
				resetWidgets(form);
				var errHtml = flashHtml(flash, 'error');
				if (errHtml) { showResult(form, 'error', errHtml); }
				showErrors(form, data.errors || {}, true);
				return;
			}
			var failed = flashHtml(flash, 'error');
			if (failed) {
				resetWidgets(form);
				showResult(form, 'error', failed).focus();
				return;
			}
			var to = data.save_data && data.save_data.redirect;
			if (to && !samePage(to)) {
				window.location.assign(to);
				return;
			}
			var ok = flashHtml(flash, 'success') || flashHtml(flash, 'info') || '<p>' + txt(fwFormsI18n('sent')) + '</p>';
			var box = showResult(form, 'success', ok);
			form.dispatchEvent(new CustomEvent('fw-forms:sent', { bubbles: true }));
			var wrap = form.closest('[data-fw-confirm]');
			if (wrap && wrap.hasAttribute('data-fw-editing')) {
				// Editing a sent entry: keep the saved answers on screen (a reset would bring back the old ones).
			} else if (wrap && wrap.getAttribute('data-fw-confirm') === 'hide') {
				form.hidden = true;
			} else {
				form.reset();
				resetWidgets(form);
				// Let conditional fields re-evaluate against the emptied form.
				form.dispatchEvent(new Event('change', { bubbles: true }));
			}
			try { box.focus({ preventScroll: true }); } catch (e) { box.focus(); }
			(box.scrollIntoView && box.scrollIntoView({ block: 'center', behavior: 'smooth' }));
		}).catch(function () {
			setBusy(form, false);
			fallback(form);
		});
	}

	function fwFormsI18n(key) {
		var d = window.fwFormsSubmit && window.fwFormsSubmit.i18n;
		var defaults = { one: 'There is a problem with this form:', many: 'There are %d problems with this form:', sent: 'Thank you, your message has been sent.' };
		return (d && d[key]) || defaults[key];
	}

	/**
	 * The error list the server prints after a reload (inside the form that was sent), turned into the
	 * same accessible pattern. A list printed just before the form (older form types) is used too.
	 */
	function adoptServerErrors(form) {
		var list = null;
		var prev = form.previousElementSibling;
		while (prev && !list) {
			if (prev.classList && prev.classList.contains('fw-form-errors')) { list = prev; }
			prev = prev && prev.previousElementSibling;
		}
		list = list || form.querySelector('.fw-form-errors');
		if (!list) { return; }
		var errors = {};
		Array.prototype.forEach.call(list.querySelectorAll('li'), function (li, i) {
			errors[li.getAttribute('data-input-name') || ('_' + i)] = li.innerHTML;
		});
		list.parentNode.removeChild(list);
		showErrors(form, errors, true);
	}

	function init(form) {
		if (form.__fwFormsSubmit) { return; }
		form.__fwFormsSubmit = true;
		watchFixes(form);
		adoptServerErrors(form);
		if (!window.fetch || !window.FormData || form.hasAttribute('data-fw-no-ajax-default')) { return; }
		form.addEventListener('submit', function (e) {
			if (form.getAttribute('data-fw-no-ajax') === '1') { return; }
			// No checkValidity() gate: an invalid form without `novalidate` never fires submit (the browser
			// shows its own messages), and one WITH it asks for the server's checks, which come back below.
			e.preventDefault();
			if (form.getAttribute('aria-busy') === 'true') { return; }
			send(form, e.submitter || null);
		});
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll(SELECTOR), init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
	window.fwFormsSubmitInit = boot;
})();
