<?php

class WC_Reepay_Subscription_Plan_Variable extends WC_Reepay_Subscription_Plan_Simple {
	public static $frontend_template = 'plan-subscription-frontend-variable.php';

	public $plan_fields_template = 'plan-subscription-fields-variable.php';

	public $loop = 0;

	public function create_subscription_product_class() {
		include_once( reepay_s()->settings( 'plugin_path' ) . '/includes/WC_Product_Reepay_Variable_Subscription.php' );
	}

	public function load_subscription_product_class( $php_classname, $product_type ) {
		if ( $product_type == 'reepay_variable_subscriptions' ) {
			$php_classname = 'WC_Product_Reepay_Variable_Subscription';
		}

		return $php_classname;
	}

	public function add_subscription_product_type( $types ) {
		$types['reepay_variable_subscriptions'] = __( 'Billwerk+ Variable Subscription' );

		return $types;
	}

	protected function register_actions() {
		add_action( "woocommerce_reepay_variable_subscriptions_add_to_cart", [ $this, 'add_to_cart' ] );
		add_action( 'woocommerce_variation_options_pricing', [ $this, 'add_custom_field_to_variations' ], 10, 3 );
		add_action( 'woocommerce_save_product_variation', [ $this, 'save_subscription_meta' ], 10, 2 );
		add_filter( 'woocommerce_add_to_cart_handler', [ $this, 'variable_add_to_cart_fix' ], 10, 2 );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_filter( 'woocommerce_get_price_html', [ $this, 'change_currency_symbol_variation' ], 10, 2 );
	}

	public function change_currency_symbol_variation( $price, $product ) {
		if ( WC_Reepay_Checkout::is_reepay_product( $product ) && get_class( $product ) == 'WC_Product_Variation' ) {
			$currency_product = WC_Product_Reepay_Variable_Subscription::get_currency( $product );
			if ( $currency_product != get_woocommerce_currency() ) {
				$args['currency'] = $currency_product;
				$price            = wc_price( wc_get_price_to_display( $product ),
						$args ) . $product->get_price_suffix();

				return $price;
			}
		}

		return $price;
	}

	public function variable_add_to_cart_fix( $type, $adding_to_cart ) {
		if ( $type == 'reepay_variable_subscriptions' ) {
			return 'variable';
		}

		return $type;
	}

	/**
	 * @param  int  $post_id
	 *
	 * @return array<string, mixed>
	 */
	public function get_subscription_template_data( $post_id ) {
		$data = parent::get_subscription_template_data( $post_id );

		$data['loop'] = $this->loop;

		$data['data_plan'] = json_encode( [
			'product_id' => $data['post_id'],
			'loop'       => $data['loop']
		] );

		return $data;
	}

	/**
	 * @param  array  $data
	 *
	 * @return false|string
	 */
	public function get_plan_fields_data_template( $data ) {
		$data['loop'] = $this->loop;

		ob_start();
		wc_get_template(
			$this->plan_fields_data_template,
			$data,
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);

		return ob_get_clean();
	}

	public function save_subscription_meta( $post_id, $i = null ) {
		$this->loop = $i;

		parent::save_subscription_meta( $post_id );

		if ( ! empty( $_POST['_reepay_subscription_customer_role'][ $i ] ) && $_POST['_reepay_subscription_customer_role'][ $i ] !== 'without_changes' ) {
			update_post_meta( $post_id, '_reepay_subscription_customer_role',
				$_POST['_reepay_subscription_customer_role'][ $i ] );
		} else {
			delete_post_meta( $post_id, '_reepay_subscription_customer_role' );
		}
	}

	public function get_subscription_handle_from_request() {
		if ( empty( $_REQUEST['_reepay_subscription_handle'] ) ) {
			return '';
		}

		return wc_clean( $_REQUEST['_reepay_subscription_handle'][ $this->loop ] ?? '' );
	}

	public function is_reepay_product_saving() {
		return ! empty( $_REQUEST ) &&
		       ! empty( $_REQUEST['product-type'] ) &&
		       $_REQUEST['product-type'] == 'reepay_variable_subscriptions' &&
		       ! empty( $_REQUEST['_reepay_subscription_handle'] );
	}


	/**
	 * @param  int  $loop  Position in the loop.
	 * @param  array  $variation_data  Variation data.
	 * @param  WP_Post  $variation  Post data.
	 */
	public function add_custom_field_to_variations( $loop, $variation_data, $variation ) {
		global $post;
		$post = $variation;

		$this->loop = $loop;
		$this->subscription_pricing_fields( $post->ID );

		wp_reset_postdata();
	}

	/**
	 * @param  string  $handle  reepay plan handle
	 *
	 * @return array<string, mixed> meta fields to save
	 */
	public function get_remote_plan_meta( $handle ) {
		$plan_data             = parent::get_remote_plan_meta( $handle );
		$plan_data['variable'] = true;

		return $plan_data;
	}

	public function register_scripts() {
		wp_register_script( 'reepay-variable-product',
			reepay_s()->settings( 'plugin_url' ) . 'assets/js/product-variable.js',
			[ 'jquery' ],
			reepay_s()->settings( 'version' ),
			true
		);
	}

	/**
	 * @param  WC_Product  $product
	 */
	public function enqueue_variable_product_script( $product ) {
		$variable_template         = static::$frontend_template;
		static::$frontend_template = parent::$frontend_template;

		$variations_info = [];

		foreach ( $product->get_children() as $variation_id ) {
			$variation_product                = wc_get_product( $variation_id );
			$variations_info[ $variation_id ] = $this->get_subscription_info_html( $variation_product );
		}

		static::$frontend_template = $variable_template;

		wp_enqueue_script( 'reepay-variable-product' );
		wp_localize_script(
			'reepay-variable-product',
			'reepay_variable_product_info',
			$variations_info
		);
	}

	public function add_to_cart() {
		$product = wc_get_product();

		echo $this->get_subscription_info_html( $product );
		do_action( 'woocommerce_variable_add_to_cart' );

		$this->enqueue_variable_product_script( $product );
	}
}
