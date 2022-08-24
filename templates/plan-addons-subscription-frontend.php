<?php
?>

<h4><?php esc_html_e('Add-ons', $domain); ?></h4>
<div class="reepay_subscription_addons">
    <?php foreach ($addons as $i => $addon): ?>
        <p>
            <?php if ($addon['type'] == 'on_off'): ?>
                <input type="checkbox" name="addon-<?= $addon['handle'] ?>" value="yes">
            <?php elseif ($addon['type'] == 'quantity'): ?>
                <input type="number" min="0" name="addon-<?= $addon['handle'] ?>" value="0">
            <?php endif; ?>
            <?= $addon['name'] ?> +<?= wc_price($addon['amount']) ?>
            / <?= $billing_plan ?> <?= !empty($addon['description']) ? '(' . $addon['description'] . ')' : '' ?>
        </p>
    <?php endforeach; ?>
</div>
