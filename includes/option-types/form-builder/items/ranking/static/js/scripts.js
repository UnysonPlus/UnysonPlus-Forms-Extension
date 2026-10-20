fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'ranking', {
		preview: function (o) { return '<span class="fw-preview-ranking">1. 2. 3.</span>'; }
	});
});
