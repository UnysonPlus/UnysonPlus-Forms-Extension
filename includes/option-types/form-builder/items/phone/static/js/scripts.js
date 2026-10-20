fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'phone', {
		required: true,
		preview: function (o) { return '<span class="fw-preview-input">+1 555 010 0100</span>'; }
	});
});
