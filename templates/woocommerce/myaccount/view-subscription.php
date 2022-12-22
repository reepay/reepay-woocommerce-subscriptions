<?php
/**
 * View Subscription
 *
 * Shows the details of a particular subscription on the account page
 *
 * @author  Prospress
 * @package WooCommerce_Subscription/Templates
 * @version 2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

wc_print_notices();

/**
 * Gets subscription details table template
 * @param WC_Subscription $subscription A subscription object
 * @since 2.2.19
 */
do_action( 'woocommerce_subscription_details_table', $subscription );
if ( ! class_exists( 'WC_Subscriptions' ) ) {
	wc_get_template(
		'myaccount/subscription-details.php',
		array( 'subscription' => $subscription ),
		'',
		reepay_s()->settings( 'plugin_path' ) . 'templates/'
	);
}
/**
 * Gets subscription totals table template
 * @param WC_Subscription $subscription A subscription object
 * @since 2.2.19
 */
do_action( 'woocommerce_subscription_totals_table', $subscription );

if ( is_a( $subscription, 'WC_Subscription' ) ) {
	do_action( 'woocommerce_subscription_details_after_subscription_table', $subscription );
}

wc_get_template( 'order/order-details-customer.php', array( 'order' => $subscription ) );
?>

<div class="clear"></div>
