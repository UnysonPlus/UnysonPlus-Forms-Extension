function fw_forms_builder_item_recaptcha_init() {
    Array.prototype.forEach.call(document.querySelectorAll('.form-builder-item-recaptcha'), function (el) {
        grecaptcha.render(el, {
            sitekey : form_builder_item_recaptcha.site_key
        });
    });
}
