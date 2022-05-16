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

    $('#_subscription_fee').change(function() {
        show_fee_settings(this);
    });

    $('input[type=radio][name=_subscription_billing_cycles]').change(function() {
        billing_cycles_settings(this.value);
    });

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

    function billing_cycles_settings(val){
        if(val == 'false'){
            $('.fields-billing_cycles').show();
        }else{
            $('.fields-billing_cycles').hide();
        }
    }

    function init(){
        show_settings();
        show_fee_settings($('#_subscription_fee'))
        show_trial_settings($( '#_subscription_trial' ).val())
        show_plan_settings($( '#_subscription_schedule_type' ).val());
        show_notice_settings( $( '#_subscription_notice_period' ).val())
        billing_cycles_settings( $('input[type=radio][name=_subscription_billing_cycles]:checked').val())
    }

    init();
});