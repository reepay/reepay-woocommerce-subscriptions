<?php

/**
 * Class WC_Reepay_Meta_Boxes
 */
class WC_Reepay_Meta_Boxes {

	/**
	 * @var string
	 */
	private $dashboard_url = 'https://app.reepay.com/#/rp/';

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

		if ( ! empty( get_post_meta( $post->ID, '_reepay_customer', true ) ) ) {
			add_meta_box(
				'reepay_checkout_customer',
				__( 'Customer' ),
				array( $this, 'generate_meta_box_content_customer' ),
				'shop_order',
				'side',
				'high'
			);
		}

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
		$template_args = [
			'email' => get_post_meta($post->ID, '_billing_email', true),
			'handle' => get_post_meta($post->ID, '_reepay_customer', true)
		];

		$template_args['link'] = $this->dashboard_url . 'customers/customers/customer/' . $template_args['handle'];

		wc_get_template(
			'meta-boxes/customer.php',
			$template_args,
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	/**
	 * function to show customer meta box content
	 *
	 * @param WP_Post $post current post object
	 * @param array $args additional arguments sent to add_meta_box function
	 */
	public function generate_meta_box_content_invoice( $post, $args ) {
		$order = wc_get_order( $post );

		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();

		if ( ! in_array( $payment_method, WC_ReepayCheckout::PAYMENT_METHODS ) ) {
			return;
		}

		$gateway = rp_get_payment_method( $order );

		if ( empty( $gateway ) ) {
			return;
		}

		$order_data = $gateway->api->get_invoice_data( $order );

		if ( is_wp_error( $order_data ) ) {
			return;
		}

		wc_get_template(
			'meta-boxes/invoice.php',
			[
				'gateway'            => $gateway,
				'order'              => $order,
				'order_id'           => $order->get_order_number(),
				'order_data'         => $order_data,
				'order_is_cancelled' => $order->get_meta( '_reepay_order_cancelled' ) === '1' && 'cancelled' != $order_data['state'],
				'link' => $this->dashboard_url . 'payments/invoices/invoice/' . $order_data['handle']
			],
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	/**
	 * function to show customer meta box content
	 *
	 * @param WP_Post $post current post object
	 * @param array $args additional arguments sent to add_meta_box function
	 */
	public function generate_meta_box_content_subscription( $post, $args ) {
		$template_args = [
			'handle' => get_post_meta($post->ID, '_reepay_subscription_handle', true),
			'plan' => get_post_meta($post->ID, '_reepay_subscription_plan', true)
		];

		if ( empty( $template_args['plan'] ) ) {
			try {
				$subscription = reepay_s()->api()->request( "subscription/{$template_args['handle']}");
				$template_args['plan'] = $subscription['plan'];
				update_post_meta( $post->ID, '_reepay_subscription_plan', $subscription['plan'] );
			} catch (Exception $e) {}
		}

		$template_args['link'] = $this->dashboard_url . 'subscriptions/subscription/' . $template_args['handle'];

		wc_get_template(
			'meta-boxes/plan.php',
			$template_args,
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}
}