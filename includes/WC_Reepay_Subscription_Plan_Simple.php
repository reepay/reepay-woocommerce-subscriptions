<?php

class WC_Reepay_Subscription_Plan_Simple {

	const TYPE_DAILY = 'daily';
	const TYPE_MONTH_START_DATE = 'month_startdate';
	const TYPE_MONTH_FIXED_DAY = 'month_fixedday';
	const TYPE_MONTH_LAST_DAY = 'month_lastday';
	const TYPE_PRIMO = 'primo';
	const TYPE_ULTIMO = 'ultimo';
	const TYPE_HALF_YEARLY = 'half_yearly';
	const TYPE_START_DATE_12 = 'month_startdate_12';
	const TYPE_WEEKLY_FIXED_DAY = 'weekly_fixedday';
	const TYPE_MANUAL = 'manual';

	public static $schedule_types = [];

	public static $trial = [];

	public static $types_info = [];

	public static $types_info_short = [];

	public static $bill_types = [];

	public static $proration_types = [];

	public static $number_to_week_day = [];

	public static $meta_fields = [
		'_reepay_subscription_handle',
		'_reepay_subscription_price',
		'_reepay_subscription_name',
		'_reepay_subscription_schedule_type',
		'_reepay_subscription_daily',
		'_reepay_subscription_month_startdate',
		'_reepay_subscription_month_fixedday',
		'_reepay_subscription_month_lastday',
		'_reepay_subscription_primo',
		'_reepay_subscription_ultimo',
		'_reepay_subscription_half_yearly',
		'_reepay_subscription_month_startdate_12',
		'_reepay_subscription_weekly_fixedday',
		'_reepay_subscription_renewal_reminder',
		'_reepay_subscription_default_quantity',
		'_reepay_subscription_contract_periods',
		'_reepay_subscription_contract_periods_full',
		'_reepay_subscription_notice_period',
		'_reepay_subscription_notice_period_start',
		'_reepay_subscription_billing_cycles',
		'_reepay_subscription_supersedes',
		'_reepay_subscription_billing_cycles_period',
		'_reepay_subscription_trial',
		'_reepay_subscription_fee',
	];

	/**
	 * @var string
	 */
	public static $frontend_template = 'plan-subscription-frontend.php';

	/**
	 * @var string
	 */
	public $plan_fields_template = 'plan-subscription-fields.php';

	/**
	 * @var string
	 */
	public $plan_fields_data_template = 'plan-subscription-fields-data.php';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'create_subscription_product_class' ] );
		add_filter( 'woocommerce_product_class', [ $this, 'load_subscription_product_class' ], 10, 2 );
		add_filter( 'product_type_selector', [ $this, 'add_subscription_product_type' ] );
		add_action( 'save_post', [ $this, 'set_sold_individual' ], PHP_INT_MAX );
		add_filter( 'woocommerce_get_order_item_totals', [ $this, 'rework_total' ], 10, 3 );

		add_filter( 'woocommerce_cart_item_price', [ $this, 'format_price' ], 10, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'format_price' ], 10, 2 );
		add_filter( 'woocommerce_order_formatted_line_subtotal', [ $this, 'format_price' ], 10, 3 );

		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'add_setup_fee' ] );

		$this->register_actions();
		$this->set_text_properties();
	}

	protected function register_actions() {
		add_action( "woocommerce_reepay_simple_subscriptions_add_to_cart", [ $this, 'add_to_cart' ] );
		add_action( 'woocommerce_product_options_general_product_data', [ $this, 'subscription_pricing_fields' ] );
		add_action( 'reepay_subscription_ajax_get_plan_html', [ $this, 'subscription_pricing_fields' ] );
		add_action( 'save_post', [ $this, 'save_subscription_meta' ], 11 );
		add_filter( 'woocommerce_cart_item_name', [ $this, 'checkout_subscription_info' ], 10, 3 );
		add_action( 'woocommerce_before_order_itemmeta', [ $this, 'admin_order_subscription_info' ], 10, 3 );
	}

	protected function set_text_properties() {
		self::$schedule_types = [
			self::TYPE_DAILY            => __( 'Day(s)', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_MONTH_START_DATE => __( 'Month(s)', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_MONTH_FIXED_DAY  => __( 'Fixed day of month', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_MONTH_LAST_DAY   => __( 'Last day of month', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_PRIMO            => __( 'Quarterly Primo', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_ULTIMO           => __( 'Quarterly Ultimo', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_HALF_YEARLY      => __( 'Half-yearly', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_START_DATE_12    => __( 'Yearly', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_WEEKLY_FIXED_DAY => __( 'Fixed day of week', 'reepay-subscriptions-for-woocommerce' ),
			self::TYPE_MANUAL           => __( 'Manual', 'reepay-subscriptions-for-woocommerce' ),
		];

		self::$trial = [
			''          => __( 'No Trial', 'reepay-subscriptions-for-woocommerce' ),
			'7days'     => __( '7 days', 'reepay-subscriptions-for-woocommerce' ),
			'14days'    => __( '14 days', 'reepay-subscriptions-for-woocommerce' ),
			'1month'    => __( '1 month', 'reepay-subscriptions-for-woocommerce' ),
			'customize' => __( 'Customize', 'reepay-subscriptions-for-woocommerce' ),
		];

		self::$types_info = [
			WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY               => __( 'Billed every day', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY . '_multiple' => __( 'Billed every %s days', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE               => __( 'Billed every month on the first day of the month', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE . '_multiple' => __( 'Billed every %s months on the first day of the month', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY               => __( 'Billed every month', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY . '_multiple' => __( 'Billed every %s months', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY               => __( 'Billed every month on the last day of the month', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY . '_multiple' => __( 'Billed every %s months on the last day of the month', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_PRIMO . '_multiple'         => __( 'Billed every %s months, on the first day of the month. The billing is fixed to these months: January, April, July, October', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_ULTIMO . '_multiple'        => __( 'Billed every %s months, on the last day of the month. The billing is fixed to these months: January, April, July, October', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_HALF_YEARLY . '_multiple'   => __( 'Billed every %s months, on the first day of the month. The billing is fixed to these months: January, July', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_START_DATE_12 . '_multiple' => __( 'Billed every %s months, on the first day of the month. The billing is fixed to these months: January', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY               => __( 'Billed every Week', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY . '_multiple' => __( 'Billed every %s Weeks', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MANUAL => __( 'Manual', 'reepay-subscriptions-for-woocommerce' ),
		];

		self::$types_info_short = [
			WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY               => __( 'Day', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY . '_multiple' => __( '%s days', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE               => __( 'Month', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE . '_multiple' => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY               => __( 'Month', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY . '_multiple' => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY               => __( 'Month', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY . '_multiple' => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_PRIMO . '_multiple'         => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_ULTIMO . '_multiple'        => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_HALF_YEARLY . '_multiple'   => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_START_DATE_12 . '_multiple' => __( '%s months', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY               => __( 'Week', 'reepay-subscriptions-for-woocommerce' ),
			WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY . '_multiple' => __( '%s Weeks', 'reepay-subscriptions-for-woocommerce' ),

			WC_Reepay_Subscription_Plan_Simple::TYPE_MANUAL => __( 'Manual', 'reepay-subscriptions-for-woocommerce' ),
		];

		self::$bill_types = [
			'bill_prorated'    => __( 'Bill prorated (Default)', 'reepay-subscriptions-for-woocommerce' ),
			'bill_full'        => __( 'Bill for full period)', 'reepay-subscriptions-for-woocommerce' ),
			'bill_zero_amount' => __( 'Bill a zero amount', 'reepay-subscriptions-for-woocommerce' ),
			'no_bill'          => __( 'Do not consider the partial period a billing period', 'reepay-subscriptions-for-woocommerce' ),
		];

		self::$proration_types = [
			'full_day' =>	__( 'Full day proration', 'reepay-subscriptions-for-woocommerce' ),
			'by_minute' =>	__( 'By the minute proration', 'reepay-subscriptions-for-woocommerce' ),
		];

		self::$number_to_week_day = [
			1 => __( 'Monday', 'reepay-subscriptions-for-woocommerce' ),
			2 => __( 'Tuesday', 'reepay-subscriptions-for-woocommerce' ),
			3 => __( 'Wednesday', 'reepay-subscriptions-for-woocommerce' ),
			4 => __( 'Thursday', 'reepay-subscriptions-for-woocommerce' ),
			5 => __( 'Friday', 'reepay-subscriptions-for-woocommerce' ),
			6 => __( 'Saturday', 'reepay-subscriptions-for-woocommerce' ),
			7 => __( 'Sunday', 'reepay-subscriptions-for-woocommerce' ),
		];
	}

	public function checkout_subscription_info( $name, $cart_item, $cart_item_key ) {
		if ( ! empty( $cart_item['data'] ) && WC_Reepay_Checkout::is_reepay_product( $cart_item['data'] ) ) {
			$name = $name . $this->get_subscription_info_html( $cart_item['data'], true );
		}

		return $name;
	}

	public function admin_order_subscription_info( $item_id, $item, $product ) {
		if ( ! empty( $product ) && WC_Reepay_Checkout::is_reepay_product( $product ) ) {
			echo $this->get_subscription_info_html( $product, true );
		}
	}

	public function create_subscription_product_class() {
		include_once( reepay_s()->settings( 'plugin_path' ) . '/includes/WC_Product_Reepay_Simple_Subscription.php' );
	}

	public function load_subscription_product_class( $php_classname, $product_type ) {
		if ( $product_type == 'reepay_simple_subscriptions' ) {
			$php_classname = 'WC_Product_Reepay_Simple_Subscription';
		}

		return $php_classname;
	}

	public function add_subscription_product_type( $types ) {
		$types['reepay_simple_subscriptions'] = __( 'Reepay Simple Subscription', 'reepay-subscriptions-for-woocommerce' );

		return $types;
	}

	/**
	 * @param string $price
	 * @param array<string, mixed> $product
	 *
	 * @return string
	 */
	public function format_price( $price, $product ) {
		$product = wc_get_product( $product['variation_id'] ?: $product['product_id'] );

		if ( empty( $product ) || ! WC_Reepay_Checkout::is_reepay_product( $product ) ) {
			return $price;
		}

		if ( $product->is_type( 'variation' ) ) {
			return WC_Product_Reepay_Simple_Subscription::format_price( $product->get_price_html(), $product );
		}


		return $product->get_price_html();
	}

	public function add_to_cart() {
		echo $this->get_subscription_info_html( wc_get_product() );
		do_action( 'woocommerce_simple_add_to_cart' );
	}

	public function set_sold_individual( $post_id ) {
		if ( ! $this->is_reepay_product_saving() ) {
			return;
		}

		update_post_meta( $post_id, '_sold_individually', 'no' );
	}

	public function get_subscription_info_html( $product, $is_checkout = false ) {
		if ( ! WC_Reepay_Checkout::is_reepay_product( $product ) ) {
			return '';
		}

		ob_start();
		wc_get_template(
			static::$frontend_template,
			[
				'billing_plan'    => self::get_billing_plan( $product ),
				'trial'           => self::get_trial( $product ),
				'setup_fee'       => self::get_setup_fee( $product ),
				'contract_period' => self::get_contract_period( $product ),
				'domain'          => 'reepay-subscriptions-for-woocommerce',
				'is_checkout'     => $is_checkout
			],
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);

		return ob_get_clean();
	}

	public function get_plan( $handle ) {
		try {
			$result = reepay_s()->api()->request( "plan/" . $handle . "/current" );

			return $result;
		} catch ( Exception $e ) {
			$this->plan_error( $e->getMessage() );
		}

		return false;
	}

	public static function get_plans_wc() {
		$plansQuery    = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'meta_query'     => [
				[
					'key'     => '_reepay_subscription_handle',
					'compare' => 'EXISTS',
				]
			]
		] );
		$varPlansQuery = new WP_Query( [
			'post_type'      => 'product_variation',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'meta_query'     => [
				[
					'key'     => '_reepay_subscription_handle',
					'compare' => 'EXISTS',
				]
			]
		] );
		$posts         = array_merge( $plansQuery->posts ?? [], $varPlansQuery->posts ?? [] );
		$plans         = [];
		foreach ( $posts as $item ) {
			$handle           = get_post_meta( $item->ID, '_reepay_subscription_handle', true );
			$plans[ $handle ] = $item->post_title;
		}

		return $plans;
	}

	public static function wc_get_plan( $handle ) {
		$query = new WP_Query( [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_query'     => [
				[
					'key'   => '_reepay_subscription_handle',
					'value' => $handle,
				]
			]
		] );

		return wc_get_product( $query->post ?? null );
	}

	/**
	 * @return array|bool
	 */
	public function get_reepay_plans_list() {
		static $plans = null;

		if ( ! is_null( $plans ) ) {
			return $plans;
		}

		try {
			return reepay_s()->api()->request( "plan?only_active=true" ) ?: false;
		} catch ( Exception $e ) {
			$this->plan_error( $e->getMessage() );
		}

		return false;
	}

	/**
	 * @param int $post_id
	 *
	 * @return array<string, mixed>
	 */
	public function get_subscription_template_data( $post_id ) {
		$data = [
			'post_id'    => $post_id,
			'plans_list' => $this->get_reepay_plans_list() ?: [],
			'disabled'   => true,
			'domain'     => 'reepay-subscriptions-for-woocommerce',
		];

		foreach ( self::$meta_fields as $meta_field ) {
			$data[ $meta_field ] = get_post_meta( $post_id, $meta_field, true );
		}

		return $data;
	}

	/**
	 * @param int $post_id
	 * @param array $data
	 */
	public function subscription_pricing_fields( $post_id = null, $data = [] ) {
		global $post;

		$post_id = $post_id ?: $post->ID;

		$data = ! empty( $data ) ? $data : $this->get_subscription_template_data( $post_id );

		$data['product_object']          = wc_get_product( $post_id );

		foreach ( self::$meta_fields as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				$data[ $key ] = '';
			}
		}

		if ( ! empty( $data['_reepay_subscription_handle'] ) ) {
			$data['settings_exist'] = $this->get_plan_fields_data_template( $data );
		}

		echo $this->get_plan_fields_template( $data );
	}

	/**
	 * @param array $data
	 *
	 * @return false|string
	 */
	public function get_plan_fields_data_template( $data ) {
		ob_start();
		wc_get_template(
			$this->plan_fields_data_template,
			$data,
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);

		return ob_get_clean();
	}

	/**
	 * @param array $data
	 *
	 * @return false|string
	 */
	public function get_plan_fields_template( $data ) {
		ob_start();
		wc_get_template(
			$this->plan_fields_template,
			$data,
			'',
			reepay_s()->settings( 'plugin_path' ) . 'templates/'
		);

		return ob_get_clean();
	}

	public function save_remote_plan( $post_id, $handle ) {
		$data = $this->get_remote_plan_meta( $handle );

		if ( empty( $data ) ) {
			return false;
		}

		foreach ( self::$meta_fields as $key ) {
			delete_post_meta( $post_id, $key );

			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $post_id, $key, $data[ $key ] );
			}
		}

		return $data;
	}

	/**
	 * @param string $handle reepay plan handle
	 *
	 * @return array<string, mixed> meta fields to save
	 */
	public function get_remote_plan_meta( $handle ) {
		$plan_data = $this->get_plan( $handle );
		$plan_meta = [];

		if ( empty( $plan_data ) ) {
			$this->plan_error( __( 'Plan not found', 'reepay-subscriptions-for-woocommerce' ) );

			return [];
		}

		$plan_meta['_reepay_subscription_handle'] = $handle;

		$plan_meta['_regular_price'] = $plan_data['amount'] / 100;
		$plan_meta['_price']         = $plan_data['amount'] / 100;

		if ( ! empty( $plan_data['amount'] ) ) {
			$plan_meta['_reepay_subscription_price'] = intval( $plan_data['amount'] ) / 100;
		}

		if ( ! empty( $plan_data['name'] ) ) {
			$plan_meta['_reepay_subscription_name'] = $plan_data['name'];
		}

		if ( ! empty( $plan_data['vat'] ) ) {
			$plan_meta['_reepay_subscription_vat'] = $plan_data['vat'];
		}

		if ( ! empty( $plan_data['setup_fee'] ) ) {
			$fee = [
				'enabled'  => 'yes',
				'amount'   => intval( $plan_data['setup_fee'] ) / 100,
				'text'     => ! empty( $plan_data['setup_fee_text'] ) ? $plan_data['setup_fee_text'] : '',
				'handling' => $plan_data['setup_fee_handling'],
			];

			$plan_meta['_reepay_subscription_fee'] = $fee;
		}

		if ( ! empty( $plan_data['trial_interval_length'] ) ) {
			$type = '';
			if ( $plan_data['trial_interval_length'] == 7 && $plan_data['trial_interval_unit'] == 'days' ) {
				$type = '7days';
			} elseif ( $plan_data['trial_interval_length'] == 14 && $plan_data['trial_interval_unit'] == 'days' ) {
				$type = '14days';
			} elseif ( $plan_data['trial_interval_length'] == 1 && $plan_data['trial_interval_unit'] == 'months' ) {
				$type = '1month';
			}

			$trial = [
				'type'     => $type,
				'length'   => $plan_data['trial_interval_length'],
				'unit'     => $plan_data['trial_interval_unit'],
				'reminder' => ! empty( $plan_data['trial_reminder_email_days'] ),
			];

			if ( ! empty( $plan_data['trial_reminder_email_days'] ) ) {
				$trial['reminder'] = $plan_data['trial_reminder_email_days'];
			}

			$plan_meta['_reepay_subscription_trial'] = $trial;
		}

		if ( ! empty( $plan_data["fixed_count"] ) ) {
			$plan_meta['_reepay_subscription_billing_cycles']        = 'true';
			$plan_meta['_reepay_subscription_billing_cycles_period'] = $plan_data["fixed_count"];
		} else {
			$plan_meta['_reepay_subscription_billing_cycles'] = 'false';
		}

		if ( ! empty( $plan_data['notice_periods'] ) ) {
			$plan_meta['_reepay_subscription_notice_period'] = $plan_data['notice_periods'];
		}

		if ( isset( $plan_data['notice_periods_after_current'] ) ) {
			$plan_meta['_reepay_subscription_notice_period_start'] = $plan_data['notice_periods_after_current'] ? 'true' : 'false';
		}

		if ( ! empty( $plan_data['fixation_periods'] ) ) {
			$plan_meta['_reepay_subscription_contract_periods'] = $plan_data['fixation_periods'];
		}

		if ( isset( $plan_data['fixation_periods_full'] ) ) {
			$plan_meta['_reepay_subscription_contract_periods_full'] = $plan_data['fixation_periods_full'] ? 'true' : 'false';
		}

		if ( ! empty( $plan_data['quantity'] ) ) {
			$plan_meta['_reepay_subscription_default_quantity'] = $plan_data['quantity'];
		}

		if ( ! empty( $plan_data['renewal_reminder_email_days'] ) ) {
			$plan_meta['_reepay_subscription_renewal_reminder'] = $plan_data['renewal_reminder_email_days'];
		}

		if ( ! empty( $plan_data['schedule_type'] ) ) {
			$type = $plan_data['schedule_type'];

			if ( ! empty( $plan_data['schedule_fixed_day'] ) && ! empty( $plan_data['interval_length'] ) && $plan_data['schedule_type'] == 'month_fixedday' ) {
				if ( $plan_data['schedule_fixed_day'] == 28 ) {
					$type = 'ultimo';
				} elseif ( $plan_data['schedule_fixed_day'] == 1 ) {
					if ( $plan_data['interval_length'] == 3 ) {
						$type = 'primo';
					} elseif ( $plan_data['interval_length'] == 6 ) {
						$type = 'half_yearly';
					} elseif ( $plan_data['interval_length'] == 12 ) {
						$type = 'month_startdate_12';
					}
				}
			}

			$plan_meta['_reepay_subscription_schedule_type'] = $type;
		}

		if ( ! empty( $plan_data['interval_length'] ) ) {
			$plan_meta['_reepay_subscription_daily']           = $plan_data['interval_length'];
			$plan_meta['_reepay_subscription_month_startdate'] = $plan_data['interval_length'];

			$type_data = [
				'month'             => $plan_data['interval_length'],
				'day'               => ! empty( $plan_data['schedule_fixed_day'] ) ? $plan_data['schedule_fixed_day'] : '',
				'period'            => ! empty( $plan_data['partial_period_handling'] ) ? $plan_data['partial_period_handling'] : '',
				'proration'         => ! empty( $plan_data['proration'] ) ? 'full_day' : 'by_minute',
				'proration_minimum' => ! empty( $plan_data['minimum_prorated_amount'] ) ? $plan_data['minimum_prorated_amount'] : '',

			];

			$plan_meta['_reepay_subscription_month_fixedday'] = $type_data;

			unset( $type_data['day'] );
			$plan_meta['_reepay_subscription_month_lastday'] = $type_data;

			unset( $type_data['month'] );
			$plan_meta['_reepay_subscription_primo']              = $type_data;
			$plan_meta['_reepay_subscription_month_startdate_12'] = $type_data;
			$plan_meta['_reepay_subscription_half_yearly']        = $type_data;
			$plan_meta['_reepay_subscription_ultimo']             = $type_data;


			$type_data['week']                                 = $plan_data['interval_length'];
			$type_data['day']                                  = ! empty( $plan_data['schedule_fixed_day'] ) ? $plan_data['schedule_fixed_day'] : '';
			$plan_meta['_reepay_subscription_weekly_fixedday'] = $type_data;
		}

		return $plan_meta;
	}

	public function save_subscription_meta( $post_id ) {
		$title = get_the_title( $post_id );

		if ( ! $this->is_reepay_product_saving()
		     || empty( $title )
		     || strpos( $title, 'AUTO-DRAFT' ) !== false
		) {
			return;
		}

		$plan_handle = $this->get_subscription_handle_from_request();

		if ( empty( $plan_handle ) ) {
			return;
		}

		$plan_data = $this->save_remote_plan( $post_id, $plan_handle );

		if ( empty( $plan_data ) ) {
			return;
		}

		if ( ! empty( $plan_data['_reepay_subscription_price'] ) ) {
			update_post_meta( $post_id, '_regular_price', $plan_data['_reepay_subscription_price'] );
			update_post_meta( $post_id, '_price', $plan_data['_reepay_subscription_price'] );
		}
	}

	public function get_subscription_handle_from_request() {
		return wc_clean( $_REQUEST['_reepay_subscription_handle'] ?? '' );
	}

	public function is_reepay_product_saving() {
		return ! empty( $_REQUEST ) && ! empty( $_REQUEST['product-type'] ) && $_REQUEST['product-type'] == 'reepay_simple_subscriptions';
	}

	public static function get_vat( $post_id ) {
		$product = wc_get_product( $post_id );
		$vat     = 0;
		if ( 'taxable' == $product->get_tax_status() && wc_tax_enabled() ) {
			$calculate_tax_for              = [
				'country'  => '*',
				'state'    => '*',
				'city'     => '*',
				'postcode' => '*',
			];
			$calculate_tax_for['tax_class'] = $product->get_tax_class();
			$tax_rates                      = WC_Tax::find_rates( $calculate_tax_for );
			if ( ! empty( $tax_rates ) ) {
				reset( $tax_rates );
				$first_key = key( $tax_rates );
				if ( ! empty( $tax_rates[ $first_key ]['rate'] ) ) {
					$vat = floatval( $tax_rates[ $first_key ]['rate'] ) / 100;
				}
			}
		}

		return $vat;
	}

	public static function get_vat_shipping() {

		$vat                = 0;
		$shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );

		$tax_class = $shipping_tax_class;

		if ( ! is_null( $tax_class ) ) {
			$matched_tax_rates = WC_Tax::find_shipping_rates(
				[
					'country'   => '*',
					'state'     => '*',
					'city'      => '*',
					'postcode'  => '*',
					'tax_class' => $tax_class,
				]
			);
			if ( ! empty( $matched_tax_rates ) ) {
				reset( $matched_tax_rates );
				$first_key = key( $matched_tax_rates );
				if ( ! empty( $matched_tax_rates[ $first_key ]['rate'] ) ) {
					$vat = floatval( $matched_tax_rates[ $first_key ]['rate'] ) / 100;
				}
			}
		}

		return $vat;
	}

	public static function get_interval( $post_id, $type, $type_data ) {
		if ( $type == 'daily' ) {
			return get_post_meta( $post_id, '_reepay_subscription_daily', true );
		} elseif ( $type == 'month_startdate' ) {
			return get_post_meta( $post_id, '_reepay_subscription_month_startdate', true );
		} elseif ( $type == 'month_fixedday' || $type == 'month_lastday' ) {
			return $type_data['month'];
		} elseif ( $type == 'weekly_fixedday' ) {
			return $type_data['week'];
		} elseif ( $type == 'primo' || $type == 'ultimo' ) {
			return 3;
		} elseif ( $type == 'half_yearly' ) {
			return 6;
		} elseif ( $type == 'month_startdate_12' ) {
			return 12;
		} else {
			return false;
		}
	}

	/**
	 * @param WC_Product $product
	 * @param bool $is_short
	 *
	 * @return string
	 */
	public static function get_billing_plan( $product, $is_short = false ) {
		if ( $product ) {
			$type      = $product->get_meta( '_reepay_subscription_schedule_type' );
			$type_data = $product->get_meta( '_reepay_subscription_' . $type );
			$interval  = self::get_interval( $product->get_id(), $type, $type_data );

			$types_info = $is_short ? self::$types_info_short : self::$types_info;

			$type_str = $types_info[ $interval > 1 ? $type . '_multiple' : $type ] ?? $types_info[ $type ] ?? '';
			$ret      = '';
			if ( ! empty( $type_str ) ) {
				$ret = sprintf(
					$type_str,
					$interval
				);
			}

			return $ret;
		}

		return '';
	}

	/**
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public static function get_trial( $product ) {
		$trial = $product->get_meta( '_reepay_subscription_trial' );
		$ret   = '';

		if ( ! empty( $trial['type'] ) ) {
			if ( $trial['type'] != 'customize' ) {
				$ret = 'Trial period: ' . WC_Reepay_Subscription_Plan_Simple::$trial[ $trial['type'] ];
			} else {
				$ret = 'Trial period: ' . $trial['length'] . ' ' . $trial['unit'];
			}
		}

		return $ret;
	}

	/**
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public static function get_setup_fee( $product ) {
		$fee = $product->get_meta( '_reepay_subscription_fee' );
		$ret = '';
		if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ) {
			$ret = $fee["text"] . ': ' . wc_price( $fee["amount"] );
		}

		return $ret;
	}

	/**
	 * @param WC_Product $product
	 *
	 * @return string
	 */
	public static function get_contract_period( $product ) {
		$periods = $product->get_meta( '_reepay_subscription_contract_periods' );
		$plan    = WC_Reepay_Subscription_Plan_Simple::get_billing_plan( $product, true );
		$ret     = '';
		if ( ! empty( $periods ) ) {
			$ret = __( 'Contract Period', 'reepay-subscriptions-for-woocommerce' ) . ': ' . $periods . ' x ' . $plan;
		}

		return $ret;
	}

	public function add_setup_fee() {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( WC_Reepay_Checkout::is_reepay_product( $cart_item['data'] ) ) {
				$product = $cart_item['data'];
				$fee     = $product->get_meta( '_reepay_subscription_fee' );
				if ( ! empty( $fee ) && ! empty( $fee['enabled'] ) && $fee['enabled'] == 'yes' ) {
					$amount = floatval( $fee["amount"] ) * $cart_item['quantity'];
					WC()->cart->add_fee( __( $product->get_name() . ' - ' . $fee["text"], 'reepay-subscriptions-for-woocommerce' ), $amount, false );
				}
			}
		}
	}

	public function rework_total( $total_rows, $order, $tax_display ) {
		$another_orders = get_post_meta( $order->get_id(), '_reepay_another_orders', true );
		if ( ! empty( $another_orders ) ) {
			$total = 0;
			foreach ( $another_orders as $order_id ) {
				$order_another = wc_get_order( $order_id );
				$total         += floatval( $order_another->get_total() );
			}

			$total_rows['cart_subtotal'] = [
				'label' => __( 'Subtotal:', 'woocommerce' ),
				'value' => wc_price( $total )
			];
			$total_rows['order_total']   = [
				'label' => __( 'Total:', 'woocommerce' ),
				'value' => wc_price( $total )
			];
		}

		return $total_rows;
	}

	protected function plan_error( $message ) {
		if ( is_ajax() ) {
			WC_Admin_Meta_Boxes::add_error( $message );
		} else {
			WC_Reepay_Subscription_Admin_Notice::add_notice( $message );
		}
	}
}
