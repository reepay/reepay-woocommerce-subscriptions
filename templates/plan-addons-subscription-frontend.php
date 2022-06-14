<?php
?>

<h4><?php esc_html_e( 'Addons', $domain ); ?></h4>
<div class="reepay_subscription_addons">
    <?php foreach ($addons as $i => $addon):?>
        <p>
            <?php if($addon['type'] == 'on_off'): ?>
                <input type="checkbox" name="addon-<?=$addon['handle']?>" value="yes">
            <?php elseif ($addon['type'] == 'quantity'): ?>
                <input type="number" name="addon-<?=$addon['handle']?>" value="0">
            <?php endif; ?>
            <?= $addon['name'] ?> +<?= wc_price($addon['amount']) ?> (<?= $addon['description'] ?>)
        </p>
    <?php endforeach; ?>
</div>