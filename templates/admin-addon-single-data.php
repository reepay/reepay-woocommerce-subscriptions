<?php
/** @var int $loop */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $addon['choose'] ) ) {
	$addon['choose'] = 'exist';
}

if ( empty( $addon['disabled'] ) ) {
	$addon['disabled'] = true;
}

global $post;
?>
<p class="form-field">
    <label for="addon_name_<?php echo esc_html( $loop ); ?>">
		<?php _e( 'Name', 'reepay-subscriptions-for-woocommerce' ); ?>
    </label>
    <span><?php echo esc_attr( $addon['name'] ) ?></span>
</p>
<p class="form-field">
    <label for="addon_type_<?php echo esc_html( $loop ); ?>">
		<?php _e( 'Type', 'reepay-subscriptions-for-woocommerce' ); ?>
    </label>
    <span>
        <?php if ( $addon['type'] == 'on_off' ): ?>
	        <?php _e( 'On/Off', 'reepay-subscriptions-for-woocommerce' ); ?>
        <?php else: ?>
	        <?php _e( 'Quantity', 'reepay-subscriptions-for-woocommerce' ); ?>
        <?php endif; ?>
    </span>
</p>

<p class="form-field">
    <label for="addon_name_<?php echo esc_html( $loop ); ?>">
		<?php
		_e( 'Description', 'reepay-subscriptions-for-woocommerce' );
		echo wc_help_tip( __( 'Will display on the frontend', 'reepay-subscriptions-for-woocommerce' ) );
		?>
    </label>
    <span><?php echo ! empty( $addon['description'] ) ? esc_textarea( $addon['description'] ) : '' ?></span>
</p>

<p class="form-field">
    <label for="addon_name_<?php echo esc_html( $loop ); ?>">
		<?php _e( 'Amount (per unit)', 'reepay-subscriptions-for-woocommerce' ); ?>
    </label>
    <span><?php echo esc_attr( $addon['amount'] ) ?></span>
</p>

<p class="form-field">
    <label for="addon_type_<?php echo esc_html( $loop ); ?>">
		<?php echo __( 'Add-on availability', 'reepay-subscriptions-for-woocommerce' ); ?>
    </label>
    <span>
        <?php if ( $addon['avai'] == 'current' ): ?>
	        <?php echo __( 'Current plan', 'reepay-subscriptions-for-woocommerce' ); ?>
        <?php else: ?>
	        <?php echo __( 'All plans', 'reepay-subscriptions-for-woocommerce' ); ?>
        <?php endif; ?>
    </span>
</p>
