<?php
class WC_Product_Reepay_Variable_Subscription extends WC_Product_Variable {
    public function __construct( $product ) {
        parent::__construct( $product );
    }

    public function get_type() {
        return 'reepay_variable_subscriptions';
    }
}