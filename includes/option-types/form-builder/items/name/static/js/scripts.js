fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'name', {
		required: true,
		preview: function (o) { return '<span class="fw-preview-input">' + _.escape(fw.opg('format', o) === 'first_last' ? 'First name  |  Last name' : 'Full name') + '</span>'; }
	});
});
