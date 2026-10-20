fwEvents.on('fw-builder:form-builder:register-items', function (builder) {
	fwFormBuilderSimpleItem(builder, 'coupon', {
		required: true,
		preview: function (o) {
			var b = String((o && o.button) || 'Apply').replace(/[<>&"]/g, '');
			return '<input type="text" disabled placeholder="CODE" style="width:60%"> <button type="button" disabled>' + b + '</button>';
		}
	});
});
