<?php
/**
 * Subscription details table
 *
 * @author  Prospress
 * @package WooCommerce_Subscription/Templates
 * @since 2.2.19
 * @version 2.6.5
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$user_payment_methods = wc_get_customer_saved_methods_list( get_current_user_id() );
$user_payment_methods_reepay = [];

foreach ( $user_payment_methods['reepay'] ?? [] as $user_payment_method ) {
	$user_payment_methods_reepay[] = WC_Payment_Tokens::get( $user_payment_method['method']['id'] );
}

?>
<table class="shop_table subscription_details">
    <tbody>
    <tr>
        <td><?php esc_html_e( 'Status', 'woocommerce-subscriptions' ); ?></td>
        <td><?php echo esc_html( ucfirst( $subscription->get_status() ) ); ?></td>
    </tr>
	<?php do_action( 'wcs_subscription_details_table_before_dates', $subscription ); ?>
	<?php do_action( 'wcs_subscription_details_table_after_dates', $subscription ); ?>
	<?php do_action( 'wcs_subscription_details_table_before_payment_method', $subscription ); ?>
	<?php do_action( 'woocommerce_subscription_before_actions', $subscription ); ?>
	<?php do_action( 'woocommerce_subscription_after_actions', $subscription ); ?>

	<?php
	try {
		$reepay_subscription = reepay_s()->api()->request( "subscription/" . $subscription->get_meta( '_reepay_subscription_handle' ) );
		$payment_methods = reepay_s()->api()->request( "subscription/" . $subscription->get_meta( '_reepay_subscription_handle' ) . "/pm" );
	} catch (Exception $e) {
		$reepay_subscription = false;
	}

	if ( ! empty( $reepay_subscription ) && empty( $reepay_subscription['is_expired'] ) ): ?>
		<?php if ( reepay_s()->settings( '_reepay_enable_on_hold' ) || reepay_s()->settings( '_reepay_enable_cancel' ) ): ?>
            <tr>
                <td><?php _e( 'Actions:', 'reepay-subscriptions-for-woocommerce' ); ?></td>
                <td>
					<?php if ( $reepay_subscription['state'] === 'on_hold' ): ?>
                        <a href="?reactivate=<?php echo esc_attr( $reepay_subscription['handle'] ) ?>"
                           class="button"><?php _e( 'Reactivate', 'reepay-subscriptions-for-woocommerce' ); ?></a>
					<?php else: ?>
						<?php if ( reepay_s()->settings( '_reepay_enable_on_hold' ) ): ?>
                            <a href="?put_on_hold=<?php echo esc_attr( $reepay_subscription['handle'] ) ?>"
                               class="button"><?php _e( 'Put on hold', 'reepay-subscriptions-for-woocommerce' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( $reepay_subscription['state'] !== 'on_hold' ): ?>
						<?php if ( $reepay_subscription['is_cancelled'] === true ): ?>
                            <a href="?uncancel_subscription=<?php echo esc_attr( $reepay_subscription['handle'] ) ?>"
                               class="button"><?php _e( 'Uncancel', 'reepay-subscriptions-for-woocommerce' ); ?></a>
						<?php else: ?>
							<?php if ( reepay_s()->settings( '_reepay_enable_cancel' ) ): ?>
                                <a href="?cancel_subscription=<?php echo esc_attr( $reepay_subscription['handle'] ) ?>"
                                   class="button"><?php _e( 'Cancel Subscription', 'reepay-subscriptions-for-woocommerce' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>
					<?php endif; ?>
                </td>
            </tr>
		<?php endif; ?>

        <tr>
            <td><?php _e( 'Payment methods:', 'reepay-subscriptions-for-woocommerce' ); ?></td>
            <td></td>
        </tr>
		<?php foreach ( $user_payment_methods_reepay ?? [] as $payment_method ): ?>
            <tr>
                <td><?php echo $payment_method->get_masked_card() ?><?php echo $payment_method->get_expiry_month() . '/' . $payment_method->get_expiry_year() ?></td>
                <td>
					<?php if ( $payment_method->get_token() === $payment_methods[0]['id'] ): ?>
						<?php _e( 'Current', 'reepay-subscriptions-for-woocommerce' ); ?>
					<?php else: ?>
                        <a href="?change_payment_method=<?php echo __( $reepay_subscription['handle'] ) ?>&token_id=<?php echo esc_html( $payment_method->get_id() ) ?>"
                           class="button"><?php _e( 'Change', 'reepay-subscriptions-for-woocommerce' ); ?></a>
					<?php endif; ?>
                </td>
            </tr>

		<?php endforeach; ?>
        <tr>
            <td></td>
            <td>
                <a href="<?php echo wc_get_endpoint_url( 'add-payment-method' ) . '?reepay_subscription=' . esc_attr( $reepay_subscription['handle'] ) ?>"
                   class="button"><?php _e( 'Add payment method', 'reepay-subscriptions-for-woocommerce' ); ?></a></td>
        </tr>
	<?php endif; ?>
    </tbody>
    </tbody>
</table>