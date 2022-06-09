<?php
?>

<div class="reepay_subscription_info">
    <p><?= $product->reepay_get_billing_plan() ?></p>
    <p><?= $product->reepay_get_trial() ?></p>
    <?php if(!empty($product->get_meta('_reepay_subscription_contract_periods'))):?>
        <?php esc_html_e( 'Minimum Contract Period - ', $domain ); ?> <?=$product->get_meta('_reepay_subscription_contract_periods')?>
    <?php endif; ?>
</div>
