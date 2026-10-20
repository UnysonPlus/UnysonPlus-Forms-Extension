fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'likert', {
		preview: function (o) { return '<span class="fw-preview-likert">&#9675; &#9675; &#9675; &#9675; &#9675;</span>'; }
	});
});
