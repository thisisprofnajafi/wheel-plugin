<?php
/**
 * Optin Wheel Integration Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_Wheel_Integration {
    private static $instance = null;
    private $mycred_integration;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->mycred_integration = wheel_manager_bme_mycred_integration();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add hooks for wheel integration
        add_filter('optin_wheel_should_show', array($this, 'should_show_wheel'), 10, 2);
        add_action('optin_wheel_before_spin', array($this, 'before_spin'), 10, 2);
        add_action('optin_wheel_after_spin', array($this, 'after_spin'), 10, 3);
        add_filter('optin_wheel_prize_multiplier', array($this, 'apply_points_multiplier'), 10, 2);
    }

    /**
     * Determine if wheel should be shown based on available points
     */
    public function should_show_wheel($should_show, $user_id) {
        if (!$user_id) {
            return false;
        }

        $available_spins = $this->mycred_integration->get_available_spins($user_id);
        return $available_spins > 0;
    }

    /**
     * Actions to perform before wheel spin
     */
    public function before_spin($wheel_id, $user_id) {
        // Check if user has enough points
        $available_spins = $this->mycred_integration->get_available_spins($user_id);
        if ($available_spins <= 0) {
            wp_send_json_error(array(
                'message' => 'Not enough points available for spinning.'
            ));
        }

        // Deduct points for the spin
        $points_to_deduct = 10; // Base cost for one spin
        $this->deduct_points($user_id, $points_to_deduct);
    }

    /**
     * Actions to perform after wheel spin
     */
    public function after_spin($wheel_id, $user_id, $prize) {
        // Apply multiplier to prize points
        $multiplier = $this->mycred_integration->get_points_multiplier($user_id);
        $final_prize = $prize * $multiplier;

        // Save the wheel points
        $this->save_wheel_points($user_id, $final_prize, $multiplier);

        // Log the spin
        $this->log_spin($user_id, $wheel_id, $prize, $final_prize, $multiplier);
    }

    /**
     * Apply points multiplier to prize
     */
    public function apply_points_multiplier($prize, $user_id) {
        $multiplier = $this->mycred_integration->get_points_multiplier($user_id);
        return $prize * $multiplier;
    }

    /**
     * Deduct points for a spin
     */
    private function deduct_points($user_id, $points) {
        global $wpdb;
        
        // Get the most recent MyCred log entries that haven't been used
        $log_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT id, creds FROM {$wpdb->prefix}mycred_log 
            WHERE user_id = %d AND ref != 'wheel_spin' 
            AND id NOT IN (
                SELECT mycred_log_id FROM {$wpdb->prefix}wheel_spin_history
            )
            ORDER BY id DESC",
            $user_id
        ));

        $remaining_points = $points;
        $used_log_ids = array();

        foreach ($log_entries as $entry) {
            if ($remaining_points <= 0) break;

            $points_to_use = min($remaining_points, $entry->creds);
            $this->mycred_integration->mark_points_as_used(
                $user_id,
                $points_to_use,
                array($entry->id)
            );

            $remaining_points -= $points_to_use;
            $used_log_ids[] = $entry->id;
        }
    }

    /**
     * Save wheel points after applying multiplier
     */
    private function save_wheel_points($user_id, $points, $multiplier) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'wheel_points',
            array(
                'user_id' => $user_id,
                'points' => $points,
                'multiplier' => $multiplier,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%f', '%f', '%s')
        );
    }

    /**
     * Log spin details
     */
    private function log_spin($user_id, $wheel_id, $original_prize, $final_prize, $multiplier) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'wheel_spin_history',
            array(
                'user_id' => $user_id,
                'wheel_id' => $wheel_id,
                'original_prize' => $original_prize,
                'final_prize' => $final_prize,
                'multiplier' => $multiplier,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%f', '%f', '%s')
        );
    }
}

// Initialize the integration
function wheel_manager_bme_wheel_integration() {
    return Wheel_Manager_BME_Wheel_Integration::get_instance();
}

wheel_manager_bme_wheel_integration(); 