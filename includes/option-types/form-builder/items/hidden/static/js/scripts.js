fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'hidden', {
		required: false,
		preview: function (o) {
			var src = fw.opg('source', o) || 'static', v = fw.opg('value', o) || '';
			var text = { static: v || '\u2014', url_param: '?' + (v || 'param') + '=\u2026', page_url: 'page URL', page_title: 'page title' }[src];
			return '<span class="fw-hidden-preview"><span class="dashicons dashicons-hidden"></span> ' + _.escape(text) + '</span>';
		}
	});
});
