<?php
/**
 * Class PrivacyTest
 *
 * Covers WC_Reepay_Subscription_Privacy (includes/WC_Reepay_Subscription_Privacy.php).
 *
 * Extends WooCommerce core's WC_Abstract_Privacy (the whole file no-ops via
 * an early `return` if that class doesn't exist, which won't happen in this
 * suite's matrix since `woo` is always active). get_privacy_message() is a
 * pure static-string getter - one direct test confirming non-empty HTML
 * output is sufficient per the brief's lighter bar for this file.
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\Reepay_Subscriptions_UnitTestCase;

/**
 * PrivacyTest.
 */
class PrivacyTest extends Reepay_Subscriptions_UnitTestCase {

	/**
	 * Test @see WC_Reepay_Subscription_Privacy::get_privacy_message returns
	 * a non-empty HTML string containing the expected "Terms of Service"
	 * heading.
	 */
	public function test_get_privacy_message_returns_non_empty_html() {
		$privacy = new WC_Reepay_Subscription_Privacy();

		$message = $privacy->get_privacy_message();

		$this->assertIsString( $message );
		$this->assertNotEmpty( $message );
		$this->assertStringContainsString( 'Terms of Service', $message );
		$this->assertStringContainsString( '<h1>', $message );
	}
}
