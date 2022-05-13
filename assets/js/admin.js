jQuery( function ( $ ) {
    $( 'body' ).on( 'woocommerce-product-type-change', function () {
        show_settings()
    } );
    $( '#_subscription_schedule_type' ).on( 'change', function () {
        show_plan_settings($(this).val());
    } );

    function show_settings(){
        if ( 'reepay_simple_subscriptions' === $( 'select#product-type' ).val() ) {
            $( '.show_if_reepay_subscription' ).show();
        }else{
            $( '.show_if_reepay_subscription' ).hide();
        }
    }

    function show_plan_settings(type){
        var subs_block = $('.show_if_reepay_subscription');
        subs_block.find('.type-fields').hide();
        subs_block.find('.fields-' + type).show();
    }

    function init(){
        show_settings();
        show_plan_settings($( '#_subscription_schedule_type' ).val());
    }

    init();
});