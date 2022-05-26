<?php

class WC_Reepay_Discounts_And_Coupons
{

    public static $coupon_types = array(
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

    public static $apply_to = array(
        'setup_fee' => 'Setup fee',
        'plan' => 'Plan',
        'additional_cost' => 'Additional Costs',
        'ondemand' => 'Instant Charges',
        'add_on' => 'Add-on',
    );

    /**
     * Constructor
     */
    public function __construct()
    {
        add_filter('woocommerce_coupon_discount_types', [$this, 'add_coupon_types'], 10, 1);
        add_action( 'woocommerce_coupon_options', [$this, 'add_coupon_text_field'], 10 );
        add_action( 'woocommerce_coupon_options_save', [$this, 'save_coupon_text_field'], 10, 2 );
    }

    function get_discount_default_params(WC_Coupon $coupon) {

        $amount = null;
        $percentage = null;

        if ($coupon->get_discount_type() === 'reepay_percentage') {
            $amount = null;
            $percentage = $coupon->get_amount();
        }

        if ($coupon->get_discount_type() === 'reepay_fixed_product') {
            $amount = $coupon->get_amount();
            $percentage = null;
        }


        $params = [
            "name" => $coupon->get_description(),
            "description" => $coupon->get_description(),
            "amount" => $amount,
            "percentage" => $percentage,
        ];

        return $params;
    }

    function use_existing_coupon(WC_Coupon $wc_coupon, $handle) {
        try{
            $api = new WC_Reepay_Subscription_API();
            $api->set_params([]);
            $couponObj = $api->request('GET', 'https://api.reepay.com/v1/coupon/' . $handle);
            $discountObj = $api->request('GET', 'https://api.reepay.com/v1/discount/' . $couponObj['discount']);

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

    function get_coupon_default_params(WC_Coupon $coupon) {
        $apply_plans = $_REQUEST['_reepay_discount_eligible_plans'];
        $end = $coupon->get_date_expires();


        $paramsCoupon = [
            "name" => $coupon->get_description(),
            "all_plans" => empty($apply_plans),
            "eligible_plans" => $apply_plans,
            "max_redemptions" => $coupon->get_usage_limit(),
            "valid_until" => $end->format(DATE_ISO8601),
        ];

        return $paramsCoupon;
    }

    function create_discount(WC_Coupon $coupon) {

        $params = $this->get_discount_default_params($coupon);


        $post_id = $coupon->get_id();
        $apply_items = $_REQUEST['_reepay_discount_apply_to_items'] ?? [];




        $discountHandle = 'discount'.$post_id;

        $end = $coupon->get_date_expires()->diff(new DateTime());

        $params["handle"] = $discountHandle;
        $params["apply_to"] = $apply_items;
        $params["fixed_count"] = $coupon->get_usage_limit();
        $params["fixed_period_unit"] = "days";
        $params["fixed_period"] = $end->days;


        try{
            $api = new WC_Reepay_Subscription_API();
            $api->set_params($params);
            $discountObj = $api->request('POST', 'https://api.reepay.com/v1/discount');
            update_post_meta($post_id, '_reepay_discount_handle', $params['handle']);
            return $discountObj;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }
        return false;
    }

    function update_discount(WC_Coupon $coupon) {
        $params = $this->get_discount_default_params($coupon);


        try{
            $api = new WC_Reepay_Subscription_API();
            $api->set_params($params);
            $discountObj = $api->request('PUT', 'https://api.reepay.com/v1/discount');
            return $discountObj;
        }catch (Exception $e){
            WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
        }

        return false;
    }


    function get_coupons() {
        $apiCoupons = new WC_Reepay_Subscription_API();
        return $apiCoupons->request('GET', 'https://api.reepay.com/v1/coupon')['content'] ?? [];
    }
    function get_plans() {
        $plansQuery = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => [[
                'key' => '_reepay_subscription_handle',
                'compare' => 'EXISTS',
            ]]
        ]);
        $plans = [];
        foreach ($plansQuery->posts as $item) {
            $handle = get_post_meta($item->ID, '_reepay_subscription_handle', true);
            $plans[$handle] = $item->post_title;
        }
        return $plans;
    }

    function create_coupon(WC_Coupon $coupon, $discount_handle) {

        $paramsCoupon = $this->get_coupon_default_params($coupon);

        $post_id = $coupon->get_id();
        $couponHandle = 'coupon'.$post_id;

        $paramsCoupon["handle"] = $couponHandle;
        $paramsCoupon["code"] = $coupon->get_code();
        $paramsCoupon["discount"] = $discount_handle;


        try{
            $apiCoupons = new WC_Reepay_Subscription_API();
            $apiCoupons->set_params($paramsCoupon);
            $result2 = $apiCoupons->request('POST', 'https://api.reepay.com/v1/coupon');
            update_post_meta($post_id, '_reepay_coupon_handle', $paramsCoupon['coupon']);
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
            $apiCoupons = new WC_Reepay_Subscription_API();
            $apiCoupons->set_params($paramsCoupon);
            $result = $apiCoupons->request('POST', 'https://api.reepay.com/v1/coupon/' . $handle);
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
        } else if (empty($discountHandle) && empty($couponHandle)) {
            $discount = $this->create_discount($coupon);
            $this->create_coupon($coupon, $discount);
        } else if (!empty($discountHandle) && !empty($couponHandle)) {
            $this->update_discount($coupon);
            $this->update_coupon($coupon);
        }
        $coupon->save();
    }

    function add_coupon_text_field() {
        $meta = get_post_meta(get_the_ID());
        $meta['_reepay_discount_apply_to_items'][0] = unserialize($meta['_reepay_discount_apply_to_items'][0]) ?: [];

        $plans = $this->get_plans();
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
            WC_Reepay_Subscriptions::$plugin_path.'templates/'
        );
    }
    public function add_coupon_types($discount_types)
    {
        $discount_types['reepay_percentage'] = 'Reepay Percentage Discount';
        $discount_types['reepay_fixed_product'] = 'Reepay Fixed product Discount';
        return $discount_types;
    }
}

new WC_Reepay_Discounts_And_Coupons();

add_action('init', 'reepay_create_subscription_product_class');