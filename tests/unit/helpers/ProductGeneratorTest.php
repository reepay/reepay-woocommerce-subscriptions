<?php
/**
 * Class ProductGeneratorTest
 *
 * @package Reepay\Subscriptions
 */

use Reepay\Subscriptions\Tests\Helpers\ProductGenerator;

/**
 * ProductGeneratorTest.
 */
class ProductGeneratorTest extends WP_UnitTestCase {

	/**
	 * Test @see ProductGenerator::generate builds a simple product.
	 */
	public function test_generate_simple_product() {
		$generator = new ProductGenerator();
		$product   = $generator->generate( 'simple' );

		$this->assertInstanceOf( 'WC_Product_Simple', $product );
		$this->assertSame( 12.23, (float) $product->get_regular_price() );
	}

	/**
	 * Test @see ProductGenerator::generate builds an rp_sub product when the class exists.
	 */
	public function test_generate_rp_sub_product() {
		if ( ! class_exists( 'WC_Product_Reepay_Simple_Subscription' ) ) {
			$this->markTestSkipped( 'WC_Product_Reepay_Simple_Subscription not loaded.' );
		}

		$generator = new ProductGenerator();
		$product   = $generator->generate( 'rp_sub' );

		$this->assertInstanceOf( 'WC_Product_Reepay_Simple_Subscription', $product );
	}

	/**
	 * Test @see ProductGenerator::generate throws on an unknown type.
	 */
	public function test_generate_unknown_type_throws() {
		$this->expectException( \Exception::class );

		( new ProductGenerator() )->generate( 'not_a_real_type' );
	}
}
