<?php
if ( ! isset( $active_item ) ) {
	$active_item = 0;
} else {
    $active_item = (int) $active_item;
}

/**
 * @var int $active_item
 */
?>


<ul class="subsubsub" style="float:unset">
    <li><a href="<?php echo get_admin_url() ?>admin.php?page=wc-settings&tab=reepay_subscriptions"
           class="<?php echo 0 === $active_item ? 'current' : '' ?>"><?php _e( 'General', 'reepay-subscriptions-for-woocommerce' ); ?></a> |
    </li>
    <li>
        <a href="<?php echo get_admin_url() ?>tools.php?page=reepay_import"
           class="<?php echo 1 === $active_item ? 'current' : '' ?>"><?php _e( 'Import tools', 'reepay-subscriptions-for-woocommerce' ); ?></a>
    </li>
</ul>