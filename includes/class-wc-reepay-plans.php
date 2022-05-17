<?php

class WC_Reepay_Subscription_Plans{

    public static $schedule_types = array(
        'daily' => 'Day(s)',
        'month_startdate' => 'Month(s)',
        'month_fixedday' => 'Fixed day of month',
        'month_lastday' => 'Last day of month',
        'primo' => 'Quarterly Primo',
        'ultimo' => 'Quarterly Ultimo',
        'half_yearly' => 'Half-yearly',
        'month_startdate_12' => 'Yearly',
        'weekly_fixedday' => 'Fixed day of week',
        'manual' => 'Manual',
    );

    public static $trial = array(
        '' => 'No Trial',
        '7days' => '7 days',
        '14days' => '14 days',
        '1month' => '1 month',
        'customize' => 'Customize',
    );

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'product_type_selector', array( $this, 'add_reepay_type' ) );
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'subscription_pricing_fields' ) );
        add_action( 'save_post', array( $this, 'save_subscription_meta' ), 11 );
        add_filter( 'woocommerce_product_class', array( $this, 'reepay_load_subscription_product_class' ),10,2);
    }

    public function subscription_pricing_fields(){
        global $post;

        $meta = get_post_meta( $post->ID );

        wc_get_template(
            'simple-subscription-fields.php',
            array(
                'meta' => $meta
            ),
            '',
            WC_Reepay_Subscriptions::$plugin_path.'templates/'
        );
    }

    public function save_subscription_meta($post_id){
        if(!empty($_POST['product-type']) && $_POST['product-type'] != 'reepay_simple_subscriptions'){
            return;
        }

        if(!empty($_REQUEST)){
            foreach ($_REQUEST as $i => $value){
                if(strpos($i, 'reepay_subscription')){
                    update_post_meta( $post_id, $i, $value );
                }
            }
        }

        $params = [
            'name' => $_REQUEST['post_title'],
            'description' => $_REQUEST['content'],
            'amount' => $_REQUEST['_reepay_subscription_price'],
        ];

        $api = new WC_Reepay_Subscription_API();
        $api->set_params($params);

        try{
            $result = $api->request('POST', 'https://api.reepay.com/v1/plan');
        }catch (Exception $e){
            //Display error
        }

    }

    public function reepay_load_subscription_product_class($php_classname, $product_type){
        if ( $product_type == 'reepay_simple_subscriptions' ) {
            $php_classname = 'WC_Product_Reepay_Simple_Subscription';
        }
        return $php_classname;
    }

    public function add_reepay_type( $types ){
        $types['reepay_simple_subscriptions'] = __( 'Reepay Simple Subscription', WC_Reepay_Subscriptions::$domain );
        $types['reepay_variable_subscriptions'] = __( 'Reepay Variable Subscription', WC_Reepay_Subscriptions::$domain );

        return $types;
    }
}

new WC_Reepay_Subscription_Plans();

add_action( 'init', 'reepay_create_subscription_product_class' );

function reepay_create_subscription_product_class(){
    class WC_Product_Reepay_Simple_Subscription extends WC_Product {
        public function get_type() {
            return 'reepay_simple_subscriptions'; // so you can use $product = wc_get_product(); $product->get_type()
        }
    }
}