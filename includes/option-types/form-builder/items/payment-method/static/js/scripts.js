fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'payment-method', {
		preview: function (o) {
			var m = (o && o.methods) || {}, out = [];
			var esc = function (s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
			['stripe', 'paypal', 'offline'].forEach(function (k) { if (m[k]) { out.push('<label style="margin-right:12px"><input type="radio" disabled> ' + esc(o[k + '_label'] || k) + '</label>'); } });
			return out.join('') || '<em>No method chosen</em>';
		}
	});
});
