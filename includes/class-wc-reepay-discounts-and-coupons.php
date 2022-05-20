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

    function save_coupon_text_field( $post_id, WC_Coupon $coupon ) {
        if(!empty($_REQUEST)){
            foreach ($_REQUEST as $i => $value){
                if(strpos($i, 'reepay_discount')){
                    update_post_meta( $post_id, $i, $value );
                }
            }
        }

        $apply_items = $_REQUEST['_reepay_discount_apply_to_items'] ?? [];
        $apply_plans = $_REQUEST['_reepay_discount_eligible_plans'];


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


        $discountHandle = 'discount'.$post_id;
        $couponHandle = 'coupon'.$post_id;

        $end = $coupon->get_date_expires()->diff(new DateTime());



        $params = [
            "name" => $coupon->get_description(),
            "description" => $coupon->get_description(),
            "amount" => $amount,
            "percentage" => $percentage,
            "handle" => $discountHandle,
            "apply_to" => $apply_items,
            "fixed_count" => $coupon->get_usage_limit(),
            "fixed_period_unit" => "days",
            "fixed_period" => $end->days,
        ];

        $api = new WC_Reepay_Subscription_API();
        $api->set_params($params);
        $discountObj = $api->request('POST', 'https://api.reepay.com/v1/discount');


        $paramsCoupon = [
            "name" => $coupon->get_description(),
            "handle" => $couponHandle,
            "code" => $coupon->get_code(),
            "discount" => $discountObj->handle,
            "all_plans" => empty($apply_plans),
            "eligible_plans" => $apply_plans,
            "max_redemptions" => $coupon->get_usage_limit(),
            "valid_until" => $end->format(DATE_ISO8601),
        ];

        $apiCoupons = new WC_Reepay_Subscription_API();
        $apiCoupons->set_params($paramsCoupon);
        $result2 = $apiCoupons->request('POST', 'https://api.reepay.com/v1/coupon');

        try{
            update_post_meta($post_id, '_reepay_discount_handle', $discountHandle);
            update_post_meta($post_id, '_reepay_coupon_handle', $couponHandle);
        }catch (Exception $e){
            var_dump($e->getMessage());
            return;
        }
    }

    function add_coupon_text_field() {
        $meta = get_post_meta(get_the_ID());
        $meta['_reepay_discount_apply_to_items'][0] = unserialize($meta['_reepay_discount_apply_to_items'][0]);
        wc_get_template(
            'discounts-and-coupons-fields.php',
            array(
                'meta' => $meta,
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