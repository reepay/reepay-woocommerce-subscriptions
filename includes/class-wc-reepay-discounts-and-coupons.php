<?php

class WC_Reepay_Discounts_And_Coupons
{

    public static $apply_to = [
        'setup_fee' => 'Setup fee',
        'plan' => 'Plan',
        'additional_cost' => 'Additional Costs',
        'ondemand' => 'Instant Charges',
        'add_on' => 'Add-on',
    ];

    public static $coupon_types = [
            'reepay_percentage' => 'Reepay Percentage Discount',
            'reepay_fixed_product' => 'Reepay Fixed product Discount',
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
        add_filter('woocommerce_coupon_discount_types', [$this, 'add_coupon_types'], 10, 1);
        add_action( 'woocommerce_coupon_options', [$this, 'add_coupon_text_field'], 10 );
        add_action( 'woocommerce_coupon_options_save', [$this, 'save_coupon_text_field'], 10, 2 );
        add_filter('woocommerce_coupon_is_valid', [$this, 'validate_coupon'], 10, 4);
        add_filter('woocommerce_coupon_get_discount_amount', [$this, 'apply_discount'], 10, 5);

        add_filter("woocommerce_coupon_error",[$this, "plugin_coupon_error_message"],10,3);

    }

    function get_discount_default_params(WC_Coupon $coupon) {

        $name = get_post_meta($coupon->get_id(), '_reepay_discount_name', true);

        $params = [
            "name" => $name,
        ];
        $amount = $coupon->get_amount();

        if ($amount > 0) {
            if ($coupon->get_discount_type() === 'reepay_percentage') {
                $params['percentage'] = $amount;
            } else if ($coupon->get_discount_type() === 'reepay_fixed_product') {
                $params['amount'] = $amount;
            }
        }

        if ($description = $coupon->get_description()) {
            $params["description"] = $description;
        }



        return $params;
    }


    function get_coupon_default_params(WC_Coupon $coupon) {
        $apply_plans = $_REQUEST['_reepay_discount_eligible_plans'];
        $end = $coupon->get_date_expires();

        $name = get_post_meta($coupon->get_id(), '_reepay_discount_name', true);


        $paramsCoupon = [
            "name" => $name,
            "all_plans" => empty($apply_plans),
            "eligible_plans" => $apply_plans,
        ];
        if ($max_redemptions = $coupon->get_usage_limit()) {
            $paramsCoupon["max_redemptions"] = $max_redemptions;
        }
        if (!empty($end)) {
            $paramsCoupon["valid_until"] = $end->format(DATE_ISO8601);
        }
        return $paramsCoupon;
    }

    function use_existing_coupon(WC_Coupon $wc_coupon, $handle) {
        try{
            $couponObj = reepay_s()->api()->request('coupon/' . $handle);
            $discountObj = reepay_s()->api()->request('discount/' . $couponObj['discount']);

            $wc_coupon->set_code($couponObj['code']);
            $wc_coupon->set_description($discountObj['description']);
            $wc_coupon->set_date_expires($couponObj['valid_until']);
            $wc_coupon->set_usage_limit($couponObj['max_redemptions']);

            if (!empty($discountObj['amount'])) {
                $wc_coupon->set_amount($discountObj['amount']);
                $wc_coupon->set_discount_type('reepay_fixed_product');
            } else {
                $wc_coupon->set_amount($discountObj['percentage']);
                $wc_coupon->set_discount_type('reepay_percentage');
            }

            $post_id = $wc_coupon->get_id();

            update_post_meta($post_id, '_reepay_discount_name', $couponObj['name']);
            update_post_meta($post_id, '_reepay_discount_apply_to', empty($discountObj['apply_to']) ? 'all' : 'custom');
            update_post_meta($post_id, '_reepay_discount_apply_to_items', $discountObj['apply_to']);
            update_post_meta($post_id, '_reepay_discount_all_plans', $couponObj['all_plans']);
            update_post_meta($post_id, '_reepay_discount_eligible_plans', $couponObj['eligible_plans']);


            update_post_meta($post_id, '_reepay_discount_handle', $discountObj['handle']);
            update_post_meta($post_id, '_reepay_coupon_handle', $couponObj['handle']);

            return $wc_coupon;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }
    }

    function plugin_coupon_error_message($err,$err_code, WC_Coupon $coupon) {
        if( $coupon->is_type( array_keys(static::$coupon_types) ) && intval($err_code) === 117 ) {
            return __('Coupon is not applied for this plans', reepay_s()->settings('domain'));
        }
        return $err;
    }

    function create_discount(WC_Coupon $coupon) {

        $params = $this->get_discount_default_params($coupon);


        $post_id = $coupon->get_id();
        $apply_items = $_REQUEST['_reepay_discount_apply_to_items'] ?? ['all'];

        $discountHandle = 'discount'.$post_id;
        $params["handle"] = $discountHandle;
        $params["apply_to"] = $apply_items;

        if ($end = $coupon->get_date_expires()) {
            $end = $end->diff(new DateTime());
            $params["fixed_period_unit"] = "days";
            $params["fixed_period"] = $end->days;
        }

        if ($coupon->get_usage_limit() > 0) {
            $params["fixed_count"] = $coupon->get_usage_limit();
        }


        try{
            $discountObj = reepay_s()->api()->request('discount', 'POST', $params);
            update_post_meta($post_id, '_reepay_discount_handle', $params['handle']);
            return $discountObj;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }
        return false;
    }

    function update_discount(WC_Coupon $coupon) {
        $params = $this->get_discount_default_params($coupon);
        $handle = get_post_meta($coupon->get_id(), '_reepay_discount_handle', true);

        try{
            $discountObj = reepay_s()->api()->request('discount/' . $handle, 'PUT', $params);
            return $discountObj;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }

        return false;
    }


    function get_coupons() {
        return reepay_s()->api()->request('coupon')['content'] ?? [];
    }

    function create_coupon(WC_Coupon $coupon, $discount_handle) {

        $paramsCoupon = $this->get_coupon_default_params($coupon);

        $post_id = $coupon->get_id();
        $couponHandle = 'coupon'.$post_id;

        $paramsCoupon["handle"] = $couponHandle;
        $paramsCoupon["code"] = $coupon->get_code();
        $paramsCoupon["discount"] = $discount_handle;


        try{
            $result2 = reepay_s()->api()->request('coupon', 'POST', $paramsCoupon);
            update_post_meta($post_id, '_reepay_coupon_handle', $paramsCoupon['handle']);
            return $result2;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }

        return false;
    }

    function update_coupon(WC_Coupon $coupon) {
        $paramsCoupon = $this->get_coupon_default_params($coupon);
        $handle = get_post_meta($coupon->get_id(), '_reepay_coupon_handle', true);

        try{
            $result = reepay_s()->api()->request('coupon/' . $handle, 'PUT', $paramsCoupon);
            return $result;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }

        return false;
    }


    function save_coupon_text_field( $post_id, WC_Coupon $coupon ) {
        $type = $coupon->get_discount_type();

        if($type !== 'reepay_percentage' && $type !== 'reepay_fixed_product'){
            return;
        }

        if(!empty($_REQUEST)){
            foreach ($_REQUEST as $i => $value){
                if(strpos($i, 'reepay_discount')){
                    update_post_meta( $post_id, $i, $value );
                }
            }
        }

        $discountHandle = get_post_meta($post_id, '_reepay_discount_handle', true);
        $couponHandle = get_post_meta($post_id, '_reepay_coupon_handle', true);

        if ($_REQUEST['use_existing_coupon'] === 'true') {
            $this->use_existing_coupon($coupon, $_REQUEST['_reepay_discount_use_existing_coupon_id']);
        }

        if (empty($discountHandle)) {
            $discount = $this->create_discount($coupon);
            $discountHandle = $discount['handle'];
        } else {
            $this->update_discount($coupon);
        }

        if (empty($couponHandle) && !empty($discountHandle)) {
            $this->create_coupon($coupon, $discountHandle);
        } else if (!empty($couponHandle)) {
            $this->update_coupon($coupon);
        }

        $coupon->save();
    }

    function apply_discount($discount, $discounting_amount, $cart_item, $single, WC_Coupon $coupon) {
        
        if ($coupon->is_type('reepay_percentage')){
            $discount = (float) $coupon->get_amount() * ( $discounting_amount / 100 );
        }

        if (!$this->applied_fixed_coupon && $coupon->is_type('reepay_fixed_product')){
            $discount = $coupon->get_amount();
            $this->applied_fixed_coupon = true;
        }

        return $discount;
    }

    function validate_coupon($valid, WC_Coupon $coupon, WC_Discounts $discounts){
        if ( ! $coupon->is_type( array_keys(static::$coupon_types) ) ) {
            return $valid;
        }

        $apply_to_plans = get_post_meta($coupon->get_id(), '_reepay_discount_eligible_plans', true);
        if (count($apply_to_plans) > 0) {
            foreach ($discounts->get_items_to_validate() as $item) {
                $plan_handle = get_post_meta($item->product->get_id(), '_reepay_subscription_handle', true);
                $valid = in_array($plan_handle, $apply_to_plans);
                if (!$valid) {
                    throw new Exception(__('Invalid coupon', 'woocommerce'), 117);
                }
            }
        }

        return $valid;
    }

    function add_coupon_text_field() {
        $meta = get_post_meta(get_the_ID());

        $apply_to_items = [];
        if (!empty($meta['_reepay_discount_apply_to_items'][0])) {
            $apply_to_items = unserialize($meta['_reepay_discount_apply_to_items'][0]);
        }
        $meta['_reepay_discount_apply_to_items'][0] = $apply_to_items;

        $apply_to_plans = [];
        if (!empty($meta['_reepay_discount_eligible_plans'][0])) {
            $apply_to_plans = unserialize($meta['_reepay_discount_eligible_plans'][0]);
        }
        $meta['_reepay_discount_eligible_plans'][0] = $apply_to_plans;

        $plans = WC_Reepay_Subscription_Plans::get_plans_wc();
        $coupons = $this->get_coupons();

        $handle = get_post_meta(get_the_ID(), '_reepay_coupon_handle', true);

        $is_update = false;
        if(!empty($handle)){
            $is_update = true;
        }

        wc_get_template(
            'discounts-and-coupons-fields.php',
            array(
                'meta' => $meta,
                'plans' => $plans,
                'coupons' => $coupons,
                'is_update' => $is_update,
            ),
            '',
            reepay_s()->settings('plugin_path').'templates/'
        );
    }
    public function add_coupon_types($discount_types)
    {
        return array_merge($discount_types, static::$coupon_types);
    }
}

new WC_Reepay_Discounts_And_Coupons();