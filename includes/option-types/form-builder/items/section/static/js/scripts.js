fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'section', {
		required: false,
		preview: function (o) { return '<span class="fw-preview-section">' + _.escape(fw.opg('text', o) || '') + '</span>'; }
	});
});
