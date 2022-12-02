<?php

class WC_Reepay_Subscription_Addons_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest {
	public function init() {
		$this->namespace = reepay_s()->settings( 'rest_api_namespace' );
		$this->rest_base = "/addon/";
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
			$html = '<option value="">' . __( 'Select add-on', 'reepay-subscriptions-for-woocommerce' ) . '</option>';
			$addons = WC_Reepay_Subscription_Addons::get_reepay_addons_list( false, true );

			foreach ( $addons['content'] ?? [] as $addon ) {
				$html .= '<option value="' . $addon['handle'] . '" ' . selected( $addon['handle'], $request['handle'], false ) . '>' . esc_attr( $addon['name'] ) . '</option>';
			}

			return new WP_REST_Response( [
				'success' => true,
				'html'    => $html,
			] );
		} catch ( Exception $e ) {
			reepay_s()->log()->log( [
				'source'  => 'WC_Reepay_Subscription_Addons_Rest::get_item',
				'message' => 'Getting addon error',
				'handle'  => $request['handle']
			], 'error' );

			return new WP_Error( 400, $e->getMessage() );
		}
	}

	/**
	 * Get reepay info by handle
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 *
	 */
	public function get_info( $request ) {
		try {
			$addon_data = reepay_s()->api()->request( "add_on/{$request['handle']}" );

			if ( isset( $request['amount'] ) ) {
				return new WP_REST_Response( [
					'success' => true,
					'amount'  => $addon_data['amount'] / 100,
				] );
			}

			$addon_data['choose']   = 'exist';
			$addon_data['disabled'] = true;
			$addon_data['amount']   = $addon_data['amount'] / 100;
			$addon_data['avai']     = $addon_data['all_plans'] ? 'all' : 'current';
			ob_start();
			wc_get_template(
				'admin-addon-single-data.php',
				array(
					'addon'  => $addon_data,
					'loop'   => - 1,
					'domain' => 'reepay-subscriptions-for-woocommerce'
				),
				'',
				reepay_s()->settings( 'plugin_path' ) . 'templates/'
			);

			return new WP_REST_Response( [
				'success' => true,
				'html'    => ob_get_clean(),
			] );
		} catch ( Exception $e ) {
			reepay_s()->log()->log( [
				'source'  => 'WC_Reepay_Subscription_Addons_Rest::get_item',
				'message' => 'Getting add_on error',
				'handle'  => $request['handle']
			], 'error' );

			return new WP_Error( 400, $e->getMessage() );
		}
	}
}

