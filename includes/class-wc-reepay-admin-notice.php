<?php

class WC_Reepay_Subscription_Admin_Notice
{

    /**
     * Stores notices.
     *
     * @var array
     */
    private static $notices = array();


    /**
     * Constructor
     */
    public function __construct()
    {
        self::$notices = get_option('reepay_admin_notices', array());
        add_action('post_updated_messages', array($this, 'show_editor_message'));
        add_filter('woocommerce_reepay_check_payment', array($this, 'show_thankyou_message'), 10, 2);
    }

    /**
     * Show a notice.
     *
     * @param string $name Notice name.
     * @param bool $force_save Force saving inside this method instead of at the 'shutdown'.
     */
    public static function add_notice($notice)
    {
        self::$notices = array_unique(array_merge(self::$notices, array($notice)));

        self::store_notices();
    }

    /**
     * Add a frontend notice.
     *
     * @param string $name Notice name.
     * @param bool $force_save Force saving inside this method instead of at the 'shutdown'.
     */
    public static function add_frontend_notice($notice, $order_id)
    {
        self::store_frontend_notices($notice, $order_id);
    }

    /**
     * Store notices to DB
     */
    public static function store_notices()
    {
        update_option('reepay_admin_notices', self::$notices);
    }

    /**
     * Store notices to DB
     */
    public static function store_frontend_notices($notice, $order_id)
    {
        update_post_meta($order_id, '_reepay_frontend_notices', $notice);
    }

    public function show_editor_message($messages)
    {
        $notices = self::$notices;

        if (!empty($notices)) {
            foreach ($notices as $i => $notice) {
                add_settings_error('reepay_notice_' . $i, '', $notice, 'error');
                settings_errors('reepay_notice_' . $i);
            }
            update_option('reepay_admin_notices', array());
        }

        return $messages;
    }

    public function show_thankyou_message($ret, $order_id)
    {
        $notice = get_post_meta($order_id, '_reepay_frontend_notices', true);

        if (!empty($notice)) {
            $ret = array(
                'state' => 'failed',
                'message' => $notice
            );
        }

        return $ret;
    }
}

new WC_Reepay_Subscription_Admin_Notice();