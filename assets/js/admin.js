jQuery(function ($) {
    let tab;

    const $body = $('body');
    const $selectProductType = $('select#product-type');

    if ($('.woocommerce_product_addon').length > 0) {
        $('.save-addons-button').show();
    }

    $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
        init('#variable_product_options');
    });

    $body.on('woocommerce-product-type-change', function () {
        let tab;

        if ('reepay_simple_subscriptions' === $selectProductType.val()) {
            tab = '#general_product_data';
        } else if ('reepay_variable_subscriptions' === $selectProductType.val()) {
            tab = '#variable_product_options';
        }

        init(tab);
    });

    $body.on('init_tooltips', function () {
        $('.addon-shipping-new').closest('tr').addClass('hidden');
        $('.addon-shipping-choose').on('change', function () {
            init_shipping($(this));
        });

        init_shipping($('.addon-shipping-choose'))

        function init_shipping(addon_field) {
            if (addon_field.val() === 'new') {
                $('.addon-shipping-new').closest('tr').removeClass('hidden');
            } else {
                $('.addon-shipping-new').closest('tr').addClass('hidden');
            }
        }
    });


    $body.on('woocommerce_variations_added', function () {
        init('#variable_product_options');
    });

    $('#variable_product_options').on('reload', function () {
        show_settings();
    });

    let $coupon_type = $('#discount_type');
    let $coupon_amount_label = $('[for="_reepay_discount_amount"]')
    let default_coupon_amount_label = $coupon_amount_label.text()

    if ($coupon_type.length) {
        let $apply_to_inputs = $('input[type=radio][name=_reepay_discount_apply_to]');
        let $apply_to_all_plans_input = $('input[name=_reepay_discount_all_plans]');
        let $use_existing_coupon_input = $('input[name=use_existing_coupon]');
        let $use_existing_coupon_select = $('[name=_reepay_discount_use_existing_coupon_id]');
        let $reepay_discount_type = $('input[name=_reepay_discount_type]');
        let $reepay_discount_duration = $('input[name=_reepay_discount_duration]');
        let $container = $('.reepay_coupon_new');
        let $requiredFix = $('[name="_reepay_discount_apply_to_items[]"]')

        $requiredFix.on('change', function() {
            requiredApplyItems($container)
        })

        $use_existing_coupon_select.on('change', function () {
            show_existing_select($(document))
        })

        $coupon_type.on('change', function () {
            coupon_type_settings($(document))
        })

        $reepay_discount_duration.on('change', function () {
            duration_settings($container)
        })

        $reepay_discount_type.on('change', function () {
            coupon_type_percentage($container)
        })

        $apply_to_inputs.on('change', function () {
            apply_to_settings($container);
        })

        $apply_to_all_plans_input.on('change', function () {
            apply_to_plans($container);
        })

        $use_existing_coupon_input.on('change', function () {
            show_existing_coupon_settings($(document));
        })

        coupon_type_settings($(document))
        show_existing_coupon_settings($(document))

        requiredApplyItems($container)
        coupon_type_percentage($container)
        apply_to_settings($container)
        apply_to_plans($container)
        duration_settings($container)

        function show_existing_select($container) {
            let handle = $container.find('[name=_reepay_discount_use_existing_coupon_id]').val()
            load_coupon(handle, $('.reepay_coupon_settings_exist'))
        }

        function show_existing_coupon_settings($container) {
            let value = $container.find('input[name=use_existing_coupon]:checked').val();
            let $existing_container = $('.show_if_use_existing_coupon')
            if (value === 'true') {
                $existing_container.find("input").prop("disabled", false);
                $existing_container.find("select").prop("disabled", false);
                $('.show_if_use_existing_coupon').show()
                $('.hide_if_use_existing_coupon').hide()
            } else {
                $existing_container.find("input").prop("disabled", true);
                $existing_container.find("select").prop("disabled", true);
                $('.show_if_use_existing_coupon').hide()
                $('.hide_if_use_existing_coupon').show()
            }
            check_required()
        }

        function requiredApplyItems($container) {
            let $items = $container.find('[name="_reepay_discount_apply_to_items[]"]')
            if ($items.is(':checked')) {
                $items.removeAttr('required')
            } else {
                $items.attr('required', 'required')
            }
        }

        function duration_settings($container) {
            let input = $container.find('input[name=_reepay_discount_duration]:checked')
            let value = input.val()

            if (input.attr('disabled')) {
                $('.show_if_fixed_number').find('input, select').attr('disabled', 'disabled')
                $('.show_if_limited_time').find('input, select').attr('disabled', 'disabled')
            } else {
                $('.show_if_fixed_number').find('input, select').attr('disabled', false)
                $('.show_if_limited_time').find('input, select').attr('disabled', false)
            }

            if (value === 'fixed_number') {
                $('.show_if_fixed_number').show()
                $('.show_if_limited_time').hide()
            } else if (value === 'limited_time') {
                $('.show_if_fixed_number').hide()
                $('.show_if_limited_time').show()
            } else {
                $('.show_if_fixed_number').hide()
                $('.show_if_limited_time').hide()
            }


        }

        function coupon_type_settings($container) {
            let type = $container.find('#discount_type').val();
            if (type === 'reepay_type') {
                $('.show_if_reepay').show();
                $('.coupon_amount_field').hide();
                $('.expiry_date_field').hide();
                check_required()
            } else {
                $('.show_if_reepay').hide();
                $('.expiry_date_field').show();
                check_required()
            }
        }

        function check_required() {
            $('.reepay-required').attr('required', false)
            $('.reepay-required:visible').attr('required', true)
        }

        function coupon_type_percentage($container) {
            let value = $container.find('input[name=_reepay_discount_type]:checked').val();
            if (value === 'reepay_percentage') {
                $coupon_amount_label.text(window.reepay.amountPercentageLabel)
            } else {
                $coupon_amount_label.text(default_coupon_amount_label)
            }
        }

        function apply_to_settings($container) {
            let input = $container.find('input[type=radio][name=_reepay_discount_apply_to]:checked');
            let applyItems = $container.find('.active_if_apply_to_custom input');
            let value = input.val();

            if (input.attr('disabled')) {
                applyItems.attr('disabled', 'disabled')
            } else {
                applyItems.attr('disabled', false)
            }

            if (value === 'custom') {
                $('.active_if_apply_to_custom').show()
                applyItems.attr('required', 'required')
            } else {
                $('.active_if_apply_to_custom').hide()
                applyItems.removeAttr('required')
            }
        }

        function apply_to_plans($container) {
            let value = $container.find('input[name=_reepay_discount_all_plans]:checked').val();
            if (value === '0') {
                $('.show_if_selected_plans').show()
                $('.show_if_selected_plans select').attr('disabled', false)
            } else {
                $('.show_if_selected_plans').hide()
                $('.show_if_selected_plans select').attr('disabled', true)
            }
        }

    }

    if (('.wp-list-table').length) {
        init_table();
    }

    function init_table() {
        $('tr.sub-order').hide();
        $('a.show-sub-orders').on('click', function (e) {
            e.preventDefault();

            var $self = $(this),
                el = $('tr.' + $self.data('class'));

            if (el.is(':hidden')) {
                el.show();
                $self.text($self.data('hide'));
            } else {
                el.hide();
                $self.text($self.data('show'));
            }
        });

        $('button.toggle-sub-orders').on('click', function (e) {
            e.preventDefault();

            $('tr.sub-order').toggle();
        });
    }

    function show_settings() {
        if ('reepay_simple_subscriptions' === $selectProductType.val() || 'reepay_variable_subscriptions' === $selectProductType.val()) {
            $('.show_if_reepay_subscription').show();
        } else {
            $('.show_if_reepay_subscription').hide();
        }

        const $variablePricing = $('#variable_product_options .variable_pricing');

        if ('reepay_variable_subscriptions' === $selectProductType.val() ||
            'variable' === $selectProductType.val()) {
            $variablePricing.children(':first').hide();
            $variablePricing.children(':nth-child(2)').hide();
            $('#variable_product_options .sale_price_dates_fields').hide();
            $('.show_if_variable').show();
            $('.general_tab').hide();
            $('#general_product_data .show_if_reepay_subscription').hide();
        } else {
            $('.show_if_variable').hide();
            $variablePricing.children(':first').show();
            $variablePricing.children(':nth-child(2)').show();
        }
    }

    function show_plan_settings($container) {
        const type = $container.find('#_subscription_schedule_type').val()
        const subs_block = $container.find('.reepay_subscription_pricing');

        subs_block.find('.type-fields').hide();
        subs_block.find('.fields-' + type).show();
    }

    function show_fee_settings($container) {
        const val = $container.find('#_subscription_schedule_type').val()

        if (val) {
            $container.find('.fee-fields').show();
        } else {
            $container.find('.fee-fields').hide();
        }
    }


    function choose_change_settings($select) {
        const val = $select.val();
        const $container = $select.parents('.reepay_subscription_choose').parent();

        const $reepay_subscription_settings = $container.find('.reepay_subscription_settings');
        const $reepay_subscription_choose_exist = $container.find('.reepay_subscription_choose_exist');

        if ('reepay_simple_subscriptions' === $selectProductType.val() || 'reepay_variable_subscriptions' === $selectProductType.val()) {
            if (val === 'new') {
                $reepay_subscription_choose_exist.find("input").prop("disabled", true);
                $reepay_subscription_choose_exist.find("select").prop("disabled", true);
                $('input#reepay-publish').val('Create plan');
                $reepay_subscription_settings.show();
                $reepay_subscription_choose_exist.hide();
            } else {
                $reepay_subscription_choose_exist.find("input").prop("disabled", false);
                $reepay_subscription_choose_exist.find("select").prop("disabled", false);
                $('input#reepay-publish').val('Update plan');
                $reepay_subscription_settings.hide();
                $reepay_subscription_choose_exist.show();
            }
        }
    }

    function show_trial_settings($container) {
        const type = $container.find('#_subscription_trial').val()
        const subs_block = $container.find('.reepay_subscription_trial');

        subs_block.find('.trial-fields').hide();
        subs_block.find('.fields-' + type).show();
    }

    function show_notice_settings($container) {
        const val = $container.find('#_subscription_notice_period').val()

        if (parseInt(val) > 0) {
            $container.find('.fields-notice_period').show();
        } else {
            $container.find('.fields-notice_period').hide();
        }
    }

    function show_contract_settings($container) {
        const val = $container.find('#_subscription_notice_period').val()

        if (parseInt(val) > 0) {
            $container.find('.fields-contract_periods').show();
        } else {
            $container.find('.fields-contract_periods').hide();
        }
    }

    function billing_cycles_settings($container) {
        const val = $container.find('#_subscription_notice_period').val()

        if (val === 'true') {
            $container.find('.fields-billing_cycles').show();
        } else {
            $container.find('.fields-billing_cycles').hide();
        }
    }

    function load_plan(handle, $container) {
        if (!handle) {
            return;
        }

        $container.html('')

        $.ajax({
            url: window.reepay.rest_urls.get_plan + `&handle=${handle}`,
            method: 'GET',
            beforeSend: function (xhr) {

            },
            success: function (response_data) {
                if (!response_data.success) {
                    return;
                }

                $container.append($(`<div style="width: 100%">${response_data.html}</div>`))

                show_plan_settings($container);
                show_trial_settings($container);
                show_notice_settings($container);
                show_contract_settings($container);
                show_fee_settings($container);
            },
            error: function (request, status, error) {

            },
            complete: function () {

            },
        })
    }

    function load_coupon(handle, $container) {
        if (!handle) {
            return;
        }

        $container.html('')

        $.ajax({
            url: window.reepay.rest_urls.get_coupon + `?handle=${handle}`,
            method: 'GET',
            beforeSend: function (xhr) {

            },
            success: function (response_data) {
                if (!response_data.success) {
                    return;
                }

                $container.append($(`<div style="width: 100%">${response_data.html}</div>`))

                coupon_type_percentage($container)
                apply_to_settings($container)
                apply_to_plans($container)
                duration_settings($container)
            },
            error: function (request, status, error) {

            },
            complete: function () {

            },
        })
    }

    function init(tab) {
        const $tab = $(tab);

        show_settings();

        if ($tab.find('#_subscription_fee').is(':checked')) {
            $('.fee-fields').show();
        } else {
            $('.fee-fields').hide();
        }

        $(tab + ' #_subscription_schedule_type').on('change', function () {
            show_plan_settings($tab);
        }).trigger('change');

        $(tab + ' #_subscription_trial').on('change', function () {
            show_trial_settings($tab);
        }).trigger('change');

        $(tab + ' #_subscription_notice_period').on('change', function () {
            show_notice_settings($tab);
        }).trigger('change');

        $(tab + ' #_subscription_contract_periods').on('change', function () {
            show_contract_settings($tab);
        }).trigger('change');

        $(tab + ' #_subscription_fee').on('change', function () {
            show_fee_settings($tab);
        }).trigger('change');

        choose_change_settings($tab.find('[name="_reepay_subscription_choose"]:checked'));
        $(tab + ' #_reepay_subscription_choose').change(function () {
            choose_change_settings($(this));
        });

        $(tab + ' #_subscription_choose_exist').change(function () {
            const $this = $(this);
            const $container = $this.parents('.reepay_subscription_choose_exist');

            load_plan(
                $this.val(),
                $container.find('.reepay_subscription_settings_exist')
            );
        })

        billing_cycles_settings($tab.find('input[type=radio][name=_reepay_subscription_billing_cycles]:checked'));
        $(tab + ' input[type=radio][name=_reepay_subscription_billing_cycles]').change(function () {
            billing_cycles_settings($(this));
        });
    }

    if ('reepay_simple_subscriptions' === $selectProductType.val()) {
        tab = '#general_product_data';
    } else if ('reepay_variable_subscriptions' === $selectProductType.val()) {
        tab = '#variable_product_options';
    }

    init(tab);
});