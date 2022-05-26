jQuery( function ( $ ) {
    $( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', function () {
        init('#variable_product_options');
    } );
    $( 'body' ).on( 'woocommerce-product-type-change', function () {
        var tab;

        if ( 'reepay_simple_subscriptions' === $( 'select#product-type' ).val()){
            tab = '#general_product_data';
        }else if('reepay_variable_subscriptions' === $( 'select#product-type' ).val()){
            tab = '#variable_product_options';
        }

        init(tab);
    } );

    $( '#variable_product_options' ).on( 'reload', function () {
        show_settings();
    } );

    function show_settings(){
        if ( 'reepay_simple_subscriptions' === $( 'select#product-type' ).val() || 'reepay_variable_subscriptions' === $( 'select#product-type' ).val() ) {
            $( '.show_if_reepay_subscription' ).show();
        }else{
            $( '.show_if_reepay_subscription' ).hide();
        }

        if ( 'reepay_variable_subscriptions' === $( 'select#product-type' ).val() ) {
            $('#variable_product_options .variable_pricing').children( ':first' ).hide();
            $('#variable_product_options .variable_pricing').children( ':nth-child(2)' ).hide();
            $('#variable_product_options .sale_price_dates_fields').hide();
            $( '.show_if_variable' ).show();
            $( '.general_tab' ).hide();
            $( '#general_product_data .show_if_reepay_subscription' ).hide();
        }else{
            $( '.show_if_variable' ).hide();
            $('#variable_product_options .variable_pricing').children( ':first' ).show();
            $('#variable_product_options .variable_pricing').children( ':nth-child(2)' ).show();
        }
    }

    function show_plan_settings(type){
        var subs_block = $('.reepay_subscription_pricing');
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

    function show_trial_settings(type){
        var subs_block = $('.reepay_subscription_trial');
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
        if(val == 'true'){
            $('.fields-billing_cycles').show();
        }else{
            $('.fields-billing_cycles').hide();
        }
    }

    function init(tab){
        let tab_ = $(tab);
        show_settings();
        tab_.find('#_subscription_fee').trigger('change');
        show_trial_settings(tab_.find('#_subscription_trial').val());
        show_plan_settings(tab_.find('#_subscription_schedule_type').val());
        show_notice_settings( tab_.find('#_subscription_notice_period').val());
        show_contract_settings( tab_.find('#_subscription_contract_periods').val());
        billing_cycles_settings( tab_.find('input[type=radio][name=_reepay_subscription_billing_cycles]:checked').val());

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

        $( tab + ' #_subscription_fee').change(function() {
            show_fee_settings(this);
        });

        $(tab + ' input[type=radio][name=_reepay_subscription_billing_cycles]').change(function() {
            billing_cycles_settings(this.value);
        });
    }

    var tab;

    if ( 'reepay_simple_subscriptions' === $( 'select#product-type' ).val()){
        tab = '#general_product_data';
    }else if('reepay_variable_subscriptions' === $( 'select#product-type' ).val()){
        tab = '#variable_product_options';
    }

    init(tab);
});