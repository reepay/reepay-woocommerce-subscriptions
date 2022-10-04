<?php
/**
 * @var Int $loop
 * @var String $_reepay_subscription_choose
 * @var String $settings
 * @var Bool $is_exist
 * @var String $_reepay_choose_exist
 */

$variable = $variable ?? false;
?>
<div class="options_group reepay_subscription_choose show_if_reepay_subscription">
    <p class="form-field choose-fields <?php echo $variable ? 'form-row' : '' ?> ">
        <label for="_reepay_subscription_choose">
			<?php echo esc_html( 'Creation type', 'reepay-subscriptions' ); ?>
        </label>
		<?php echo esc_html( 'Create new plan', 'reepay-subscriptions' ); ?>
        <input type="radio" id="_reepay_subscription_choose"
               name="_reepay_subscription_choose<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               value="new" <?php checked( 'new', esc_attr( $_reepay_subscription_choose ) ); ?>>
		<?php echo esc_html( 'Choose existing plan', 'reepay-subscriptions' ); ?>
        <input type="radio" id="_reepay_subscription_choose"
               name="_reepay_subscription_choose<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
               value="exist" <?php checked( 'exist', esc_attr( $_reepay_subscription_choose ) ); ?>>
    </p>
</div>

<div class="reepay_subscription_settings">
	<?php echo wp_kses_normalize_entities( $settings ) ?>
</div>

<div class="reepay_subscription_choose_exist">
    <div class="options_group show_if_reepay_subscription">
        <p class="form-field exist-fields <?php echo $variable ? 'dimensions_field form-row' : '' ?> ">
            <label for="_subscription_choose_exist">
				<?php echo esc_html( 'Choose plan', 'reepay-subscriptions' ); ?>
            </label>
			<?php if ( ! empty( $plans_list ) ): ?>
                <select id="_subscription_choose_exist"
                        name="_reepay_choose_exist<?php echo $variable ? '[' . esc_attr( $loop ) . ']' : '' ?>"
                        class="wc_input_subscription_period_interval"
					<?php if ( isset( $data_plan ) ) : ?>
                        data-plan='<?php echo esc_html( $data_plan ) ?>'
					<?php endif; ?>>
                    <option value=""><?php echo esc_html( 'Select plan', 'reepay-subscriptions' ); ?></option>
					<?php foreach ( $plans_list as $plan ): ?>
                        <option value="<?php echo esc_attr( $plan['handle'] ) ?>" <?php echo $_reepay_subscription_choose == 'exist' ? selected( $plan['handle'], $_reepay_choose_exist ) : '' ?>><?php echo esc_attr( $plan['name'] ) ?></option>
					<?php endforeach; ?>
                </select>
			<?php else: ?>
				<?php echo esc_html( 'Plans list is empty', 'reepay-subscriptions' ); ?>
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
               value="<?php echo ! $is_exist ? __( 'Create plan', 'reepay-subscriptions' ) : __( 'Update plan', 'reepay-subscriptions' ) ?>">
    </p>
</div>

<div class="options_group show_if_reepay_simple_subscriptions clear"></div>
