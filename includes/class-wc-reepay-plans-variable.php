<?php
class WC_Reepay_Subscription_Plans_Variable extends WC_Reepay_Subscription_Plans {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter( 'product_type_selector', array( $this, 'add_reepay_variable_type' ) );
        add_filter( 'woocommerce_product_class', array( $this, 'reepay_variable_load_subscription_product_class' ), 10, 2);
        add_action( 'init', array( $this, 'reepay_variable_create_subscription_product_class' ) );

        add_action( 'woocommerce_variation_options_pricing', array( $this, 'add_custom_field_to_variations' ), 10, 3 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'save_reepay_variation' ), 10, 2 );
    }

    public function save_reepay_variation($variation_id, $i){
        if(!empty($_POST['product-type']) && $_POST['product-type'] != 'reepay_variable_subscriptions'){
            return;
        }

        if(!empty($_POST)){
            $title = get_the_title( $variation_id );
            if(!empty($title) && $title != 'AUTO-DRAFT'){
                $handle = get_post_meta($variation_id, '_reepay_subscription_handle', true);
                if(!empty($handle)){
                    if($this->update_plan($variation_id, $handle)) $this->save_meta($variation_id);
                }else{
                    $this->save_meta($variation_id);
                    //$this->create_plan($variation_id);
                }
            }
        }
    }

    public function add_custom_field_to_variations( $loop, $variation_data, $variation ) {
        $this->subscription_pricing_fields(true, $variation->ID);
    }

    public function reepay_variable_create_subscription_product_class(){
        include_once( reepay_s()->settings('plugin_path') . '/includes/class-wc-reepay-plan-variable-product.php' );
    }

    public function reepay_variable_load_subscription_product_class($php_classname, $product_type){
        if ( $product_type == 'reepay_variable_subscriptions' ) {
            $php_classname = 'WC_Product_Reepay_Variable_Subscription';
        }
        return $php_classname;
    }

    public function add_reepay_variable_type( $types ){
        $types['reepay_variable_subscriptions'] = __( 'Reepay Variable Subscription', reepay_s()->settings('domain') );

        return $types;
    }
}

new WC_Reepay_Subscription_Plans_Variable();