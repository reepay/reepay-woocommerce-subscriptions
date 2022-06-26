<?php
/**
 * @var string $billing_plan
 * @var string $trial
 * @var string $contract_periods
 */
?>

<div class="reepay_subscription_info">
    <p><?=$billing_plan?></p>
    <p><?=$trial?></p>
	<?php
	if ( ! empty( $contract_periods ) ) {
		 echo esc_html__( 'Minimum Contract Period - ', $domain ) . ' ' . $contract_periods;
	}
    ?>
</div>
