<?php
/**
 * Class ProductGenerator
 *
 * @package Reepay\Subscriptions
 */

namespace Reepay\Subscriptions\Tests\Helpers;

use Exception;
use WC_Product;
use WC_Product_Reepay_Simple_Subscription;
use WC_Product_Simple;
use WC_Product_Subscription;

/**
 * Class ProductGenerator
 */
class ProductGenerator {
	/**
	 * Current product.
	 *
	 * @var WC_Product|null
	 */
	private ?WC_Product $product = null;

	/**
	 * @param string $type product type.
	 * @param array  $data product meta data.
	 */
	public function __construct( string $type = '', array $data = array() ) {
		if ( ! empty( $type ) ) {
			$this->generate( $type, $data );
		}
	}

	/**
	 * Generate a new product, replacing any previously generated one.
	 *
	 * @param string $type product type.
	 * @param array  $data product meta data.
	 *
	 * @return WC_Product|null
	 *
	 * @throws Exception On an unrecognized type.
	 */
	public function generate( string $type, array $data = array() ): ?WC_Product {
		$products = array(
			'simple'  => WC_Product_Simple::class,
			'woo_sub' => WC_Product_Subscription::class,
			'rp_sub'  => WC_Product_Reepay_Simple_Subscription::class,
		);

		if ( ! isset( $products[ $type ] ) ) {
			throw new Exception( 'Wrong product type' );
		}

		$this->product = class_exists( $products[ $type ] ) ? new $products[ $type ]() : null;

		if ( ! empty( $this->product ) ) {
			$this->product->set_regular_price( 12.23 );

			foreach ( $data as $key => $value ) {
				$function = 'set_' . ltrim( $key, '_' );

				if ( is_callable( array( $this->product, $function ) ) ) {
					$this->product->{$function}( $value );
				} else {
					$this->product->update_meta_data( $key, $value );
				}
			}

			$this->product->save();
		}

		return $this->product;
	}

	/**
	 * @return WC_Product|null
	 */
	public function product(): ?WC_Product {
		return $this->product;
	}
}
