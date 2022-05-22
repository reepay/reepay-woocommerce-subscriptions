<?php

class WC_Reepay_Subscription_Admin_Notice{

    /**
     * Stores notices.
     *
     * @var array
     */
    private static $notices = array();

    /**
     * Constructor
     */
    public function __construct() {
        self::$notices = get_option( 'reepay_admin_notices', array() );
        add_action('post_updated_messages', array($this, 'show_editor_message'));
    }

    /**
     * Show a notice.
     *
     * @param string $name Notice name.
     * @param bool   $force_save Force saving inside this method instead of at the 'shutdown'.
     */
    public static function add_notice( $notice ) {
        self::$notices = array_unique( array_merge( self::$notices, array( $notice ) ) );

        self::store_notices();
    }

    /**
     * Store notices to DB
     */
    public static function store_notices() {
        update_option( 'reepay_admin_notices', self::$notices );
    }

    public function show_editor_message($messages)
    {
        $notices = self::$notices;

        if ( ! empty( $notices ) ) {
            foreach ( $notices as $i => $notice ) {
                add_settings_error('reepay_notice_'.$i, '', $notice, 'error');
                settings_errors( 'reepay_notice_'.$i );


            }
            update_option( 'reepay_admin_notices', array() );
        }

        return $messages;
    }
}

new WC_Reepay_Subscription_Admin_Notice();