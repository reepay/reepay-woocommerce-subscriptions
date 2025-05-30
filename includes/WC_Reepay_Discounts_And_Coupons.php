<?php

class WC_Reepay_Discounts_And_Coupons
{
    /**
     * @var array<string, string>
     */
    public static $apply_to = [];

    /**
     * @var string[]
     */
    public static $meta_fields = [
        '_reepay_discount_use_existing_coupon_id',
        '_reepay_discount_name',
        '_reepay_discount_amount',
        '_reepay_discount_type',
        '_reepay_discount_apply_to',
        '_reepay_discount_apply_to_items',
        '_reepay_discount_all_plans',
        '_reepay_discount_eligible_plans',
        '_reepay_discount_duration',
        '_reepay_discount_fixed_count',
        '_reepay_discount_fixed_period',
        '_reepay_discount_fixed_period_unit',
        '_reepay_discount_use_existing_coupon_id',
        '_reepay_discount_use_existing_discount_id',
    ];

    /**
     * @var bool
     */
    public $applied_fixed_coupon = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('reepay_subscriptions_init', [$this, 'init']);

        add_filter('woocommerce_coupon_discount_types', [$this, 'add_coupon_types'], 10, 1);
        add_action('woocommerce_coupon_options', [$this, 'add_coupon_text_field'], 10);
        add_action('woocommerce_coupon_options_save', [$this, 'save_coupon_text_field'], 10, 2);
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_coupon'], 10, 4);
        add_filter('woocommerce_coupon_is_valid_for_product', [$this, 'validate_coupon_for_product'], 999, 4);
        add_filter('woocommerce_coupon_get_items_to_apply', [$this, 'items_to_apply'], 10, 3);
        add_filter('woocommerce_coupon_get_discount_amount', [$this, 'apply_discount'], 10, 5);

        add_filter("woocommerce_coupon_error", [$this, "plugin_coupon_error_message"], 10, 3);

        add_action('woocommerce_after_order_object_save', [$this, "on_order_save"]);

        // add_action('reepay_subscriptions_orders_created', [$this,"add_billwerk_coupon_to_reepay_sub_orders"], 10, 2);
        add_action('reepay_subscriptions_orders_created', [$this,"remove_billwerk_coupon_main_order_after_subscriptions_orders_created"], 20, 2);
    }

    public function init()
    {
        self::$apply_to = [
            'setup_fee'       => __('Setup fee'),
            'plan'            => __('Plan'),
            'additional_cost' => __('Additional Costs'),
            'ondemand'        => __('Instant Charges'),
            'add_on'          => __('Add-on'),
        ];
    }

    function get_discount_default_params(WC_Coupon $coupon, $data = [])
    {
        $name = get_post_meta($coupon->get_id(), '_reepay_discount_name', true);
        $type = get_post_meta($coupon->get_id(), '_reepay_discount_type', true);

        $params = [
            "name" => $name,
        ];

        $amount = $coupon->get_amount();

        if ($amount >= 1) {
            if ($type === 'reepay_percentage') {
                $params['percentage'] = $amount;
            } elseif ($type === 'reepay_fixed_product') {
                $params['amount'] = $amount * 100;
            }
        }

        if ($description = $coupon->get_description()) {
            $params["description"] = $description;
        }

        return $params;
    }

    function get_coupon_default_params(WC_Coupon $coupon)
    {
        $apply_plans = get_post_meta($coupon->get_id(), '_reepay_discount_eligible_plans', true) ?: [];

        $name = get_post_meta($coupon->get_id(), '_reepay_discount_name', true);


        return [
            "name"           => $name,
            "all_plans"      => empty($apply_plans),
            "eligible_plans" => $apply_plans,
        ];
    }

    static function get_existing_discount($handle)
    {
        $discountObj = reepay_s()->api()->request('discount/'.$handle);

        $discount_data = [];

        $amount = ! empty($discountObj['amount']) ? $discountObj['amount'] / 100 : $discountObj['percentage'];

        if ( ! empty($discountObj['amount'])) {
            $discount_data['_reepay_discount_type'] = 'reepay_fixed_product';
        }

        if ( ! empty($discountObj['percentage'])) {
            $discount_data['_reepay_discount_type'] = 'reepay_percentage';
        }

        $discount_data['_reepay_discount_name']     = $discountObj['name'];
        $discount_data['_reepay_discount_apply_to'] = empty($discountObj['apply_to']) ? 'all' : 'custom';
        if (isset($discountObj['apply_to'][0]) && $discountObj['apply_to'][0] === 'all') {
            $discount_data['_reepay_discount_apply_to'] = 'all';
        }
        $discount_data['_reepay_discount_apply_to_items'] = $discountObj['apply_to'];
        $discount_data['_reepay_discount_amount']         = ! empty($amount) ? $amount : 0;

        $discount_data['_reepay_discount_duration'] = 'forever';
        if ( ! empty($discountObj['fixed_count'])) {
            $discount_data['_reepay_discount_duration']    = 'fixed_number';
            $discount_data['_reepay_discount_fixed_count'] = $discountObj['fixed_count'];
        }

        if ( ! empty($discountObj['fixed_period'])) {
            $discount_data['_reepay_discount_duration']          = 'limited_time';
            $discount_data['_reepay_discount_fixed_period']      = $discountObj['fixed_period'];
            $discount_data['_reepay_discount_fixed_period_unit'] = $discountObj['fixed_period_unit'];
        }

        $discount_data['discount_handle'] = $handle;

        return $discount_data;
    }

    static function get_existing_coupon($handle)
    {
        $couponObj   = reepay_s()->api()->request('coupon/'.$handle);
        $discountObj = static::get_existing_discount($couponObj['discount']);

        $coupon_data                                    = $discountObj;
        $coupon_data['_reepay_discount_name']           = $couponObj['name'];
        $coupon_data['_reepay_discount_all_plans']      = $couponObj['all_plans'] ? '1' : '0';
        $coupon_data['_reepay_discount_eligible_plans'] = $couponObj['eligible_plans'];
        $coupon_data['coupon_handle']                   = $handle;

        return $coupon_data;
    }

    /**
     * @param  WC_Coupon  $coupon
     *
     * @return mixed
     * @throws Exception
     */
    static function get_coupon_code_real(WC_Coupon $coupon)
    {
        $coupon_code_real = get_post_meta($coupon->get_id(), '_reepay_coupon_code_real', true);

        if (empty($coupon_code_real)) {
            $handle = get_post_meta($coupon->get_id(), '_reepay_coupon_handle', true);
            if ( ! empty($handle)) {
                $couponObj        = reepay_s()->api()->request('coupon/'.$handle);
                $coupon_code_real = $couponObj['code'];
                update_post_meta($coupon->get_id(), '_reepay_coupon_code_real', $coupon_code_real);
            }
        }

        return $coupon_code_real;
    }

    function plugin_coupon_error_message($err, $err_code, WC_Coupon $coupon = null)
    {
        if ( ! is_null($coupon) && $coupon->is_type('reepay_type') && intval($err_code) === 117) {
            return __('Coupon is not applied for this plans');
        }

        return $err;
    }

    function create_discount(WC_Coupon $coupon, $data)
    {
        $params = $this->get_discount_default_params($coupon);

        $post_id       = $coupon->get_id();
        $apply_items   = array_map('sanitize_text_field', $data['_reepay_discount_apply_to_items'] ?? ['all']);
        $duration_type = sanitize_text_field($data['_reepay_discount_duration'] ?? 'forever');

        $discountHandle     = 'discount'.$post_id;
        $params["handle"]   = $discountHandle;
        $params["apply_to"] = $apply_items;


        if ($duration_type === 'fixed_number') {
            $params["fixed_count"] = intval($data['_reepay_discount_fixed_count']);
        }

        if ($duration_type === 'limited_time') {
            $params["fixed_period_unit"] = sanitize_text_field($data['_reepay_discount_fixed_period_unit']);
            $params["fixed_period"]      = intval($data['_reepay_discount_fixed_period']);
        }


        try {
            $discountObj = reepay_s()->api()->request('discount', 'POST', $params);
            update_post_meta($post_id, '_reepay_discount_handle', $params['handle']);

            return $discountObj;
        } catch (Exception $e) {
            WC_Reepay_Subscription_Admin_Notice::add_notice($e->getMessage());
            wp_update_post([
                'ID'     => $coupon->get_id(),
                'status' => 'draft',
            ]);
        }

        return false;
    }

    function update_discount(WC_Coupon $coupon)
    {
        $params = $this->get_discount_default_params($coupon);
        $handle = get_post_meta($coupon->get_id(), '_reepay_discount_handle', true);

        try {
            $discountObj = reepay_s()->api()->request('discount/'.$handle, 'PUT', $params);

            return $discountObj;
        } catch (Exception $e) {
            WC_Reepay_Subscription_Admin_Notice::add_notice($e->getMessage());
        }

        return false;
    }

    static function get_coupons()
    {
        return reepay_s()->api()->request('coupon')['content'] ?? [];
    }

    function get_discounts()
    {
        return reepay_s()->api()->request('discount')['content'] ?? [];
    }

    function create_coupon(WC_Coupon $coupon, $discount_handle, $data)
    {
        $paramsCoupon = $this->get_coupon_default_params($coupon);

        $apply_plans = array_map('sanitize_text_field', $data['_reepay_discount_eligible_plans'] ?? []);

        $post_id      = $coupon->get_id();
        $couponHandle = 'coupon'.$post_id;

        $paramsCoupon["handle"]   = $couponHandle;
        $paramsCoupon["code"]     = $coupon->get_code();
        $paramsCoupon["discount"] = $discount_handle;


        $paramsCoupon["all_plans"]      = empty($apply_plans);
        $paramsCoupon["eligible_plans"] = $apply_plans;

        $end = $coupon->get_date_expires();

        if ($max_redemptions = $coupon->get_usage_limit()) {
            $paramsCoupon["max_redemptions"] = $max_redemptions;
        }

        if ( ! empty($end)) {
            $paramsCoupon["valid_until"] = $end->format('Y-m-d\TH:i:s');
        }

        try {
            $result2 = reepay_s()->api()->request('coupon', 'POST', $paramsCoupon);
            update_post_meta($post_id, '_reepay_coupon_handle', $paramsCoupon['handle']);
            update_post_meta($post_id, '_reepay_coupon_code_real', $paramsCoupon["code"]);

            return $result2;
        } catch (Exception $e) {
            WC_Reepay_Subscription_Admin_Notice::add_notice($e->getMessage());
            wp_update_post([
                'ID'     => $coupon->get_id(),
                'status' => 'draft',
            ]);
        }

        return false;
    }

    function update_coupon(WC_Coupon $coupon)
    {
        $paramsCoupon = $this->get_coupon_default_params($coupon);
        $handle       = get_post_meta($coupon->get_id(), '_reepay_coupon_handle', true);

        try {
            $result = reepay_s()->api()->request('coupon/'.$handle, 'PUT', $paramsCoupon);

            return $result;
        } catch (Exception $e) {
            WC_Reepay_Subscription_Admin_Notice::add_notice($e->getMessage());
        }

        return false;
    }

    function save_coupon_text_field($post_id, WC_Coupon $coupon)
    {
        $type = $coupon->get_discount_type();

        if ($type !== 'reepay_type') {
            return;
        }

        $data = $_REQUEST;

        $couponData = static::get_existing_coupon(sanitize_text_field($_REQUEST['_reepay_discount_use_existing_coupon_id']));

        update_post_meta($post_id, '_reepay_coupon_handle', $couponData['coupon_handle']);
        update_post_meta($post_id, '_reepay_discount_handle', $couponData['discount_handle']);
        update_post_meta($post_id, '_reepay_coupon_code_real', $couponData['code']);

        $data = array_merge($data, $couponData);


        if ($_REQUEST['use_existing_discount'] === 'true') {
            $discountData = static::get_existing_discount(sanitize_text_field($_REQUEST['_reepay_discount_use_existing_discount_id']));
            update_post_meta($post_id, '_reepay_discount_handle', $discountData['discount_handle']);
            $data = array_merge($data, $discountData);
        }

        $data['_reepay_discount_name'] = isset($_REQUEST['_reepay_discount_name']) ? sanitize_text_field($_REQUEST['_reepay_discount_name']) : '';

        if ( ! empty($data)) {
            foreach (self::$meta_fields as $key) {
                if (isset($data[$key])) {
                    if (is_array($data[$key])) {
                        update_post_meta($post_id, $key, array_map('sanitize_text_field', $data[$key]));
                    } else {
                        update_post_meta($post_id, $key, sanitize_text_field($data[$key]));
                    }
                }
            }
        }

        $duration = sanitize_text_field($data['_reepay_discount_duration'] ?? 'forever');

        if ($duration === 'fixed_number') {
            $coupon->set_usage_limit(intval($data['_reepay_discount_fixed_count']));
        }

        if ($duration === 'limited_time') {
            $length = intval($data['_reepay_discount_fixed_period']);
            $units  = sanitize_text_field($data['_reepay_discount_fixed_period_unit']);
            $date   = new DateTime();
            if ($units === 'months') {
                $date->modify("+$length months");
            }

            if ($units === 'days') {
                $date->modify("+$length days");
            }
            $coupon->set_date_expires($date->getTimestamp());
        }

        if ( ! empty($data['_reepay_discount_amount'])) {
            $coupon->set_amount(floatval($data['_reepay_discount_amount']));
        }

        $coupon->save();
    }

    function is_coupon_applied_for_plans($coupon, $cart_item)
    {
        $apply_to_plans     = get_post_meta($coupon->get_id(), '_reepay_discount_eligible_plans', true) ?: [];
        $apply_to_all_plans = get_post_meta($coupon->get_id(), '_reepay_discount_all_plans', true);
        if ($apply_to_all_plans === '1') {
            return true;
        }
        if ($apply_to_all_plans === '0' && count($apply_to_plans) > 0) {
            $product = wc_get_product( $cart_item['product_id'] );
            if ($product->is_type('reepay_variable_subscriptions')) {
                $plan_handle = get_post_meta($cart_item['variation_id'], '_reepay_subscription_handle', true);
            } else {
                $plan_handle = get_post_meta($product->get_id(), '_reepay_subscription_handle', true);
            }

            return in_array($plan_handle, $apply_to_plans);
        }

        return false;
    }

    function on_order_save()
    {
        $this->applied_fixed_coupon = false;
    }

    function apply_discount($discount, $discounting_amount, $cart_item, $single, WC_Coupon $coupon)
    {
        $product = wc_get_product( $cart_item['product_id'] );

        if ( ! $coupon->is_type('reepay_type')) {
            if (WC_Reepay_Checkout::is_reepay_product($product)) {
                $discount = 0.00;
            }
        }
        
        if ( $coupon->is_type('reepay_type')) {
            if (WC_Reepay_Checkout::is_reepay_product($product)) {
                $type = get_post_meta($coupon->get_id(), '_reepay_discount_type', true);

                if ($type === 'reepay_percentage') {
                    if ( ! empty($product) && $this->is_coupon_applied_for_plans($coupon, $cart_item)) {
                        $discount = $coupon->get_amount() * ($discounting_amount / 100);
                    }
                }

                if ( $type === 'reepay_fixed_product') {
                    if ( ! empty($product) && $this->is_coupon_applied_for_plans($coupon, $cart_item)) {
                        $discount = $coupon->get_amount() / $cart_item['quantity'];
                    }
                }

            }
        }

        return $discount;
    }

    function validate_applied_for_plans(WC_Product $product, $apply_to_plans = [])
    {
        if (count($apply_to_plans) > 0) {
            $plan_handle = get_post_meta($product->get_id(), '_reepay_subscription_handle', true);

            return in_array($plan_handle, $apply_to_plans);
        }

        return true;
    }

    /**
     * @param $valid
     * @param  WC_Coupon  $coupon
     * @param  WC_Discounts  $discounts
     *
     * @return bool
     * @throws Exception
     */
    function validate_coupon($valid, WC_Coupon $coupon, WC_Discounts $discounts)
    {
        $has_reepay_product = false;
        if ( $coupon->is_type('reepay_type')) {
            foreach ($discounts->get_items_to_validate() as $item) {
                if (WC_Reepay_Checkout::is_reepay_product($item->product)) {
                    $has_reepay_product = true;
                }
            }

            if( $has_reepay_product === false ){
                throw new Exception(__(sprintf('Sorry, This coupon "%s" available only Frisbii Billing subscription',$coupon->get_code())), 113);
            }

            if($has_reepay_product){
                $apply_to_plans     = get_post_meta($coupon->get_id(), '_reepay_discount_eligible_plans', true) ?: [];
                $apply_to_all_plans = get_post_meta($coupon->get_id(), '_reepay_discount_all_plans', true);
                $apply              = false;
                if ($apply_to_all_plans === '0' && count($apply_to_plans) > 0) {
                    foreach ($discounts->get_items_to_validate() as $item) {
                        $valid = $this->validate_applied_for_plans($item->product, $apply_to_plans);
                        if ($valid) {
                            $apply = true;
                            break;
                        }
                    }
                }

                if ($apply_to_all_plans === '0' && ! $apply) {
                    throw new Exception(__('Sorry, this coupon is not applicable to the products'), 113);
                }

                $coupon_code_real = static::get_coupon_code_real($coupon);

                $check_coupon = self::coupon_can_be_applied($coupon_code_real);

                if (true === $check_coupon) {
                    return true;
                }

                throw new Exception($check_coupon->get_error_message());
            }
        }else{
            if(WC_Reepay_Checkout::only_reepay_products_in_cart('')){
                throw new Exception(__('Sorry, this coupon is not applicable to selected products.'), 113);
            }
        }

        return $valid;
    }

    function validate_coupon_for_product($valid, WC_Product $product, WC_Coupon $coupon, $values)
    {
        /**
         * Check coupon is not reepay type and not allow to use with supscription.
         */
        if ( ! $coupon->is_type('reepay_type')) {
            if (WC_Reepay_Checkout::is_reepay_product($product)) {
                return false;
            }
        }

        /**
         * Check coupon is reepay type and allow to use with supscription.
         */
        if ( $coupon->is_type('reepay_type')) {
            if (WC_Reepay_Checkout::is_reepay_product($product)) {
                $apply_to_plans     = get_post_meta($coupon->get_id(), '_reepay_discount_eligible_plans', true) ?: [];
                $apply_to_all_plans = get_post_meta($coupon->get_id(), '_reepay_discount_all_plans', true);
                if ($apply_to_all_plans === '0' && count($apply_to_plans) > 0) {
                    if ( ! $this->validate_applied_for_plans($product, $apply_to_plans)) {
                        return false;
                    }
                }
                return true;
            }else{
                return false;
            }
        }

        return $valid;
    }

    /**
     * Fix Fixed Cart Discount assign discunt to subscription with hook woocommerce_coupon_get_items_to_apply 
     */
    function items_to_apply( $items_to_apply, WC_Coupon $coupon, WC_Discounts $discount ){
        if ( ! $coupon->is_type('reepay_type')) {
            if( $items_to_apply ){
                foreach( $items_to_apply as $key => $items){
                    if (WC_Reepay_Checkout::is_reepay_product($items->product)) {
                        unset($items_to_apply[$key]);
                    }
                }
            }
        }
        return $items_to_apply;
    }

    function add_coupon_text_field()
    {
        $meta = get_post_meta(get_the_ID());

        $apply_to_items = [];
        if ( ! empty($meta['_reepay_discount_apply_to_items'][0])) {
            $apply_to_items = unserialize($meta['_reepay_discount_apply_to_items'][0]);
        }
        $meta['_reepay_discount_apply_to_items'][0] = $apply_to_items;

        $apply_to_plans = [];
        if ( ! empty($meta['_reepay_discount_eligible_plans'][0])) {
            $apply_to_plans = unserialize($meta['_reepay_discount_eligible_plans'][0]);
        }
        $meta['_reepay_discount_eligible_plans'][0] = $apply_to_plans;

        $plans     = WC_Reepay_Subscription_Plan_Simple::get_plans_wc();
        $coupons   = self::get_coupons();
        $discounts = $this->get_discounts();

        $handle = get_post_meta(get_the_ID(), '_reepay_coupon_handle', true);

        $is_update = false;
        if ( ! empty($handle)) {
            $is_update = true;
        }

        wc_get_template(
            'discounts-and-coupons-fields.php',
            array(
                'meta'      => $meta,
                'plans'     => $plans,
                'coupons'   => $coupons,
                'discounts' => $discounts,
                'is_update' => $is_update,
            ),
            '',
            reepay_s()->settings('plugin_path').'templates/'
        );
    }

    public function add_coupon_types($discount_types)
    {
        return array_merge($discount_types, [
            'reepay_type' => __('Frisbii Billing discount'),
        ]);
    }

    /**
     * @param  string  $code
     * @param  string  $customer_handle  - current user by default
     * @param  string  $plan
     *
     * @return bool|WP_Error
     */
    public static function coupon_can_be_applied($code, $customer_handle = null, $plan = '')
    {
        $request_url = "coupon/code/validate?code=$code";

        if (empty($customer_handle)) {
            $customer_handle = get_user_meta(get_current_user_id())['reepay_customer_id'] ?? null;
            $customer_handle = is_array($customer_handle) ? $customer_handle[0] : $customer_handle;

            if ( ! empty($customer_handle)) {
                $request_url .= "&customer=$customer_handle";
            }
        }

        if ( ! empty($plan)) {
            $request_url .= "&plan=$plan";
        }

        try {
            reepay_s()->api()->request($request_url);

            return true;
        } catch (Exception $e) {
            return new WP_Error(404, __('This coupon cannot be used. '.$e->getMessage()));
        }
    }

    /**
     * Assign coupon to Frisbii subscription sub order, But not discount to order line need to investigate.
     */
    public function add_billwerk_coupon_to_reepay_sub_orders($created_reepay_order_ids, $main_order){
        if($created_reepay_order_ids){
            $coupons = $main_order->get_items( 'coupon' );
            if ( $coupons ){
                foreach($created_reepay_order_ids as $created_reepay_order_id){
                    $order = new WC_Order( $created_reepay_order_id );
                    foreach ( $coupons as $item_id => $item ){
                        $coupon = new WC_Coupon($item->get_code());
                        if ( $coupon->is_type('reepay_type')) {
                            $order->apply_coupon($coupon->get_code());
                            $order->calculate_totals( true );
                            $order->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * Remove Frisbii coupon from mix order main order
     */
    public function remove_billwerk_coupon_main_order_after_subscriptions_orders_created($created_reepay_order_ids, $main_order){
        /**
         * Check order is reepay product order
         */
        $has_reepay_product = false;
        $order = new WC_Order( $main_order->get_id() );
        foreach ($order->get_items() as $order_item) {
            $product = $order_item->get_product();
            // Check for subscription product
            if (WC_Reepay_Checkout::is_reepay_product($product)) {
                $has_reepay_product= true;
                break;
            }
        }

        /**
         * Remove reepay coupon from simple product order in main order.
         */
        if( $has_reepay_product === false ){
            $coupons = $order->get_items( 'coupon' );
            if ( $coupons ){
                foreach ( $coupons as $item_id => $item ){
                    $coupon = new WC_Coupon($item->get_code());
                    if ( $coupon->is_type('reepay_type')) {
                        $order->remove_coupon($coupon->get_code());
                    }
                }
                $order->save();
            }
        }
    }
}
