jQuery( function ( $ ) {
    $( 'body' ).on( 'woocommerce-product-type-change', function () {
        show_settings()
    } );

    $( '#_subscription_schedule_type' ).on( 'change', function () {
        show_plan_settings($(this).val());
    } );

    $( '#_subscription_trial' ).on( 'change', function () {
        show_trial_settings($(this).val());
    } );

    $( '#_subscription_notice_period' ).on( 'change', function () {
        show_notice_settings($(this).val());
    } );

    $( '#_subscription_contract_periods' ).on( 'change', function () {
        show_contract_settings($(this).val());
    } );

    $('#_subscription_fee').change(function() {
        show_fee_settings(this);
    });

    $('input[type=radio][name=_reepay_subscription_billing_cycles]').change(function() {
        billing_cycles_settings(this.value);
    });

    let $coupon_type = $('#discount_type');

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
        apply_to_settings($apply_to_inputs.closest(':checked').val())

        $apply_to_all_plans_input.on('change', function() {
            apply_to_plans(this.value);
        })
        apply_to_plans($apply_to_all_plans_input.closest(':checked').val())

        $use_existing_coupon_input.on('change', function() {
            show_existing_coupon_settings(this.value);
        })
        show_existing_coupon_settings($use_existing_coupon_input.closest(':checked').val())



        function coupon_type_settings(type) {
            if (type === 'reepay_percentage' || type === 'reepay_fixed_product') {
                $('.show_if_reepay').show();
            } else {
                $('.show_if_reepay').hide();
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
                $('.show_if_selected_plans').show()
            } else {
                $('.show_if_selected_plans').hide()
            }
        }

        function show_existing_coupon_settings(value) {
            console.log(value)
            if (value === 'true') {
                $('.show_if_use_existing_coupon').show()
            } else {
                $('.show_if_use_existing_coupon').hide()
            }
        }
    }

    function show_settings(){
        if ( 'reepay_simple_subscriptions' === $( 'select#product-type' ).val() ) {
            $( '.show_if_reepay_subscription' ).show();
        }else{
            $( '.show_if_reepay_subscription' ).hide();
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

    function init(){
        show_settings();
        $('#_subscription_fee').trigger('change');
        show_trial_settings($( '#_subscription_trial' ).val())
        show_plan_settings($( '#_subscription_schedule_type' ).val());
        show_notice_settings( $( '#_subscription_notice_period' ).val())
        show_contract_settings( $( '#_subscription_contract_periods' ).val())
        billing_cycles_settings( $('input[type=radio][name=_reepay_subscription_billing_cycles]:checked').val())
    }

    init();
});