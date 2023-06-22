<?php
/**
 * My Subscriptions section on the My Account page
 *
 * @author   Prospress
 * @category WooCommerce Subscriptions/Templates
 * @version  2.6.4
 *
 * @var array $subscriptions Reepay subscriptions https://reference.reepay.com/api/#the-subscription-object
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="woocommerce_account_subscriptions">

	<?php if ( ! empty( $subscriptions ) ) : ?>
        <table class="my_account_subscriptions my_account_orders woocommerce-orders-table woocommerce-MyAccount-subscriptions shop_table shop_table_responsive woocommerce-orders-table--subscriptions">

            <thead>
            <tr>
                <th class="subscription-id order-number woocommerce-orders-table__header woocommerce-orders-table__header-order-number woocommerce-orders-table__header-subscription-id">
                    <span class="nobr"><?php esc_html_e( 'Subscription', 'reepay-subscriptions-for-woocommerce' ); ?></span></th>
                <th class="subscription-status order-status woocommerce-orders-table__header woocommerce-orders-table__header-order-status woocommerce-orders-table__header-subscription-status">
                    <span class="nobr"><?php esc_html_e( 'Status', 'reepay-subscriptions-for-woocommerce' ); ?></span></th>
                <th class="subscription-next-payment order-date woocommerce-orders-table__header woocommerce-orders-table__header-order-date woocommerce-orders-table__header-subscription-next-payment">
                    <span class="nobr"><?php echo esc_html_x( 'Next payment', 'table heading', 'reepay-subscriptions-for-woocommerce' ); ?></span>
                </th>
                <th class="subscription-total order-total woocommerce-orders-table__header woocommerce-orders-table__header-order-total woocommerce-orders-table__header-subscription-total">
                    <span class="nobr"><?php echo esc_html_x( 'Total', 'table heading', 'reepay-subscriptions-for-woocommerce' ); ?></span>
                </th>
                <th class="subscription-actions order-actions woocommerce-orders-table__header woocommerce-orders-table__header-order-actions woocommerce-orders-table__header-subscription-actions">
                    &nbsp;
                </th>
            </tr>
            </thead>

            <tbody>
			<?php /** @var WC_Subscription|array $subscription */ ?>
			<?php foreach ( $subscriptions as $subscription_handle => $subscription_data ) :

				?>
                <tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $subscription->get_status() ); ?>">
                    <td class="subscription-id order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-id woocommerce-orders-table__cell-order-number"
                        data-title="<?php esc_attr_e( 'ID', 'reepay-subscriptions-for-woocommerce' ); ?>">
                        <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( sprintf( _x( '#%s', 'hash before order number', 'reepay-subscriptions-for-woocommerce' ), $subscription->get_order_number() ) ); ?></a>
						<?php do_action( 'woocommerce_my_subscriptions_after_subscription_id', $subscription ); ?>
                    </td>
                    <td class="subscription-status order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-status woocommerce-orders-table__cell-order-status"
                        data-title="<?php esc_attr_e( 'Status', 'reepay-subscriptions-for-woocommerce' ); ?>">
						<?php echo esc_html( wc_get_order_status_name( $subscription->get_status() ) ); ?>
                    </td>
                    <td class="subscription-next-payment order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-next-payment woocommerce-orders-table__cell-order-date"
                        data-title="<?php echo esc_attr_x( 'Next Payment', 'table heading', 'reepay-subscriptions-for-woocommerce' ); ?>">
						<?php echo WC_Reepay_Renewals::get_reepay_subscription_dates( $subscription, 'next_period_start' ) ?: '-' ?>
                    </td>
                    <td class="subscription-total order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-total woocommerce-orders-table__cell-order-total"
                        data-title="<?php echo esc_attr_x( 'Total', 'Used in data attribute. Escaped', 'reepay-subscriptions-for-woocommerce' ); ?>">
						<?php echo $subscription->get_formatted_order_total() ?><?php echo ! empty( $billing_type ) ? ' / ' . $billing_type : '' ?>
                    </td>
                    <td class="subscription-actions order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-actions woocommerce-orders-table__cell-order-actions">
                        <a href="<?php echo esc_url( $link ) ?>"
                           class="woocommerce-button button view"><?php echo esc_html_x( 'View', 'view a subscription', 'reepay-subscriptions-for-woocommerce' ); ?></a>
						<?php do_action( 'woocommerce_my_subscriptions_actions', $subscription ); ?>
                    </td>
                </tr>
			<?php endforeach; ?>
            </tbody>

        </table>
	<?php else : ?>
        <p class="no_subscriptions woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
            <?php esc_html_e( 'You have no active subscriptions.', 'reepay-subscriptions-for-woocommerce' ); ?>

            <a class="woocommerce-Button button"
               href="<?php echo esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ); ?>">
		        <?php esc_html_e( 'Browse products', 'reepay-subscriptions-for-woocommerce' ); ?>
            </a>
        </p>
	<?php endif; ?>

</div>
