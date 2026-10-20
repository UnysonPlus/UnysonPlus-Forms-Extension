fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'repeater', {
		preview: function (o) { var f = fw.opg('fields', o) || []; return '<span class="fw-preview-input">' + _.escape(_.map(f, function (x) { return x.label; }).join(' | ') || 'Fields') + ' &nbsp;+</span>'; }
	});
});
