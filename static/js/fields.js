/**
 * Small front-end helpers for richer fields:
 *  - Range slider: keeps the printed value (with prefix / suffix) and aria-valuetext in step.
 *  - Ranking: turns the position dropdowns into a list with Move up / Move down buttons (keyboard
 *    friendly, no drag needed); the dropdowns stay in the DOM, hidden, and are what is submitted.
 * Both fields work without this script.
 */
(function () {
	'use strict';

	function i18n(key) {
		var d = window.fwFormsSubmit && window.fwFormsSubmit.i18n;
		var defaults = { up: 'Move %s up', down: 'Move %s down', moved: '%1$s is now number %2$d', left: '%d places left', oneLeft: '1 place left', full: 'full', orType: 'Or type your full name to sign' };
		return (d && d[key]) || defaults[key];
	}

	function initRange(input) {
		if (input.__fwRange) { return; }
		input.__fwRange = true;
		var out = input.parentNode.querySelector('.field-range__value');
		var update = function () {
			var t = (input.getAttribute('data-prefix') || '') + input.value + ' ' + (input.getAttribute('data-suffix') || '');
			t = t.trim();
			if (out) { out.textContent = t; }
			input.setAttribute('aria-valuetext', t);
		};
		input.addEventListener('input', update);
		update();
	}

	function initRanking(box) {
		if (box.__fwRanking) { return; }
		box.__fwRanking = true;
		var list = box.querySelector('.field-ranking__list');
		var live = document.createElement('p');
		live.className = 'screen-reader-text';
		live.setAttribute('aria-live', 'polite');
		box.appendChild(live);
		box.classList.add('field-ranking--js');

		function sync() {
			Array.prototype.forEach.call(list.children, function (li, i) {
				li.querySelector('select').value = String(i + 1);
				li.querySelector('.field-ranking__up').disabled = i === 0;
				li.querySelector('.field-ranking__down').disabled = i === list.children.length - 1;
			});
		}

		Array.prototype.forEach.call(list.children, function (li) {
			var text = li.querySelector('.field-ranking__text').textContent.trim();
			var btns = document.createElement('span');
			btns.className = 'field-ranking__moves';
			btns.innerHTML = '<button type="button" class="field-ranking__up" aria-label=""><span aria-hidden="true">&#8593;</span></button>' +
				'<button type="button" class="field-ranking__down" aria-label=""><span aria-hidden="true">&#8595;</span></button>';
			btns.querySelector('.field-ranking__up').setAttribute('aria-label', i18n('up').replace('%s', text));
			btns.querySelector('.field-ranking__down').setAttribute('aria-label', i18n('down').replace('%s', text));
			li.appendChild(btns);
			btns.addEventListener('click', function (e) {
				var b = e.target.closest('button');
				if (!b) { return; }
				if (b.classList.contains('field-ranking__up') && li.previousElementSibling) {
					list.insertBefore(li, li.previousElementSibling);
				} else if (b.classList.contains('field-ranking__down') && li.nextElementSibling) {
					list.insertBefore(li.nextElementSibling, li);
				}
				sync();
				b.disabled ? li.querySelector('button:not([disabled])').focus() : b.focus();
				var n = Array.prototype.indexOf.call(list.children, li) + 1;
				live.textContent = i18n('moved').replace('%1$s', text).replace('%2$d', n);
			});
		});
		sync();
	}

	/**
	 * Repeating group: Add another / Remove, within the minimum and maximum. Every copy is renumbered
	 * after a change (names <base>[n][…], ids, "Guest n"), so the server always receives 0…n-1.
	 */
	function initRepeater(box) {
		if (box.__fwRepeat) { return; }
		box.__fwRepeat = true;
		var rows = box.querySelector('.field-repeater__rows');
		var tpl = box.querySelector('template[data-fw-repeat-template]');
		var add = box.querySelector('[data-fw-repeat-add]');
		var live = box.querySelector('[data-fw-repeat-live]');
		var base = box.getAttribute('data-name');
		var min = parseInt(box.getAttribute('data-min'), 10) || 0;
		var max = parseInt(box.getAttribute('data-max'), 10) || 1;
		var titleBase = tpl ? (tpl.content.querySelector('[data-fw-repeat-title]') || { textContent: '' }).textContent.replace(/\s*\d+\s*$/, '') : '';
		var esc = base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

		function renumber() {
			var list = rows.querySelectorAll('[data-fw-repeat-row]');
			Array.prototype.forEach.call(list, function (row, i) {
				var t = row.querySelector('[data-fw-repeat-title]');
				if (t) { t.textContent = (titleBase + ' ' + (i + 1)).trim(); }
				Array.prototype.forEach.call(row.querySelectorAll('input'), function (inp, k) {
					inp.name = inp.name.replace(new RegExp('^' + esc + '\\[\\d+\\]'), base + '[' + i + ']');
					var id = box.id || (box.id = 'fw-rep-' + Math.random().toString(36).slice(2, 8));
					inp.id = id + '-' + i + '-' + k;
					var lab = inp.closest('label');
					if (lab) { lab.setAttribute('for', inp.id); }
				});
				var rm = row.querySelector('[data-fw-repeat-remove]');
				if (rm) { rm.hidden = list.length <= Math.max(1, min); }
			});
			add.hidden = list.length >= max;
		}

		add.addEventListener('click', function () {
			if (!tpl || rows.querySelectorAll('[data-fw-repeat-row]').length >= max) { return; }
			var row = tpl.content.firstElementChild.cloneNode(true);
			rows.appendChild(row);
			renumber();
			var first = row.querySelector('input');
			if (first) { first.focus(); }
			if (live) { live.textContent = (row.querySelector('[data-fw-repeat-title]') || { textContent: '' }).textContent; }
		});
		rows.addEventListener('click', function (e) {
			var rm = e.target.closest && e.target.closest('[data-fw-repeat-remove]');
			if (!rm) { return; }
			var row = rm.closest('[data-fw-repeat-row]');
			var prev = row.previousElementSibling;
			row.parentNode.removeChild(row);
			renumber();
			(prev ? prev.querySelector('input') : add).focus();
		});
		box.closest('form') && box.closest('form').addEventListener('fw-forms:sent', function () {
			Array.prototype.slice.call(rows.querySelectorAll('[data-fw-repeat-row]'), Math.max(1, min)).forEach(function (r) { r.parentNode.removeChild(r); });
			renumber();
		});
		renumber();
	}

	/**
	 * Answer piping: a label, legend, section text or help line that contains {field_name} shows that
	 * field's current answer, updated as the visitor types ("Thanks, {name_1}. Which date suits you?").
	 * Only text nodes are touched, and the answer is inserted as text, never as markup.
	 */
	function initPiping(form) {
		if (form.__fwPipe) { return; }
		form.__fwPipe = true;
		var names = {};
		Array.prototype.forEach.call(form.querySelectorAll('input[name], select[name], textarea[name]'), function (el) {
			names[el.name.replace(/\[.*$/, '')] = true;
		});
		var nodes = [];
		Array.prototype.forEach.call(form.querySelectorAll('label, legend, .field-section__title, .field-section__text, .field-info, .header.title'), function (host) {
			var w = document.createTreeWalker(host, NodeFilter.SHOW_TEXT, null);
			for (var n = w.nextNode(); n; n = w.nextNode()) {
				var m = n.nodeValue.match(/\{([a-z0-9_\-]+)\}/gi);
				if (m && m.some(function (t) { return names[t.slice(1, -1)]; })) {
					nodes.push({ node: n, tpl: n.nodeValue });
				}
			}
		});
		if (!nodes.length) { return; }

		function valueOf(code) {
			var parts = [];
			Array.prototype.forEach.call(form.querySelectorAll('[name="' + code + '"], [name^="' + code + '["]'), function (el) {
				if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) { return; }
				if (el.type === 'hidden') { return; }
				var v = String(el.value || '').trim();
				if (v !== '') { parts.push(v); }
			});
			return parts.join(' ');
		}

		function render() {
			nodes.forEach(function (p) {
				p.node.nodeValue = p.tpl.replace(/\{([a-z0-9_\-]+)\}/gi, function (all, code) {
					return names[code] ? valueOf(code) : all;
				}).replace(/\s+([,.!?])/g, '$1').replace(/\s{2,}/g, ' ');
			});
		}
		form.addEventListener('input', render);
		form.addEventListener('change', render);
		render();
	}

	/**
	 * Places per choice (Settings → Limits): "3 places left" / "full" next to the choice, and a full
	 * choice switched off. The server refuses a full choice anyway.
	 */
	function initSeats(wrap) {
		if (wrap.__fwSeats) { return; }
		wrap.__fwSeats = true;
		var seats = {};
		try { seats = JSON.parse(wrap.getAttribute('data-fw-seats') || '{}'); } catch (e) { return; }
		var note = function (s) {
			return s.left <= 0 ? i18n('full') : (s.left === 1 ? i18n('oneLeft') : i18n('left').replace('%d', s.left));
		};
		Array.prototype.forEach.call(wrap.querySelectorAll('input[type="radio"], input[type="checkbox"], option'), function (el) {
			var s = seats[el.value];
			if (!s) { return; }
			var text = ' (' + note(s) + ')';
			if (el.tagName === 'OPTION') {
				el.textContent += text;
			} else {
				var label = el.closest('label') || (el.id && wrap.querySelector('label[for="' + el.id + '"]'));
				if (label) {
					var span = document.createElement('span');
					span.className = 'fw-form-seats' + (s.left <= 0 ? ' is-full' : '');
					span.textContent = text;
					label.appendChild(span);
				}
			}
			if (s.left <= 0) { el.disabled = true; if (el.checked || el.selected) { el.checked = false; el.selected = false; } }
		});
	}

	/**
	 * Signature: draw with a mouse, finger or pen; the drawing goes into the hidden [image] input as a
	 * PNG. Without this script (or for anyone who cannot draw) the typed name signs instead.
	 */
	function initSignature(box) {
		if (box.__fwSig) { return; }
		box.__fwSig = true;
		var pad = box.querySelector('[data-fw-signature-pad]'), canvas = pad && pad.querySelector('canvas');
		var out = box.querySelector('[data-fw-signature-image]');
		if (!canvas || !canvas.getContext || !out) { return; }
		pad.hidden = false;
		var typedLabel = box.querySelector('[data-fw-signature-typed-label]');
		if (typedLabel) { typedLabel.textContent = i18n('orType'); }
		var ctx = canvas.getContext('2d'), drawing = false, drawn = false, last = null;
		var ratio = Math.max(1, window.devicePixelRatio || 1);
		function size() {
			var w = canvas.clientWidth || 600, h = canvas.clientHeight || 180;
			canvas.width = Math.round(w * ratio); canvas.height = Math.round(h * ratio);
			ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
			ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
			ctx.strokeStyle = getComputedStyle(box).color || '#111';
			drawn = false; out.value = '';
		}
		size();
		function pos(e) { var r = canvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
		canvas.addEventListener('pointerdown', function (e) { drawing = true; last = pos(e); canvas.setPointerCapture(e.pointerId); e.preventDefault(); });
		canvas.addEventListener('pointermove', function (e) {
			if (!drawing) { return; }
			var p = pos(e);
			ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
			last = p; drawn = true;
		});
		var end = function () {
			if (!drawing) { return; }
			drawing = false;
			if (drawn) { out.value = canvas.toDataURL('image/png'); out.dispatchEvent(new Event('change', { bubbles: true })); }
		};
		canvas.addEventListener('pointerup', end);
		canvas.addEventListener('pointercancel', end);
		box.querySelector('[data-fw-signature-clear]').addEventListener('click', function () { size(); });
		var form = box.closest('form');
		if (form) { form.addEventListener('fw-forms:sent', function () { size(); }); }
	}

	function boot(root) {
		root = root || document;
		Array.prototype.forEach.call(root.querySelectorAll('[data-fw-signature]'), initSignature);
		Array.prototype.forEach.call(root.querySelectorAll('[data-fw-seats]'), initSeats);
		Array.prototype.forEach.call(root.querySelectorAll('.wrap-forms input[type="range"]'), initRange);
		Array.prototype.forEach.call(root.querySelectorAll('[data-fw-ranking]'), initRanking);
		Array.prototype.forEach.call(root.querySelectorAll('[data-fw-repeater]'), initRepeater);
		Array.prototype.forEach.call(root.querySelectorAll('form[data-fw-ext-forms-type]'), initPiping);
		// Floating labels (a Form Styles option) tell an empty field by :placeholder-shown, which needs a
		// placeholder; a single space is invisible and harmless everywhere else.
		Array.prototype.forEach.call(root.querySelectorAll('.wrap-forms :is(.field-text, .field-textarea) > :is(input, textarea)'), function (el) {
			if (!el.getAttribute('placeholder')) { el.setAttribute('placeholder', ' '); }
		});
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { boot(); }); } else { boot(); }
	document.addEventListener('fw-forms:sent', function (e) {
		// A reset form puts slider values back; refresh their readouts.
		setTimeout(function () {
			Array.prototype.forEach.call((e.target || document).querySelectorAll('input[type="range"]'), function (i) { i.dispatchEvent(new Event('input')); });
		}, 0);
	});
})();
