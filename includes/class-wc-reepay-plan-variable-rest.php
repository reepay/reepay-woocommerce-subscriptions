<?php

class WC_Reepay_Subscription_Plan_Variable_Rest extends WC_Reepay_Subscription_Plan_Simple_Rest {
	public function init() {
		$this->namespace = reepay_s()->settings( 'rest_api_namespace' );
		$this->rest_base = "/plan_variable/";
	}
}

new WC_Reepay_Subscription_Plan_Variable_Rest;