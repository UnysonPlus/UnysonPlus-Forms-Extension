fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'amount', {
		required: true,
		preview: function (o) {
			var list = String((o && o.suggested) || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 5);
			return list.map(function (s) { return '<span style="display:inline-block;border:1px solid #c3c4c7;border-radius:4px;padding:2px 10px;margin:0 4px 4px 0">' + s.replace(/[<>&"]/g, '') + '</span>'; }).join('') +
				(o && o.allow_other !== 'no' ? '<span style="display:inline-block;border:1px dashed #c3c4c7;border-radius:4px;padding:2px 10px">Other</span>' : '');
		}
	});
});
