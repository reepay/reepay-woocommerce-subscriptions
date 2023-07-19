<?php

class WC_Reepay_Subscription_Coupons_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest {
	public function init() {
		$this->namespace = reepay_s()->settings( 'rest_api_namespace' );
		$this->rest_base = "/coupon/";
	}

	/**
	 * Get all reepay coupons
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 *
	 */
	public function get_list( $request ) {
		try {
			$html   = '<option value="">' . __( 'Select coupon', 'reepay-subscriptions-for-woocommerce' ) . '</option>';
			$addons = WC_Reepay_Discounts_And_Coupons::get_coupons();

			foreach ( $addons ?? [] as $addon ) {
				$html .= '<option value="' . $addon['handle'] . '">' . esc_attr( $addon['name'] ) . '</option>';
			}

			return new WP_REST_Response( [
				'success' => true,
				'html'    => $html,
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
	 * Get reepay coupon info by handle
	 *
	 * @param  WP_REST_Request  $request  Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 * @throws Exception
	 *
	 */
	public function get_info( $request ) {
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
					'is_update' => false,
					'loop'      => - 1,
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
