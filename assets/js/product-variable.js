jQuery(function ($) {
    const variations_info = window.reepay_variable_product_info;

    if (!variations_info) {
        return;
    }

    $('.variations_form.cart')
        .on('found_variation', function (e, variation) {
            const $form = $(this);

            const variation_id = variation.variation_id;

            $form
                .parent()
                .find('.reepay_subscription_info_container')
                .replaceWith(variations_info[variation_id])
        })
        .on('reset_data', function (e) {
            const $form = $(this);

            $form
                .parent()
                .find('.reepay_subscription_info_container')
                .html('');
        });
})