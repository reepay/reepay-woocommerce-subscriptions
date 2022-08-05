<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(empty($addon['choose'])){
    $addon['choose'] = 'new';
}



global $post;
?>
<div class="woocommerce_product_addon wc-metabox closed">
    <h3>
        <button type="button" class="remove_addon button"><?php _e( 'Remove', $domain ); ?></button>

        <div class="handlediv" title="<?php _e( 'Click to toggle', $domain ); ?>"></div>
        <strong><?php _e( 'Add-on', $domain ); ?>: <span class="group_name"><?php if ( $addon['name'] ) echo '"' . esc_attr( $addon['name'] ) . '"'; ?></span></strong>;
        <span> <?php _e( 'Type', $domain ); ?>: <?php $addon['type'] === 'on_off' ? _e('On/Off', $domain) : _e('Quantity', $domain) ?></span>;
        <span> <?php _e( 'Amount', $domain ); ?>: <?php echo floatval($addon['amount']) * 100 ?></span>


        <input type="hidden" name="product_addon_position[<?php echo $loop; ?>]" class="product_addon_position" value="<?php echo $loop; ?>" />
        <input type="hidden" name="product_addon_handle[<?php echo $loop; ?>]" class="product_addon_position" value="<?= !empty($addon['handle']) ? $addon['handle'] : '' ?>" />
    </h3>


    <table cellpadding="0" cellspacing="0" class="wc-metabox-content">
        <tr>
            <td class="addon_name">
                <p class="form-row choose-radio">
                    <label><?php esc_html_e( 'Creation type', $domain ); ?></label>
                    &nbsp&nbsp<?php esc_html_e( 'Create new', $domain ); ?> &nbsp
                    <input type="radio" id="_reepay_subscription_choose" name="_reepay_addon_choose[<?php echo $loop; ?>]" value="new" <?php checked( 'new', $addon['choose'], true ); ?>>
                    &nbsp&nbsp<?php esc_html_e( 'Choose existing', $domain ); ?> &nbsp
                    <input type="radio" id="_reepay_subscription_choose" name="_reepay_addon_choose[<?php echo $loop; ?>]" value="exist" <?php checked( 'exist', $addon['choose'], true ); ?>>
                </p>
            </td>
        </tr>
        <tbody class="new-addon <?= $addon['choose'] == 'exist' ? 'hidden' : ''?>">
        <?php
        wc_get_template(
	        'admin-addon-single-data.php',
	        array(
		        'addon' => $addon,
		        'loop' => $loop,
		        'domain' => $domain,
	        ),
	        '',
	        reepay_s()->settings('plugin_path').'templates/'
        );
        ?>
        </tbody>
        <tbody class="exist <?= $addon['choose'] == 'new' ? 'hidden' : ''?>">
        <tr>
            <td class="addon_name" width="100%">
			    <?php if(!empty($addons_list)):?>
                    <select id="_subscription_choose_exist"  name="addon_choose_exist[<?php echo $loop; ?>]" class="wc_input_subscription_period_interval js-subscription_choose_exist">
                        <option value=""><?php esc_html_e( 'Select add-on', $domain ); ?></option>
					    <?php foreach ($addons_list as $addon_rem):?>
                            <option value="<?=$addon_rem['handle']?>" <?php !empty($addon['exist']) && $addon['choose'] == 'exist' ? selected( $addon_rem['handle'], $addon['exist'], true ) : '' ?>><?=$addon_rem['name']?></option>
					    <?php endforeach; ?>
                    </select>
			    <?php else: ?>
				    <?php esc_html_e( 'Add-ons list is empty', $domain ); ?>
			    <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td class="js-exist-addon-data"></td>
        </tr>
        </tbody>
    </table>
</div>