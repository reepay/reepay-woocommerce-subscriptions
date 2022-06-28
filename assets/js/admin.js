jQuery( function ( $ ) {
    let tab;

    const $body = $( 'body' );
    const $selectProductType = $( 'select#product-type' );

    $( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function () {
        init('#variable_product_options');
    } );

    $body.on( 'woocommerce-product-type-change', function () {
        let tab;

        if ( 'reepay_simple_subscriptions' === $selectProductType.val()){
            tab = '#general_product_data';
        }else if('reepay_variable_subscriptions' === $selectProductType.val()){
            tab = '#variable_product_options';
        }

        init(tab);
    } );

    $body.on( 'init_tooltips', function () {
        $( '.addon-shipping-new' ).closest('tr').addClass('hidden');
        $( '.addon-shipping-choose' ).on( 'change', function () {
            if($(this).val() === 'new'){
                $( '.addon-shipping-new' ).closest('tr').removeClass('hidden');
            }else{
                $( '.addon-shipping-new' ).closest('tr').addClass('hidden');
            }
        } );
    } );



    $body.on( 'woocommerce_variations_added', function () {
        init('#variable_product_options');
    } );

    $( '#variable_product_options' ).on( 'reload', function () {
        show_settings();
    } );

    let $coupon_type = $('#discount_type');
    let $coupon_amount_label = $('[for="coupon_amount"]')
    let default_coupon_amount_label = $coupon_amount_label.text()

    if ($coupon_type.length) {
        $coupon_type.on('change', function() {
            coupon_type_settings(this.value)
        })
        coupon_type_settings($coupon_type.val())
        let $apply_to_inputs = $('input[type=radio][name=_reepay_discount_apply_to]');
        let $apply_to_all_plans_input = $('input[name=_reepay_discount_all_plans]');
        let $use_existing_coupon_input = $('input[name=use_existing_coupon]');

        $apply_to_inputs.on('change', function() {
            apply_to_settings(this.value);
        })

        $apply_to_all_plans_input.on('change', function() {
            apply_to_plans(this.value);
        })
        apply_to_plans($apply_to_all_plans_input.closest(':checked').val())

        $use_existing_coupon_input.on('change', function() {
            show_existing_coupon_settings(this.value);
        })
        show_existing_coupon_settings($use_existing_coupon_input.closest(':checked').val())



        function coupon_type_settings(type) {
            if (type === 'reepay_percentage') {
                $coupon_amount_label.text(window.reepay.amountPercentageLabel)
            } else {
                $coupon_amount_label.text(default_coupon_amount_label)
            }

            if (type === 'reepay_percentage' || type === 'reepay_fixed_product') {
                $('.show_if_reepay').show();

                let input = $('.show_if_reepay').find('.reepay-required')
                if (input.length) {
                    input.attr('required', true)
                }
            } else {
                $('.show_if_reepay').hide();
                let input = $('.show_if_reepay').find('.reepay-required')
                if (input.length) {
                    input.attr('required', false)
                }
            }
        }

        function apply_to_settings(value) {
            console.log(value)
            if (value === 'custom') {
                $('.active_if_apply_to_custom input').attr('disabled', false)
            } else {
                $('.active_if_apply_to_custom input').attr('disabled', 'disabled')
            }
        }

        function apply_to_plans(value) {
            if (value === '0') {
                $('#_reepay_discount_eligible_plans').attr('disabled', false)
            } else {
                $('#_reepay_discount_eligible_plans').attr('disabled', true)
            }
        }

        function show_existing_coupon_settings(value) {
            if (value === 'true') {
                $('.show_if_use_existing_coupon').show()
                $('.hide_if_use_existing_coupon').hide()
            } else {
                $('.show_if_use_existing_coupon').hide()
                $('.hide_if_use_existing_coupon').show()
            }
        }

    }

    if (('.wp-list-table').length) {
        init_table();
        //setInterval(update_subscriptions_table, 3000)
    }

    function update_subscriptions_table() {
        return $.get(location.href)
            .then(html => {
               $('.wp-list-table').replaceWith($(html).find('.wp-list-table'))
                init_table();
            })
    }

    function init_table(){
        $('tr.sub-order').hide();
        $('a.show-sub-orders').on('click', function(e) {
            e.preventDefault();

            var $self = $(this),
                el = $('tr.' + $self.data('class') );

            if ( el.is(':hidden') ) {
                el.show();
                $self.text( $self.data('hide') );
            } else {
                el.hide();
                $self.text( $self.data('show') );
            }
        });

        $('button.toggle-sub-orders').on('click', function(e) {
            e.preventDefault();

            $('tr.sub-order').toggle();
        });
    }

    function show_settings(){
        if ( 'reepay_simple_subscriptions' === $selectProductType.val() || 'reepay_variable_subscriptions' === $selectProductType.val() ) {
            $( '.show_if_reepay_subscription' ).show();
        }else{
            $( '.show_if_reepay_subscription' ).hide();
        }

        const $variablePricing = $('#variable_product_options .variable_pricing');

        if ('reepay_variable_subscriptions' === $selectProductType.val() ||
            'variable' === $selectProductType.val()) {
            $variablePricing.children( ':first' ).hide();
            $variablePricing.children( ':nth-child(2)' ).hide();
            $('#variable_product_options .sale_price_dates_fields').hide();
            $( '.show_if_variable' ).show();
            $( '.general_tab' ).hide();
            $( '#general_product_data .show_if_reepay_subscription' ).hide();
        }else{
            $( '.show_if_variable' ).hide();
            $variablePricing.children( ':first' ).show();
            $variablePricing.children( ':nth-child(2)' ).show();
        }
    }

    function show_plan_settings(type){
        const subs_block = $('.reepay_subscription_pricing');
        subs_block.find('.type-fields').hide();
        subs_block.find('.fields-' + type).show();
    }

    function show_fee_settings(val){
        if(val.checked) {
            $('.fee-fields').show();
        }else{
            $('.fee-fields').hide();
        }
    }


    function choose_change_settings(val){
        if ( 'reepay_simple_subscriptions' === $selectProductType.val() || 'reepay_variable_subscriptions' === $selectProductType.val() ) {
            if(val === 'new'){
                $('.reepay_subscription_settings').show();
                $('.reepay_subscription_choose_exist').hide();
            }else{
                $('.reepay_subscription_settings').hide();
                $('.reepay_subscription_choose_exist').show();
            }
        }
    }

    function show_trial_settings(type){
        const subs_block = $('.reepay_subscription_trial');
        subs_block.find('.trial-fields').hide();
        subs_block.find('.fields-' + type).show();
    }

    function show_notice_settings(val){
        if(parseInt(val) > 0){
            $('.fields-notice_period').show();
        }else{
            $('.fields-notice_period').hide();
        }
    }

    function show_contract_settings(val){
        if(parseInt(val) > 0){
            $('.fields-contract_periods').show();
        }else{
            $('.fields-contract_periods').hide();
        }
    }

    function billing_cycles_settings(val){
        if(val === 'true'){
            $('.fields-billing_cycles').show();
        }else{
            $('.fields-billing_cycles').hide();
        }
    }

    function init(tab){
        let tab_ = $(tab);
        show_settings();
        if(tab_.find('#_subscription_fee').is(':checked')) {
            $('.fee-fields').show();
        }else{
            $('.fee-fields').hide();
        }
        show_trial_settings(tab_.find('#_subscription_trial').val());
        show_plan_settings(tab_.find('#_subscription_schedule_type').val());
        show_notice_settings( tab_.find('#_subscription_notice_period').val());
        show_contract_settings( tab_.find('#_subscription_contract_periods').val());
        billing_cycles_settings( tab_.find('input[type=radio][name=_reepay_subscription_billing_cycles]:checked').val());
        choose_change_settings(tab_.find('#_reepay_subscription_choose:checked').val());

        $( tab + ' #_subscription_schedule_type' ).on( 'change', function () {
            show_plan_settings($(this).val());
        } );

        $( tab + ' #_subscription_trial' ).on( 'change', function () {
            show_trial_settings($(this).val());
        } );

        $( tab + ' #_subscription_notice_period' ).on( 'change', function () {
            show_notice_settings($(this).val());
        } );

        $( tab + ' #_subscription_contract_periods' ).on( 'change', function () {
            show_contract_settings($(this).val());
        } );

        $( tab + ' #_subscription_fee').on( 'change', function () {
            show_fee_settings(this);
        });

        $(tab + ' #_reepay_subscription_choose').change(function() {
            choose_change_settings(this.value);
        });

        $(tab + ' input[type=radio][name=_reepay_subscription_billing_cycles]').change(function() {
            billing_cycles_settings(this.value);
        });
    }

    if ( 'reepay_simple_subscriptions' === $selectProductType.val()){
        tab = '#general_product_data';
    }else if('reepay_variable_subscriptions' === $selectProductType.val()){
        tab = '#variable_product_options';
    }

    init(tab);
});