<?php
/**
 * My Subscriptions section on the My Account page
 *
 * @author   Prospress
 * @category WooCommerce Subscriptions/Templates
 * @version  2.6.4
 *
 * @var array $subscriptions subscriptions data to output
 *
 * @see WC_Reepay_My_Account_Subscriptions_Page::subscriptions_endpoint
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="woocommerce_account_subscriptions">
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
                <span class="nobr"><?php echo esc_html_x( 'Amount', 'table heading', 'reepay-subscriptions-for-woocommerce' ); ?></span>
            </th>
            <th class="subscription-actions order-actions woocommerce-orders-table__header woocommerce-orders-table__header-order-actions woocommerce-orders-table__header-subscription-actions">
                &nbsp;
            </th>
        </tr>
        </thead>

        <tbody>
		<?php foreach ( $subscriptions as $subscription ) : ?>
            <tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $subscription['state'] ); ?>">
                <td class="subscription-id order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-id woocommerce-orders-table__cell-order-number"
                    data-title="<?php esc_attr_e( 'ID', 'reepay-subscriptions-for-woocommerce' ); ?>">
                    <a href="<?php echo esc_url( $subscription['link'] ); ?>"><?php echo esc_html( sprintf( _x( '#%s', 'hash before order number', 'reepay-subscriptions-for-woocommerce' ), $subscription['id'] ) ); ?></a>
                </td>
                <td class="subscription-status order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-status woocommerce-orders-table__cell-order-status"
                    data-title="<?php esc_attr_e( 'Status', 'reepay-subscriptions-for-woocommerce' ); ?>">
	                <?php echo esc_html( WC_Reepay_My_Account_Subscription_Page::get_status( $subscription ) ); ?>
                </td>
                <td class="subscription-next-payment order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-next-payment woocommerce-orders-table__cell-order-date"
                    data-title="<?php echo esc_attr_x( 'Next Payment', 'table heading', 'reepay-subscriptions-for-woocommerce' ); ?>">
	                <?php echo ! empty( $subscription['next_period_start'] ) ? wp_date( get_option( 'date_format' ), strtotime( $subscription['next_period_start'] ) ) : '' ?>
                </td>
                <td class="subscription-total order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-total woocommerce-orders-table__cell-order-total"
                    data-title="<?php echo esc_attr_x( 'Total', 'Used in data attribute. Escaped', 'reepay-subscriptions-for-woocommerce' ); ?>">
	                <?php echo $subscription['amount'] . ' / ' . $subscription['billing_period'] ?>
                </td>
                <td class="subscription-actions order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-subscription-actions woocommerce-orders-table__cell-order-actions">
                    <a href="<?php echo esc_url( $subscription['link'] ) ?>"
                       class="woocommerce-button button view"><?php echo esc_html_x( 'View', 'view a subscription', 'reepay-subscriptions-for-woocommerce' ); ?></a>
                </td>
            </tr>
		<?php endforeach; ?>
        </tbody>

    </table>
</div>
