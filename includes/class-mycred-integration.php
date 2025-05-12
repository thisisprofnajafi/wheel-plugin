<?php
/**
 * MyCred Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_MyCred_Integration {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add hooks for MyCred integration
        add_filter('mycred_get_users_total', array($this, 'get_user_mycred_points'), 10, 2);
        add_action('mycred_after_log_entry', array($this, 'log_points_used'), 10, 1);
    }

    /**
     * Get user's total MyCred points
     */
    public function get_user_mycred_points($total, $user_id) {
        global $wpdb, $mycred_log_table;
        
        $points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(creds) FROM {$mycred_log_table} 
            WHERE user_id = %d AND ctype = %s",
            $user_id, MYCRED_DEFAULT_TYPE_KEY
        ));

        return $points ? $points : 0;
    }

    /**
     * Get user's available points (excluding used points)
     */
    public function get_user_available_points($user_id) {
        $total_points = $this->get_user_mycred_points(0, $user_id);
        $used_points = $this->get_used_points($user_id);
        return $total_points - $used_points;
    }

    /**
     * Get points that have been used for spins
     */
    private function get_used_points($user_id) {
        global $wpdb;
        
        $used_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_used) FROM {$wpdb->prefix}wheel_spin_history 
            WHERE user_id = %d",
            $user_id
        ));

        return $used_points ? $used_points : 0;
    }

    /**
     * Mark points as used in spin history
     */
    public function mark_points_as_used($user_id, $points, $mycred_log_ids) {
        global $wpdb;
        
        foreach($mycred_log_ids as $log_id) {
            $wpdb->insert(
                $wpdb->prefix . 'wheel_spin_history',
                array(
                    'user_id' => $user_id,
                    'mycred_log_id' => $log_id,
                    'points_used' => $points
                ),
                array('%d', '%d', '%f')
            );
        }
    }

    /**
     * Log points used after a spin
     */
    public function log_points_used($log_entry) {
        // This function will be called after MyCred logs a points entry
        // We can use this to track which points have been used for spins
        if (isset($log_entry->ref) && $log_entry->ref === 'wheel_spin') {
            $this->mark_points_as_used(
                $log_entry->user_id,
                $log_entry->creds,
                array($log_entry->id)
            );
        }
    }

    /**
     * Get available spins based on points
     */
    public function get_available_spins($user_id) {
        $available_points = $this->get_user_available_points($user_id);
        
        if ($available_points >= 100) {
            return 15;
        } elseif ($available_points >= 50) {
            return 6;
        }
        
        return floor($available_points / 10);
    }

    /**
     * Get points multiplier based on available points
     */
    public function get_points_multiplier($user_id) {
        $available_points = $this->get_user_available_points($user_id);
        
        if ($available_points >= 100) {
            return 1.5; // 50% bonus
        } elseif ($available_points >= 50) {
            return 1.2; // 20% bonus
        }
        
        return 1.0; // No bonus
    }
}

// Initialize the integration
function wheel_manager_bme_mycred_integration() {
    return Wheel_Manager_BME_MyCred_Integration::get_instance();
}

wheel_manager_bme_mycred_integration(); 