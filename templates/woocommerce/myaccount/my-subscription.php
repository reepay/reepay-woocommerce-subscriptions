<?php
/**
 * Subscription details table
 *
 * @var array $subscription     reepay subscription
 * @var array $plan             reepay plan
 * @var array $payment_methods  reepay payment method
 * @var array $dates_to_display subscription dates to display
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<table class="shop_table subscription_details">
    <tbody>
    <tr>
        <td><?php esc_html_e( 'Status', 'reepay-subscriptions-for-woocommerce' ); ?></td>
        <td><?php echo esc_html( WC_Reepay_My_Account_Subscription_Page::get_status( $subscription ) ); ?></td>
    </tr>
    <tr>
        <td><?php esc_html_e( 'Plan', 'reepay-subscriptions-for-woocommerce' ); ?></td>
        <td><?php echo ucfirst( $plan['name'] ) ?>
        </td>
    </tr>

	<?php
	foreach ( $dates_to_display as $date_type => ['label' => $label, 'value' => $value] ) : ?>
		<?php if ( ! empty( $value ) ) : ?>
            <tr>
                <td><?php echo esc_html( $label ); ?></td>
                <td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $value ) ) ); ?></td>
            </tr>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php

	if ( empty( $subscription['is_expired'] ) ): ?>
		<?php if ( reepay_s()->settings( '_reepay_enable_on_hold' ) || reepay_s()->settings( '_reepay_enable_cancel' ) ): ?>
            <tr>
                <td><?php _e( 'Actions:', 'reepay-subscriptions-for-woocommerce' ); ?></td>
                <td>
					<?php if ( $subscription['state'] === 'on_hold' ): ?>
                        <a href="?reactivate=<?php echo esc_attr( $subscription['handle'] ) ?>"
                           class="button"><?php _e( 'Reactivate', 'reepay-subscriptions-for-woocommerce' ); ?></a>
					<?php else: ?>
						<?php if ( reepay_s()->settings( '_reepay_enable_on_hold' ) ): ?>
                            <a href="?put_on_hold=<?php echo esc_attr( $subscription['handle'] ) ?>"
                               class="button"><?php _e( 'Put on hold', 'reepay-subscriptions-for-woocommerce' ); ?></a>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ( $subscription['state'] !== 'on_hold' ): ?>
						<?php if ( $subscription['is_cancelled'] === true ): ?>
                            <a href="?uncancel_subscription=<?php echo esc_attr( $subscription['handle'] ) ?>"
                               class="button"><?php _e( 'Uncancel', 'reepay-subscriptions-for-woocommerce' ); ?></a>
						<?php else: ?>
							<?php if ( reepay_s()->settings( '_reepay_enable_cancel' ) ): ?>
                                <a href="?cancel_subscription=<?php echo esc_attr( $subscription['handle'] ) ?>"
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
		<?php foreach ( $payment_methods as $payment_method ):
			if ( 'card' !== $payment_method['payment_type'] ) {
				continue;
			}

			$card = $payment_method['card'];
			[ $month, $year ] = explode( '-', $card['exp_date'] );
			?>
            <tr>
                <td><?php echo $card['masked_card'] ?> <?php echo "$month/$year" ?></td>
                <td>
					<?php if ( false && $payment_method['id'] === $payment_methods[0]['id'] ): ?>
						<?php _e( 'Current', 'reepay-subscriptions-for-woocommerce' ); ?>
					<?php else: ?>
                        <a href="?change_payment_method=<?php _e( $subscription['handle'] ) ?>&token_id=<?php echo esc_html( $payment_method['id']) ?>"
                           class="button"><?php _e( 'Use this card', 'reepay-subscriptions-for-woocommerce' ); ?></a>
					<?php endif; ?>
                </td>
            </tr>

		<?php endforeach; ?>
        <tr>
            <td></td>
            <td>
                <a href="<?php echo wc_get_endpoint_url( 'add-payment-method' ) . '?reepay_subscription=' . esc_attr( $subscription['handle'] ) ?>"
                   class="button">
					<?php _e( 'Add payment method', 'reepay-subscriptions-for-woocommerce' ); ?>
                </a>
            </td>
        </tr>
	<?php endif; ?>
    </tbody>
</table>