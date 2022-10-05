<?php

class WC_Reepay_Subscription_Coupons_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest {
	public function init() {
		$this->namespace = reepay_s()->settings( 'rest_api_namespace' );
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
	public function get_item( $request ) {
		try {
			$plans = WC_Reepay_Subscription_Plan_Simple::get_plans_wc();

			$coupon_data = WC_Reepay_Discounts_And_Coupons::get_existing_coupon( $request['handle'] );

			foreach ( $coupon_data as $key => $coupon_datum ) {
				$coupon_data[ $key ] = [ $coupon_datum ];
			}

			ob_start();
			wc_get_template(
				'discounts-and-coupons-fields-data-coupon.php',
				array(
					'meta'      => $coupon_data,
					'plans'     => $plans,
					'is_update' => true,
					'loop'      => - 1,
					'domain'    => 'reepay-subscriptions-for-woocommerce'
				),
				'',
				reepay_s()->settings( 'plugin_path' ) . 'templates/'
			);
			wc_get_template(
				'discounts-and-coupons-fields-data-discount.php',
				array(
					'meta'      => $coupon_data,
					'plans'     => $plans,
					'is_update' => true,
					'loop'      => - 1,
					'domain'    => 'reepay-subscriptions-for-woocommerce'
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
				'source'  => 'WC_Reepay_Subscription_Coupons_Rest::get_item',
				'message' => 'Getting coupon error',
				'handle'  => $request['handle']
			], 'error' );

			return new WP_Error( 400, $e->getMessage() );
		}
	}
}
