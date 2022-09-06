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
        <li><?= $billing_plan ?></li>
    <?php endif; ?>

    <?php if (!empty($trial)) : ?>
        <li><?= $trial ?></li>
    <?php endif; ?>

    <?php if (!empty($contract_periods)) : ?>
        <li><?= esc_html__('Contract Period', $domain) . ': ' . $contract_periods ?></li>
    <?php endif; ?>

    <?php if (!empty($setup_fee)) : ?>
        <li><?= __($setup_fee, $domain) ?></li>
    <?php endif; ?>
</ul>
