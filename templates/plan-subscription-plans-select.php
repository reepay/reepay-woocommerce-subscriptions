<?php

$loop = isset($loop) ? "[$loop]" : '';
?>

<select id="_reepay_subscription_handle"
        name="_reepay_subscription_handle<?php echo $loop ?>"
        class="wc_input_subscription_period_interval"
	<?php if ( isset( $data_plan ) ) : ?>
		data-plan='<?php echo esc_html( $data_plan ) ?>'
	<?php endif; ?>>

	<option value="">
		<?php
		if ( empty( $plans_list ) ) {
			_e( 'Plans list is empty', 'reepay-subscriptions-for-woocommerce' );
		} else {
			_e( 'Select plan', 'reepay-subscriptions-for-woocommerce' );
		}
		?>
	</option>

	<?php foreach ( $plans_list ?? [] as $plan ): ?>
		<option value="<?php echo esc_attr( $plan['handle'] ) ?>"
			<?php selected( $plan['handle'], $current ?? '') ?>>
			<?php echo esc_attr( $plan['name'] ) ?>
		</option>
	<?php endforeach; ?>
</select>