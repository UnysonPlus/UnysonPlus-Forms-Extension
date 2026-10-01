fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'consent', {
		labelEditor: false,
		preview: function (o) {
			var text = (fw.opg('label', o) || '').replace(/<[^>]+>/g, '');
			var purpose = fw.opg('purpose', o) || 'terms';
			return '<label class="fw-consent-preview"><input type="checkbox" disabled> <span>' + _.escape(text) + '</span></label>' +
				(purpose === 'newsletter' ? ' <span class="fw-consent-preview__tag">newsletter opt-in</span>' : '');
		}
	});
});
