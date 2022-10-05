<?php
/**
 * @var string $billing_plan
 * @var string $trial
 * @var string $contract_period
 * @var string $setup_fee
 * @var string 'reepay-subscriptions-for-woocommerce'
 * @var boolean $is_checkout
 */
?>
<div class="reepay_subscription_info_container">
    <?php
	if ( ! $is_checkout ) : ?>
        <h4><?php _e( 'Subscription details', 'reepay-subscriptions-for-woocommerce' ) ?></h4>
	<?php endif; ?>

    <ul class="reepay_subscription_info">
		<?php if ( ! empty( $billing_plan ) ) : ?>
            <li><?php echo esc_html( $billing_plan ) ?></li>
		<?php endif; ?>

		<?php if ( ! empty( $trial ) ) : ?>
            <li><?php echo esc_html( $trial ) ?></li>
		<?php endif; ?>

		<?php if ( ! empty( $contract_period ) ) : ?>
            <li><?php echo esc_html( $contract_period ) ?></li>
		<?php endif; ?>

		<?php if ( ! empty( $setup_fee ) ) : ?>
            <li><?php echo wp_kses_post( $setup_fee ) ?></li>
		<?php endif; ?>
    </ul>
</div>
