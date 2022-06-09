<?php

class WC_Reepay_Subscription_Addons{

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'woocommerce_product_write_panel_tabs', array( $this, 'tab_addons' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'panel_addons' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_addons' ), 1 );
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'addons_display' ));
        add_action( 'woocommerce_add_to_cart', array( $this, 'addons_add_to_cart' ), 10, 6);
    }

    public function addons_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data){
        foreach ($_POST as $key => $item){
            if(strpos($key, 'addon-') !== false){
                $handle = str_replace('addon-', '', $key);
                /*var_dump($_POST);
                exit;*/
            }
        }

    }

    public function addons_display(){
        global $product;
        $product_addons = array_filter((array)$product->get_meta('_product_addons'));
        var_dump($product_addons);
        if(!empty($product_addons)){
            wc_get_template(
                'plan-addons-subscription-frontend.php',
                array(
                    'product' => $product,
                    'addons' => $product_addons,
                    'domain' => reepay_s()->settings('domain')
                ),
                '',
                reepay_s()->settings('plugin_path').'templates/'
            );
        }
    }

    /**
     * Add product tab.
     */
    public function tab_addons() {
        global $post;
        $_product = wc_get_product( $post->ID );
        if($_product->is_type( 'reepay_simple_subscriptions' ) || $_product->is_type( 'reepay_variable_subscriptions' )){
            ?><li class="addons_tab product_addons">
                <a href="#product_addons_data"><span><?php _e( 'Add-ons', reepay_s()->settings('domain') ); ?></span></a>
            </li><?php
        }
    }

    /**
     * Add product panel.
     */
    public function panel_addons() {
        global $post;

        $product = wc_get_product($post);
        $product_addons = array_filter((array)$product->get_meta('_product_addons'));
        $addons_list = $this->get_reepay_addons_list();


        if(!empty($addons_list['content'])){
            foreach ($addons_list['content'] as $i => $addon){
                if($addon['state'] != 'active'){
                    unset($addons_list['content'][$i]);
                }
            }
        }

        wc_get_template(
            'admin-addons-panel.php',
            array(
                'domain' => reepay_s()->settings('domain'),
                'product_addons' => $product_addons,
                'addons_list' => $addons_list
            ),
            '',
            reepay_s()->settings('plugin_path').'templates/'
        );
    }

    public function get_reepay_addons_list(){
        try{
            $result = reepay_s()->api()->request("add_on?size=100");
            return $result;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }
        return false;
    }

    public function get_reepay_addon_data($handle){
        try{
            $result = reepay_s()->api()->request("add_on/".$handle);

            $addon_data = [
                'name' => $result['name'],
                'description' => $result['description'],
                'type' => $result['type'],
                'amount' => $result['amount'] / 100,
                'vat' => $result['vat'] * 100,
                'handle' => $result['handle'],
                'exist' => $result['handle'],
                'vat_type' => $result['amount_incl_vat'] ? 'include' : 'exclude',
            ];

            return $addon_data;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }

        return false;
    }

    /**
     * Process meta box.
     *
     * @param int $post_id Post ID.
     */
    public function save_addons( $post_id ) {
        // Save addons as serialised array.
        $product_addons = $this->get_posted_product_addons($post_id);

        $product = wc_get_product( $post_id );
        $product->update_meta_data( '_product_addons', $product_addons );
        $product->save();
    }

    public function save_to_reepay($product_addon, $post_id, $i){
        $plan_handle = get_post_meta($post_id, '_reepay_subscription_handle', true);

        $params = [
            'name' => !empty($product_addon['name']) ? $product_addon['name'] : '',
            'description' => !empty($product_addon['description']) ? $product_addon['description'] : '',
            'amount' => !empty($product_addon['amount']) ? floatval($product_addon['amount']) * 100 : 0,
            'vat' => !empty($product_addon['vat']) ? floatval($product_addon['vat']) / 100 : 0,
            'type' => $product_addon['type'],
            'amount_incl_vat' => $product_addon['vat_type'] == 'include',
            'all_plans' => false,
            'eligible_plans' => [$plan_handle],
        ];

        if(!empty($product_addon['handle'])){ //Update
            $handle = $product_addon['handle'];
            try{
                $result = reepay_s()->api()->request("add_on/$handle", 'PUT', $params);
            }catch (Exception $e){
                WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
            }
        }else{ //Create
            $addon_handle = 'Woocommerce_'.$post_id.'_'.$i;
            $params['handle'] = $addon_handle;
            try{
                $result = reepay_s()->api()->request('add_on', 'POST', $params);
                $product_addon['handle'] = $addon_handle;
            }catch (Exception $e){
                WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
            }
        }

        return $product_addon;
    }

    /**
     * Put posted addon data into an array.
     *
     * @return array
     */
    protected function get_posted_product_addons($post_id)
    {
        $product_addons = [];
        if (isset($_POST['product_addon_name'])) {
            $addon_name = $_POST['product_addon_name'];
            $addon_description = $_POST['product_addon_description'];
            $addon_type = $_POST['product_addon_type'];
            $addon_position = $_POST['product_addon_position'];
            $addon_amount = $_POST['product_addon_amount'];
            $addon_vat = $_POST['product_addon_vat'];
            $addon_vat_type = $_POST['product_addon_vat_type'];
            $addon_handle = $_POST['product_addon_handle'];
            $addon_choose = $_POST['_reepay_addon_choose'];
            $addon_exist = $_POST['addon_choose_exist'];

            for ($i = 0; $i < sizeof($addon_name); $i++) {
                $data = [];

                if($addon_choose[ $i ] == 'exist' && !empty($addon_exist[$i])){
                    $data = $this->get_reepay_addon_data($addon_exist[$i]);
                    $data['choose'] = $addon_choose[$i];
                    $data['position'] = $addon_position[$i];
                }else{
                    if (!isset($addon_name[$i]) || ('' == $addon_name[$i])) {
                        continue;
                    }

                    $data['name'] = sanitize_text_field(stripslashes($addon_name[$i]));
                    $data['description'] = wp_kses_post(stripslashes($addon_description[$i]));
                    $data['type'] = sanitize_text_field(stripslashes($addon_type[$i]));
                    $data['position'] = absint($addon_position[$i]);
                    $data['amount'] = wc_format_decimal(sanitize_text_field(stripslashes($addon_amount[$i])));
                    $data['vat'] = wc_format_decimal(sanitize_text_field(stripslashes($addon_vat[$i])));
                    $data['vat_type'] = sanitize_text_field(stripslashes($addon_vat_type[$i]));
                    $data['handle'] = $addon_handle[$i];
                    $data['choose'] = $addon_choose[$i];
                    $data['exist'] = $addon_exist[$i];

                    $data = $this->save_to_reepay($data, $post_id, $i);
                }


                // Add to array.
                $product_addons[] = apply_filters('woocommerce_product_addons_save_data', $data, $i);
            }
        }

        return $product_addons;
    }
}

new WC_Reepay_Subscription_Addons();
