<?php

class WC_Reepay_Subscription_API{
    public $params;

    /**
     * Constructor
     */
    public function __construct() {

        $this->private_key_test = 'priv_3728a84bd1d89da26f4da17a75aa81c3';
        $this->test_mode = 'yes';
        $this->debug = 'yes';
        $this->private_key = 'priv_3728a84bd1d89da26f4da17a75aa81c3';

    }

    public function set_params($params = array()){
        $this->params = $params;
    }

    /**
     * Request
     * @param $method
     * @param $url
     * @param array $params
     * @return array|mixed|object
     * @throws Exception
     */
    public function request($method, $url) {
        $start = microtime(true);

        $params = $this->params;

        WC_RS_Log::i()->log(sprintf('Request: %s %s %s', $method, $url, json_encode( $params, JSON_PRETTY_PRINT ) ) ); ;


        $key = $this->test_mode === 'yes' ? $this->private_key_test : $this->private_key;
        $key = base64_encode($key.':');

        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => "Basic $key"
            ],
            'method' => $method,
            'timeout' => 60,
        ];
        if (count($params) > 0) {
            $args['body'] = json_encode($params, JSON_PRETTY_PRINT);
            $args['headers']['Content-Length'] = strlen(json_encode($params, JSON_PRETTY_PRINT));
        }

        $response = wp_remote_request( $url, $args );
        $body = wp_remote_retrieve_body( $response );
        $http_code = wp_remote_retrieve_response_code( $response );
        $code = $http_code / 100;

        if ( $this->debug === 'yes' ) {
            $time = microtime(true) - $start;
            WC_RS_Log::i()->log( sprintf( '[%.4F] HTTP Code: %s. Response: %s', $time, wp_remote_retrieve_response_code( $response ), $body ) );
        }

        switch ($code) {
            case 0:
                if ( is_wp_error($response) ){
                    wc_add_notice($response->get_error_message(), 'error');
                    throw new Exception(sprintf(__('Error: %s. Code: %s.', WC_Reepay_Subscriptions::$domain ), $response->get_error_message(), $code));
                }
            case 1:
                throw new Exception(sprintf(__('Invalid HTTP Code: %s', WC_Reepay_Subscriptions::$domain ), $http_code));
            case 2:
            case 3:
                return json_decode($body, true);
            case 4:
            case 5:
                if ( mb_strpos( $body, 'Request rate limit exceeded', 0, 'UTF-8' ) !== false ) {
                    global $request_retry;
                    if ($request_retry) {
                        throw new Exception( __('Reepay: Request rate limit exceeded', WC_Reepay_Subscriptions::$domain ) );
                    }

                    sleep(10);
                    $request_retry = true;
                    $result = $this->request($method, $url, $params);
                    $request_retry = false;

                    return  $result;
                }

                throw new Exception(sprintf(__('API Error (request): %s. HTTP Code: %s', WC_Reepay_Subscriptions::$domain ), $body, $http_code));
            default:
                if ( $this->debug === 'yes' ) {
                    throw new Exception($body);
                }else{
                    throw new Exception(sprintf(__('Invalid HTTP Code: %s', WC_Reepay_Subscriptions::$domain ), $http_code));
                }

        }
    }
}
