fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'address', {
		required: true,
		preview: function (o) { return '<span class="fw-preview-input">Street, city, postcode, country</span>'; }
	});
});
