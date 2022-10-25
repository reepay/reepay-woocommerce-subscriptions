<?php
/**
 * @var Int $loop
 * @var String $_reepay_subscription_choose
 * @var String $settings
 * @var Bool $is_exist
 * @var String $_reepay_choose_exist
 */

?>
<div class="options_group reepay_subscription_choose show_if_reepay_subscription">
    <p class="form-field choose-fields 'form-row'">
        <label for="_reepay_subscription_choose">
			<?php echo __( 'Creation type', 'reepay-subscriptions-for-woocommerce' ); ?>
        </label>
		<?php echo __( 'Create new plan', 'reepay-subscriptions-for-woocommerce' ); ?>
        <input type="radio" id="_reepay_subscription_choose"
               name="_reepay_subscription_choose[<?php echo esc_attr( $loop ) ?>]"
               value="new" <?php checked( 'new', esc_attr( $_reepay_subscription_choose ) ); ?>>
		<?php echo __( 'Choose existing plan', 'reepay-subscriptions-for-woocommerce' ); ?>
        <input type="radio" id="_reepay_subscription_choose"
               name="_reepay_subscription_choose[<?php echo esc_attr( $loop ) ?>]"
               value="exist" <?php checked( 'exist', esc_attr( $_reepay_subscription_choose ) ); ?>>
    </p>
</div>

<div class="reepay_subscription_settings">
	<?php echo wp_kses_normalize_entities( $settings ) ?>
</div>

<div class="reepay_subscription_choose_exist">
    <div class="options_group show_if_reepay_subscription">
        <p class="form-field exist-fields">
            <label for="_subscription_choose_exist">
				<?php echo __( 'Choose plan', 'reepay-subscriptions-for-woocommerce' ); ?>
            </label>
			<?php if ( ! empty( $plans_list ) ): ?>
                <select id="_subscription_choose_exist"
                        name="_reepay_choose_exist[<?php echo esc_attr( $loop ) ?>]"
                        class="wc_input_subscription_period_interval"
					<?php if ( isset( $data_plan ) ) : ?>
                        data-plan='<?php echo esc_html( $data_plan ) ?>'
					<?php endif; ?>>
                    <option value=""><?php echo __( 'Select plan', 'reepay-subscriptions-for-woocommerce' ); ?></option>
					<?php foreach ( $plans_list as $plan ): ?>
                        <option value="<?php echo esc_attr( $plan['handle'] ) ?>" <?php echo $_reepay_subscription_choose == 'exist' ? selected( $plan['handle'], $_reepay_choose_exist ) : '' ?>><?php echo esc_attr( $plan['name'] ) ?></option>
					<?php endforeach; ?>
                </select>
			<?php else: ?>
				<?php echo __( 'Plans list is empty', 'reepay-subscriptions-for-woocommerce' ); ?>
			<?php endif; ?>
        </p>
    </div>
    <div class="reepay_subscription_settings_exist">
		<?php echo ! empty( $settings_exist ) ? wp_kses_normalize_entities( $settings_exist ) : '' ?>
    </div>
</div>

<div id="reepay_subscription_publish_btn"
     class="options_group reepay_subscription_publish_btn show_if_reepay_subscription">
    <p class="form-field">
        <input type="submit" name="save" id="reepay-publish" class="button button-primary button-large"
               value="<?php echo ! $is_exist ? __( 'Create plan', 'reepay-subscriptions-for-woocommerce' ) : __( 'Update plan', 'reepay-subscriptions-for-woocommerce' ) ?>">
    </p>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>
