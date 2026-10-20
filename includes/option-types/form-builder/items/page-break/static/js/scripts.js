fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'page-break', {
		required: false,
		preview: function (o) { return '<span class="fw-preview-break">&#9473;&#9473; ' + _.escape(fw.opg('label', o) || 'Next step') + ' &#9473;&#9473;</span>'; }
	});
});
