<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($addon['choose'])) {
    $addon['choose'] = 'new';
}

if (empty($addon['disabled'])) {
    $addon['disabled'] = false;
}

global $post;
?>
<tr style="display: flex">
    <td class="addon_name" style="width: 50%;">
        <label for="addon_name_<?php esc_html_e($loop); ?>">
            <?php _e('Name', $domain); ?>
        </label>
        <input style="width: 100%;" type="text" id="addon_name_<?php esc_html_e($loop); ?>"
               name="product_addon_name[<?php esc_html_e($loop); ?>]" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
               value="<?php esc_attr_e($addon['name']) ?>"/>
    </td>
    <td class="addon_name" style="width: 50%;">
        <label for="addon_name_<?php esc_html_e($loop); ?>">
            <?php _e('Type', $domain); ?>
        </label>
        <select name="product_addon_type[<?php esc_html_e($loop); ?>]"
                class="product_addon_type" <?php echo $addon['choose'] == 'exist' || $addon['disabled'] ? 'disabled' : '' ?>
                style="min-height: 38px">
            <option <?php selected('on_off', $addon['type']); ?> value="on_off"><?php _e('On/Off', $domain); ?></option>
            <option <?php selected('quantity', $addon['type']); ?>
                    value="quantity"><?php _e('Quantity', $domain); ?></option>
        </select>
    </td>
</tr>

<tr>
    <td class="addon_description" style="width: 100%">
        <label for="addon_description_<?php esc_html_e($loop); ?>">
            <?php
            _e('Description', $domain);
            echo wc_help_tip(__('Will display on the frontend', $domain));
            ?>
        </label>
        <textarea cols="20" id="addon_description_<?php esc_html_e($loop); ?>"
                  rows="3" <?php echo $addon['disabled'] ? 'disabled' : '' ?> name="product_addon_description[<?php esc_attr_e($loop) ?>]"><?php echo !empty($addon['description']) ? esc_textarea($addon['description']) : '' ?></textarea>
    </td>
</tr>

<tr style="display: flex">
    <td class="addon_name" style="width: 33%">
        <label for="addon_amount_<?php esc_html_e($loop); ?>">
            <?php _e('Amount (per unit)', $domain); ?>
        </label>
        <input style="width: 100%;" type="number"
               placeholder="<?php _e('kr 0.00', $domain); ?>" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
               id="addon_amount_<?php esc_html_e($loop); ?>" name="product_addon_amount[<?php esc_html_e($loop); ?>]"
               value="<?php esc_attr_e($addon['amount']) ?>"/>
    </td>
</tr>

<tr>
    <td class="addon_name">
        <p class="form-row choose-radio">
            <label><?php esc_html_e('Add-on availability', $domain); ?></label>
            &nbsp&nbsp<?php esc_html_e('Current plan', $domain); ?> &nbsp
            <input type="radio" id="_reepay_subscription_avai"
                   name="_reepay_addon_avai[<?php esc_html_e($loop); ?>]" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
                   value="current" <?php echo empty($addon['avai']) || $addon['avai'] == 'current' ? 'checked' : '' ?>>
            &nbsp&nbsp<?php esc_html_e('All plans', $domain); ?> &nbsp
            <input type="radio" id="_reepay_subscription_avai"
                   name="_reepay_addon_avai[<?php esc_html_e($loop); ?>]" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
                   value="all" <?php checked('all', $addon['avai'], true); ?>>
        </p>
    </td>
</tr>