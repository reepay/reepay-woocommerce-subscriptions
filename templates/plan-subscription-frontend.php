<?php
/**
 * @var string $billing_plan
 * @var string $trial
 * @var string $contract_periods
 * @var string $setup_fee
 * @var string $domain
 */

?>

<ul class="reepay_subscription_info">
    <?php if (!empty($billing_plan)) : ?>
        <li><?php echo $billing_plan ?></li>
    <?php endif; ?>

    <?php if (!empty($trial)) : ?>
        <li><?php echo $trial ?></li>
    <?php endif; ?>

    <?php if (!empty($contract_periods)) : ?>
        <li><?php esc_html_e('Contract Period', $domain) . ': ' . $contract_periods ?></li>
    <?php endif; ?>

    <?php if (!empty($setup_fee)) : ?>
        <li><?php echo __($setup_fee, $domain) ?></li>
    <?php endif; ?>
</ul>
