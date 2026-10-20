fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'hcaptcha', {
		required: false,
		preview: function () {
			return '<span class="fw-captcha-preview"><span class="dashicons dashicons-shield"></span> hCaptcha</span>';
		}
	});
});
