<?php

class WC_Reepay_Subscription_Plan_Simple_Rest extends WP_REST_Controller {
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function get_url() {
		return get_rest_url( 0, $this->namespace . $this->rest_base );
	}

	public function init() {
		$this->namespace = reepay_s()->settings( 'rest_api_namespace' );
		$this->rest_base = "/plan_simple/";
	}

	public function register_routes() {
		register_rest_route( $this->namespace, $this->rest_base, [
			"methods"             => WP_REST_Server::READABLE,
			"callback"            => array( $this, "get_item" ),
			"permission_callback" => array( $this, "get_item_permissions_check" ),
			"args"                => array(
				"handle" => array(
					"type"              => "string",
					"required"          => false,
				),
				"product_id" => array(
					"type"              => "string",
					"required"          => false,
				),
			)
		] );
	}

	/**
	 * Retrieves one item from the collection.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 *
	 */
	public function get_item( $request ) {
		if ( !empty( $request['get_list'] ) ) {
			return $this->get_list( $request );
		} else {
			return $this->get_info( $request );
		}
	}

	/**
	 * Get all reepay plans
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 *
	 */
	public function get_list( $request ) {
		try {
			ob_start();

			wc_get_template(
				'plan-subscription-plans-select.php', [
					'plans_list' => reepay_s()->plan( $request['product_id'] ?? 0 )->get_reepay_plans_list(),
					'current'    => $request['handle'],
					'loop'       => $request['loop'] ?? '',
					'data_plan'  => json_encode( [
						'product_id' => $request['product_id'] ?? 0,
						'loop'       => $request['loop'] ?? '',
					] ),
				],
				'',
				reepay_s()->settings( 'plugin_path' ) . 'templates/'
			);

			return new WP_REST_Response( [
				'success' => true,
				'html'    => ob_get_clean(),
			] );
		} catch ( Exception $e ) {
			reepay_s()->log()->log( [
				'source'  => 'WC_Reepay_Subscription_Plan_Simple_Rest::get_item',
				'message' => 'Getting plan error',
				'handle'  => $request['handle']
			], 'error' );

			return new WP_Error( 400, $e->getMessage() );
		}
	}

	/**
	 * Get reepay plan info by handle
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 *
	 */
	public function get_info( $request ) {
		try {
			$plan = reepay_s()->plan( $request['product_id'] );
			$plan_meta_data                            = $plan->get_remote_plan_meta( $request['handle'] );
			$plan_meta_data['disabled']                = true;
			$plan_meta_data['plans_list']              = reepay_s()->plan()->get_reepay_plans_list() ?: [];
			$plan_meta_data['domain']                  = 'reepay-subscriptions-for-woocommerce';

			if ( isset( $request['loop'] ) ) {
				$plan_meta_data['loop'] = $request['loop'];
			}

			foreach ( WC_Reepay_Subscription_Plan_Simple::$meta_fields as $key ) {
				if ( ! isset( $plan_meta_data[ $key ] ) ) {
					$plan_meta_data[ $key ] = '';
				}
			}

			return new WP_REST_Response( [
				'success' => true,
				'html'    => $plan->get_plan_fields_data_template( $plan_meta_data ),
			] );
		} catch ( Exception $e ) {
			reepay_s()->log()->log( [
				'source'  => 'WC_Reepay_Subscription_Plan_Simple_Rest::get_item',
				'message' => 'Getting plan error',
				'handle'  => $request['handle']
			], 'error' );

			return new WP_Error( 400, $e->getMessage() );
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
	public function get_item_permissions_check( $request ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return true;
		}

		//@todo сделать проверку прав и авторизацию эта работает криво
		/*if ( ! current_user_can( 'edit_posts' ) ) {
			return new WP_Error(401, 'Not authorized or no access');
		}*/

		return true;
	}
}

