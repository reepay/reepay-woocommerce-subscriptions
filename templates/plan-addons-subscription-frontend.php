<?php
?>

<h4><?php esc_html_e('Add-ons', $domain); ?></h4>
<div class="reepay_subscription_addons">
    <?php foreach ($addons as $i => $addon): ?>
        <p>
            <?php if ($addon['type'] == 'on_off'): ?>
                <input type="checkbox" name="addon-<?php echo $addon['handle'] ?>" value="yes">
            <?php elseif ($addon['type'] == 'quantity'): ?>
                <input type="number" min="0" name="addon-<?php echo $addon['handle'] ?>" value="0">
            <?php endif; ?>
            <?php echo $addon['name'] ?> +<?php echo wc_price($addon['amount']) ?>
            / <?php echo $billing_plan ?> <?php echo !empty($addon['description']) ? '(' . $addon['description'] . ')' : '' ?>
        </p>
    <?php endforeach; ?>
</div>
