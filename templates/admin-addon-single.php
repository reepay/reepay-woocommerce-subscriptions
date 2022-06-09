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
        <strong><?php _e( 'Add-on', $domain ); ?> <span class="group_name"><?php if ( $addon['name'] ) echo '"' . esc_attr( $addon['name'] ) . '"'; ?></span> &mdash; </strong>
        <select name="product_addon_type[<?php echo $loop; ?>]" class="product_addon_type"  <?= $addon['choose'] == 'exist' ? 'disabled' : ''?>>
            <option <?php selected('on_off', $addon['type']); ?> value="on_off"><?php _e('On/Off', $domain); ?></option>
            <option <?php selected('quantity', $addon['type']); ?> value="quantity"><?php _e('Quantity', $domain); ?></option>
        </select>

        &nbsp&nbsp<?php esc_html_e( 'Create new', $domain ); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose" name="_reepay_addon_choose[<?php echo $loop; ?>]" value="new" <?php checked( 'new', $addon['choose'], true ); ?>>
        &nbsp&nbsp<?php esc_html_e( 'Choose existing', $domain ); ?> &nbsp
        <input type="radio" id="_reepay_subscription_choose" name="_reepay_addon_choose[<?php echo $loop; ?>]" value="exist" <?php checked( 'exist', $addon['choose'], true ); ?>>

        <input type="hidden" name="product_addon_position[<?php echo $loop; ?>]" class="product_addon_position" value="<?php echo $loop; ?>" />
        <input type="hidden" name="product_addon_handle[<?php echo $loop; ?>]" class="product_addon_position" value="<?= !empty($addon['handle']) ? $addon['handle'] : '' ?>" />
    </h3>


    <table cellpadding="0" cellspacing="0" class="wc-metabox-content">
        <tbody class="exist <?= $addon['choose'] == 'new' ? 'hidden' : ''?>">
        <tr>
            <td class="addon_name" width="100%">
                <?php if(!empty($addons_list)):?>
                    <select id="_subscription_choose_exist"  name="addon_choose_exist[<?php echo $loop; ?>]" class="wc_input_subscription_period_interval">
                        <option value=""><?php esc_html_e( 'Select plan', $domain ); ?></option>
                        <?php foreach ($addons_list as $addon_rem):?>
                            <option value="<?=$addon_rem['handle']?>" <?php selected( $addon_rem['handle'], $addon['exist'], true ) ?>><?=$addon_rem['name']?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <?php esc_html_e( 'Add-ons list is empty', $domain ); ?>
                <?php endif; ?>
            </td>
        </tr>
        </tbody>
        <tbody class="new-addon <?= $addon['choose'] == 'exist' ? 'hidden' : ''?>">
        <tr>
            <td class="addon_name" width="100%">
                <label for="addon_name_<?php echo $loop; ?>">
                    <?php _e( 'Name', $domain );?>
                </label>
                <input style="width: 100%;" type="text" id="addon_name_<?php echo $loop; ?>" name="product_addon_name[<?php echo $loop; ?>]" value="<?php echo esc_attr( $addon['name'] ) ?>" />
            </td>
        </tr>

        <tr>
            <td class="addon_description" colspan="2">
                <label for="addon_description_<?php echo $loop; ?>">
                    <?php
                    _e( 'Description', $domain );
                    echo wc_help_tip( __( 'Will display on the frontend', $domain ) );
                    ?>
                </label>
                <textarea cols="20" id="addon_description_<?php echo $loop; ?>" rows="3" name="product_addon_description[<?php echo $loop; ?>]"><?php echo esc_textarea( $addon['description'] ) ?></textarea>
            </td>
        </tr>

        <tr style="display: flex">
            <td class="addon_name" style="width: 32%">
                <label for="addon_amount_<?php echo $loop; ?>">
                    <?php _e( 'Amount (per unit)', $domain );?>
                </label>
                <input style="width: 100%;" type="number" placeholder="<?php _e( 'kr 0.00', $domain );?>" id="addon_amount_<?php echo $loop; ?>" name="product_addon_amount[<?php echo $loop; ?>]" value="<?php echo esc_attr( $addon['amount'] ) ?>" />
            </td>
            <td class="addon_name"style="width: 32%">
                <label for="addon_vat_<?php echo $loop; ?>">
                    <?php _e( 'VAT %', $domain );?>
                </label>
                <input style="width: 100%;" type="number" id="addon_vat_<?php echo $loop; ?>" name="product_addon_vat[<?php echo $loop; ?>]" value="<?php echo esc_attr( $addon['vat'] ) ?>" />
            </td>
            <td class="addon_name" style="width: 32%">
                <label for="addon_vat_type_<?php echo $loop; ?>">
                    <?php _e( 'VAT type', $domain );?>
                </label>
                <select style="width: 100%;" id="addon_vat_type_<?php echo $loop; ?>" name="product_addon_vat_type[<?php echo $loop; ?>]">
                    <option <?php selected('include', $addon['vat_type']); ?> value="include"><?php _e('Include VAT', $domain); ?></option>
                    <option <?php selected('exclude', $addon['vat_type']); ?> value="exclude"><?php _e('Exclude VAT', $domain); ?></option>
                </select>
            </td>
        </tr>
        </tbody>
    </table>
</div>