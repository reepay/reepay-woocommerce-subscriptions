<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( empty( $addon['choose'] ) ) {
	$addon['choose'] = 'new';
}

if ( empty( $addon['disabled'] ) ) {
	$addon['disabled'] = false;
}

global $post;
?>
<tr style="display: flex">
    <td class="addon_name" style="width: 50%;">
        <label for="addon_name_<?php echo $loop; ?>">
			<?php _e( 'Name', $domain );?>
        </label>
        <input style="width: 100%;" type="text" id="addon_name_<?php echo $loop; ?>" name="product_addon_name[<?php echo $loop; ?>]" <?= $addon['disabled'] ? 'disabled' : ''?> value="<?php echo esc_attr( $addon['name'] ) ?>" />
    </td>
    <td class="addon_name" style="width: 50%;">
        <label for="addon_name_<?php echo $loop; ?>">
			<?php _e( 'Type', $domain );?>
        </label>
        <select name="product_addon_type[<?php echo $loop; ?>]" class="product_addon_type"  <?= $addon['choose'] == 'exist' || $addon['disabled'] ? 'disabled' : ''?> style="min-height: 38px">
            <option <?php selected('on_off', $addon['type']); ?> value="on_off"><?php _e('On/Off', $domain); ?></option>
            <option <?php selected('quantity', $addon['type']); ?> value="quantity"><?php _e('Quantity', $domain); ?></option>
        </select>
    </td>
</tr>

<tr>
    <td class="addon_description" style="width: 100%">
        <label for="addon_description_<?php echo $loop; ?>">
			<?php
			_e( 'Description', $domain );
			echo wc_help_tip( __( 'Will display on the frontend', $domain ) );
			?>
        </label>
        <textarea cols="20" id="addon_description_<?php echo $loop; ?>" rows="3" <?= $addon['disabled'] ? 'disabled' : ''?> name="product_addon_description[<?php echo $loop; ?>]"><?php echo esc_textarea( $addon['description'] ) ?></textarea>
    </td>
</tr>

<tr style="display: flex">
    <td class="addon_name" style="width: 33%">
        <label for="addon_amount_<?php echo $loop; ?>">
			<?php _e( 'Amount (per unit)', $domain );?>
        </label>
        <input style="width: 100%;" type="number" min="0" placeholder="<?php _e( 'kr 0.00', $domain );?>" <?= $addon['disabled'] ? 'disabled' : ''?> id="addon_amount_<?php echo $loop; ?>" name="product_addon_amount[<?php echo $loop; ?>]" value="<?php echo esc_attr( $addon['amount'] ) ?>" />
    </td>
</tr>