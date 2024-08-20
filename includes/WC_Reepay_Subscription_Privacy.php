<?php
/**
 * Handles Billwerk+ optimize subscription privacy
 */
if ( ! class_exists( 'WC_Abstract_Privacy' ) ) {
	return;
}

class WC_Reepay_Subscription_Privacy extends WC_Abstract_Privacy {
    public function __construct() {
        parent::__construct( __( 'Billwerk+ Optimize Terms & Conditions', 'reepay-subscriptions-for-woocommerce'), 5, 10);
    }

    public function get_privacy_message() {
        $message = __('
        <h1>Terms of Service</h1>
        <h2>Duration</h2>
        <p>As long as the membership is not canceled, it will be charged on your payment card at regular intervals. Payment is due in advance. You can terminate your subscription at any time by contacting us via phone or email, but no later than 8 days before the next renewal date.</p>
        <h2>Receipt</h2>
        <p>After registration and for each charge, a receipt will be sent to your email.</p>
        <h2>Payment Card Expiration</h2>
        <p>If your payment card expires, you will receive an email with a link to renew your card information.</p>
        <h2>Payment Card Information</h2>
        <p>By signing up for this subscription, you authorize us to store the necessary payment card information for automatic debit through our payment gateway. This information will be deleted when the subscription expires.</p>
        <h2>Price</h2>
        <p>All prices include VAT unless otherwise stated.</p>
        <h2>Delivery</h2>
        <p>Once registration is confirmed, the purchased services can be used, and the product is considered delivered.</p>
        <h2>Refunding</h2>
        <p>Once a subscription has started, it is not refundable.</p>
        <h2>Complaint</h2>
        <p>Any form of complaint must be directed to our support team. For questions about your purchase, please contact our support team.</p>
        <h2>Contact Permission</h2>
        <p>By signing up, you agree that we are allowed to use your email or phone to contact you regarding your subscription and related services. This includes renewal notifications, service updates, and important account information.</p>
        ', 'reepay-subscriptions-for-woocommerce');
        return $message;
    }
}
?>