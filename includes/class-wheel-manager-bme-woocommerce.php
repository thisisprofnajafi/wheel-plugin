<?php

class Wheel_Manager_BME_WooCommerce {
    
    public function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'award_points_for_purchase'));
    }

    /**
     * Award points when a WooCommerce order is completed
     */
    public function award_points_for_purchase($order_id) {
        $order = wc_get_order($order_id);
        
        // Check if points were already awarded for this order
        if (get_post_meta($order_id, '_wheel_manager_points_awarded', true)) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        $total = $order->get_total();
        // Convert total to Toman (assuming the store uses Toman)
        $total_in_toman = $total;

        // Get points settings
        $points_settings = get_option('wheel_manager_bme_points_settings', array());
        
        // Calculate points based on purchase amount
        $points = 0;
        if ($total_in_toman < 1000000) {
            $points = isset($points_settings['points_under_1m']) ? $points_settings['points_under_1m'] : 0;
        } elseif ($total_in_toman <= 2000000) {
            $points = isset($points_settings['points_1m_to_2m']) ? $points_settings['points_1m_to_2m'] : 0;
        } else {
            $points = isset($points_settings['points_over_2m']) ? $points_settings['points_over_2m'] : 0;
        }

        if ($points > 0) {
            $this->add_points($user_id, $points, 'purchase', $order_id);
            update_post_meta($order_id, '_wheel_manager_points_awarded', true);
        }
    }

    /**
     * Add points to user account
     */
    private function add_points($user_id, $points, $activity_type, $reference_id = null) {
        global $wpdb;

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            // Update user stats
            $result = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$wpdb->prefix}wheel_manager_user_stats (user_id, total_points)
                VALUES (%d, %d)
                ON DUPLICATE KEY UPDATE total_points = total_points + %d",
                $user_id,
                $points,
                $points
            ));

            if ($result === false) {
                throw new Exception('Failed to update user stats');
            }

            // Log activity
            $result = $wpdb->insert(
                $wpdb->prefix . 'wheel_manager_activity_log',
                array(
                    'user_id' => $user_id,
                    'activity_type' => $activity_type,
                    'points' => $points,
                    'reference_id' => $reference_id,
                    'reference_type' => 'wc_order'
                ),
                array('%d', '%s', '%d', '%d', '%s')
            );

            if ($result === false) {
                throw new Exception('Failed to log activity');
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Wheel Manager BME Error: ' . $e->getMessage());
            return false;
        }
    }
} 