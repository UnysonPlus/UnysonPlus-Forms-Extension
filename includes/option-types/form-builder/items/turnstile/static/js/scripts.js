fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'turnstile', {
		required: false,
		preview: function () {
			return '<span class="fw-captcha-preview"><span class="dashicons dashicons-shield"></span> Turnstile</span>';
		}
	});
});
