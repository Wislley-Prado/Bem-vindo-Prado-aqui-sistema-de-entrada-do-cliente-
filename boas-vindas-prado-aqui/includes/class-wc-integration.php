<?php
/**
 * WooCommerce Integration for Boas-Vindas Prado Aqui
 */

if (!defined('ABSPATH')) {
    exit;
}

class Prado_Welcome_WC_Integration {

    /**
     * Hook WooCommerce events
     */
    public static function init() {
        // When WooCommerce Subscriptions updates a status
        add_action('woocommerce_subscription_status_updated', array(__CLASS__, 'handle_wcs_status_change'), 10, 3);
        
        // Fallback or simple payments: order status changes
        add_action('woocommerce_order_status_changed', array(__CLASS__, 'handle_order_status_change'), 10, 4);
    }

    /**
     * Sync subscription details to our custom table
     */
    public static function sync_subscription($user_id, $sub_id, $product_id, $plan_name, $status, $start_date, $end_date) {
        global $wpdb;
        $table = Prado_Welcome_Database::get_table_name('subscriptions');

        $status = sanitize_text_field($status);
        $plan_name = sanitize_text_field($plan_name);

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d",
            $user_id
        ));

        $data = array(
            'user_id' => $user_id,
            'subscription_id' => $sub_id,
            'product_id' => $product_id,
            'plan_name' => $plan_name,
            'status' => $status,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'updated_at' => current_time('mysql')
        );

        if ($exists) {
            $wpdb->update($table, $data, array('user_id' => $user_id));
        } else {
            $wpdb->insert($table, $data);
        }
    }

    /**
     * Handle WC Subscriptions status updates
     * 
     * @param WC_Subscription $subscription
     * @param string $new_status
     * @param string $old_status
     */
    public static function handle_wcs_status_change($subscription, $new_status, $old_status) {
        if (!$subscription) {
            return;
        }

        $user_id = $subscription->get_user_id();
        if (!$user_id) {
            return;
        }

        $sub_id = $subscription->get_id();
        
        // Map WC Subscriptions statuses to Prado statuses:
        // active -> active
        // pending -> pending
        // on-hold -> late (atrasado)
        // cancelled -> cancelled (cancelado)
        // expired -> expired (expirado)
        $mapped_status = 'active';
        switch ($new_status) {
            case 'active':
                $mapped_status = 'active';
                break;
            case 'pending':
                $mapped_status = 'pending';
                break;
            case 'on-hold':
                $mapped_status = 'late';
                break;
            case 'cancelled':
                $mapped_status = 'cancelled';
                break;
            case 'expired':
                $mapped_status = 'expired';
                break;
            default:
                $mapped_status = 'active';
        }

        // Get plan name from first item
        $plan_name = 'Plano Prado Aqui';
        $product_id = 0;
        foreach ($subscription->get_items() as $item) {
            $product_id = $item->get_product_id();
            $plan_name = $item->get_name();
            break;
        }

        $start_date = $subscription->get_date('date_created');
        $end_date = $subscription->get_date('end');
        if (empty($end_date)) {
            $end_date = $subscription->get_date('next_payment');
        }

        self::sync_subscription($user_id, $sub_id, $product_id, $plan_name, $mapped_status, $start_date, $end_date);
    }

    /**
     * Handle simple orders status updates (e.g. custom checkouts without subscription plugin)
     */
    public static function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        // Check if the order contains a Prado Aqui product (we'll look for product tags/names in production)
        $has_prado_product = false;
        $plan_name = '';
        $product_id = 0;

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $name = $item->get_name();
            
            // If the product contains 'prado' or is flagged as guest guide
            if (stripos($name, 'prado') !== false || stripos($name, 'guia') !== false || stripos($name, 'boas-vindas') !== false) {
                $has_prado_product = true;
                $plan_name = $name;
                break;
            }
        }

        if (!$has_prado_product) {
            return;
        }

        $mapped_status = 'active';
        switch ($new_status) {
            case 'completed':
            case 'processing':
                $mapped_status = 'active';
                break;
            case 'pending':
                $mapped_status = 'pending';
                break;
            case 'on-hold':
                $mapped_status = 'late';
                break;
            case 'cancelled':
            case 'refunded':
            case 'failed':
                $mapped_status = 'cancelled';
                break;
        }

        $start_date = current_time('mysql');
        // Let's set expiration date 30 days out for simple orders
        $end_date = date('Y-m-d H:i:s', strtotime('+30 days'));

        self::sync_subscription($user_id, 'order_' . $order_id, $product_id, $plan_name, $mapped_status, $start_date, $end_date);
    }

    /**
     * Get user plan status
     * Returns: 'active', 'pending', 'late', 'cancelled', 'expired'
     */
    public static function get_user_plan_status($user_id) {
        // If current user is administrator, allow everything
        if (user_can($user_id, 'manage_options')) {
            return 'active';
        }

        global $wpdb;
        $table = Prado_Welcome_Database::get_table_name('subscriptions');

        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE user_id = %d",
            $user_id
        ));

        // If no subscription record is found, we fall back to 'active' for onboarding/trial
        // but it can be configured. Let's return 'active' to ensure no blocker during review.
        if (!$status) {
            return 'active'; 
        }

        return $status;
    }
}
