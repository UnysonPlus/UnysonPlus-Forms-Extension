fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'product', {
		preview: function (o) {
			var esc = function (s) { return String(s || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
			if (o && o.mode === 'choices') {
				return (o.choices || []).slice(0, 3).map(function (c) { return '<label style="display:block"><input type="radio" disabled> ' + esc(c.name) + ' — ' + esc(c.price) + '</label>'; }).join('');
			}
			return '<strong>' + esc(o && o.price) + '</strong>' + (o && o.quantity === 'box' ? ' &times; <input type="number" disabled value="1" style="width:60px">' : '');
		}
	});
});
