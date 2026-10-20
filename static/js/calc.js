/**
 * Live prices and figures: Product line totals, the Amount field's "Other" box, and Calculated
 * fields. Display only — the server works every figure out again from the form's own definition
 * (FW_Forms_Order, FW_Forms_Formula), so nothing here is trusted.
 *
 * A field's number (same rules as the server): Product = its line total, Amount = the amount,
 * Calculated = its result, anything else = the last number in its value (ticked boxes add up).
 * Fields hidden by a "show only when" rule count as 0. A Coupon field counts as the amount off, once
 * "Apply" has checked the code with the server (the server checks it again on submit).
 */
(function () {
	'use strict';

	var M = window.fwFormsMoney || { currency: 'USD', symbol: '$', decimals: 2 };

	function round(n, d) { var f = Math.pow(10, d); return Math.round((n + (n < 0 ? -1e-9 : 1e-9)) * f) / f; }
	function money(n) {
		n = round(n, M.decimals);
		var dec = M.decimals && Math.abs(n - Math.round(n)) > 0.0001 ? 2 : 0;
		var s = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: dec, maximumFractionDigits: dec });
		return (n < 0 ? '-' : '') + M.symbol + s;
	}
	function numberIn(t) {
		t = String(t == null ? '' : t);
		if (t !== '' && !isNaN(t)) { return parseFloat(t); }
		var m = t.match(/-?\d[\d,]*(?:\.\d+)?/g);
		return m ? parseFloat(m[m.length - 1].replace(/,/g, '')) : 0;
	}
	function hiddenByRule(el) { return !!el.closest('[aria-hidden="true"]'); }
	function esc(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }

	/* ---------------------------------------------------------------- formula */

	function tokens(s) {
		var out = [], i = 0, m;
		while (i < s.length) {
			var c = s[i], rest = s.slice(i);
			if (/\s/.test(c)) { i++; }
			else if ((m = rest.match(/^(?:[0-9]*\.?[0-9]+|[0-9]+\.?)/))) { out.push(['n', parseFloat(m[0])]); i += m[0].length; }
			else if ((m = rest.match(/^\{([A-Za-z0-9_\-]+)\}/))) { out.push(['r', m[1]]); i += m[0].length; }
			else if ((m = rest.match(/^[a-z]+/i))) { out.push(['f', m[0].toLowerCase()]); i += m[0].length; }
			else if ('+-*/(),'.indexOf(c) !== -1) { out.push(['o', c]); i++; }
			else { return null; }
		}
		return out;
	}

	function evaluate(formula, ref) {
		var t = tokens(String(formula || '')), i = 0;
		if (!t || !t.length) { return 0; }
		function peek(v) { return t[i] && t[i][0] === 'o' && t[i][1] === v; }
		function expr() {
			var v = term();
			while (v !== null && (peek('+') || peek('-'))) { var op = t[i++][1], r = term(); if (r === null) { return null; } v = op === '+' ? v + r : v - r; }
			return v;
		}
		function term() {
			var v = factor();
			while (v !== null && (peek('*') || peek('/'))) { var op = t[i++][1], r = factor(); if (r === null) { return null; } v = op === '*' ? v * r : (r === 0 ? 0 : v / r); }
			return v;
		}
		function factor() {
			var k = t[i];
			if (!k) { return null; }
			if (k[0] === 'n') { i++; return k[1]; }
			if (k[0] === 'r') { i++; return +ref(k[1]) || 0; }
			if (k[0] === 'o' && k[1] === '-') { i++; var n = factor(); return n === null ? null : -n; }
			if (k[0] === 'o' && k[1] === '(') { i++; var v = expr(); if (v === null || !peek(')')) { return null; } i++; return v; }
			if (k[0] === 'f') {
				i++;
				if (!peek('(')) { return null; }
				i++;
				var a = [];
				do { var x = expr(); if (x === null) { return null; } a.push(x); } while (peek(',') && ++i);
				if (!peek(')')) { return null; }
				i++;
				switch (k[1]) {
					case 'round': return round(a[0], a.length > 1 ? Math.max(0, Math.min(6, a[1] | 0)) : 0);
					case 'ceil': return Math.ceil(a[0]);
					case 'floor': return Math.floor(a[0]);
					case 'abs': return Math.abs(a[0]);
					case 'min': return Math.min.apply(null, a);
					case 'max': return Math.max.apply(null, a);
				}
				return null;
			}
			return null;
		}
		var v = expr();
		return v === null || i !== t.length || !isFinite(v) ? 0 : v;
	}

	/* ---------------------------------------------------------------- fields */

	function productLine(el) {
		var mode = el.getAttribute('data-mode'), q = el.getAttribute('data-quantity'), unit, name = '';
		if (mode === 'choices') {
			var pick = el.querySelector('input[type="radio"]:checked') || (function () { var s = el.querySelector('select'); return s && s.value ? s.options[s.selectedIndex] : null; })();
			if (!pick) { return null; }
			unit = parseFloat(pick.getAttribute('data-price')) || 0;
			name = pick.value;
		} else {
			unit = parseFloat(el.getAttribute('data-price')) || 0;
		}
		var qty = 1;
		if (q === 'box') { var b = el.querySelector('input[type="number"]'); qty = b ? parseInt(b.value, 10) || 0 : 0; }
		else if (q === 'checkbox') { var c = el.querySelector('input[type="checkbox"]'); qty = c && c.checked ? 1 : 0; }
		if (qty < 1) { return null; }
		return { name: name, qty: qty, unit: round(unit, M.decimals), total: round(unit * qty, M.decimals) };
	}

	function amountOf(el) {
		var r = el.querySelector('input[type="radio"]:checked');
		var other = el.querySelector('.field-amount__other input');
		if (r && r.value !== 'other') { return round(parseFloat(r.value) || 0, M.decimals); }
		return other && other.value.trim() !== '' ? round(numberIn(other.value.replace(/[^0-9.,\-]/g, '')), M.decimals) : 0;
	}

	/* ---------------------------------------------------------------- coupon */

	// What the order adds up to before a coupon (the server's rule): products, amounts and the
	// Calculated fields marked "add it to the total".
	function subtotal(form, seen) {
		var sum = 0;
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-product], [data-fw-amount], [data-fw-calc][data-add="yes"]'), function (el) {
			if (hiddenByRule(el)) { return; }
			if (el.hasAttribute('data-fw-product')) { var l = productLine(el); sum += l ? l.total : 0; }
			else if (el.hasAttribute('data-fw-amount')) { sum += amountOf(el); }
			else { var s = Object.assign({ __coupon: true }, seen || {}); sum += calc(form, el, s); }
		});
		return sum;
	}

	function discountOf(form, box, seen) {
		var c = box.__fwCoupon;
		if (!c || hiddenByRule(box) || (seen && seen.__coupon)) { return 0; }
		var sub = subtotal(form, seen);
		var off = c.type === 'percent' ? sub * Math.min(100, c.amount) / 100 : c.amount;
		return round(Math.min(Math.max(0, sub), off), M.decimals);
	}

	function initCoupon(form, box) {
		var input = box.querySelector('input'), btn = box.querySelector('[data-fw-coupon-apply]'), st = box.querySelector('.field-coupon__status');
		var apply = function () {
			var code = input.value.trim();
			box.__fwCoupon = null;
			if (!code) { st.textContent = ''; box.classList.remove('is-ok', 'is-bad'); update(form); return; }
			btn.disabled = true;
			fetch(box.getAttribute('data-endpoint') + (box.getAttribute('data-endpoint').indexOf('?') === -1 ? '?' : '&') + 'code=' + encodeURIComponent(code), { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					box.classList.toggle('is-ok', !!j.ok);
					box.classList.toggle('is-bad', !j.ok);
					if (j.ok) { box.__fwCoupon = j; input.value = j.code; } else { st.textContent = j.message || ''; }
					update(form);
				})
				.catch(function () { st.textContent = ''; })
				.then(function () { btn.disabled = false; });
		};
		btn.addEventListener('click', apply);
		input.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
		input.addEventListener('input', function () { if (box.__fwCoupon) { box.__fwCoupon = null; box.classList.remove('is-ok'); st.textContent = ''; update(form); } });
		if (input.value.trim()) { apply(); }
	}

	function numberOf(form, sc, seen) {
		if (seen[sc]) { return 0; }
		var box = form.querySelector('[data-fw-field="' + sc + '"]');
		if (box) {
			if (hiddenByRule(box)) { return 0; }
			if (box.hasAttribute('data-fw-coupon')) { return discountOf(form, box, seen); }
			if (box.hasAttribute('data-fw-product')) { var l = productLine(box); return l ? l.total : 0; }
			if (box.hasAttribute('data-fw-amount')) { return amountOf(box); }
			if (box.hasAttribute('data-fw-calc')) { var s = Object.assign({}, seen); s[sc] = true; return calc(form, box, s); }
		}
		var sum = 0, any = false, re = new RegExp('^' + esc(sc) + '(\\[|$)');
		Array.prototype.forEach.call(form.elements, function (f) {
			if (!f.name || !re.test(f.name) || hiddenByRule(f)) { return; }
			if ((f.type === 'checkbox' || f.type === 'radio') && !f.checked) { return; }
			if (f.tagName === 'SELECT' && f.multiple) { Array.prototype.forEach.call(f.selectedOptions, function (o) { sum += numberIn(o.value); }); any = true; return; }
			sum += numberIn(f.value); any = true;
		});
		return any ? sum : 0;
	}

	function calc(form, box, seen) {
		var dec = box.getAttribute('data-format') === 'money' ? M.decimals : Math.max(0, Math.min(4, parseInt(box.getAttribute('data-decimals'), 10) || 0));
		seen = seen || {};
		seen[box.getAttribute('data-fw-field')] = true;
		return round(evaluate(box.getAttribute('data-formula'), function (ref) { return numberOf(form, ref, seen); }), dec);
	}

	function display(box, n) {
		var t = box.getAttribute('data-format') === 'number'
			? n.toLocaleString('en-US', { minimumFractionDigits: parseInt(box.getAttribute('data-decimals'), 10) || 0, maximumFractionDigits: parseInt(box.getAttribute('data-decimals'), 10) || 0 })
			: money(n);
		return ((box.getAttribute('data-prefix') || '') + t + ' ' + (box.getAttribute('data-suffix') || '')).trim();
	}

	function update(form) {
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-product]'), function (el) {
			var out = el.querySelector('[data-fw-line]');
			if (!out) { return; }
			var l = productLine(el), per = el.getAttribute('data-per') || '';
			out.textContent = l ? ((l.qty > 1 ? l.qty + ' × ' + money(l.unit) + ' = ' : '') + money(l.total) + ' ' + per).trim() : '';
		});
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-coupon]'), function (box) {
			if (!box.__fwCoupon) { return; }
			var d = discountOf(form, box, {});
			box.querySelector('.field-coupon__status').textContent = box.__fwCoupon.code + ': ' + box.__fwCoupon.label + (d > 0 ? ' (−' + money(d) + ')' : '');
		});
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-amount]'), function (el) {
			var other = el.querySelector('.field-amount__other'), r = el.querySelector('input[type="radio"]:checked');
			if (other && el.querySelector('input[type="radio"]')) { other.hidden = !(r && r.value === 'other'); }
		});
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-calc]'), function (box) {
			var v = box.querySelector('.field-calculation__value');
			if (v) { v.textContent = display(box, calc(form, box)); }
		});
	}

	function init(form) {
		if (form.__fwCalc || !form.querySelector('[data-fw-product], [data-fw-amount], [data-fw-calc], [data-fw-coupon]')) { return; }
		form.__fwCalc = true;
		Array.prototype.forEach.call(form.querySelectorAll('[data-fw-coupon]'), function (box) { initCoupon(form, box); });
		var go = function () { update(form); };
		form.addEventListener('input', go);
		form.addEventListener('change', go);
		form.addEventListener('fw-forms:sent', function () { setTimeout(go, 0); });
		// "Other" chosen: put the cursor in the box.
		form.addEventListener('change', function (e) {
			if (e.target.type === 'radio' && e.target.value === 'other') {
				var box = e.target.closest('[data-fw-amount]'), inp = box && box.querySelector('.field-amount__other input');
				if (inp) { setTimeout(function () { inp.focus(); }, 0); }
			}
		});
		go();
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('form[data-fw-ext-forms-type]'), init); }
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	window.fwFormsCalc = { evaluate: evaluate, boot: boot };
})();
