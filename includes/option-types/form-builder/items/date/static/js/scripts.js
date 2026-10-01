fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'date', {
		preview: function (o) {
			var mode = fw.opg('mode', o) || 'date';
			var type = mode === 'datetime' ? 'datetime-local' : mode;
			return '<input type="' + type + '" disabled>';
		}
	});
});
