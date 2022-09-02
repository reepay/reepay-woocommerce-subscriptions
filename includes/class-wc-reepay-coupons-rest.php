<?php

class WC_Reepay_Subscription_Coupons_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest
{
    public function init()
    {
        $this->namespace = reepay_s()->settings('rest_api_namespace');
        $this->rest_base = "/coupon/";
    }

    /**
     * Retrieves one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     * @throws Exception
     * @since 4.7.0
     */
    public function get_item($request)
    {
        try {
            $couponObj = reepay_s()->api()->request('coupon/' . $request['handle']);
            $discountObj = reepay_s()->api()->request('discount/' . $couponObj['discount']);
            $plans = WC_Reepay_Subscription_Plan_Simple::get_plans_wc();

            $coupon_data = [];

            $coupon_data['_reepay_discount_name'] = [$couponObj['name']];
            $coupon_data['_reepay_discount_apply_to'] = empty($discountObj['apply_to']) ? ['all'] : ['custom'];
            $coupon_data['_reepay_discount_apply_to_items'] = [$discountObj['apply_to']];
            $coupon_data['_reepay_discount_all_plans'] = [$couponObj['all_plans']];
            $coupon_data['_reepay_discount_eligible_plans'] = [$couponObj['eligible_plans']];
            $coupon_data['_reepay_discount_duration'] = ['forever'];
            $coupon_data['_reepay_discount_amount'] = [!empty($discountObj['amount']) ? $discountObj['amount'] / 100 : 0];

            if (!empty($discountObj['fixed_count'])) {
                $coupon_data['_reepay_discount_duration'] = ['fixed_number'];
                $coupon_data['_reepay_discount_fixed_count'] = [$discountObj['fixed_count']];
            }

            if (!empty($discountObj['fixed_period'])) {
                $coupon_data['_reepay_discount_duration'] = ['limited_duration'];
                $coupon_data['_reepay_discount_fixed_period'] = [$discountObj['fixed_period']];
                $coupon_data['_reepay_discount_fixed_period_unit'] = [$discountObj['fixed_period_unit']];
            }

            ob_start();
            wc_get_template(
                'discounts-and-coupons-fields-data.php',
                array(
                    'meta' => $coupon_data,
                    'plans' => $plans,
                    'is_update' => true,
                    'loop' => -1,
                    'domain' => reepay_s()->settings('domain')
                ),
                '',
                reepay_s()->settings('plugin_path') . 'templates/'
            );

            return new WP_REST_Response([
                'success' => true,
                'html' => ob_get_clean(),
            ]);
        } catch (Exception $e) {
            reepay_s()->log()->log([
                'source' => 'WC_Reepay_Subscription_Coupons_Rest::get_item',
                'message' => 'Getting coupon error',
                'handle' => $request['handle']
            ], 'error');

            return new WP_Error(400, $e->getMessage());
        }
    }
}

new WC_Reepay_Subscription_Coupons_Rest();
