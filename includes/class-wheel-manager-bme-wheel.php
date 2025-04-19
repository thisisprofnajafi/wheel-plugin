<?php

class Wheel_Manager_BME_Wheel {
    
    public function __construct() {
        // Hook into WP Optin Wheel events
        add_filter('mabel_wof_lite_before_spin', array($this, 'check_spin_availability'), 10, 2);
        add_action('mabel_wof_lite_after_spin', array($this, 'handle_spin_result'), 10, 2);
        
        // Add AJAX handlers
        add_action('wp_ajax_convert_points_to_spins', array($this, 'ajax_convert_points_to_spins'));
        add_action('wp_ajax_record_spin_result', array($this, 'ajax_record_spin_result'));
    }

    /**
     * Check if user can spin
     */
    public function check_spin_availability($can_spin, $user_id) {
        if (!$user_id) {
            return false;
        }

        global $wpdb;
        $available_spins = $wpdb->get_var($wpdb->prepare(
            "SELECT available_spins FROM {$wpdb->prefix}wheel_manager_user_stats WHERE user_id = %d",
            $user_id
        ));

        if (!$available_spins || $available_spins <= 0) {
            return false;
        }

        // Check daily spin limit
        $today = date('Y-m-d');
        $spins_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wheel_manager_spin_history 
            WHERE user_id = %d AND DATE(spin_date) = %s",
            $user_id,
            $today
        ));

        return $spins_today < 2;
    }

    /**
     * Handle spin result
     */
    public function handle_spin_result($prize_data, $user_id) {
        if (!$user_id || !$prize_data) {
            return;
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            // Deduct one spin
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}wheel_manager_user_stats 
                SET available_spins = available_spins - 1,
                    total_chances = total_chances + %d
                WHERE user_id = %d AND available_spins > 0",
                $prize_data['chance'],
                $user_id
            ));

            if ($result === false) {
                throw new Exception('Failed to update spins');
            }

            // Log spin history
            $result = $wpdb->insert(
                $wpdb->prefix . 'wheel_manager_spin_history',
                array(
                    'user_id' => $user_id,
                    'points_used' => 0,
                    'result_type' => $prize_data['type'],
                    'result_value' => $prize_data['chance']
                ),
                array('%d', '%d', '%s', '%d')
            );

            if ($result === false) {
                throw new Exception('Failed to log spin history');
            }

            // Check if user earned enough chances for a raffle code
            $total_chances = $wpdb->get_var($wpdb->prepare(
                "SELECT total_chances FROM {$wpdb->prefix}wheel_manager_user_stats WHERE user_id = %d",
                $user_id
            ));

            if ($total_chances >= 10000) {
                $this->generate_raffle_code($user_id, $total_chances);
            }

            $wpdb->query('COMMIT');
            return true;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Wheel Manager BME Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate raffle code
     */
    private function generate_raffle_code($user_id, $total_chances) {
        global $wpdb;

        $code = strtoupper(substr(md5(uniqid($user_id, true)), 0, 8));
        $chances_used = floor($total_chances / 10000) * 10000;

        $wpdb->insert(
            $wpdb->prefix . 'wheel_manager_raffle_codes',
            array(
                'user_id' => $user_id,
                'raffle_code' => $code,
                'total_chances' => $chances_used
            ),
            array('%d', '%s', '%d')
        );

        // Update remaining chances
        $wpdb->update(
            $wpdb->prefix . 'wheel_manager_user_stats',
            array('total_chances' => $total_chances - $chances_used),
            array('user_id' => $user_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * AJAX handler for converting points to spins
     */
    public function ajax_convert_points_to_spins() {
        check_ajax_referer('wheel_manager_bme_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => __('Please log in to convert points.', 'wheel-manager-bme')));
        }

        $points = isset($_POST['points']) ? intval($_POST['points']) : 0;
        if (!in_array($points, array(10, 50, 100))) {
            wp_send_json_error(array('message' => __('Invalid points amount.', 'wheel-manager-bme')));
        }

        // Calculate spins
        $spins = $this->calculate_spins($points);
        if (!$spins) {
            wp_send_json_error(array('message' => __('Invalid conversion amount.', 'wheel-manager-bme')));
        }

        // Perform conversion
        $result = $this->convert_points_to_spins($user_id, $points, $spins);
        if ($result) {
            wp_send_json_success(array(
                'message' => sprintf(__('Successfully converted %d points to %d spins!', 'wheel-manager-bme'), $points, $spins),
                'total_points' => $this->get_user_points($user_id),
                'available_spins' => $this->get_user_spins($user_id)
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to convert points. Please try again.', 'wheel-manager-bme')));
        }
    }

    /**
     * Calculate spins based on points
     */
    private function calculate_spins($points) {
        switch ($points) {
            case 10:
                return 1;
            case 50:
                return 6; // 5 + 20% bonus
            case 100:
                return 15; // 10 + 50% bonus
            default:
                return 0;
        }
    }

    /**
     * Convert points to spins
     */
    private function convert_points_to_spins($user_id, $points, $spins) {
        global $wpdb;

        $wpdb->query('START TRANSACTION');

        try {
            // Check if user has enough points
            $current_points = $this->get_user_points($user_id);
            if ($current_points < $points) {
                throw new Exception('Not enough points');
            }

            // Update user stats
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}wheel_manager_user_stats 
                SET total_points = total_points - %d,
                    available_spins = available_spins + %d
                WHERE user_id = %d AND total_points >= %d",
                $points,
                $spins,
                $user_id,
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
                    'activity_type' => 'points_conversion',
                    'points' => -$points,
                    'reference_type' => 'spins',
                    'reference_id' => $spins
                ),
                array('%d', '%s', '%d', '%s', '%d')
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

    /**
     * Get user points
     */
    private function get_user_points($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT total_points FROM {$wpdb->prefix}wheel_manager_user_stats WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get user spins
     */
    private function get_user_spins($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT available_spins FROM {$wpdb->prefix}wheel_manager_user_stats WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * AJAX handler for recording spin results
     */
    public function ajax_record_spin_result() {
        check_ajax_referer('wheel_manager_bme_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
        }

        $prize = isset($_POST['prize']) ? $_POST['prize'] : null;
        if (!$prize) {
            wp_send_json_error();
        }

        $result = $this->handle_spin_result($prize, $user_id);
        if ($result) {
            wp_send_json_success(array(
                'available_spins' => $this->get_user_spins($user_id)
            ));
        } else {
            wp_send_json_error();
        }
    }
} 