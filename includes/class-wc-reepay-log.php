<?php

class WC_RS_Log{
    private static $instance;

    /**
     * Constructor
     */
    public function __construct() {
        $this->test_mode = 'yes';
        $this->debug = 'yes';
    }

    public static function i() {
        if ( is_null( self::$instance ) )
        {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Logging method.
     *
     * @param string $message Log message.
     * @param string $level   Optional. Default 'info'.
     *     emergency|alert|critical|error|warning|notice|info|debug
     *
     * @see WC_Log_Levels
     *
     * @return void
     */
    public function log( $message, $level = 'info' ) {
        // Is Enabled
        if ( $this->debug !== 'yes' ) {
            return;
        }

        // Get Logger instance
        $logger = wc_get_logger();

        // Write message to log
        if ( ! is_string( $message ) ) {
            $message = var_export( $message, TRUE );
        }

        $logger->log( $level, $message, array(
            'source'  => WC_Reepay_Subscriptions::$domain,
            '_legacy' => TRUE
        ) );
    }
}