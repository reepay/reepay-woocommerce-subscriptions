<?php
/**
 * @var string $billing_plan
 * @var string $trial
 * @var string $contract_periods
 * @var string $setup_fee
 * @var string 'reepay-subscriptions'
 */

?>
<div class="reepay_subscription_info_container">
    <?php if ( empty( $is_checkout ) ) : ?>
        <h4><?php _e( 'Subscription details', 'reepay-subscriptions' ) ?></h4>
    <?php endif; ?>

    <ul class="reepay_subscription_info">
        <?php if ( ! empty( $billing_plan ) ) : ?>
            <li><?php esc_attr_e( $billing_plan ) ?></li>
        <?php endif; ?>

        <?php if ( ! empty( $trial ) ) : ?>
            <li><?php esc_html_e( $trial ) ?></li>
        <?php endif; ?>

        <?php if ( ! empty( $contract_period ) ) : ?>
            <li><?php echo $contract_period ?></li>
        <?php endif; ?>

        <?php if ( ! empty( $setup_fee ) ) : ?>
            <li><?php echo wp_kses_post( $setup_fee ) ?></li>
        <?php endif; ?>
    </ul>
</div>