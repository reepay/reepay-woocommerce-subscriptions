<?php

class WC_Reepay_My_Account {
	public function __construct() {
		new WC_Reepay_My_Account_Orders_Page();
		new WC_Reepay_My_Account_Payment_Method();
		new WC_Reepay_My_Account_Subscription_Page();
		new WC_Reepay_My_Account_Subscriptions_Page();
	}
}