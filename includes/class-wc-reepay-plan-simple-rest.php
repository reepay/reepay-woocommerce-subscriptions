<?php

class WC_Reepay_Subscription_Plan_Simple_Rest extends WP_REST_Controller {
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function get_path() {
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
					"required"          => true,
					"validate_callback" => function( $param, $request, $key ) {
						return ! empty( $param );
					},
				),
			)
		] );
	}

	/**
	 * Retrieves one item from the collection.
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 * @since 4.7.0
	 *
	 */
	public function get_item( $request ) {
		try {
			return new WP_REST_Response(
				reepay_s()->api()->request( "plan/{$request['handle']}" )[0]
			);
		}catch( Exception $e ) {
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
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return true|WP_Error True if the request has read access for the item, WP_Error object otherwise.
	 * @since 4.7.0
	 *
	 */
	public function get_item_permissions_check( $request ) {
		return true;
	}
}

new WC_Reepay_Subscription_Plan_Simple_Rest();