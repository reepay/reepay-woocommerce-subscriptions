<?php
/**
 * @var string $billing_plan
 * @var string $trial
 * @var string $contract_periods
 * @var string $domain
 */
?>

<ul class="reepay_subscription_info">
    <li><?= $billing_plan ?></li>
    <li><?= $trial ?></li>
	<?php if ( ! empty( $contract_periods ) ) : ?>
        <li><?= esc_html__( 'Minimum Contract Period - ', $domain ) . ' ' . $contract_periods ?> </li>
	<?php endif; ?>
</ul>
