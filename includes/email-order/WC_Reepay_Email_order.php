<?php

class WC_Reepay_Email_order extends WC_Email_New_Order {
    /**
     * Override trigger method to check invoice data
     *
     * @param int $order_id The order ID.
     * @param WC_Order|false $order Order object.
     */
    public function trigger($order_id, $order = false) {
        error_log('WC_Reepay_Email_order trigger');
        $this->setup_locale();

        if ($order_id && !is_a($order, 'WC_Order')) {
            $order = wc_get_order($order_id);
        }

        // Check if order is paid via Reepay
        if (!rp_is_order_paid_via_reepay($order)) {
            parent::trigger($order_id, $order);
            return;
        }

        // Add retry logic for invoice data
        $max_attempts = 5;
        $attempts = 0;
        
        while ($attempts < $max_attempts) {
            $invoice_data = reepay()->api($order)->get_invoice_data($order);
            
            if (!is_wp_error($invoice_data) && 
                isset($invoice_data['state']) && 
                in_array($invoice_data['state'], ['authorized', 'settled'], true)) {
                    
                // Continue with parent trigger if invoice data is valid
                parent::trigger($order_id, $order);
                return;
            }
            
            $attempts++;
            if ($attempts < $max_attempts) {
                sleep(2);
            }
        }

        // Log error if invoice data couldn't be retrieved
        if (is_wp_error($invoice_data)) {
            $order->add_order_note(sprintf(
                __('Failed to send new order email: %s', 'reepay-checkout-gateway'),
                $invoice_data->get_error_message()
            ));
        }

        $this->restore_locale();
    }
}
?>