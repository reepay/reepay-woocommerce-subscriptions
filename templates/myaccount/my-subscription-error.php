<?php
/**
 * My Subscriptions section on the My Account page
 *
 * @var array $error error message to show
 * @var array $show_return_to_shop_btn
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="woocommerce_account_subscriptions">
    <p class="no_subscriptions woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
		<?php echo $error; ?>

		<?php if ( ! empty( $show_return_to_shop_btn ) ) : ?>
            <a class="woocommerce-Button button"
               href="<?php echo esc_url( wc_get_endpoint_url( WC_Reepay_My_Account_Subscriptions_Page::$menu_item_slug ) ); ?>">
				<?php esc_html_e( 'View subscriptions', 'reepay-subscriptions-for-woocommerce' ); ?>
            </a>
		<?php endif; ?>
    </p>
</div>
