<?php
/**
 * Subscription details table
 *
 * @author  Prospress
 * @package WooCommerce_Subscription/Templates
 * @since 2.2.19
 * @version 2.6.5
 *
 * @var WC_Order $subscription
 * @var array<string, mixed>|WP_Error $subscription_reepay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$user_payment_methods        = wc_get_customer_saved_methods_list( get_current_user_id() );
$user_payment_methods_reepay = [];

foreach ( $user_payment_methods['reepay'] ?? [] as $user_payment_method ) {
	$user_payment_methods_reepay[] = WC_Payment_Tokens::get( $user_payment_method['method']['id'] );
}

$subscription_reepay = reepay_s()->api()->request( "subscription/" . $subscription->get_meta( '_reepay_subscription_handle' ) );

$plan_data = reepay_s()->api()->request( "plan/" . $subscription_reepay['plan'] . "/current" );

?>
<table class="shop_table subscription_details">
    <tbody>
    <tr>
        <td><?php esc_html_e( 'Status', 'reepay-subscriptions-for-woocommerce' ); ?></td>
        <td><?php echo esc_html( ucfirst( $subscription->get_status() ) ); ?></td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Plan', 'reepay-subscriptions-for-woocommerce' ); ?></td>
        <td><?php echo esc_html( is_wp_error( $plan_data ) ?
				__( 'Undefined', 'reepay-subscriptions-for-woocommerce' )
				: ucfirst( $plan_data['name'] ) ); ?>
        </td>
    </tr>
	<?php do_action( 'wcs_subscription_details_table_before_dates', $subscription ); ?>

	<?php
	if ( is_wp_error( $subscription_reepay ) ) {
		$dates_to_display = [];
	} else {
		$dates_to_display = [
			'start_date'              => [
				'label' => _x( 'Start date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription_reepay['first_period_start'] ?? '',
			],
			'last_order_date_created' => [
				'label' => _x( 'Last payment date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription_reepay['current_period_start'] ?? '',
			],
			'next_payment'            => [
				'label' => _x( 'Next payment date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription_reepay['next_period_start'] ?? '',
			],
			'end'                     => [
				'label' => _x( 'End date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription_reepay['expires'] ?? '',
			],
			'start_end'               => [
				'label' => _x( 'Trial start date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription_reepay['trial_start'] ?? '',
			],
			'trial_end'               => [
				'label' => _x( 'Trial end date', 'customer subscription table header', 'reepay-subscriptions-for-woocommerce' ),
				'value' => $subscription_reepay['trial_end'] ?? '',
			],
		];
	}

	foreach ( $dates_to_display as $date_type => ['label' => $label, 'value' => $value] ) : ?>
		<?php if ( ! empty( $value ) ) : ?>
            <tr>
                <td><?php echo esc_html( $label ); ?></td>
                <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $value ) ) ); ?></td>
            </tr>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php do_action( 'wcs_subscription_details_table_after_dates', $subscription ); ?>
	<?php do_action( 'wcs_subscription_details_table_before_payment_method', $subscription ); ?>
	<?php do_action( 'woocommerce_subscription_before_actions', $subscription ); ?>
	<?php do_action( 'woocommerce_subscription_after_actions', $subscription ); ?>

	<?php
	try {
		$reepay_subscription = reepay_s()->api()->request( "subscription/" . $subscription->get_meta( '_reepay_subscription_handle' ) );
		$payment_methods     = reepay_s()->api()->request( "subscription/" . $subscription->get_meta( '_reepay_subscription_handle' ) . "/pm" );
	} catch ( Exception $e ) {
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
                   class="button">
					<?php _e( 'Add payment method', 'reepay-subscriptions-for-woocommerce' ); ?>
                </a>
            </td>
        </tr>
	<?php endif; ?>
    </tbody>
</table>

<header>
    <h2><?php esc_html_e( 'Related orders', 'woocommerce-subscriptions' ); ?></h2>
</header>

<table class="shop_table shop_table_responsive my_account_orders woocommerce-orders-table woocommerce-MyAccount-orders woocommerce-orders-table--orders">

    <thead>
    <tr>
        <th class="order-number woocommerce-orders-table__header woocommerce-orders-table__header-order-number"><span
                    class="nobr"><?php esc_html_e( 'Order', 'woocommerce-subscriptions' ); ?></span></th>
        <th class="order-date woocommerce-orders-table__header woocommerce-orders-table__header-order-date woocommerce-orders-table__header-order-date">
            <span class="nobr"><?php esc_html_e( 'Date', 'woocommerce-subscriptions' ); ?></span></th>
        <th class="order-status woocommerce-orders-table__header woocommerce-orders-table__header-order-status"><span
                    class="nobr"><?php esc_html_e( 'Status', 'woocommerce-subscriptions' ); ?></span></th>
        <th class="order-total woocommerce-orders-table__header woocommerce-orders-table__header-order-total"><span
                    class="nobr"><?php echo esc_html_x( 'Total', 'table heading', 'woocommerce-subscriptions' ); ?></span>
        </th>
        <th class="order-actions woocommerce-orders-table__header woocommerce-orders-table__header-order-actions">
            &nbsp;
        </th>
    </tr>
    </thead>

    <tbody>
	<?php foreach ( $subscription_orders ?? [] as $subscription_order ) :
		$order = wc_get_order( $subscription_order );

		if ( ! $order ) {
			continue;
		}

		$item_count = $order->get_item_count();
		$order_date = $order->get_date_created();

		?>
        <tr class="order woocommerce-orders-table__row woocommerce-orders-table__row--status-<?php echo esc_attr( $order->get_status() ); ?>">
            <td class="order-number woocommerce-orders-table__cell woocommerce-orders-table__cell-order-number"
                data-title="<?php esc_attr_e( 'Order Number', 'woocommerce-subscriptions' ); ?>">
                <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
					<?php echo sprintf( esc_html_x( '#%s', 'hash before order number', 'woocommerce-subscriptions' ), esc_html( $order->get_order_number() ) ); ?>
                </a>
            </td>
            <td class="order-date woocommerce-orders-table__cell woocommerce-orders-table__cell-order-date"
                data-title="<?php esc_attr_e( 'Date', 'woocommerce-subscriptions' ); ?>">
                <time datetime="<?php echo esc_attr( $order_date->date( 'Y-m-d' ) ); ?>"
                      title="<?php echo esc_attr( $order_date->getTimestamp() ); ?>"><?php echo wp_kses_post( $order_date->date_i18n( wc_date_format() ) ); ?></time>
            </td>
            <td class="order-status woocommerce-orders-table__cell woocommerce-orders-table__cell-order-status"
                data-title="<?php esc_attr_e( 'Status', 'woocommerce-subscriptions' ); ?>" style="white-space:nowrap;">
				<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
            </td>
            <td class="order-total woocommerce-orders-table__cell woocommerce-orders-table__cell-order-total"
                data-title="<?php echo esc_attr_x( 'Total', 'Used in data attribute. Escaped', 'woocommerce-subscriptions' ); ?>">
				<?php
				// translators: $1: formatted order total for the order, $2: number of items bought
				echo wp_kses_post( sprintf( _n( '%1$s for %2$d item', '%1$s for %2$d items', $item_count, 'woocommerce-subscriptions' ), $order->get_formatted_order_total(), $item_count ) );
				?>
            </td>
            <td class="order-actions woocommerce-orders-table__cell woocommerce-orders-table__cell-order-actions">
				<?php $actions = array();

				if ( $order->needs_payment() && wcs_get_objects_property( $order, 'id' ) == $subscription->get_last_order( 'ids', 'any' ) ) {
					$actions['pay'] = array(
						'url'  => $order->get_checkout_payment_url(),
						'name' => esc_html_x( 'Pay', 'pay for a subscription', 'woocommerce-subscriptions' ),
					);
				}

				if ( in_array( $order->get_status(), apply_filters( 'woocommerce_valid_order_statuses_for_cancel', array(
					'pending',
					'failed'
				), $order ) ) ) {
					$redirect = wc_get_page_permalink( 'myaccount' );

					if ( wcs_is_view_subscription_page() ) {
						$redirect = $subscription->get_view_order_url();
					}

					$actions['cancel'] = array(
						'url'  => $order->get_cancel_order_url( $redirect ),
						'name' => esc_html_x( 'Cancel', 'an action on a subscription', 'woocommerce-subscriptions' ),
					);
				}

				$actions['view'] = array(
					'url'  => $order->get_view_order_url(),
					'name' => esc_html_x( 'View', 'view a subscription', 'woocommerce-subscriptions' ),
				);

				$actions = apply_filters( 'woocommerce_my_account_my_orders_actions', $actions, $order );

				if ( $actions ) {
					foreach ( $actions as $key => $action ) {
						echo wp_kses_post( '<a href="' . esc_url( $action['url'] ) . '" class="woocommerce-button button ' . sanitize_html_class( $key ) . '">' . esc_html( $action['name'] ) . '</a>' );
					}
				}
				?>
            </td>
        </tr>
	<?php endforeach; ?>
    </tbody>
</table>