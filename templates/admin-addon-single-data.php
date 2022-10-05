<?php
/** @var int $loop */

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
        <label for="addon_name_<?php echo esc_html( $loop ); ?>">
			<?php _e( 'Name', 'reepay-subscriptions' ); ?>
        </label>
        <input style="width: 100%;" type="text" id="addon_name_<?php echo esc_html( $loop ); ?>"
               name="product_addon_name[<?php echo esc_html( $loop ); ?>]" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
               value="<?php echo esc_attr( $addon['name'] ) ?>"/>
    </td>
    <td class="addon_name" style="width: 50%;">
        <label for="addon_type_<?php echo esc_html( $loop ); ?>">
			<?php _e( 'Type', 'reepay-subscriptions' ); ?>
        </label>
        <select id="addon_type_<?php echo esc_html( $loop ); ?>"
                name="product_addon_type[<?php echo esc_html( $loop ); ?>]"
                class="product_addon_type" <?php echo $addon['choose'] == 'exist' || $addon['disabled'] ? 'disabled' : '' ?>
                style="min-height: 38px">
            <option <?php selected( 'on_off', $addon['type'] ); ?>
                    value="on_off"><?php _e( 'On/Off', 'reepay-subscriptions' ); ?></option>
            <option <?php selected( 'quantity', $addon['type'] ); ?>
                    value="quantity"><?php _e( 'Quantity', 'reepay-subscriptions' ); ?></option>
        </select>
    </td>
</tr>

<tr>
    <td class="addon_description" style="width: 100%">
        <label for="addon_description_<?php echo esc_html( $loop ); ?>">
			<?php
			_e( 'Description', 'reepay-subscriptions' );
			echo wc_help_tip( __( 'Will display on the frontend', 'reepay-subscriptions' ) );
			?>
        </label>
        <textarea cols="20" id="addon_description_<?php echo esc_html( $loop ); ?>"
                  rows="3" <?php echo $addon['disabled'] ? 'disabled' : '' ?> name="product_addon_description[<?php echo esc_attr( $loop ) ?>]"><?php echo ! empty( $addon['description'] ) ? esc_textarea( $addon['description'] ) : '' ?></textarea>
    </td>
</tr>

<tr style="display: flex">
    <td class="addon_name" style="width: 33%">
        <label for="addon_amount_<?php echo esc_html( $loop ); ?>">
			<?php _e( 'Amount (per unit)', 'reepay-subscriptions' ); ?>
        </label>
        <input style="width: 100%;" type="number"
               placeholder="<?php _e( 'kr 0.00', 'reepay-subscriptions' ); ?>" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
               id="addon_amount_<?php echo esc_html( $loop ); ?>"
               name="product_addon_amount[<?php echo esc_html( $loop ); ?>]"
               value="<?php echo esc_attr( $addon['amount'] ) ?>"/>
    </td>
</tr>

<tr>
    <td class="addon_name">
        <p class="form-row choose-radio">
            <label for="_reepay_subscription_avai"><?php echo __( 'Add-on availability', 'reepay-subscriptions' ); ?></label>
            &nbsp&nbsp<?php echo __( 'Current plan', 'reepay-subscriptions' ); ?> &nbsp
            <input type="radio" id="_reepay_subscription_avai"
                   name="_reepay_addon_avai[<?php echo esc_html( $loop ); ?>]" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
                   value="current" <?php echo empty( $addon['avai'] ) || $addon['avai'] == 'current' ? 'checked' : '' ?>>
            &nbsp&nbsp<?php echo __( 'All plans', 'reepay-subscriptions' ); ?> &nbsp
            <input type="radio" id="_reepay_subscription_avai"
                   name="_reepay_addon_avai[<?php echo esc_html( $loop ); ?>]" <?php echo $addon['disabled'] ? 'disabled' : '' ?>
                   value="all" <?php checked( 'all', $addon['avai'] ); ?>>
        </p>
    </td>
</tr>
