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
        <button type="button" class="remove_addon button"><?php _e( 'Remove', 'reepay-subscriptions-for-woocommerce' ); ?></button>

        <div class="handlediv" title="<?php _e( 'Click to toggle', 'reepay-subscriptions-for-woocommerce' ); ?>"></div>
        <strong><?php _e( 'Add-on', 'reepay-subscriptions-for-woocommerce' ); ?>: <span
                    class="group_name"><?php if ( $addon['name'] ) {
					echo '"' . esc_attr( $addon['name'] ) . '"';
				} ?></span></strong>;
        <span> <?php _e( 'Type', 'reepay-subscriptions-for-woocommerce' ); ?>: <?php $addon['type'] === 'on_off' ? _e( 'On/Off', 'reepay-subscriptions-for-woocommerce' ) : _e( 'Quantity', 'reepay-subscriptions-for-woocommerce' ) ?></span>;
        <span> <?php _e( 'Amount', 'reepay-subscriptions-for-woocommerce' ); ?>: <?php echo floatval( esc_attr( $addon['amount'] ) ) * 100 ?></span>


        <input type="hidden" name="product_addon_position[<?php echo esc_attr( $loop ) ?>]"
               class="product_addon_position"
               value="<?php echo esc_attr( $loop ); ?>"/>
        <input type="hidden" name="product_addon_handle[<?php echo esc_attr( $loop ) ?>]" class="product_addon_position"
               value="<?php echo ! empty( $addon['handle'] ) ? esc_attr( $addon['handle'] ) : '' ?>"/>
    </h3>


    <table class="wc-metabox-content">
        <tr>
            <td class="addon_name">
                <p class="form-row choose-radio">
                    <label for="_reepay_subscription_choose"><?php echo __( 'Creation type', 'reepay-subscriptions-for-woocommerce' ); ?></label>
                    &nbsp&nbsp<?php echo __( 'Create new', 'reepay-subscriptions-for-woocommerce' ); ?> &nbsp
                    <input type="radio" id="_reepay_subscription_choose"
                           name="_reepay_addon_choose[<?php echo esc_attr( $loop ); ?>]"
                           value="new" <?php checked( 'new', esc_attr( $addon['choose'] ) ); ?>>
                    &nbsp&nbsp<?php echo __( 'Choose existing', 'reepay-subscriptions-for-woocommerce' ); ?> &nbsp
                    <input type="radio" id="_reepay_subscription_choose"
                           name="_reepay_addon_choose[<?php echo esc_attr( $loop ); ?>]"
                           value="exist" <?php checked( 'exist', esc_attr( $addon['choose'] ) ); ?>>
                </p>
            </td>
        </tr>
        <tbody class="new-addon <?php echo $addon['choose'] == 'exist' ? 'hidden' : '' ?>">
		<?php
		wc_get_template(
			'admin-addon-single-data.php',
			[
				'addon'  => $addon,
				'loop'   => $loop,
				'domain' => 'reepay-subscriptions-for-woocommerce',
			],
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
		?>
        </tbody>
        <tbody class="exist <?php echo $addon['choose'] == 'new' ? 'hidden' : '' ?>">
        <tr>
            <td class="addon_name" style="width: 100%">
				<?php if ( ! empty( $addons_list ) ): ?>
                    <select id="_subscription_choose_exist" name="addon_choose_exist[<?php echo $loop; ?>]"
                            class="wc_input_subscription_period_interval js-subscription_choose_exist">
                        <option value=""><?php echo __( 'Select add-on', 'reepay-subscriptions-for-woocommerce' ); ?></option>
						<?php foreach ( $addons_list as $addon_rem ): ?>
                            <option value="<?php echo esc_attr( $addon_rem['handle'] ) ?>" <?php ! empty( $addon['exist'] ) && $addon['choose'] == 'exist' ? selected( $addon_rem['handle'], $addon['exist'] ) : '' ?>><?php echo esc_attr( $addon_rem['name'] ) ?></option>
						<?php endforeach; ?>
                    </select>
				<?php else: ?>
					<?php echo __( 'Add-ons list is empty', 'reepay-subscriptions-for-woocommerce' ); ?>
				<?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="js-exist-addon-data"></td>
        </tr>
        </tbody>
    </table>
</div>
