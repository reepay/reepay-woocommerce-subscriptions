<?php

class WC_Reepay_Discounts_And_Coupons {

	public static $apply_to = [
		'setup_fee'       => 'Setup fee',
		'plan'            => 'Plan',
		'additional_cost' => 'Additional Costs',
		'ondemand'        => 'Instant Charges',
		'add_on'          => 'Add-on',
	];

	public static $coupon_types = [
		'reepay_percentage'    => 'Reepay Percentage Discount',
		'reepay_fixed_product' => 'Reepay Fixed product Discount',
	];

	public static $meta_fields = [
		'_reepay_discount_use_existing_coupon_id',
		'_reepay_discount_name',
		'_reepay_discount_amount',
		'_reepay_discount_type',
		'_reepay_discount_apply_to',
		'_reepay_discount_apply_to_items',
		'_reepay_discount_all_plans',
		'_reepay_discount_eligible_plans',
		'_reepay_discount_duration',
		'_reepay_discount_fixed_count',
		'_reepay_discount_fixed_period',
		'_reepay_discount_fixed_period_unit',
		'_reepay_discount_use_existing_coupon_id',
		'_reepay_discount_use_existing_discount_id',
	];

	/**
	 * @var bool
	 */
	public $applied_fixed_coupon = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'woocommerce_coupon_discount_types', [ $this, 'add_coupon_types' ], 10, 1 );
		add_action( 'woocommerce_coupon_options', [ $this, 'add_coupon_text_field' ], 10 );
		add_action( 'woocommerce_coupon_options_save', [ $this, 'save_coupon_text_field' ], 10, 2 );
		add_filter( 'woocommerce_coupon_is_valid', [ $this, 'validate_coupon' ], 10, 4 );
		add_filter( 'woocommerce_coupon_is_valid_for_product', [ $this, 'validate_coupon_for_product' ], 10, 4 );
		add_filter( 'woocommerce_coupon_get_discount_amount', [ $this, 'apply_discount' ], 10, 5 );

		add_filter( "woocommerce_coupon_error", [ $this, "plugin_coupon_error_message" ], 10, 3 );

	}

	function get_discount_default_params( WC_Coupon $coupon, $data = [] ) {

		$name = get_post_meta( $coupon->get_id(), '_reepay_discount_name', true );
		$type = get_post_meta( $coupon->get_id(), '_reepay_discount_type', true );

		$params = [
			"name" => $name,
		];

		$amount = $coupon->get_amount();

		if ( $amount >= 1 ) {
			if ( $type === 'reepay_percentage' ) {
				$params['percentage'] = $amount;
			} else if ( $type === 'reepay_fixed_product' ) {
				$params['amount'] = $amount * 100;
			}
		}

		if ( $description = $coupon->get_description() ) {
			$params["description"] = $description;
		}

		return $params;
	}

	function get_coupon_default_params( WC_Coupon $coupon ) {
		$apply_plans = get_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', true ) ?: [];

		$name = get_post_meta( $coupon->get_id(), '_reepay_discount_name', true );


		$paramsCoupon = [
			"name"           => $name,
			"all_plans"      => empty( $apply_plans ),
			"eligible_plans" => $apply_plans,
		];

		return $paramsCoupon;
	}

	static function get_existing_discount( $handle ) {
		$discountObj = reepay_s()->api()->request( 'discount/' . $handle );

		$discount_data = [];

		$amount = ! empty( $discountObj['amount'] ) ? $discountObj['amount'] / 100 : $discountObj['percentage'];

		if ( ! empty( $discountObj['amount'] ) ) {
			$discount_data['_reepay_discount_type'] = 'reepay_fixed_product';
		}

		if ( ! empty( $discountObj['percentage'] ) ) {
			$discount_data['_reepay_discount_type'] = 'reepay_percentage';
		}

		$discount_data['_reepay_discount_name']     = $discountObj['name'];
		$discount_data['_reepay_discount_apply_to'] = empty( $discountObj['apply_to'] ) ? 'all' : 'custom';
		if ( isset( $discountObj['apply_to'][0] ) && $discountObj['apply_to'][0] === 'all' ) {
			$discount_data['_reepay_discount_apply_to'] = 'all';
		}
		$discount_data['_reepay_discount_apply_to_items'] = $discountObj['apply_to'];
		$discount_data['_reepay_discount_amount']         = ! empty( $amount ) ? $amount : 0;

		$discount_data['_reepay_discount_duration'] = 'forever';
		if ( ! empty( $discountObj['fixed_count'] ) ) {
			$discount_data['_reepay_discount_duration']    = 'fixed_number';
			$discount_data['_reepay_discount_fixed_count'] = $discountObj['fixed_count'];
		}

		if ( ! empty( $discountObj['fixed_period'] ) ) {
			$discount_data['_reepay_discount_duration']          = 'limited_time';
			$discount_data['_reepay_discount_fixed_period']      = $discountObj['fixed_period'];
			$discount_data['_reepay_discount_fixed_period_unit'] = $discountObj['fixed_period_unit'];
		}

		$discount_data['discount_handle'] = $handle;

		return $discount_data;
	}

	static function get_existing_coupon( $handle ) {
		$couponObj   = reepay_s()->api()->request( 'coupon/' . $handle );
		$discountObj = static::get_existing_discount( $couponObj['discount'] );

		$coupon_data                                    = $discountObj;
		$coupon_data['_reepay_discount_name']           = $couponObj['name'];
		$coupon_data['_reepay_discount_all_plans']      = $couponObj['all_plans'] ? '1' : '0';
		$coupon_data['_reepay_discount_eligible_plans'] = $couponObj['eligible_plans'];
		$coupon_data['coupon_handle']                   = $handle;

		return $coupon_data;
	}

	function plugin_coupon_error_message( $err, $err_code, WC_Coupon $coupon = null ) {
		if ( ! is_null( $coupon ) && $coupon->is_type( 'reepay_type' ) && intval( $err_code ) === 117 ) {
			return __( 'Coupon is not applied for this plans', 'reepay-subscriptions-for-woocommerce' );
		}

		return $err;
	}

	function create_discount( WC_Coupon $coupon, $data ) {

		$params = $this->get_discount_default_params( $coupon );

		$post_id       = $coupon->get_id();
		$apply_items   = array_map( 'sanitize_text_field', $data['_reepay_discount_apply_to_items'] ?? [ 'all' ] );
		$duration_type = sanitize_text_field( $data['_reepay_discount_duration'] ?? 'forever' );

		$discountHandle     = 'discount' . $post_id;
		$params["handle"]   = $discountHandle;
		$params["apply_to"] = $apply_items;


		if ( $duration_type === 'fixed_number' ) {
			$params["fixed_count"] = intval( $data['_reepay_discount_fixed_count'] );
		}

		if ( $duration_type === 'limited_time' ) {
			$params["fixed_period_unit"] = sanitize_text_field( $data['_reepay_discount_fixed_period_unit'] );
			$params["fixed_period"]      = intval( $data['_reepay_discount_fixed_period'] );
		}


		try {
			$discountObj = reepay_s()->api()->request( 'discount', 'POST', $params );
			update_post_meta( $post_id, '_reepay_discount_handle', $params['handle'] );

			return $discountObj;
		} catch ( Exception $e ) {
			WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
			wp_update_post( [
				'ID'     => $coupon->get_id(),
				'status' => 'draft',
			] );
		}

		return false;
	}

	function update_discount( WC_Coupon $coupon ) {
		$params = $this->get_discount_default_params( $coupon );
		$handle = get_post_meta( $coupon->get_id(), '_reepay_discount_handle', true );

		try {
			$discountObj = reepay_s()->api()->request( 'discount/' . $handle, 'PUT', $params );

			return $discountObj;
		} catch ( Exception $e ) {
			WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
		}

		return false;
	}

	function get_coupons() {
		return reepay_s()->api()->request( 'coupon' )['content'] ?? [];
	}

	function get_discounts() {
		return reepay_s()->api()->request( 'discount' )['content'] ?? [];
	}

	function create_coupon( WC_Coupon $coupon, $discount_handle, $data ) {

		$paramsCoupon = $this->get_coupon_default_params( $coupon );

		$apply_plans = array_map( 'sanitize_text_field', $data['_reepay_discount_eligible_plans'] ?? [] );

		$post_id      = $coupon->get_id();
		$couponHandle = 'coupon' . $post_id;

		$paramsCoupon["handle"]   = $couponHandle;
		$paramsCoupon["code"]     = $coupon->get_code();
		$paramsCoupon["discount"] = $discount_handle;


		$paramsCoupon["all_plans"]      = empty( $apply_plans );
		$paramsCoupon["eligible_plans"] = $apply_plans;

		$end = $coupon->get_date_expires();

		if ( $max_redemptions = $coupon->get_usage_limit() ) {
			$paramsCoupon["max_redemptions"] = $max_redemptions;
		}

		if ( ! empty( $end ) ) {
			$paramsCoupon["valid_until"] = $end->format( 'Y-m-d\TH:i:s' );
		}

		try {
			$result2 = reepay_s()->api()->request( 'coupon', 'POST', $paramsCoupon );
			update_post_meta( $post_id, '_reepay_coupon_handle', $paramsCoupon['handle'] );

			return $result2;
		} catch ( Exception $e ) {
			WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
			wp_update_post( [
				'ID'     => $coupon->get_id(),
				'status' => 'draft',
			] );
		}

		return false;
	}

	function update_coupon( WC_Coupon $coupon ) {
		$paramsCoupon = $this->get_coupon_default_params( $coupon );
		$handle       = get_post_meta( $coupon->get_id(), '_reepay_coupon_handle', true );

		try {
			$result = reepay_s()->api()->request( 'coupon/' . $handle, 'PUT', $paramsCoupon );

			return $result;
		} catch ( Exception $e ) {
			WC_Reepay_Subscription_Admin_Notice::add_notice( $e->getMessage() );
		}

		return false;
	}

	function save_coupon_text_field( $post_id, WC_Coupon $coupon ) {
		$type = $coupon->get_discount_type();

		if ( $type !== 'reepay_type' ) {
			return;
		}

		$data         = $_REQUEST;
		$use_existing = false;
		if ( $_REQUEST['use_existing_coupon'] === 'true' ) {
			$couponData = static::get_existing_coupon( sanitize_text_field( $_REQUEST['_reepay_discount_use_existing_coupon_id'] ) );
			update_post_meta( $post_id, '_reepay_coupon_handle', $couponData['coupon_handle'] );
			update_post_meta( $post_id, '_reepay_discount_handle', $couponData['discount_handle'] );
			$data         = array_merge( $data, $couponData );
			$use_existing = true;
		}

		if ( $_REQUEST['use_existing_discount'] === 'true' ) {
			$discountData = static::get_existing_discount( sanitize_text_field( $_REQUEST['_reepay_discount_use_existing_discount_id'] ) );
			update_post_meta( $post_id, '_reepay_discount_handle', $discountData['discount_handle'] );
			$data         = array_merge( $data, $discountData );
			$use_existing = true;
		}

		$data['_reepay_discount_name'] = sanitize_text_field( $_REQUEST['_reepay_discount_name'] );

		if ( ! empty( $data ) ) {
			foreach ( self::$meta_fields as $key ) {
				if ( isset( $data[ $key ] ) ) {
					if ( is_array( $data[ $key ] ) ) {
						update_post_meta( $post_id, $key, array_map( 'sanitize_text_field', $data[ $key ] ) );
					} else {
						update_post_meta( $post_id, $key, sanitize_text_field( $data[ $key ] ) );
					}
				}
			}
		}

		$discountHandle = get_post_meta( $post_id, '_reepay_discount_handle', true );
		$couponHandle   = get_post_meta( $post_id, '_reepay_coupon_handle', true );
		$duration       = sanitize_text_field( $data['_reepay_discount_duration'] ?? 'forever' );

		$is_update = false;

		if ( ! empty( $couponHandle ) ) {
			$is_update = true;
		}

		if ( ! $is_update || $use_existing ) {
			if ( $duration === 'fixed_number' ) {
				$coupon->set_usage_limit( intval( $data['_reepay_discount_fixed_count'] ) );
			}

			if ( $duration === 'limited_time' ) {
				$length = intval( $data['_reepay_discount_fixed_period'] );
				$units  = sanitize_text_field( $data['_reepay_discount_fixed_period_unit'] );
				$date   = new DateTime();
				if ( $units === 'months' ) {
					$date->modify( "+$length months" );
				}

				if ( $units === 'days' ) {
					$date->modify( "+$length days" );
				}
				$coupon->set_date_expires( $date->getTimestamp() );
			}

			if ( ! empty( $data['_reepay_discount_amount'] ) ) {
				$coupon->set_amount( floatval( $data['_reepay_discount_amount'] ) );
			}

		}


		if ( empty( $discountHandle ) ) {
			$discount       = $this->create_discount( $coupon, $data );
			$discountHandle = $discount['handle'];
		} else {
			$this->update_discount( $coupon );
		}

		if ( empty( $couponHandle ) && ! empty( $discountHandle ) ) {
			$this->create_coupon( $coupon, $discountHandle, $data );
		} else if ( ! empty( $couponHandle ) ) {
			$this->update_coupon( $coupon );
		}

		$coupon->save();

	}

	function is_coupon_applied_for_plans( $coupon, WC_Product $product ) {
		$apply_to_plans     = get_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', true ) ?: [];
		$apply_to_all_plans = get_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', true );
		if ( $apply_to_all_plans === '1' ) {
			return true;
		}
		if ( $apply_to_all_plans === '0' && count( $apply_to_plans ) > 0 ) {
			$plan_handle = get_post_meta( $product->get_id(), '_reepay_subscription_handle', true );

			return in_array( $plan_handle, $apply_to_plans );
		}

		return false;
	}

	function apply_discount( $discount, $discounting_amount, $cart_item, $single, WC_Coupon $coupon ) {
		$type = get_post_meta( $coupon->get_id(), '_reepay_discount_type', true );

		if ( $type === 'reepay_percentage' ) {
			$product = $cart_item['data'];
			if ( $this->is_coupon_applied_for_plans( $coupon, $product ) ) {
				$discount = (float) $coupon->get_amount() * ( $discounting_amount / 100 );

			}
		}

		if ( ! $this->applied_fixed_coupon && $type === 'reepay_fixed_product' ) {
			$discount                   = $coupon->get_amount() / $cart_item['quantity'];
			$this->applied_fixed_coupon = true;
		}

		return $discount;
	}

	function validate_applied_for_plans( WC_Product $product, $apply_to_plans = [] ) {
		if ( count( $apply_to_plans ) > 0 ) {
			$plan_handle = get_post_meta( $product->get_id(), '_reepay_subscription_handle', true );

			return in_array( $plan_handle, $apply_to_plans );
		}

		return true;
	}

	/**
	 * @param $valid
	 * @param WC_Coupon $coupon
	 * @param WC_Discounts $discounts
	 *
	 * @return bool
	 * @throws Exception
	 */
	function validate_coupon( $valid, WC_Coupon $coupon, WC_Discounts $discounts ) {
		if ( ! $coupon->is_type( 'reepay_type' ) ) {
			return $valid;
		}

		$apply_to_plans     = get_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', true ) ?: [];
		$apply_to_all_plans = get_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', true );
		$apply              = false;
		if ( $apply_to_all_plans === '0' && count( $apply_to_plans ) > 0 ) {
			foreach ( $discounts->get_items_to_validate() as $item ) {
				$valid = $this->validate_applied_for_plans( $item->product, $apply_to_plans );
				if ( $valid ) {
					$apply = true;
					break;
				}
			}
		}

		if ( $apply_to_all_plans === '0' && ! $apply ) {
			throw new Exception( __( 'Sorry, this coupon is not applicable to the products', 'woocommerce' ), 113 );
		}

		$check_coupon = self::coupon_can_be_applied( $coupon->get_code() );

		if ( true === $check_coupon ) {
			return true;
		}

		throw new Exception( $check_coupon->get_error_message() );
	}

	function validate_coupon_for_product( $valid, WC_Product $product, WC_Coupon $coupon, $values ) {
		if ( $coupon->is_type( 'reepay_type' ) ) {
			return true;
		}

		$apply_to_plans     = get_post_meta( $coupon->get_id(), '_reepay_discount_eligible_plans', true ) ?: [];
		$apply_to_all_plans = get_post_meta( $coupon->get_id(), '_reepay_discount_all_plans', true );
		if ( $apply_to_all_plans === '0' && count( $apply_to_plans ) > 0 ) {
			if ( ! $this->validate_applied_for_plans( $product, $apply_to_plans ) ) {
				return false;
			}
		}

		return $valid;
	}

	function add_coupon_text_field() {
		$meta = get_post_meta( get_the_ID() );

		$apply_to_items = [];
		if ( ! empty( $meta['_reepay_discount_apply_to_items'][0] ) ) {
			$apply_to_items = unserialize( $meta['_reepay_discount_apply_to_items'][0] );
		}
		$meta['_reepay_discount_apply_to_items'][0] = $apply_to_items;

		$apply_to_plans = [];
		if ( ! empty( $meta['_reepay_discount_eligible_plans'][0] ) ) {
			$apply_to_plans = unserialize( $meta['_reepay_discount_eligible_plans'][0] );
		}
		$meta['_reepay_discount_eligible_plans'][0] = $apply_to_plans;

		$plans     = WC_Reepay_Subscription_Plan_Simple::get_plans_wc();
		$coupons   = $this->get_coupons();
		$discounts = $this->get_discounts();

		$handle = get_post_meta( get_the_ID(), '_reepay_coupon_handle', true );

		$is_update = false;
		if ( ! empty( $handle ) ) {
			$is_update = true;
		}

		wc_get_template(
			'discounts-and-coupons-fields.php',
			array(
				'meta'      => $meta,
				'plans'     => $plans,
				'coupons'   => $coupons,
				'discounts' => $discounts,
				'is_update' => $is_update,
			),
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);
	}

	public function add_coupon_types( $discount_types ) {
		return array_merge( $discount_types, [
			'reepay_type' => 'Reepay discount',
		] );
	}

	/**
	 * @param string  $code
	 * @param string  $customer_handle - current user by default
	 * @param string  $plan
	 *
	 * @return bool|WP_Error
	 */
	public static function coupon_can_be_applied( $code, $customer_handle = null, $plan = '' ) {
		$request_url = "coupon/code/validate?code=$code";

		if ( empty( $customer_handle ) ) {
			$customer_handle = get_user_meta( get_current_user_id() )['reepay_customer_id'] ?? null;
			$customer_handle = is_array($customer_handle) ? $customer_handle[0] : $customer_handle;

			if(!empty($customer_handle)) {
				$request_url .= "&customer=$customer_handle";
			}
		}

		if ( ! empty( $plan ) ) {
			$request_url .= "&plan=$plan";
		}

		try {
			reepay_s()->api()->request( $request_url );
			return true;
		} catch (Exception $e) {
			return new WP_Error( 404, 'This coupon cannot be used. Try another' );
		}
	}
}
