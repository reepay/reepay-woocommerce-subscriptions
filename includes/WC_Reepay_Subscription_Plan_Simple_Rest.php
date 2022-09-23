<?php

class WC_Reepay_Subscription_Plan_Simple_Rest extends WP_REST_Controller
{
    public function __construct()
    {
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function get_url()
    {
        return get_rest_url(0, $this->namespace . $this->rest_base);
    }

    public function init()
    {
        $this->namespace = reepay_s()->settings('rest_api_namespace');
        $this->rest_base = "/plan_simple/";
    }

    public function register_routes()
    {
        register_rest_route($this->namespace, $this->rest_base, [
            "methods" => WP_REST_Server::READABLE,
            "callback" => array($this, "get_item"),
            "permission_callback" => array($this, "get_item_permissions_check"),
            "args" => array(
                "handle" => array(
                    "type" => "string",
                    "required" => true,
                    "validate_callback" => function ($param, $request, $key) {
                        return !empty($param);
                    },
                ),
            )
        ]);
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
            $plan_meta_data = reepay_s()->plan($request['product_id'])->get_remote_plan_meta($request['handle']);
            $plan_meta_data['disabled'] = true;
            $plan_meta_data['plans_list'] = reepay_s()->plan()->get_reepay_plans_list() ?: [];
            $plan_meta_data['domain'] = reepay_s()->settings('domain');
            $plan_meta_data['is_exist'] = true;
            $plan_meta_data['is_creating_new_product'] = false;

	        if ( isset( $request['loop'] ) ) {
				$plan_meta_data['loop'] = $request['loop'];
	        }

            foreach (WC_Reepay_Subscription_Plan_Simple::$meta_fields as $key) {
                if (!isset($plan_meta_data[$key])) {
                    $plan_meta_data[$key] = '';
                }
            }

            ob_start();
            wc_get_template(
                'plan-subscription-fields-data.php',
                $plan_meta_data,
                '',
                reepay_s()->settings('plugin_path') . 'templates/'
            );

            return new WP_REST_Response([
                'success' => true,
                'html' => ob_get_clean(),
            ]);
        } catch (Exception $e) {
            reepay_s()->log()->log([
                'source' => 'WC_Reepay_Subscription_Plan_Simple_Rest::get_item',
                'message' => 'Getting plan error',
                'handle' => $request['handle']
            ], 'error');

            return new WP_Error(400, $e->getMessage());
        }
    }

    /**
     * Checks if a given request has access to get a specific item.
     *
     * @param WP_REST_Request $request Full details about the request.
     *
     * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
     * @since 4.7.0
     *
     */
    public function get_item_permissions_check($request)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        //@todo сделать проверку прав и авторизацию эта работает криво
        /*if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(401, 'Not authorized or no access');
        }*/

        return true;
    }
}

