<?php

class WC_Reepay_Subscription_Discounts_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest
{
    public function init()
    {
        $this->namespace = reepay_s()->settings('rest_api_namespace');
        $this->rest_base = "/discount/";
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
            $discountObj = reepay_s()->api()->request('discount/' . $request['handle']);

            $discount_data = [];

            $amount = !empty($discountObj['amount']) ? $discountObj['amount'] / 100 : $discountObj['percentage'];

            if (!empty($discountObj['amount'])) {
                $discount_data['_reepay_discount_type'] = 'reepay_fixed_product';
            }

            if (!empty($discountObj['percentage'])) {
                $discount_data['_reepay_discount_type'] = 'reepay_percentage';
            }

            $discount_data['_reepay_discount_name'] = $discountObj['name'];
            $discount_data['_reepay_discount_apply_to'] = empty($discountObj['apply_to']) ? 'all' : 'custom';
            $discount_data['_reepay_discount_apply_to_items'] = $discountObj['apply_to'];
            $discount_data['_reepay_discount_duration'] = 'forever';
            $discount_data['_reepay_discount_amount'] = !empty($amount) ? $amount : 0;

            if (!empty($discountObj['fixed_count'])) {
                $discount_data['_reepay_discount_duration'] = 'fixed_number';
                $discount_data['_reepay_discount_fixed_count'] = $discountObj['fixed_count'];
            }

            if (!empty($discountObj['fixed_period'])) {
                $discount_data['_reepay_discount_duration'] = 'limited_time';
                $discount_data['_reepay_discount_fixed_period'] = $discountObj['fixed_period'];
                $discount_data['_reepay_discount_fixed_period_unit'] = $discountObj['fixed_period_unit'];
            }


            return new WP_REST_Response([
                'success' => true,
                'discount' => $discount_data,
            ]);
        } catch (Exception $e) {
            reepay_s()->log()->log([
                'source' => 'WC_Reepay_Subscription_Discounts_Rest::get_item',
                'message' => 'Getting discount error',
                'handle' => $request['handle']
            ], 'error');

            return new WP_Error(400, $e->getMessage());
        }
    }
}

