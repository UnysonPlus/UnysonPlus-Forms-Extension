fwEvents.on('fw-builder:'+ 'form-builder' +':register-items', function(builder){
	var currentItemType = 'honeypot';

	var localized = fw.unysonShortcodesData()['contact_form_items'][currentItemType];

	var ItemView = builder.classes.ItemView.extend({
		template: _.template(
			'<div class="fw-form-builder-item-style-default fw-form-builder-item-type-'+ currentItemType +'">'+
				'<div class="fw-form-item-controls fw-row">'+
					'<div class="fw-form-item-controls-left fw-col-xs-7">'+
						'<div class="fw-form-item-width"></div>'+
					'</div>'+
					'<div class="fw-form-item-controls-right fw-col-xs-5 fw-text-right">'+
						'<div class="fw-form-item-control-buttons">'+
							'<a class="fw-form-item-control-edit dashicons dashicons-admin-generic" data-hover-tip="<%- edit %>" href="#" onclick="return false;" ></a>'+
							'<a class="fw-form-item-control-remove dashicons dashicons-no" data-hover-tip="<%- remove %>" href="#" onclick="return false;" ></a>'+
						'</div>'+
					'</div>'+
				'</div>'+
				'<div class="fw-form-item-preview">'+
					'<div class="fw-form-item-preview-input fw-form-honeypot-note">'+
						'<em><span class="dashicons dashicons-shield-alt"></span> <%- note %></em>'+
					'</div>'+
				'</div>'+
			'</div>'
		),
		events: {
			'click': 'onWrapperClick',
			'click .fw-form-item-control-edit': 'openEdit',
			'click .fw-form-item-control-remove': 'removeItem'
		},
		initialize: function() {
			this.defaultInitialize();

			this.modal = new fw.OptionsModal({
				title: localized.l10n.item_title,
				options: this.model.modalOptions,
				values: this.model.get('options'),
				size: 'medium'
			});

			this.listenTo(this.modal, 'change:values', function(modal, values) {
				this.model.set('options', values);
			});

			this.model.on('change:options', function() {
				this.modal.set('values', this.model.get('options'));
			}, this);

			this.widthChangerView = new FwBuilderComponents.ItemView.WidthChanger({
				model: this.model,
				view: this
			});
		},
		render: function () {
			this.defaultRender({
				edit: localized.l10n.edit,
				remove: localized.l10n.delete,
				note: localized.l10n.item_title
			});

			if (this.widthChangerView) {
				this.$('.fw-form-item-width').append(this.widthChangerView.$el);
				this.widthChangerView.delegateEvents();
			}
		},
		openEdit: function() {
			this.modal.open();
		},
		removeItem: function() {
			this.remove();
			this.model.collection.remove(this.model);
		},
		onWrapperClick: function(e) {
			if (!this.$el.parent().length) {
				return;
			}
			if (!fw.elementEventHasListenerInContainer(jQuery(e.srcElement), 'click', this.$el)) {
				this.openEdit();
			}
		}
	});

	var Item = builder.classes.Item.extend({
		defaults: function() {
			var defaults = _.clone(localized.defaults);
			defaults.shortcode = fwFormBuilder.uniqueShortcode(defaults.type +'_');
			return defaults;
		},
		initialize: function() {
			this.defaultInitialize();
			this.modalOptions = localized.options;
			this.view = new ItemView({
				id: 'fw-builder-item-'+ this.cid,
				model: this
			});
		}
	});

	builder.registerItemClass(Item);
});
