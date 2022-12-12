<?php

/**
 * Class WC_Reepay_Meta_Boxes
 */
class WC_Reepay_Meta_Boxes {
	/**
	 * WC_Reepay_Meta_Boxes constructor.
	 */
	public function __construct() {
		add_action( 'reepay_checkout_product_show_meta_box', '__return_false' );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Register meta boxes on order page
	 */
	public function add_meta_boxes() {
		global $post;

		$screen     = get_current_screen();
		$post_types = array( 'shop_order', 'shop_subscription' );

		if ( ! in_array( $screen->id, $post_types, true ) || ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$order = wc_get_order( $post->ID );

		if ( empty( $order ) ) {
			return;
		}

		add_meta_box(
			'reepay_checkout_customer',
			__( 'Customer' ),
			array( $this, 'generate_meta_box_content_customer' ),
			'shop_order',
			'side',
			'high'
		);

		add_meta_box(
			'reepay_checkout_invoice',
			__( 'Invoice' ),
			array( $this, 'generate_meta_box_content_invoice' ),
			'shop_order',
			'side',
			'high'
		);

		add_meta_box(
			'reepay_checkout_subscription',
			__( 'Subscription' ),
			array( $this, 'generate_meta_box_content_subscription' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * function to show customer meta box content
	 *
	 * @param WP_Post $post current post object
	 * @param array $args additional arguments sent to add_meta_box function
	 */
	public function generate_meta_box_content_customer( $post, $args ) {
		
	}

	/**
	 * function to show customer meta box content
	 *
	 * @param WP_Post $post current post object
	 * @param array $args additional arguments sent to add_meta_box function
	 */
	public function generate_meta_box_content_invoice( $post, $args ) {

	}

	/**
	 * function to show customer meta box content
	 *
	 * @param WP_Post $post current post object
	 * @param array $args additional arguments sent to add_meta_box function
	 */
	public function generate_meta_box_content_subscription( $post, $args ) {

	}
}