jQuery(function ($) {
    const $body = $('body');
    const $selectProductType = $('select#product-type');
    
    function getReepaySelectedTab( selectedType = '' ) {
        const type = selectedType || $selectProductType.val();

        if ('reepay_simple_subscriptions' === type || 'reepay_variable_subscriptions' === type) {
            if ('reepay_simple_subscriptions' === type) {
                return '#general_product_data';
            } else if ('reepay_variable_subscriptions' === type) {
                return '#variable_product_options';
            }
        }
        
        return false;
    }

    if ($('.woocommerce_product_addon').length > 0) {
        $('.save-addons-button').show();
    }

    $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
        init('#variable_product_options');
    });

    $body.on('woocommerce-product-type-change', function (e, type) {
        $('.show_if_reepay_subscription').toggle(!!getReepaySelectedTab(type))

        if ('reepay_simple_subscriptions' === $selectProductType.val()) {
            init('#general_product_data')
        } else if ('reepay_variable_subscriptions' === $selectProductType.val()) {
            init('#variable_product_options')
        }
    });

    $('.show_if_reepay_subscription').toggle(!!getReepaySelectedTab())

    $body.on('init_tooltips', function () {
        $('.addon-shipping-new').closest('tr').addClass('hidden');
        $('.addon-shipping-choose').on('change', function () {
            init_shipping($(this));
        });

        init_shipping($('.addon-shipping-choose'))

        function fill_addon_price(addon_field) {
            var amount_field = $(".wc-modal-shipping-method-settings input[name*='_cost']")
            $('.addon-notice').remove();
            if (addon_field.val() !== 'new' && addon_field.val() !== '') {
                amount_field.prop("disabled", true);
                $.ajax({
                    url: window.reepay.rest_urls.get_addon + `?amount=true&handle=${addon_field.val()}`,
                    method: 'GET',
                    success: function (response_data) {
                        if (!response_data && !response_data.success) {
                            console.log(response_data);
                            return;
                        }
                        amount_field.val(response_data.amount);
                        if ($('.addon-notice').length <= 0) {
                            amount_field.after('<p class="addon-notice">Add-on amount can be changed <a target="_blank" href="https://app.reepay.com/#/rp/config/addons/' + addon_field.val() + '">here</a></p>');
                        }
                    },
                })
            } else {
                amount_field.prop("disabled", false);
            }
        }

        function init_shipping(addon_field) {
            if (addon_field.val() === 'new') {
                $('.addon-shipping-new').closest('tr').removeClass('hidden');
            } else {
                $('.addon-shipping-new').closest('tr').addClass('hidden');
            }

            fill_addon_price(addon_field);
        }
    });

    $body.on('click', '.js-refresh-addons-list', function (e) {
        e.preventDefault();

        const $selects = $('[name^="addon_choose_exist"]');

        $.each( $selects, function () {
            const $select = $(this);

            const $container = $select.parents('.addon_name')
                .block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });

            let url = `${window.reepay.rest_urls.get_addon}?&handle=${$select.val()}&get_list=1`;

            $.ajax({
                url,
                method: 'GET',
                beforeSend: function (xhr) {

                },
                success: function (response_data) {
                    if (!response_data.success) {
                        return;
                    }

                    $select.html(response_data.html);
                },
                error: function (request, status, error) {
                    alert('Request error. Try again')
                },
                complete: function () {
                    $container.unblock();
                },
            })
        } );
    })

    $('[name$="reepay_shipping_addon"]')
        .parents('fieldset')
        .append($('<button class="button button-primary button-large js-refresh-addons-shipping-list">Refresh list</button>'))
        .append($('<a class="button button-primary button-large" style="margin-left: 5px;" href="https://app.reepay.com/#/rp/config/addons/create" target="_blank">Create new addon</a>'))

    $body.on('click', '.js-refresh-addons-shipping-list', function (e) {
        e.preventDefault();

        const $selects = $('[name$="reepay_shipping_addon"]');

        $.each( $selects, function () {
            const $select = $(this);

            const $container = $select.parents('tr')
                .block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });

            let url = `${window.reepay.rest_urls.get_addon}?&handle=${$select.val()}&get_list=1`;

            $.ajax({
                url,
                method: 'GET',
                beforeSend: function (xhr) {

                },
                success: function (response_data) {
                    if (!response_data.success) {
                        return;
                    }

                    $select.html(response_data.html);
                },
                error: function (request, status, error) {
                    alert('Request error. Try again')
                },
                complete: function () {
                    $container.unblock();
                },
            })
        } );
    })

    $body.on('woocommerce_variations_added', function () {
        init();
    });

    $('#variable_product_options').on('reload', function () {
        show_settings();
    });

    let $coupon_type = $('#discount_type');
    let $coupon_amount_label = $('[for="_reepay_discount_amount"]')
    let default_coupon_amount_label = $coupon_amount_label.text()
    let new_discount_values = {};
    let exist_discount_values = {};


    if ($coupon_type.length) {
        let $use_existing_coupon_select = $('[name=_reepay_discount_use_existing_coupon_id]');
        let $use_existing_coupon_input = $('input[name=use_existing_coupon]');
        let $use_existing_discount_input = $('input[name=use_existing_discount]');
        let $use_existing_discount_select = $('[name=_reepay_discount_use_existing_discount_id]');
        let $apply_to_inputs = $('input[type=radio][name=_reepay_discount_apply_to]');
        let $apply_to_all_plans_input = $('input[name=_reepay_discount_all_plans]');
        let $reepay_discount_type = $('input[name=_reepay_discount_type]');
        let $reepay_discount_duration = $('input[name=_reepay_discount_duration]');
        let $container = $('.reepay_coupon_new');
        let $requiredFix = $('[name="_reepay_discount_apply_to_items[]"]')

        $requiredFix.on('change', function () {
            requiredApplyItems($container)
        })

        $use_existing_coupon_select.on('change', function () {
            show_existing_select($(document))
        })

        $use_existing_discount_input.on('change', function () {
            show_existing_discount_settings($container)
        })

        $use_existing_discount_select.on('change', function () {
            show_existing_discount($container)
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
        show_existing_discount_settings($(document))

        requiredApplyItems($container)
        coupon_type_percentage($container)
        apply_to_settings($container)
        apply_to_plans($container)
        duration_settings($container)

        function updateCouponContainer($container) {
            coupon_type_percentage($container)
            apply_to_settings($container)
            apply_to_plans($container)
            duration_settings($container)
            check_required()
        }

        function show_existing_select($container) {
            let handle = $container.find('[name=_reepay_discount_use_existing_coupon_id]').val()
            load_coupon(handle, $('.reepay_coupon_settings_exist'))
        }

        function show_existing_discount($container) {
            let handle = $container.find('[name=_reepay_discount_use_existing_discount_id]').val()
            load_discount(handle, $container)
        }

        function show_existing_coupon_settings($container) {
            let value = $container.find('input[name=use_existing_coupon]:checked').val();
            let $existing_container = $('.show_if_use_existing_coupon')
            $existing_container.find("input").prop("disabled", true);
            $existing_container.find("select").prop("disabled", true);
            if (value === 'true') {
                $existing_container.find('[name="_reepay_discount_name"').prop("disabled", false);
                $existing_container.find('[name="_reepay_discount_use_existing_coupon_id"').prop("disabled", false);
                $('.show_if_use_existing_coupon').show()
                $('.hide_if_use_existing_coupon').hide()
            } else {
                $existing_container.find('[name="_reepay_discount_name"').prop("disabled", true);
                $existing_container.find('[name="_reepay_discount_use_existing_coupon_id"').prop("disabled", true);
                $('.show_if_use_existing_coupon').hide()
                $('.hide_if_use_existing_coupon').show()
            }

            check_required()
            requiredApplyItems($container)
        }

        function show_existing_discount_settings($container) {
            let value = $container.find('input[name=use_existing_discount]:checked').val();
            if (value === 'true') {
                new_discount_values = discount_get_data($container)
                discount_set_data(exist_discount_values, $container, 'disabled')
                $('.show_if_use_existing_discount').show()
                $('.hide_if_use_existing_discount').hide()
            } else {
                exist_discount_values = discount_get_data($container)
                discount_set_data(new_discount_values, $container, false)
                $('.show_if_use_existing_discount').hide()
                $('.hide_if_use_existing_discount').show()
            }
            updateCouponContainer($container)
        }

        function requiredApplyItems($container) {
            let $items = $container.find('[name="_reepay_discount_apply_to_items[]"]')
            if ($items.is(':checked') || !$items.is(':visible')) {
                $items.removeAttr('required')
            } else {
                $items.attr('required', 'required')
            }
            check_required()
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
            check_required()
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
            $('.select2-container:visible').prev('.reepay-required').attr('required', true)
        }

        function coupon_type_percentage($container) {
            let value = $container.find('input[name=_reepay_discount_type]:checked').val();
            if (value === 'reepay_percentage') {
                $coupon_amount_label.text(window.reepay.amountPercentageLabel)
            } else {
                $coupon_amount_label.text(default_coupon_amount_label)
            }
            check_required()
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
            check_required()
        }

        function apply_to_plans($container) {
            let input = $container.find('input[name=_reepay_discount_all_plans]:checked');
            let value = input.val()

            if (value === '0') {
                $('.show_if_selected_plans').show()
                $('.show_if_selected_plans select').attr('disabled', false)
            } else {
                $('.show_if_selected_plans').hide()
                $('.show_if_selected_plans select').attr('disabled', true)
            }

            if (input.attr('disabled')) {
                $('.show_if_selected_plans select').attr('disabled', true)
            }

            check_required()
        }

    }

    function show_settings() {
        const $variablePricing = $('.woocommerce_variation .variable_pricing');

        if ('reepay_variable_subscriptions' === $selectProductType.val() ||
            'variable' === $selectProductType.val()) {
            jQuery.each($variablePricing, function () {
                const $this = $(this);
                $this.children(':first').hide();
                $this.children(':nth-child(2)').hide();
            })

            $('#variable_product_options .sale_price_dates_fields').hide();
            $('.show_if_variable').show();
            $('.general_tab').hide();
            $('#general_product_data .show_if_reepay_subscription').hide();
        } else {
            $('.show_if_variable').hide();

            jQuery.each($variablePricing, function () {
                const $this = $(this);
                $this.children(':first').show();
                $this.children(':nth-child(2)').show();
            })
        }
    }

    function load_plan($select, $container) {
        const handle = $select.val();

        if (!handle) {
            return;
        }

        $container
            .show()
            .html('<span class="spinner is-active" style="margin:0;float:unset"></span>');

        const $submitBtn = $container
            .parents('.variable_pricing')
            .find('#reepay_subscription_publish_btn')
            .hide();

        const dataPlan = JSON.parse($select.attr('data-plan') || '{}');
        const product_id = dataPlan.product_id || window.reepay.product.id

        let url = `${window.reepay.rest_urls.get_plan}?product_id=${product_id}&handle=${handle}`;

        if (dataPlan.loop !== undefined) {
            url += `&loop=${dataPlan.loop}`
        }

        $.ajax({
            url,
            method: 'GET',
            beforeSend: function (xhr) {

            },
            success: function (response_data) {
                if (!response_data.success) {
                    return;
                }

                $container.html(`<div style="width: 100%">${response_data.html}</div>`)
                $submitBtn.show();
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
        const $select = $('[name="_reepay_discount_use_existing_coupon_id"]')
            .parent()
            .block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

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
                $container.find('.wc-enhanced-select').select2()
                updateCouponContainer($container)
            },
            error: function (request, status, error) {

            },
            complete: function () {
                $select.unblock();
            },
        })
    }

    function load_discount(handle, $container) {
        if (!handle) {
            return;
        }

        $.get(window.reepay.rest_urls.get_discount + `?handle=${handle}`)
            .then(function (response_data) {
                if (!response_data.success) {
                    return;
                }

                let discount = response_data.discount

                discount_set_data(discount, $container)

                updateCouponContainer($container)
            })
    }

    function discount_get_data($container) {
        let discount = {};
        discount['_reepay_discount_amount'] = $container.find('[name="_reepay_discount_amount"]').val()
        discount['_reepay_discount_name'] = $container.find('[name="_reepay_discount_name"]').val()
        discount['_reepay_discount_type'] = $container.find('[name="_reepay_discount_type"]:checked').val() || 'reepay_fixed_product'
        discount['_reepay_discount_apply_to'] = $container.find('[name="_reepay_discount_apply_to"]:checked').val() || 'all'
        let apply_to_items = [];
        $container.find('[name="_reepay_discount_apply_to_items[]"]:checked').each(function (i) {
            apply_to_items[i] = $(this).val();
        });

        discount['_reepay_discount_apply_to_items'] = apply_to_items

        discount['_reepay_discount_duration'] = $container.find('[name="_reepay_discount_duration"]:checked').val() || 'forever'

        discount['_reepay_discount_fixed_count'] = $container.find('[name="_reepay_discount_fixed_count"]').val()
        discount['_reepay_discount_fixed_period'] = $container.find('[name="_reepay_discount_fixed_period"]').val()
        discount['_reepay_discount_fixed_period_unit'] = $container.find('[name="_reepay_discount_fixed_period_unit"]').val()
        return discount;
    }

    function discount_set_data(discount, $container, disable = true) {
        //use .val(['value']) for radio buttons
        discount['_reepay_discount_amount'] && $container.find('[name="_reepay_discount_amount"]').val(discount['_reepay_discount_amount']).attr('disabled', disable)
        // discount['_reepay_discount_name'] && $container.find('[name="_reepay_discount_name"]').val(discount['_reepay_discount_name'])
        discount['_reepay_discount_type'] && $container.find('[name="_reepay_discount_type"]').val([discount['_reepay_discount_type']]).attr('disabled', disable)
        discount['_reepay_discount_apply_to'] && $container.find('[name="_reepay_discount_apply_to"]').val([discount['_reepay_discount_apply_to']]).attr('disabled', disable)
        discount['_reepay_discount_apply_to_items'] && $container.find('[name="_reepay_discount_apply_to_items[]"]').val(discount['_reepay_discount_apply_to_items']).attr('disabled', disable)
        discount['_reepay_discount_duration'] && $container.find('[name="_reepay_discount_duration"]').val([discount['_reepay_discount_duration']]).attr('disabled', disable)
        discount['_reepay_discount_fixed_count'] && $container.find('[name="_reepay_discount_fixed_count"]').val(discount['_reepay_discount_fixed_count']).attr('disabled', disable)
        discount['_reepay_discount_fixed_period'] && $container.find('[name="_reepay_discount_fixed_period"]').val(discount['_reepay_discount_fixed_period']).attr('disabled', disable)
        discount['_reepay_discount_fixed_period_unit'] && $container.find('[name="_reepay_discount_fixed_period_unit"]').val(discount['_reepay_discount_fixed_period_unit']).attr('disabled', disable)
    }

    $body.on('click', '.js-refresh-plans-list', function (e) {
        e.preventDefault();

        const $selects = $('[name^="_reepay_subscription_handle"]');

        $.each( $selects, function () {
            const $select = $(this);

            const $container = $select.parents('.options_group')
                .block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });

            const dataPlan = JSON.parse($select.attr('data-plan') || '{}');
            const product_id = dataPlan.product_id || window.reepay.product.id

            let url = `${window.reepay.rest_urls.get_plan}?product_id=${product_id}&handle=${$select.val()}&get_list=1`;

            if (dataPlan.loop !== undefined) {
                url += `&loop=${dataPlan.loop}`
            }

            $.ajax({
                url,
                method: 'GET',
                beforeSend: function (xhr) {

                },
                success: function (response_data) {
                    if (!response_data.success) {
                        return;
                    }

                    $select.html($(response_data.html).html())
                },
                error: function (request, status, error) {
                    alert('Request error. Try again')
                },
                complete: function () {
                    $container.unblock();
                },
            })
        } );
    })

    $body.on('click', '.js-refresh-coupons-list', function (e) {
        e.preventDefault();

        const $selects = $('[name="_reepay_discount_use_existing_coupon_id"]');

        $.each( $selects, function () {
            const $select = $(this);

            const $container = $select.parents('.form-field')
                .block({
                    message: null,
                    overlayCSS: {
                        background: '#fff',
                        opacity: 0.6
                    }
                });

            let url = `${window.reepay.rest_urls.get_coupon}?get_list=1`;

            $.ajax({
                url,
                method: 'GET',
                beforeSend: function (xhr) {

                },
                success: function (response_data) {
                    if (!response_data.success) {
                        return;
                    }
                    console.log($select, response_data.html);
                    $select.html(response_data.html)
                },
                error: function (request, status, error) {
                    alert('Request error. Try again')
                },
                complete: function () {
                    $container.unblock();
                },
            })
        } );
    })

    function init() {
        const tab = getReepaySelectedTab();

        if (!tab) {
            return;
        }

        show_settings();

        $(tab + ' #_reepay_subscription_handle').change(function () {
            const $select = $(this);
            const $container = $select.parents('.reepay_subscription_container');

            load_plan(
                $select,
                $container.find('.reepay_subscription_settings_exist')
            );
        })
    }

    init();
});