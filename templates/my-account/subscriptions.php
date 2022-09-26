<?php


$user_payment_methods = wc_get_customer_saved_methods_list( get_current_user_id() );

$user_payment_methods2 = [];

foreach ( $user_payment_methods['reepay'] ?? [] as $user_payment_method ) {
	$user_payment_methods2[] = WC_Payment_Tokens::get( $user_payment_method['method']['id'] );
}

?>
<?php foreach ( $args['subscriptions'] as $subscription ): ?>
	<?php
	$plan                        = $args['plans'][ $subscription['plan'] ];
	$is_expired                  = $subscription['state'] === 'expired';
	$subscription_payment_method = $subscription['payment_methods'][0] ?? [];
	?>
    <h1><?php echo esc_attr( $plan['name'] ) ?></h1>
    <table>
        <tbody>
        <tr>
            <td><?php _e( 'Status', 'reepay-subscriptions' ); ?>:</td>
            <td>
                <span style="text-transform: capitalize">
                    <?php if ( $subscription['state'] === 'expired' ): ?>
	                    <?php _e( 'Expired', 'reepay-subscriptions' ); ?><?php echo esc_attr( $subscription['formatted_expired_date'] ) ?>
                    <?php else: ?>
	                    <?php echo esc_attr( $subscription['formatted_status'] ) ?>
	                    <?php if ( $subscription['renewing'] === false ): ?>
		                    <?php _e( 'Non-renewing', 'reepay-subscriptions' ); ?>
	                    <?php endif; ?>
                    <?php endif; ?>
                </span>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'First period start', 'reepay-subscriptions' ); ?>:</td>
            <td>
				<?php if ( ! empty( $subscription['first_period_start'] ) ): ?>
					<?php echo esc_attr( $subscription['formatted_first_period_start'] ) ?>
				<?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Current period', 'reepay-subscriptions' ); ?>:</td>
            <td>
				<?php if ( ! empty( $subscription['current_period_start'] ) ): ?>
					<?php echo esc_attr( $subscription['formatted_current_period_start'] ) . '-' . esc_attr( $subscription['formatted_next_period_start'] ) ?>
				<?php else: ?>
					<?php _e( 'No Active period', 'reepay-subscriptions' ); ?>
				<?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Total Amount (Incl. VAT)', 'reepay-subscriptions' ); ?>:</td>
            <td>
				<?php echo number_format( esc_attr( $plan['amount'] ) / 100, 2 ) ?> <?php echo esc_attr( $plan['currency'] ) ?>
                / <?php echo $subscription['formatted_schedule'] ?>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Billing Cycle', 'reepay-subscriptions' ); ?>:</td>
            <td>
				<?php if ( ! empty( $plan['fixed_count'] ) ): ?>
					<?php _e( '1 out of', 'reepay-subscriptions' ); ?><?php echo esc_attr( $plan['fixed_count'] ) ?>
				<?php else: ?>
					<?php _e( 'Forever Until Canceled', 'reepay-subscriptions' ); ?>
				<?php endif; ?>
            </td>
        </tr>
		<?php if ( ! $is_expired ): ?>
			<?php if ( reepay_s()->settings( '_reepay_enable_on_hold' ) || reepay_s()->settings( '_reepay_enable_cancel' ) ): ?>
                <tr>
                    <td><?php _e( 'Actions:', 'reepay-subscriptions' ); ?></td>
                    <td>
						<?php if ( $subscription['state'] === 'on_hold' ): ?>
                            <a href="?reactivate=<?php echo esc_attr( $subscription['handle'] ) ?>"
                               class="button"><?php _e( 'Reactivate', 'reepay-subscriptions' ); ?></a>
						<?php else: ?>
							<?php if ( reepay_s()->settings( '_reepay_enable_on_hold' ) ): ?>
                                <a href="?put_on_hold=<?php echo esc_attr( $subscription['handle'] ) ?>"
                                   class="button"><?php _e( 'Put on hold', 'reepay-subscriptions' ); ?></a>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( $subscription['state'] !== 'on_hold' ): ?>
							<?php if ( $subscription['is_cancelled'] === true ): ?>
                                <a href="?uncancel_subscription=<?php echo esc_attr( $subscription['handle'] ) ?>"
                                   class="button"><?php _e( 'Uncancel', 'reepay-subscriptions' ); ?></a>
							<?php else: ?>
								<?php if ( reepay_s()->settings( '_reepay_enable_cancel' ) ): ?>
                                    <a href="?cancel_subscription=<?php echo esc_attr( $subscription['handle'] ) ?>"
                                       class="button"><?php _e( 'Cancel Subscription', 'reepay-subscriptions' ); ?></a>
								<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
                    </td>
                </tr>
			<?php endif; ?>

            <tr>
                <td><?php _e( 'Payment methods:', 'reepay-subscriptions' ); ?></td>
                <td></td>
            </tr>
			<?php foreach ( $user_payment_methods2 ?? [] as $payment_method ): ?>
                <tr>
                    <td><?php echo $payment_method->get_masked_card() ?><?php echo $payment_method->get_expiry_month() . '/' . $payment_method->get_expiry_year() ?></td>
                    <td>
						<?php if ( $payment_method->get_token() === $subscription_payment_method['id'] ): ?>
							<?php _e( 'Current', 'reepay-subscriptions' ); ?>
						<?php else: ?>
                            <a href="?change_payment_method=<?php esc_html_e( $subscription['handle'] ) ?>&token_id=<?php esc_html_e( $payment_method->get_id() ) ?>"
                               class="button"><?php _e( 'Change', 'reepay-subscriptions' ); ?></a>
						<?php endif; ?>
                    </td>
                </tr>

			<?php endforeach; ?>
            <tr>
                <td></td>
                <td>
                    <a href="<?php echo wc_get_endpoint_url( 'add-payment-method' ) . '?reepay_subscription=' . esc_attr( $subscription['handle'] ) ?>"
                       class="button"><?php _e( 'Add payment method', 'reepay-subscriptions' ); ?></a></td>
            </tr>
		<?php endif; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
	<?php if ( $args['current_token'] !== "" && $args['previous_token'] !== null ) : ?>
        <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button"
           href="<?php esc_url_e( wc_get_endpoint_url( 'subscriptions', $args['previous_token'] ) ); ?>"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></a>
	<?php endif; ?>

	<?php if ( ! empty( $args['next_page_token'] ) ) : ?>
        <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button"
           href="<?php esc_url_e( wc_get_endpoint_url( 'subscriptions', $args['next_page_token'] ) ); ?>"><?php esc_html_e( 'Next', 'woocommerce' ); ?></a>
	<?php endif; ?>
</div>