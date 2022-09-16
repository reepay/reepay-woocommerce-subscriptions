<?php
?>

<h4><?php esc_html_e('Add-ons', $domain); ?></h4>
<div class="reepay_subscription_addons">
    <?php foreach ($addons as $i => $addon): ?>
        <p>
            <?php if ($addon['type'] == 'on_off'): ?>
                <input type="checkbox" name="addon-<?php esc_attr_e($addon['handle']) ?>" value="yes">
            <?php elseif ($addon['type'] == 'quantity'): ?>
                <input type="number" min="0" name="addon-<?php esc_attr_e($addon['handle']) ?>" value="0">
            <?php endif; ?>
            <?php esc_attr_e($addon['name']) ?> +<?php echo wc_price(esc_attr($addon['amount'])) ?>
            / <?php esc_attr_e($billing_plan) ?> <?php echo !empty($addon['description']) ? '(' . esc_attr($addon['description']) . ')' : '' ?>
        </p>
    <?php endforeach; ?>
</div>
