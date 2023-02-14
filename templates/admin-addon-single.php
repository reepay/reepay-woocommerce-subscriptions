<?php
/** @var int $loop */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $addon['choose'] ) ) {
	$addon['choose'] = 'new';
}


global $post;
?>
<div class="woocommerce_product_addon wc-metabox closed">
    <h3>
        <div class="handlediv" title="<?php _e( 'Click to toggle', 'reepay-subscriptions-for-woocommerce' ); ?>"></div>
        <strong><?php _e( 'Add-on', 'reepay-subscriptions-for-woocommerce' ); ?>: <span
                    class="group_name"><?php if ( $addon['name'] ) {
					echo '"' . esc_attr( $addon['name'] ) . '"';
				} ?></span></strong>;
        <span> <?php _e( 'Type', 'reepay-subscriptions-for-woocommerce' ); ?>: <?php $addon['type'] === 'on_off' ? _e( 'On/Off', 'reepay-subscriptions-for-woocommerce' ) : _e( 'Quantity', 'reepay-subscriptions-for-woocommerce' ) ?></span>;
        <span> <?php _e( 'Amount', 'reepay-subscriptions-for-woocommerce' ); ?>: <?php echo floatval( esc_attr( $addon['amount'] ) ) ?></span>


        <input type="hidden" name="product_addon_position[<?php echo esc_attr( $loop ) ?>]"
               class="product_addon_position"
               value="<?php echo esc_attr( $loop ); ?>"/>
        <input type="hidden" name="product_addon_handle[<?php echo esc_attr( $loop ) ?>]" class="product_addon_position"
               value="<?php echo ! empty( $addon['handle'] ) ? esc_attr( $addon['handle'] ) : '' ?>"/>
    </h3>


    <table class="wc-metabox-content">
        <tbody class="exist">
        <tr>
            <td class="addon_name" style="width: 100%">
				<?php if ( ! empty( $addons_list ) ): ?>
                    <select name="addon_choose_exist[<?php echo $loop; ?>]"
                            class="wc_input_subscription_period_interval js-subscription_choose_exist">
                        <option value=""><?php _e( 'Select add-on', 'reepay-subscriptions-for-woocommerce' ); ?></option>
						<?php foreach ( $addons_list as $addon_rem ): ?>
                            <option value="<?php echo esc_attr( $addon_rem['handle'] ) ?>" <?php ! empty( $addon['exist'] ) && $addon['choose'] == 'exist' ? selected( $addon_rem['handle'], $addon['exist'] ) : '' ?>><?php echo esc_attr( $addon_rem['name'] ) ?></option>
						<?php endforeach; ?>
                    </select>

                    <button class="button button-primary button-large js-refresh-addons-list">
						<?php _e( 'Refresh list', 'reepay-subscriptions-for-woocommerce' ) ?>
                    </button>
                    <a class="button button-primary button-large"
                       style="margin-left: 5px;"
                       href="https://app.reepay.com/#/rp/config/addons/create"
                       target="_blank">
						<?php
						_e( 'Create new addon', 'reepay-subscriptions-for-woocommerce' ) ?>
                    </a>
				<?php else: ?>
					<?php _e( 'Add-ons list is empty', 'reepay-subscriptions-for-woocommerce' ); ?>
				<?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="js-exist-addon-data"></td>
        </tr>
        </tbody>
    </table>
</div>
