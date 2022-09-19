<?php

class WC_Reepay_Subscription_Addons_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest
{
    public function init()
    {
        $this->namespace = reepay_s()->settings('rest_api_namespace');
        $this->rest_base = "/addon/";
    }

    /**
     * Retrieves one item from the collection.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     * @throws Exception
     * @since 4.7.0
     *
     */
    public function get_item($request)
    {
        try {
            $addon_data = reepay_s()->api()->request("add_on/{$request['handle']}");

            if (isset($request['amount'])) {
                return new WP_REST_Response([
                    'success' => true,
                    'amount' => $addon_data['amount'] / 100,
                ]);
            }

            $addon_data['choose'] = 'exist';
            $addon_data['disabled'] = true;
            $addon_data['amount'] = $addon_data['amount'] / 100;
            $addon_data['avai'] = $addon_data['all_plans'] ? 'all' : 'current';
            ob_start();
            wc_get_template(
                'admin-addon-single-data.php',
                array(
                    'addon' => $addon_data,
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
                'source' => 'WC_Reepay_Subscription_Addons_Rest::get_item',
                'message' => 'Getting add_on error',
                'handle' => $request['handle']
            ], 'error');

            return new WP_Error(400, $e->getMessage());
        }
    }
}

