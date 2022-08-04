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
        add_action( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 6);
        add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 20, 1 );
        // Load cart data per page load.
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 20, 2 );
        // Get item data to display.
        add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
        // Add meta to order.
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'order_line_item' ), 10, 3 );
    }


    /**
     * Include add-ons line item meta.
     *
     * @param  WC_Order_Item_Product $item          Order item data.
     * @param  string                $cart_item_key Cart item key.
     * @param  array                 $values        Order item values.
     */
    public function order_line_item( $item, $cart_item_key, $values ) {
        if ( ! empty( $values['addons'] ) ) {
            $addons_info = [];

            foreach ( $values['addons'] as $addon ) {
	            $result = reepay_s()->api()->request("add_on/{$addon['handle']}");

	             $addons_info[] = [
		            'name' => $result['name'],
		            'description' => $result['description'],
		            'type' => $result['type'],
		            'amount' => $result['amount'] / 100,
		            'vat' => $result['vat'] * 100,
		            'handle' => $result['handle'],
		            'exist' => $result['handle'],
		            'add_on' => $result['handle'],
		            'vat_type' => $result['amount_incl_vat'] ? 'include' : 'exclude',
	            ];

                $key = $addon['name'];
                $price = $addon['amount'];
                if ( ! empty( $addon['quantity'] ) && apply_filters( 'woocommerce_addons_add_price_to_name', '__return_true' ) ) {
                    $key .= ' x'.$addon['quantity'];
                    $price = $price * intval($addon['quantity']);
                }

                $item->add_meta_data( $key, '+'. wc_price($price) );
            }

	        $item->add_meta_data( 'addons', $addons_info );
        }
    }

    /**
     * Get item data.
     *
     * @param array $other_data Other data.
     * @param array $cart_item  Cart item data.
     * @return array
     */
    public function get_item_data( $other_data, $cart_item ) {	//echo '<pre>' . print_r($cart_item, true) . '</pre>'; die;
        if ( ! empty( $cart_item['addons'] ) ) {
            foreach ( $cart_item['addons'] as $addon ) {
                $name = $addon['name'];
                $price = $addon['amount'];

                if ( ! empty( $addon['quantity'] ) && apply_filters( 'woocommerce_addons_add_price_to_name', '__return_true' ) ) {
                    $name .= ' x'.$addon['quantity'];
                    $price = $price * intval($addon['quantity']);
                }
                $other_data[] = array(
                    'name'    => $name,
                    'display' => wc_price($price),
                );
            }
        }
        return $other_data;
    }

    /**
     * Get cart item from session.
     *
     * @param array $cart_item Cart item data.
     * @param array $values    Cart item values.
     * @return array
     */
    public function get_cart_item_from_session( $cart_item, $values ) {
        if ( ! empty( $values['addons'] ) ) {
            $cart_item['addons'] = $values['addons'];
            $cart_item = $this->add_cart_item( $cart_item );
        }

        return $cart_item;
    }

    /**
     * Adjust add-on price if set on cart.
     *
     * @param array $cart_item Cart item data.
     * @return array
     */
    public function add_cart_item( $cart_item ) {

        if ( ! empty( $cart_item['addons'] ) && apply_filters( 'woocommerce_product_addons_adjust_price', true, $cart_item ) ) {
            $price = $cart_item['data']->get_price();

            foreach ( $cart_item['addons'] as $addon ) {
                if ( (float) $addon['amount'] > 0) {
                    if(!empty($addon['quantity'])){
                        $price += (float) $addon['amount'] * (int) $addon['quantity'];
                    }else{
                        $price += (float) $addon['amount'];
                    }

                }
            }

            $cart_item['data']->set_price( $price );
        }

        return $cart_item;
    }

    function get_product_addons($product_id){
        $product = wc_get_product($product_id);
        return array_filter((array)$product->get_meta('_product_addons'));
    }

	/**
	 * Add cart item data.
	 *
	 * @param  array  $cart_item_data  Cart item meta data.
	 * @param  int  $product_id  Product ID.
	 * @param  int  $variation_id
	 * @param  int  $quantity
	 *
	 * @return array
	 */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
	    $post_data = $_POST;

        $product_addons = $this->get_product_addons( $product_id );

        if ( empty( $cart_item_data['addons'] ) ) {
            $cart_item_data['addons'] = array();
        }

        if ( is_array( $product_addons ) && ! empty( $product_addons ) ) {;

            foreach ( $product_addons as $i => $addon ) {

                if(isset($post_data[ 'addon-' . $addon['handle'] ])){
                    $data = array();
                    $value = $post_data[ 'addon-' . $addon['handle'] ];

                    if($value != 'yes' && !intval($value)){
                        continue;
                    }

                    $data[$i] = [
                        'name' => $addon['name'],
                        'handle' => $addon['handle'],
                        'add_on' => $addon['handle'],
                        'amount' => $addon['amount'],
                        'description' => $addon['description'],
                        'fixed_amount' => true,
                        'amount_incl_vat' => $addon['vat_type'] == 'include',
                    ];

                    if($value != 'yes' && intval($value) != 0){

                        $data[$i]['quantity'] = intval($value);
                    }

                    $cart_item_data['addons'] = array_merge( $cart_item_data['addons'], apply_filters( 'woocommerce_product_addon_cart_item_data', $data, $addon, $product_id, $post_data ) );
                }
            }
        }

        return $cart_item_data;
    }

    public function addons_display(){
        global $product;
        $product_addons = array_filter((array)$product->get_meta('_product_addons'));

        if(!empty($product_addons)){
            wc_get_template(
                'plan-addons-subscription-frontend.php',
                array(
                    'billing_plan' => WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $product, true ),
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
        $addons_list = $this->get_reepay_addons_list(true);
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

	/**
	 * @param bool $drop_cache
	 *
	 * @return mixed
	 */
	public function get_reepay_addons_list( $drop_cache = false ){
		$addons_list = $drop_cache ? null : get_option('reepay_s_addons_list');

	    if ( ! empty( $addons_list ) ) {
            return $addons_list;
	    }

        try{
	        $addons_list = reepay_s()->api()->request("add_on?size=100");

            if(!empty($addons_list['content'])){
                foreach ($addons_list['content'] as $i => $addon){
                    if($addon['state'] != 'active'){
                        unset($addons_list['content'][$i]);
                    }
                }
            }

	        update_option( 'reepay_s_addons_list', $addons_list );

            return $addons_list;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }
        return false;
    }

    public function get_reepay_addon_data($handle){
        try{
            $result = reepay_s()->api()->request("add_on/".$handle);

	        return [
                'name' => $result['name'],
                'description' => !empty($result['description']) ? $result['description'] : '',
                'type' => !empty($result['type']) ? $result['type'] : '',
                'amount' => $result['amount'] / 100,
                'vat' => $result['vat'] * 100,
                'handle' => $result['handle'],
                'exist' => $result['handle'],
                'vat_type' => $result['amount_incl_vat'] ? 'include' : 'exclude',
            ];
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
            'vat' => !empty($product_addon['vat']) ? floatval($product_addon['vat']) : 0,
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

    public function add_plan_to_addon($post_id, $handle){

        $plan_handle = get_post_meta($post_id, '_reepay_subscription_handle', true);

        $product = wc_get_product($post_id);

        try{
            $result = reepay_s()->api()->request("add_on/".$handle);
            if(!$result['all_plans']){

                if($product->is_type( 'reepay_variable_subscriptions' )){
                    $variations = $product->get_available_variations();
                    if(!empty($variations)){
                        $plan_handle = [];
                        foreach ($variations as $loop => $variation){
                            $plan_handle = 'wc_subscription_'.$loop.'_'.$variation['variation_id'];
                            array_push( $result['eligible_plans'], $plan_handle);
                        }
                    }
                }else{
                    if(empty($result['eligible_plans'])){
                        $result['eligible_plans'] = [$plan_handle];
                    }elseif(!in_array($result['eligible_plans'], $plan_handle)){
                        $result['eligible_plans'][] = $plan_handle;
                    }
                }

                reepay_s()->api()->request("add_on/$handle", 'PUT', $result);
            }

        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }

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
            $addon_type = !empty($_POST['product_addon_type']) ? $_POST['product_addon_type'] : '';
            $addon_position = $_POST['product_addon_position'];
            $addon_amount = $_POST['product_addon_amount'];
            $addon_handle = $_POST['product_addon_handle'];
            $addon_choose = $_POST['_reepay_addon_choose'];
            $addon_exist = $_POST['addon_choose_exist'];

            for ($i = 0; $i < sizeof($addon_name); $i++) {
                $data = [];

                if($addon_choose[ $i ] == 'exist' && !empty($addon_exist[$i])){
                    $data = $this->get_reepay_addon_data($addon_exist[$i]);
                    $this->add_plan_to_addon($post_id, $addon_exist[$i]);
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
                    $data['vat'] = WC_Reepay_Subscription_Plan_Simple::get_vat($post_id);
                    $data['vat_type'] = wc_prices_include_tax();
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
