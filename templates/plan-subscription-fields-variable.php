<?php
/**
 * @var Int $loop
 * @var String $settings
 * @var String $_reepay_subscription_handle
 */

?>

<div class="reepay_subscription_variable show_if_reepay_subscription">
    <h6 style="padding:0;margin: 0;font-size:18px;"><?php _e('Reepay plan options', 'reepay-subscriptions-for-woocommerce' ) ?></h6>

    <div class="reepay_subscription_container">
        <div class="options_group">
            <p class="form-field" style="display:block;">
                <label for="_reepay_subscription_handle">
		            <?php echo __( 'Choose plan', 'reepay-subscriptions-for-woocommerce' ); ?>
                </label>

	            <?php
	            wc_get_template(
		            'plan-subscription-plans-select.php',
		            [
			            'plans_list' => $plans_list,
			            'current' => $_reepay_subscription_handle,
                        'loop' => $loop,
			            'data_plan' => $data_plan
		            ],
		            '',
		            reepay_s()->settings( 'plugin_path' ) . 'templates/'
	            );
	            ?>
                <br>
                <button class="button button-primary button-large js-refresh-plans-list">
				    <?php _e( 'Refresh list',  'reepay-subscriptions-for-woocommerce' ) ?>
                </button>
                <a class="button button-primary button-large"
                   style="margin-left: 5px;margin-top: 5px;"
                   href="https://app.reepay.com/#/rp/config/plans/create"
                   target="_blank">
				    <?php
				    _e( 'Create new plan', 'reepay-subscriptions-for-woocommerce' ) ?>
                </a>
            </p>
        </div>
        <div class="reepay_subscription_settings_exist variable">
			<?php echo ! empty( $settings_exist ) ? wp_kses_normalize_entities( $settings_exist ) : '' ?>
        </div>
    </div>

    <div id="reepay_subscription_publish_btn"
         class="options_group reepay_subscription_publish_btn">
        <p class="form-field">
            <input type="submit" name="save" id="reepay-publish" class="button button-primary button-large"
                   value="<?php _e( 'Save plan to variation', 'reepay-subscriptions-for-woocommerce' ) ?>">
        </p>
    </div>

    <div class="options_group show_if_reepay_simple_subscriptions clear"></div>
</div>
