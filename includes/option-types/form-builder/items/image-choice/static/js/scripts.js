fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'image-choice', {
		preview: function (o) { return '<span class="fw-preview-images">&#9638; &#9638; &#9638;</span>'; }
	});
});
