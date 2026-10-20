fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'range', {
		preview: function (o) { return '<span class="fw-preview-range">&#9473;&#9473;&#9673;&#9473;&#9473;</span>'; }
	});
});
