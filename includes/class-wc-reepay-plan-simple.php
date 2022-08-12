<?php

class WC_Reepay_Subscription_Plan_Simple
{

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

    public static $schedule_types = [
        self::TYPE_DAILY => 'Day(s)',
        self::TYPE_MONTH_START_DATE => 'Month(s)',
        self::TYPE_MONTH_FIXED_DAY => 'Fixed day of month',
        self::TYPE_MONTH_LAST_DAY => 'Last day of month',
        self::TYPE_PRIMO => 'Quarterly Primo',
        self::TYPE_ULTIMO => 'Quarterly Ultimo',
        self::TYPE_HALF_YEARLY => 'Half-yearly',
        self::TYPE_START_DATE_12 => 'Yearly',
        self::TYPE_WEEKLY_FIXED_DAY => 'Fixed day of week',
        self::TYPE_MANUAL => 'Manual',
    ];

    public static $trial = [
        '' => 'No Trial',
        '7days' => '7 days',
        '14days' => '14 days',
        '1month' => '1 month',
        'customize' => 'Customize',
    ];

    public static $types_info = [
        WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY => 'Billed every day',
        WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY . '_multiple' => 'Billed every %s days',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE => 'Billed every month on the first day of the month',
        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE . '_multiple' => 'Billed every %s months on the first day of the month',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY => 'Billed every month',
        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY . '_multiple' => 'Billed every %s months',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY => 'Billed every month on the last day of the month',
        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY . '_multiple' => 'Billed every %s months on the last day of the month',

        WC_Reepay_Subscription_Plan_Simple::TYPE_PRIMO . '_multiple' => 'Billed every %s months, on the first day of the month. The billing is fixed to these months: January, April, July, October',
        WC_Reepay_Subscription_Plan_Simple::TYPE_ULTIMO . '_multiple' => 'Billed every %s months, on the last day of the month. The billing is fixed to these months: January, April, July, October',
        WC_Reepay_Subscription_Plan_Simple::TYPE_HALF_YEARLY . '_multiple' => 'Billed every %s months, on the first day of the month. The billing is fixed to these months: January, July',
        WC_Reepay_Subscription_Plan_Simple::TYPE_START_DATE_12 . '_multiple' => 'Billed every %s months, on the first day of the month. The billing is fixed to these months: January',

        WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY => 'Billed every Week',
        WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY . '_multiple' => 'Billed every %s Weeks',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MANUAL => 'Manual',
    ];

    public static $types_info_short = [
        WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY => 'Day',
        WC_Reepay_Subscription_Plan_Simple::TYPE_DAILY . '_multiple' => '%s days',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE => 'Month',
        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_START_DATE . '_multiple' => '%s months',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY => 'Month',
        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_FIXED_DAY . '_multiple' => '%s months',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY => 'Month',
        WC_Reepay_Subscription_Plan_Simple::TYPE_MONTH_LAST_DAY . '_multiple' => '%s months',

        WC_Reepay_Subscription_Plan_Simple::TYPE_PRIMO . '_multiple' => '%s months',
        WC_Reepay_Subscription_Plan_Simple::TYPE_ULTIMO . '_multiple' => '%s months',
        WC_Reepay_Subscription_Plan_Simple::TYPE_HALF_YEARLY . '_multiple' => '%s months',
        WC_Reepay_Subscription_Plan_Simple::TYPE_START_DATE_12 . '_multiple' => '%s months',

        WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY => 'Week',
        WC_Reepay_Subscription_Plan_Simple::TYPE_WEEKLY_FIXED_DAY . '_multiple' => '%s Weeks',

        WC_Reepay_Subscription_Plan_Simple::TYPE_MANUAL => 'Manual',
    ];

    public static $meta_fields = [
        '_reepay_subscription_handle',
        '_reepay_subscription_choose',
        '_reepay_choose_exist',
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
        '_reepay_subscription_billing_cycles_period',
        '_reepay_subscription_trial',
        '_reepay_subscription_fee',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('init', [$this, 'create_subscription_product_class']);
        add_filter('woocommerce_product_class', [$this, 'load_subscription_product_class'], 10, 2);
        add_filter('product_type_selector', [$this, 'add_subscription_product_type']);
        add_action('save_post', [$this, 'set_sold_individual'], PHP_INT_MAX);

        add_filter('woocommerce_cart_item_price', [$this, 'format_price'], 10, 2);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'format_price'], 10, 2);
        add_filter('woocommerce_order_formatted_line_subtotal', [$this, 'format_price'], 10, 2);

        $this->register_actions();
    }

    protected function register_actions()
    {
        add_action("woocommerce_reepay_simple_subscriptions_add_to_cart", [$this, 'add_to_cart']);
        add_action('woocommerce_product_options_general_product_data', [$this, 'subscription_pricing_fields']);
        add_action('reepay_subscription_ajax_get_plan_html', [$this, 'subscription_pricing_fields']);
        add_action('save_post', [$this, 'save_subscription_meta'], 11);
        add_filter('woocommerce_cart_item_name', [$this, 'checkout_subscription_info'], 10, 3);
        add_action('woocommerce_before_order_itemmeta', [$this, 'admin_order_subscription_info'], 10, 3);
    }

    public function checkout_subscription_info($name, $cart_item, $cart_item_key)
    {
        if (!empty($cart_item['data']) && WC_Reepay_Checkout::is_reepay_product($cart_item['data'])) {
            $name = $name . $this->get_subscription_info_frontend($cart_item['data']);
        }
        return $name;
    }

    public function admin_order_subscription_info($item_id, $item, $product)
    {
        if (!empty($product) && WC_Reepay_Checkout::is_reepay_product($product)) {
            echo $this->get_subscription_info_frontend($product);
        }
    }

    public function create_subscription_product_class()
    {
        include_once(reepay_s()->settings('plugin_path') . '/includes/class-wc-reepay-plan-simple-product.php');
    }

    public function load_subscription_product_class($php_classname, $product_type)
    {
        if ($product_type == 'reepay_simple_subscriptions') {
            $php_classname = 'WC_Product_Reepay_Simple_Subscription';
        }

        return $php_classname;
    }

    public function add_subscription_product_type($types)
    {
        $types['reepay_simple_subscriptions'] = __('Reepay Simple Subscription', reepay_s()->settings('domain'));

        return $types;
    }

    /**
     * @param string $price
     * @param array<string, mixed> $product
     *
     * @return string
     */
    public function format_price($price, $product)
    {
        $product = wc_get_product($product['variation_id'] ?: $product['product_id']);
        if (empty($product) || !WC_Reepay_Checkout::is_reepay_product($product)) {
            return $price;
        }

        if ($product->is_type('variation')) {
            return WC_Product_Reepay_Simple_Subscription::format_price($product->get_price_html(), $product);
        }


        return $product->get_price_html();
    }

    public function add_to_cart()
    {
        $this->display_subscription_info();
        do_action('woocommerce_simple_add_to_cart');
    }

    public function set_sold_individual($post_id)
    {
        if (!$this->is_reepay_product_saving()) {
            return;
        }

        update_post_meta($post_id, '_sold_individually', 'yes');
    }

    public function display_subscription_info()
    {
        global $product;
        echo $this->get_subscription_info_frontend($product);
    }

    public function get_subscription_info_frontend($product)
    {
        if (!WC_Reepay_Checkout::is_reepay_product($product)) {
            return '';
        }

        if ($product->is_type('variation')) {
            $product = wc_get_product($product->get_parent_id());
        }

        ob_start();
        wc_get_template(
            'plan-subscription-frontend.php',
            [
                'billing_plan' => WC_Reepay_Subscription_Plan_Simple::get_billing_plan($product),
                'trial' => WC_Reepay_Subscription_Plan_Simple::get_trial($product),
                'contract_periods' => $product->get_meta('_reepay_subscription_contract_periods'),
                'domain' => reepay_s()->settings('domain')
            ],
            '',
            reepay_s()->settings('plugin_path') . 'templates/'
        );
        return ob_get_clean();
    }

    public function get_plan($handle)
    {
        try {
            $result = reepay_s()->api()->request("plan/" . $handle . "/current");

            return $result;
        } catch (Exception $e) {
            $this->plan_error($e->getMessage());
        }

        return false;
    }

    public static function get_plans_wc()
    {
        $plansQuery = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_reepay_subscription_handle',
                    'compare' => 'EXISTS',
                ]
            ]
        ]);
        $plans = [];
        foreach ($plansQuery->posts as $item) {
            $handle = get_post_meta($item->ID, '_reepay_subscription_handle', true);
            $plans[$handle] = $item->post_title;
        }

        return $plans;
    }

    public static function wc_get_plan($handle)
    {
        $query = new WP_Query([
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => '_reepay_subscription_handle',
                    'value' => $handle,
                ]
            ]
        ]);

        return $query->post ?? null;
    }

    /**
     * @return array|bool
     */
    public function get_reepay_plans_list()
    {
        static $plans = null;

        if (!is_null($plans)) {
            return $plans;
        }

        try {
            $plans = reepay_s()->api()->request("plan?only_active=true") ?: false;
            return $plans;
        } catch (Exception $e) {
            $this->plan_error($e->getMessage());
        }

        return false;
    }

    /**
     * @param int $post_id
     *
     * @return array<string, mixed>
     */
    public function get_subscription_template_data($post_id)
    {
        $data = [
            'plans_list' => $this->get_reepay_plans_list() ?: [],
            'domain' => reepay_s()->settings('domain'),
        ];

        foreach (self::$meta_fields as $meta_field) {
            $data[$meta_field] = get_post_meta($post_id, $meta_field, true);
        }

        return $data;
    }

    /**
     * @param int $post_id
     * @param array $data
     */
    public function subscription_pricing_fields($post_id = null, $data = [])
    {
        global $post;
        global $pagenow;

        $post_id = $post_id ?: $post->ID;

        $data = !empty($data) ? $data : $this->get_subscription_template_data($post_id);

        if (empty($data['_reepay_subscription_choose'])) {
            $data['_reepay_subscription_choose'] = 'new';
        }

        $data['is_exist'] = $data['_reepay_subscription_choose'] != 'new';
        $data['product_object'] = wc_get_product($post_id);
        $data['is_creating_new_product'] = $pagenow === 'post-new.php';

        foreach (self::$meta_fields as $key) {
            if (!isset($data[$key])) {
                $data[$key] = '';
            }
        }

        $dataNew['domain'] = $data['domain'];
        ob_start();
        wc_get_template(
            'plan-subscription-fields-data.php',
            $dataNew,
            '',
            reepay_s()->settings('plugin_path') . 'templates/'
        );
        $data['settings'] = ob_get_clean();


        if ($data['is_exist']) {
            ob_start();
            wc_get_template(
                'plan-subscription-fields-data.php',
                $data,
                '',
                reepay_s()->settings('plugin_path') . 'templates/'
            );
            $data['settings_exist'] = ob_get_clean();
        }

        wc_get_template(
            'plan-subscription-fields.php',
            $data,
            '',
            reepay_s()->settings('plugin_path') . 'templates/'
        );
    }

    public function save_remote_plan($post_id, $handle)
    {
        $data = $this->get_remote_plan_meta($handle);

        if (empty($data)) {
            return;
        }

        foreach (self::$meta_fields as $key) {
            delete_post_meta($post_id, $key);

            if (isset($data[$key])) {
                update_post_meta($post_id, $key, $data[$key]);
            }
        }
    }

    /**
     * @param string $handle reepay plan handle
     *
     * @return array<string, mixed> meta fields to save
     */
    public function get_remote_plan_meta($handle)
    {
        $plan_data = $this->get_plan($handle);
        $plan_meta = [];

        if (empty($plan_data)) {
            $this->plan_error(__('Plan not found', reepay_s()->settings('domain')));
            return [];
        }

        $plan_meta['_regular_price'] = $plan_data['amount'] / 100;//@todo уточнить нужно ли добавлять fee в цену или выводить отдельно
        $plan_meta['_price'] = $plan_data['amount'] / 100;

        if (!empty($plan_data['amount'])) {
            $plan_meta['_reepay_subscription_price'] = intval($plan_data['amount']) / 100;
        }

        if (!empty($plan_data['name'])) {
            $plan_meta['_reepay_subscription_name'] = $plan_data['name'];
        }

        if (!empty($plan_data['vat'])) {
            $plan_meta['_reepay_subscription_vat'] = $plan_data['vat'];
        }

        if (!empty($plan_data['setup_fee'])) {
            $fee = [
                'enabled' => 'yes',
                'amount' => intval($plan_data['setup_fee']) / 100,
                'text' => !empty($plan_data['setup_fee_text']) ? $plan_data['setup_fee_text'] : '',
                'handling' => $plan_data['setup_fee_handling'],
            ];

            $plan_meta['_reepay_subscription_fee'] = $fee;
        }

        if (!empty($plan_data['trial_interval_length'])) {
            $type = '';
            if ($plan_data['trial_interval_length'] == 7 && $plan_data['trial_interval_unit'] == 'days') {
                $type = '7days';
            } elseif ($plan_data['trial_interval_length'] == 14 && $plan_data['trial_interval_unit'] == 'days') {
                $type = '14days';
            } elseif ($plan_data['trial_interval_length'] == 1 && $plan_data['trial_interval_unit'] == 'months') {
                $type = '1month';
            }

            $trial = [
                'type' => $type,
                'length' => $plan_data['trial_interval_length'],
                'unit' => $plan_data['trial_interval_unit'],
                'reminder' => !empty($plan_data['trial_reminder_email_days']),
            ];

            if (!empty($plan_data['trial_reminder_email_days'])) {
                $trial['reminder'] = $plan_data['trial_reminder_email_days'];
            }

            $plan_meta['_reepay_subscription_trial'] = $trial;
        }

        if (!empty($plan_data["fixed_count"])) {
            $plan_meta['_reepay_subscription_billing_cycles'] = 'true';
            $plan_meta['_reepay_subscription_billing_cycles_period'] = $plan_data["fixed_count"];
        } else {
            $plan_meta['_reepay_subscription_billing_cycles'] = 'false';
        }

        if (!empty($plan_data['notice_periods'])) {
            $plan_meta['_reepay_subscription_notice_period'] = $plan_data['notice_periods'];
        }

        if (isset($plan_data['notice_periods_after_current'])) {
            $plan_meta['_reepay_subscription_notice_period_start'] = $plan_data['notice_periods_after_current'] ? 'true' : 'false';
        }

        if (!empty($plan_data['fixation_periods'])) {
            $plan_meta['_reepay_subscription_contract_periods'] = $plan_data['fixation_periods'];
        }

        if (isset($plan_data['fixation_periods_full'])) {
            $plan_meta['_reepay_subscription_contract_periods_full'] = $plan_data['fixation_periods_full'] ? 'true' : 'false';
        }

        if (!empty($plan_data['quantity'])) {
            $plan_meta['_reepay_subscription_default_quantity'] = $plan_data['quantity'];
        }

        if (!empty($plan_data['renewal_reminder_email_days'])) {
            $plan_meta['_reepay_subscription_renewal_reminder'] = $plan_data['renewal_reminder_email_days'];
        }

        if (!empty($plan_data['schedule_type'])) {
            $type = $plan_data['schedule_type'];

            if (!empty($plan_data['schedule_fixed_day']) && !empty($plan_data['interval_length']) && $plan_data['schedule_type'] == 'month_fixedday') {
                if ($plan_data['schedule_fixed_day'] == 28) {
                    $type = 'ultimo';
                } elseif ($plan_data['schedule_fixed_day'] == 1) {
                    if ($plan_data['interval_length'] == 3) {
                        $type = 'primo';
                    } elseif ($plan_data['interval_length'] == 6) {
                        $type = 'half_yearly';
                    } elseif ($plan_data['interval_length'] == 12) {
                        $type = 'month_startdate_12';
                    }
                }
            }

            $plan_meta['_reepay_subscription_schedule_type'] = $type;
        }


        if (!empty($plan_data['interval_length'])) {
            $plan_meta['_reepay_subscription_daily'] = $plan_data['interval_length'];
            $plan_meta['_reepay_subscription_month_startdate'] = $plan_data['interval_length'];

            $type_data = [
                'month' => $plan_data['interval_length'],
                'day' => !empty($plan_data['schedule_fixed_day']) ? $plan_data['schedule_fixed_day'] : '',
                'period' => !empty($plan_data['partial_period_handling']) ? $plan_data['partial_period_handling'] : '',
                'proration' => !empty($plan_data['proration']) ? 'full_day' : 'by_minute',
                'proration_minimum' => !empty($plan_data['minimum_prorated_amount']) ? $plan_data['minimum_prorated_amount'] : '',

            ];

            $plan_meta['_reepay_subscription_month_fixedday'] = $type_data;

            unset($type_data['day']);
            $plan_meta['_reepay_subscription_month_lastday'] = $type_data;

            unset($type_data['month']);
            $plan_meta['_reepay_subscription_primo'] = $type_data;
            $plan_meta['_reepay_subscription_month_startdate_12'] = $type_data;
            $plan_meta['_reepay_subscription_half_yearly'] = $type_data;
            $plan_meta['_reepay_subscription_ultimo'] = $type_data;


            $type_data['week'] = $plan_data['interval_length'];
            $type_data['day'] = !empty($plan_data['schedule_fixed_day']) ? $plan_data['schedule_fixed_day'] : '';
            $plan_meta['_reepay_subscription_weekly_fixedday'] = $type_data;
        }

        return $plan_meta;
    }

    public function save_subscription_meta($post_id)
    {
        if (!$this->is_reepay_product_saving()) {
            return;
        }

        $request_data = $this->get_meta_from_request();


        if (!empty($request_data['_reepay_subscription_choose']) && $request_data['_reepay_subscription_choose'] == 'exist') {
            if (!empty($request_data['_reepay_choose_exist'])) {
                $this->save_remote_plan($post_id, $request_data['_reepay_choose_exist']);

                update_post_meta($post_id, '_reepay_subscription_handle', $request_data['_reepay_choose_exist']);
                update_post_meta($post_id, '_reepay_choose_exist', $request_data['_reepay_choose_exist']);
                update_post_meta($post_id, '_reepay_subscription_choose', $request_data['_reepay_subscription_choose']);

                if (!empty($request_data['_reepay_subscription_price'])) {
                    update_post_meta($post_id, '_regular_price', $request_data['_reepay_subscription_price']);
                    update_post_meta($post_id, '_price', $request_data['_reepay_subscription_price']);
                }

                $title = get_the_title($post_id);
                if (!empty($title) && strpos($title, 'AUTO-DRAFT') === false) {
                    $handle = get_post_meta($post_id, '_reepay_subscription_handle', true);
                    $this->save_meta_from_request($post_id);
                    if (!empty($handle)) {
                        $this->update_plan($handle, $this->get_params($post_id));
                    }
                }

            } else {
                $this->plan_error(__('Please choose the plan', reepay_s()->settings('domain')));
            }
        } else {
            if (!empty($request_data['_reepay_subscription_price'])) {
                update_post_meta($post_id, '_regular_price', $request_data['_reepay_subscription_price']);
                update_post_meta($post_id, '_price', $request_data['_reepay_subscription_price']);
            }

            $title = get_the_title($post_id);
            if (!empty($title) && strpos($title, 'AUTO-DRAFT') === false) {
                $this->save_meta_from_request($post_id);
                $handle = $this->generate_subscription_handle($post_id);
                $this->create_plan($post_id, $handle, $this->get_params($post_id));
                update_post_meta($post_id, '_reepay_choose_exist', $handle);
                update_post_meta($post_id, '_reepay_subscription_choose', 'exist');
            }
        }
    }

    public function is_reepay_product_saving()
    {
        return !empty($_REQUEST) && !empty($_REQUEST['product-type']) && $_REQUEST['product-type'] == 'reepay_simple_subscriptions';
    }

    public function get_meta_from_request()
    {
        $data = [];

        foreach (self::$meta_fields as $key) {
            if (isset($_REQUEST[$key])) {
                $data[$key] = $_REQUEST[$key];
            }
        }

        return $data;
    }

    public function save_meta_from_request($post_id)
    {
        foreach (self::$meta_fields as $key) {
            if (isset($_REQUEST[$key])) {
                update_post_meta($post_id, $key, $_REQUEST[$key]);
            }
        }
    }

    public function update_plan($handle, $params)
    {
        try {
            $params['supersede_mode'] = 'scheduled_sub_update'; //@todo сделать параметр для обновляемых планов выбор обновлять ли уже оформленные подписки

            $result = reepay_s()->api()->request("plan/$handle", 'POST', $params);
            return true;
        } catch (Exception $e) {
            $this->plan_error($e->getMessage());
        }

        return false;
    }

    public function get_type($type)
    {
        if ($type == 'primo' || $type == 'ultimo' || $type == 'half_yearly' || $type == 'month_startdate_12') {
            return 'month_fixedday';
        }

        return $type;
    }

    public function generate_subscription_handle($post_id)
    {
        return 'wc_subscription_' . $post_id . '-' . time();
    }

    public function get_params($post_id)
    {
        $handle = $this->generate_subscription_handle($post_id);
        $params = $this->get_default_params($post_id);

        $type = get_post_meta($post_id, '_reepay_subscription_schedule_type', true);
        $type_data = get_post_meta($post_id, '_reepay_subscription_' . $type, true);

        $params['amount'] = floatval(get_post_meta($post_id, '_reepay_subscription_price', true)) * 100;
        $params['handle'] = $handle;
        $params['quantity'] = intval(get_post_meta($post_id, '_reepay_subscription_default_quantity', true));
        $params['schedule_type'] = $this->get_type($type);
        //$params['fixed_life_time_unit'] = ''; //@todo Уточнить что за поле в админке
        //$params['fixed_life_time_length'] = ''; //@todo Уточнить что за поле в админке
        //$params['fixed_trial_days'] = ''; //@todo Уточнить что за поле в админке

        $billing_cycles = get_post_meta($post_id, '_reepay_subscription_billing_cycles', true);
        if ($billing_cycles == 'true') {
            $params['fixed_count'] = intval(get_post_meta($post_id, '_reepay_subscription_billing_cycles_period', true));
        }

        $trial = get_post_meta($post_id, '_reepay_subscription_trial', true);
        if (!empty($trial['type'])) {
            if ($trial['type'] == 'customize') {
                $params['trial_interval_unit'] = $trial['unit'];
                $params['trial_interval_length'] = intval($trial['length']);
            } else {
                if ($trial['type'] == '7days') {
                    $params['trial_interval_unit'] = 'days';
                    $params['trial_interval_length'] = 7;
                } elseif ($trial['type'] == '14days') {
                    $params['trial_interval_unit'] = 'days';
                    $params['trial_interval_length'] = 14;
                } elseif ($trial['type'] == '1month') {
                    $params['trial_interval_unit'] = 'months';
                    $params['trial_interval_length'] = 1;
                }
            }
        }

        $params['amount_incl_vat'] = wc_prices_include_tax();

        $fixation_periods = get_post_meta($post_id, '_reepay_subscription_contract_periods', true);
        if ($fixation_periods) {
            $params['fixation_periods'] = intval($fixation_periods);
            $fixation_periods_full = get_post_meta($post_id, '_reepay_subscription_contract_periods_full', true);
            $params['fixation_periods_full'] = boolval($fixation_periods_full);
        }

        $notice_periods = get_post_meta($post_id, '_reepay_subscription_notice_period', true);
        if ($notice_periods) {
            $params['notice_periods'] = intval($notice_periods);
            $notice_period_start = get_post_meta($post_id, '_subscription_notice_period_start', true);
            $params['notice_periods_after_current'] = boolval($notice_period_start);
        }

        if ($type == 'month_fixedday' || $type == 'weekly_fixedday') {
            $params['schedule_fixed_day'] = intval($type_data['day']);
        } elseif ($type == 'primo' || $type == 'half_yearly' || $type == 'month_startdate_12') {
            $params['schedule_fixed_day'] = 1;
        } elseif ($type == 'ultimo') {
            $params['schedule_fixed_day'] = 28;
        }

        if ($length = intval(self::get_interval($post_id, $type, $type_data))) {
            $params['interval_length'] = $length;
        }

        return $params;
    }

    public function create_plan($post_id, $handle, $params)
    {
        try {
            $result = reepay_s()->api()->request('plan', 'POST', $params);
            update_post_meta($post_id, '_reepay_subscription_handle', $handle);

            return true;
        } catch (Exception $e) {
            $this->plan_error($e->getMessage());
        }

        return false;
    }

    public function get_default_params($post_id)
    {
        $request_data = $this->get_meta_from_request();

        $type = get_post_meta($post_id, '_reepay_subscription_schedule_type', true);
        $type_data = get_post_meta($post_id, '_reepay_subscription_' . $type, true);

        $params = [
            'name' => $request_data['_reepay_subscription_name'],
            'description' => get_post_field('post_content', $post_id),
            //'fixed_trial_days' => '', //@todo Уточнить что за поле в админке
        ];

        if (!empty($request_data['_reepay_subscription_renewal_reminder'])) {
            $params['renewal_reminder_email_days'] = intval($request_data['_reepay_subscription_renewal_reminder']);
        }

        if (!empty($request_data['_reepay_subscription_trial']) && !empty($request_data['_reepay_subscription_trial']['reminder']) && !empty($request_data['_reepay_subscription_trial']['type'])) {
            $params['trial_reminder_email_days'] = intval($request_data['_reepay_subscription_trial']['reminder']);
        }

        if (is_array($type_data) && !empty($type_data['period'])) {
            $params['partial_period_handling'] = $type_data['period'];
        }

        if (!empty($request_data['_reepay_subscription_fee'])) {
            $fee = $request_data['_reepay_subscription_fee'];
            $params['setup_fee'] = !empty($fee['amount']) ? floatval($fee['amount']) * 100 : 0;
            $params['setup_fee_text'] = !empty($fee['text']) ? $fee['text'] : '';
            $params['setup_fee_handling'] = !empty($fee['handling']) ? $fee['handling'] : '';
        }

        if (!empty($type_data['proration'])) {
            if ($type_data['proration'] == 'full_day') {
                $params['partial_proration_days'] = true;
            } else {
                $params['partial_proration_days'] = false;
            }
        }

        if (!empty($type_data['proration_minimum'])) {
            $params['minimum_prorated_amount'] = floatval($type_data['proration_minimum']);
        }

        $params['vat'] = self::get_vat($post_id);

        return $params;
    }

    public static function get_vat($post_id)
    {
        $product = wc_get_product($post_id);
        $vat = 0;
        if ('taxable' == $product->get_tax_status() && wc_tax_enabled()) {
            $calculate_tax_for = [
                'country' => '*',
                'state' => '*',
                'city' => '*',
                'postcode' => '*',
            ];
            $calculate_tax_for['tax_class'] = $product->get_tax_class();
            $tax_rates = WC_Tax::find_rates($calculate_tax_for);
            if (!empty($tax_rates)) {
                reset($tax_rates);
                $first_key = key($tax_rates);
                if (!empty($tax_rates[$first_key]['rate'])) {
                    $vat = floatval($tax_rates[$first_key]['rate']) / 100;
                }
            }
        }

        return $vat;
    }

    public static function get_vat_shipping()
    {

        $vat = 0;
        $shipping_tax_class = get_option('woocommerce_shipping_tax_class');

        $tax_class = $shipping_tax_class;

        if (!is_null($tax_class)) {
            $matched_tax_rates = WC_Tax::find_shipping_rates(
                [
                    'country' => '*',
                    'state' => '*',
                    'city' => '*',
                    'postcode' => '*',
                    'tax_class' => $tax_class,
                ]
            );
            if (!empty($matched_tax_rates)) {
                reset($matched_tax_rates);
                $first_key = key($matched_tax_rates);
                if (!empty($matched_tax_rates[$first_key]['rate'])) {
                    $vat = floatval($matched_tax_rates[$first_key]['rate']) / 100;
                }
            }
        }

        return $vat;
    }

    public static function get_interval($post_id, $type, $type_data)
    {
        if ($type == 'daily') {
            return get_post_meta($post_id, '_reepay_subscription_daily', true);
        } elseif ($type == 'month_startdate') {
            return get_post_meta($post_id, '_reepay_subscription_month_startdate', true);
        } elseif ($type == 'month_fixedday' || $type == 'month_lastday') {
            return $type_data['month'];
        } elseif ($type == 'weekly_fixedday') {
            return $type_data['week'];
        } elseif ($type == 'primo' || $type == 'ultimo') {
            return 3;
        } elseif ($type == 'half_yearly') {
            return 6;
        } elseif ($type == 'month_startdate_12') {
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
    public static function get_billing_plan($product, $is_short = false)
    {
        $type = $product->get_meta('_reepay_subscription_schedule_type');
        $type_data = $product->get_meta('_reepay_subscription_' . $type);
        $interval = self::get_interval($product->get_id(), $type, $type_data);

        $types_info = $is_short ? self::$types_info_short : self::$types_info;

        $type_str = $types_info[$interval > 1 ? $type . '_multiple' : $type] ?? $types_info[$type] ?? '';
        $ret = '';
        if (!empty($type_str)) {
            $ret = sprintf(
                __($type_str, reepay_s()->settings('domain')),
                $interval
            );
        }

        return $ret;
    }

    /**
     * @param WC_Product $product
     *
     * @return string
     */
    public static function get_trial($product)
    {
        $trial = $product->get_meta('_reepay_subscription_trial');
        $ret = '';

        if (!empty($trial['type'])) {
            if ($trial['type'] != 'customize') {
                $ret = 'Trial period: ' . WC_Reepay_Subscription_Plan_Simple::$trial[$trial['type']];
            } else {
                $ret = 'Trial period: ' . $trial['length'] . ' ' . $trial['unit'];
            }
        }

        return $ret;
    }

    protected function plan_error($message)
    {
        if (is_ajax()) {
            WC_Admin_Meta_Boxes::add_error($message);
        } else {
            WC_Reepay_Subscription_Admin_Notice::add_notice($message);
        }
    }
}