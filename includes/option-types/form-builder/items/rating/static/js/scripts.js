fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'rating', {
		preview: function (o) {
			var scale = fw.opg('scale', o) || 'stars5';
			if (scale === 'stars5') {
				return '<span class="fw-rating-preview fw-rating-preview--stars">\u2605\u2605\u2605\u2605\u2605</span>';
			}
			var min = scale === 'nps' ? 0 : 1, out = '';
			for (var i = min; i <= 10; i++) { out += '<span class="fw-rating-preview__pill">' + i + '</span>'; }
			return '<span class="fw-rating-preview fw-rating-preview--pills">' + out + '</span>';
		}
	});
});
