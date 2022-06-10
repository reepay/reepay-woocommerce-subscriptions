<?php

function get_status($subscription, $plan) {
    if ($subscription['is_cancelled'] === true) {
        return 'cancelled';
    }
    if ($subscription['state'] === 'expired') {
        return 'expired';
    }

    if ($subscription['state'] === 'on_hold') {
        return 'on_hold';
    }

    if ($subscription['state'] === 'is_cancelled') {
        return 'is_cancelled';
    }

    if ($subscription['state'] === 'active') {
        if (isset($subscription['trial_end'])) {
            $now = new DateTime();
            $trial_end = new DateTime($subscription['trial_end']);
            if ($trial_end > $now) {
                return 'trial';
            }
        }
        return 'active';
    }

    return $subscription['state'];
}

function is_trial() {

}

function my_date($dateStr) {
    return (new DateTime($dateStr))->format('d M Y');
}

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
    $payment_methods = reepay_s()->api()->request("subscription/".$subscription['handle']."/pm")

    ?>
    <h1><?= $plan['name'] ?></h1>
    <table>
        <tbody>
        <tr>
            <td>
                Payment methods:
            </td>
            <td>
                <?php foreach($payment_methods as $payment_method): ?>
                    <?= $payment_method['card']['cart_type'] ?> <?= $payment_method['card']['masked_card'] ?>
                    <br>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php if (!$is_expired): ?>
            <tr>
                <td>Actions:</td>
                <td>
                    <?php if ($subscription['state'] === 'on_hold'): ?>
                        <a href="?reactivate=<?= $subscription['handle'] ?>">Reactivate</a>
                    <?php else: ?>
                        <a href="?put_on_hold=<?= $subscription['handle'] ?>">Put on hold</a>
                    <?php endif; ?>

                    <?php if ($subscription['state'] !== 'on_hold'): ?>
                        <?php if ($subscription['is_cancelled'] === true): ?>
                            <a href="?uncancel_subscription=<?= $subscription['handle'] ?>">Uncancel</a>
                        <?php else: ?>
                            <a href="?cancel_subscription=<?= $subscription['handle'] ?>">Cancel Subscription</a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <br>
                    <?php foreach($user_payment_methods2 ?? [] as $payment_method): ?>
                        <a href="?change_payment_method=<?= $subscription['handle'] ?>&token_id=<?= $payment_method->get_id() ?>">Change payment method to <?= $payment_method->get_masked_card() ?> <?= $payment_method->get_expiry_month() . '/' . $payment_method->get_expiry_year() ?></a>
                        <br>
                    <?php endforeach; ?>
            </tr>
        <?php endif; ?>
        <tr>
            <td>Status:</td>
            <td>
                <?php if ($is_expired): ?>
                    Expired <?= my_date($subscription['expired_date']) ?>
                <?php else: ?>
                    <?= get_status($subscription, $plan) ?>
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
                    <?= my_date($subscription['first_period_start']) ?>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>Current period:</td>
            <td>
                <?php if (!empty($subscription['current_period_start'])): ?>
                    <?= my_date($subscription['current_period_start']) . '-' .  my_date($subscription['next_period_start']) ?>
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