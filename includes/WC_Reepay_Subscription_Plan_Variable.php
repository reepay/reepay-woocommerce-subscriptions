<?php

class WC_Reepay_Subscription_Plan_Variable extends WC_Reepay_Subscription_Plan_Simple
{
    public $loop = 0;

    public function create_subscription_product_class()
    {
        include_once(reepay_s()->settings('plugin_path') . '/includes/WC_Product_Reepay_Variable_Subscription.php');
    }

    public function load_subscription_product_class($php_classname, $product_type)
    {
        if ($product_type == 'reepay_variable_subscriptions') {
            $php_classname = 'WC_Product_Reepay_Variable_Subscription';
        }

        return $php_classname;
    }

    public function add_subscription_product_type($types)
    {
        $types['reepay_variable_subscriptions'] = __('Reepay Variable Subscription', reepay_s()->settings('domain'));

        return $types;
    }

    protected function register_actions()
    {
        add_action("woocommerce_reepay_variable_subscriptions_add_to_cart", array($this, 'add_to_cart'));
        add_action('woocommerce_variation_options_pricing', array($this, 'add_custom_field_to_variations'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_subscription_meta'), 10, 2);
        add_filter('woocommerce_add_to_cart_handler', array($this, 'variable_add_to_cart_fix'), 10, 2);
    }

    public function variable_add_to_cart_fix($type, $adding_to_cart)
    {
        if ($type == 'reepay_variable_subscriptions') {
            return 'variable';
        }

        return $type;
    }

    /**
     * @param int $post_id
     *
     * @return array<string, mixed>
     */
    public function get_subscription_template_data($post_id)
    {
        $data = parent::get_subscription_template_data($post_id);

        $data['variable'] = true;
        $data['loop'] = $this->loop;

        return $data;
    }

    public function save_subscription_meta($post_id, $i = null)
    {
        $this->loop = $i;

        parent::save_subscription_meta($post_id);
    }

    public function generate_subscription_handle($post_id)
    {
        return 'plan_' . $this->loop . '_' . $post_id;
    }

    public function is_reepay_product_saving()
    {
        return !empty($_REQUEST) &&
            !empty($_REQUEST['product-type']) &&
            $_REQUEST['product-type'] == 'reepay_variable_subscriptions' &&
            !empty($_REQUEST['_reepay_subscription_choose']);
    }

    public function get_meta_from_request()
    {
        $data = [];

        foreach (self::$meta_fields as $key) {
            if (isset($_REQUEST[$key])) {
                $data[$key] = sanitize_text_field($_REQUEST[$key][$this->loop]) ?? '';
            }
        }

        return $data;
    }

    public function save_meta_from_request($post_id)
    {
        foreach (self::$meta_fields as $key) {
            if (isset($_REQUEST[$key])) {
                update_post_meta($post_id, $key, sanitize_text_field($_REQUEST[$key][$this->loop]) ?? '');
            }
        }
    }

    /**
     * @param int $loop Position in the loop.
     * @param array $variation_data Variation data.
     * @param WP_Post $variation Post data.
     */
    public function add_custom_field_to_variations($loop, $variation_data, $variation)
    {
        global $post;
        $_post = $post;
        $post = $variation;

        $this->loop = $loop;
        $this->subscription_pricing_fields();

        $post = $_post;
    }

    public function add_to_cart()
    {
        do_action('woocommerce_variable_add_to_cart');
    }
}
