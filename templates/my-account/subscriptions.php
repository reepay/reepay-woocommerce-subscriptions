<?php
/** @var Array $args */

?>
<?php foreach ( $args['subscriptions'] as $subscription ): ?>
    <h1><?php echo esc_attr( $subscription['plan']['name'] ) ?></h1>
    <table>
        <tbody>
        <tr>
            <td><?php _e( 'Status', 'reepay-subscriptions-for-woocommerce' ); ?>:</td>
            <td>
                <span style="text-transform: capitalize">
                    <?php if ( $subscription['state'] === 'expired' ): ?>
	                    <?php _e( 'Expired', 'reepay-subscriptions-for-woocommerce' ); ?><?php echo esc_attr( $subscription['formatted_expired_date'] ) ?>
                    <?php else: ?>
	                    <?php echo esc_attr( $subscription['formatted_status'] ) ?>
	                    <?php if ( $subscription['renewing'] === false ): ?>
		                    <?php _e( 'Non-renewing', 'reepay-subscriptions-for-woocommerce' ); ?>
	                    <?php endif; ?>
                    <?php endif; ?>
                </span>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'First period start', 'reepay-subscriptions-for-woocommerce' ); ?>:</td>
            <td>
				<?php if ( ! empty( $subscription['first_period_start'] ) ): ?>
					<?php echo esc_attr( $subscription['formatted_first_period_start'] ) ?>
				<?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Current period', 'reepay-subscriptions-for-woocommerce' ); ?>:</td>
            <td>
				<?php if ( ! empty( $subscription['current_period_start'] ) ): ?>
					<?php echo esc_attr( $subscription['formatted_current_period_start'] ) . '-' . esc_attr( $subscription['formatted_next_period_start'] ) ?>
				<?php else: ?>
					<?php _e( 'No Active period', 'reepay-subscriptions-for-woocommerce' ); ?>
				<?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Total Amount (Incl. VAT)', 'reepay-subscriptions-for-woocommerce' ); ?>:</td>
            <td>
				<?php echo number_format( esc_attr( $subscription['plan']['amount'] ) / 100, 2 ) ?> <?php echo esc_attr( $subscription['plan']['currency'] ) ?>
                / <?php echo $subscription['formatted_schedule'] ?>
            </td>
        </tr>
        <tr>
            <td><?php _e( 'Billing Cycle', 'reepay-subscriptions-for-woocommerce' ); ?>:</td>
            <td>
				<?php if ( ! empty( $subscription['plan']['fixed_count'] ) ): ?>
					<?php _e( '1 out of', 'reepay-subscriptions-for-woocommerce' ); ?><?php echo esc_attr( $subscription['plan']['fixed_count'] ) ?>
				<?php else: ?>
					<?php _e( 'Forever Until Canceled', 'reepay-subscriptions-for-woocommerce' ); ?>
				<?php endif; ?>
            </td>
        </tr>
		<?php if ( ! $subscription['is_expired'] ): ?>
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
			<?php foreach ( $args['user_payment_methods_reepay'] ?? [] as $payment_method ): ?>
                <tr>
                    <td><?php echo $payment_method->get_masked_card() ?><?php echo $payment_method->get_expiry_month() . '/' . $payment_method->get_expiry_year() ?></td>
                    <td>
						<?php if ( $payment_method->get_token() === $subscription['payment_method']['id'] ): ?>
							<?php _e( 'Current', 'reepay-subscriptions-for-woocommerce' ); ?>
						<?php else: ?>
                            <a href="?change_payment_method=<?php echo __( $subscription['handle'] ) ?>&token_id=<?php echo esc_html( $payment_method->get_id() ) ?>"
                               class="button"><?php _e( 'Change', 'reepay-subscriptions-for-woocommerce' ); ?></a>
						<?php endif; ?>
                    </td>
                </tr>

			<?php endforeach; ?>
            <tr>
                <td></td>
                <td>
                    <a href="<?php echo wc_get_endpoint_url( 'add-payment-method' ) . '?reepay_subscription=' . esc_attr( $subscription['handle'] ) ?>"
                       class="button"><?php _e( 'Add payment method', 'reepay-subscriptions-for-woocommerce' ); ?></a></td>
            </tr>
		<?php endif; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
	<?php if ( $args['current_token'] !== "" && $args['previous_token'] !== null ) : ?>
        <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button"
           href="<?php echo esc_url( wc_get_endpoint_url( 'subscriptions', $args['previous_token'] ) ); ?>"><?php echo __( 'Previous', 'woocommerce' ); ?></a>
	<?php endif; ?>

	<?php if ( ! empty( $args['next_page_token'] ) ) : ?>
        <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button"
           href="<?php echo esc_url( wc_get_endpoint_url( 'subscriptions', $args['next_page_token'] ) ); ?>"><?php echo __( 'Next', 'woocommerce' ); ?></a>
	<?php endif; ?>
</div>
