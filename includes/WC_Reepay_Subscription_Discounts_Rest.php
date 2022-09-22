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
            $discount_data = WC_Reepay_Discounts_And_Coupons::get_existing_discount($request['handle']);

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

