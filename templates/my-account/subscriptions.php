<?php


$user_payment_methods = wc_get_customer_saved_methods_list(get_current_user_id());

$user_payment_methods2 = [];

foreach ($user_payment_methods['reepay'] ?? [] as $user_payment_method) {
    $user_payment_methods2[] = WC_Payment_Tokens::get($user_payment_method['method']['id']);
}

?>
<?php foreach($args['subscriptions'] as $subscription): ?>
    <?php
    $plan = $args['plans'][$subscription['plan']];
    $is_expired = $subscription['state'] === 'expired';
    $subscription_payment_method = $subscription['payment_methods'][0] ?? [];
    ?>
    <h1><?= $plan['name'] ?></h1>
    <table>
        <tbody>
        <?php if (!$is_expired): ?>
            <tr>
                <td>Actions:</td>
                <td>
                    <?php if ($subscription['state'] === 'on_hold'): ?>
                        <a href="?reactivate=<?= $subscription['handle'] ?>" class="button">Reactivate</a>
                    <?php else: ?>
                        <a href="?put_on_hold=<?= $subscription['handle'] ?>&plan=<?= $plan['handle'] ?>" class="button">Put on hold</a>
                    <?php endif; ?>

                    <?php if ($subscription['state'] !== 'on_hold'): ?>
                        <?php if ($subscription['is_cancelled'] === true): ?>
                            <a href="?uncancel_subscription=<?= $subscription['handle'] ?>" class="button">Uncancel</a>
                        <?php else: ?>
                            <a href="?cancel_subscription=<?= $subscription['handle'] ?>" class="button">Cancel Subscription</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <br>
            </tr>

            <tr>
                <td>Payment methods:</td>
                <td></td>
            </tr>
            <?php foreach($user_payment_methods2 ?? [] as $payment_method): ?>
                <tr>
                    <td><?= $payment_method->get_masked_card() ?> <?= $payment_method->get_expiry_month() . '/' . $payment_method->get_expiry_year() ?></td>
                    <td>
                        <?php if ($payment_method->get_token() === $subscription_payment_method['id']): ?>
                            Current
                        <?php else: ?>
                            <a href="?change_payment_method=<?= $subscription['handle'] ?>&token_id=<?= $payment_method->get_id() ?>" class="button">Change</a>
                        <?php endif; ?>
                    </td>
                </tr>


                <br>
            <?php endforeach; ?>
            <tr>
                <td></td>
                <td><a href="<?= wc_get_endpoint_url('add-payment-method') . '?reepay_subscription=' . $subscription['handle'] ?>" class="button">Add payment method</a></td>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Status:</td>
            <td>
                <?php if ($subscription['state'] === 'expired'): ?>
                    Expired <?= $subscription['formatted_expired_date'] ?>
                <?php else: ?>
                    <?= $subscription['formatted_status'] ?>
                    <?php if ($subscription['renewing'] === false): ?>
                        Non-renewing
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>First period start:</td>
            <td>
                <?php if (!empty($subscription['first_period_start'])): ?>
                    <?= $subscription['formatted_first_period_start'] ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Current period:</td>
            <td>
                <?php if (!empty($subscription['current_period_start'])): ?>
                    <?= $subscription['formatted_current_period_start'] . '-' .  $subscription['formatted_next_period_start'] ?>
                <?php else: ?>
                    No Active period
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Total Amount (Incl. VAT):</td>
            <td>
                Kr <?= number_format($plan['amount']/100, 2) ?> DKK / Every Day
            </td>
        </tr>
        <tr>
            <td>Billing Cycle:</td>
            <td>
                <?php if (!empty($plan['fixed_count'])): ?>
                    1 out of <?= $plan['fixed_count'] ?>
                <?php else: ?>
                    Forever Until Canceled
                <?php endif; ?>
            </td>
        </tr>
        </tbody>
    </table>
<?php endforeach; ?>

<div class="woocommerce-pagination woocommerce-pagination--without-numbers woocommerce-Pagination">
    <?php if ( $args['previous_token'] !== null ) : ?>
        <a class="woocommerce-button woocommerce-button--previous woocommerce-Button woocommerce-Button--previous button" href="<?php echo esc_url( add_query_arg('prev_token', $args['previous_token'], wc_get_endpoint_url( 'subscriptions', $args['previous_token'] )) ); ?>"><?php esc_html_e( 'Previous', 'woocommerce' ); ?></a>
    <?php endif; ?>

    <?php if ( !empty($args['next_page_token']) ) : ?>
        <a class="woocommerce-button woocommerce-button--next woocommerce-Button woocommerce-Button--next button" href="<?php echo esc_url( add_query_arg('prev_token', $args['current_token'], wc_get_endpoint_url( 'subscriptions', $args['next_page_token'] )) ); ?>"><?php esc_html_e( 'Next', 'woocommerce' ); ?></a>
    <?php endif; ?>
</div>