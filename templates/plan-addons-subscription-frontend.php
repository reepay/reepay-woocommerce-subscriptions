<?php
/**
 * @var String $billing_plan
 * @var Array $addons
 */
?>

<h4><?php echo __( 'Add-ons', 'reepay-subscriptions' ); ?></h4>
<div class="reepay_subscription_addons">
	<?php foreach ( $addons as $i => $addon ): ?>
        <p>
			<?php if ( $addon['type'] == 'on_off' ): ?>
                <input type="checkbox" name="addon-<?php echo esc_attr( $addon['handle'] ) ?>" value="yes">
			<?php elseif ( $addon['type'] == 'quantity' ): ?>
                <input type="number" min="0" name="addon-<?php echo esc_attr( $addon['handle'] ) ?>" value="0">
			<?php endif; ?>
			<?php echo esc_attr( $addon['name'] ) ?> +<?php echo wc_price( esc_attr( $addon['amount'] ) ) ?>
            / <?php echo esc_attr( $billing_plan ) ?> <?php echo ! empty( $addon['description'] ) ? '(' . esc_attr( $addon['description'] ) . ')' : '' ?>
        </p>
	<?php endforeach; ?>
</div>
