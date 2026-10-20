fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'calculation', {
		required: false,
		preview: function (o) {
			var f = String((o && o.formula) || '').replace(/[<>&"]/g, '');
			return '<code style="font-size:12px">= ' + (f || '…') + '</code>' + (o && o.visible === 'no' ? ' <em style="opacity:.7">(hidden)</em>' : '');
		}
	});
});
