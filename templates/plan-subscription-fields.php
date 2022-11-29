<?php
/**
 * @var String $settings_exist
 * @var String $_reepay_subscription_handle
 */
?>
<div class="reepay_subscription_container">
    <div class="options_group show_if_reepay_subscription">
        <p class="form-field exist-fields">
            <label for="_reepay_subscription_handle">
				<?php echo __( 'Choose plan', 'reepay-subscriptions-for-woocommerce' ); ?>
            </label>
			<?php if ( ! empty( $plans_list ) ): ?>
                <select id="_reepay_subscription_handle"
                        name="_reepay_subscription_handle"
                        class="wc_input_subscription_period_interval"
					<?php if ( isset( $data_plan ) ) : ?>
                        data-plan='<?php echo esc_html( $data_plan ) ?>'
					<?php endif; ?>>

                    <option value=""><?php echo __( 'Select plan', 'reepay-subscriptions-for-woocommerce' ); ?></option>

					<?php foreach ( $plans_list as $plan ): ?>
                        <option value="<?php echo esc_attr( $plan['handle'] ) ?>"
                            <?php selected( $plan['handle'], $_reepay_subscription_handle ) ?>>
                            <?php echo esc_attr( $plan['name'] ) ?>
                        </option>
					<?php endforeach; ?>
                </select>
                <button class="button button-primary button-large js-refresh-plans-list" style="margin-left: 5px;">
	                <?php _e( 'Refresh list',  'reepay-subscriptions-for-woocommerce' ) ?>
                </button>
                <a class="button button-primary button-large"
                   style="margin-left: 5px;"
                   href="https://app.reepay.com/#/rp/config/plans/create"
                   target="_blank">
					<?php
					_e( 'Create new plan', 'reepay-subscriptions-for-woocommerce' ) ?>
                </a>
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
               value="<?php _e( 'Save plan to product', 'reepay-subscriptions-for-woocommerce' ) ?>">
    </p>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>