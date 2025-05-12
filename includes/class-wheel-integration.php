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
    private $min_points_for_spin = 10;
    private $points_for_six_spins = 50;
    private $points_for_fifteen_spins = 100;

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
        add_action('wp_ajax_wheel_manager_check_spin_eligibility', array($this, 'check_spin_eligibility'));
        add_action('wp_ajax_nopriv_wheel_manager_check_spin_eligibility', array($this, 'check_spin_eligibility'));
        
        // Add custom hooks for Optin Wheel
        add_action('optin_wheel_before_display', array($this, 'before_wheel_display'), 10, 1);
        add_action('optin_wheel_after_display', array($this, 'after_wheel_display'), 10, 1);
        add_filter('optin_wheel_can_spin', array($this, 'can_user_spin'), 10, 2);
    }

    /**
     * Actions before wheel display
     */
    public function before_wheel_display($wheel_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Add eligibility check data
        wp_localize_script('optin-wheel', 'wheel_manager_bme', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wheel_manager_bme'),
            'user_id' => $user_id,
            'min_points' => $this->min_points_for_spin
        ));

        // Add custom CSS for eligibility message
        ?>
        <style>
            .wheel-manager-message {
                padding: 10px;
                margin: 10px 0;
                border-radius: 4px;
                text-align: center;
            }
            .wheel-manager-message.error {
                background-color: #ffebee;
                color: #c62828;
                border: 1px solid #ef9a9a;
            }
            .wheel-manager-message.success {
                background-color: #e8f5e9;
                color: #2e7d32;
                border: 1px solid #a5d6a7;
            }
            .wheel-manager-points-info {
                font-size: 14px;
                margin: 10px 0;
                text-align: center;
            }
        </style>
        <?php
    }

    /**
     * Actions after wheel display
     */
    public function after_wheel_display($wheel_id) {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $available_spins = $this->mycred_integration->get_available_spins($user_id);
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        ?>
        <div class="wheel-manager-points-info">
            <p>Available Points: <strong><?php echo number_format($available_points, 2); ?></strong></p>
            <p>Available Spins: <strong><?php echo $available_spins; ?></strong></p>
        </div>
        <?php
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
     * Check if user can spin the wheel
     */
    public function can_user_spin($can_spin, $user_id) {
        if (!$user_id) {
            return false;
        }

        $available_spins = $this->mycred_integration->get_available_spins($user_id);
        return $available_spins > 0;
    }

    /**
     * Check if user is eligible for a spin
     */
    public function check_spin_eligibility() {
        check_ajax_referer('wheel_manager_bme', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array(
                'message' => 'You must be logged in to spin the wheel.'
            ));
        }

        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        $available_spins = $this->mycred_integration->get_available_spins($user_id);

        if ($available_spins <= 0) {
            wp_send_json_error(array(
                'message' => sprintf(
                    'You need at least %d points to spin the wheel. You currently have %d points.',
                    $this->min_points_for_spin,
                    $available_points
                )
            ));
        }

        wp_send_json_success(array(
            'available_spins' => $available_spins,
            'available_points' => $available_points,
            'multiplier' => $this->mycred_integration->get_points_multiplier($user_id)
        ));
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

        // Validate wheel ID
        if (!$this->is_valid_wheel($wheel_id)) {
            wp_send_json_error(array(
                'message' => 'Invalid wheel configuration.'
            ));
        }

        // Deduct points for the spin
        $points_to_deduct = $this->calculate_points_cost($user_id);
        $deduction_result = $this->deduct_points($user_id, $points_to_deduct);

        if (is_wp_error($deduction_result)) {
            wp_send_json_error(array(
                'message' => $deduction_result->get_error_message()
            ));
        }
    }

    /**
     * Calculate points cost for a spin based on available points
     */
    private function calculate_points_cost($user_id) {
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        
        if ($available_points >= $this->points_for_fifteen_spins) {
            return $this->points_for_fifteen_spins / 15; // Cost per spin when having 100+ points
        } elseif ($available_points >= $this->points_for_six_spins) {
            return $this->points_for_six_spins / 6; // Cost per spin when having 50+ points
        }
        
        return $this->min_points_for_spin; // Base cost for one spin
    }

    /**
     * Validate wheel configuration
     */
    private function is_valid_wheel($wheel_id) {
        // Add your wheel validation logic here
        // For example, check if the wheel exists and is active
        return true;
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

        // Add points to user's MyCred balance
        $this->add_points_to_mycred($user_id, $final_prize);

        // Return updated user data
        return array(
            'available_spins' => $this->mycred_integration->get_available_spins($user_id),
            'available_points' => $this->mycred_integration->get_user_available_points($user_id),
            'final_prize' => $final_prize,
            'multiplier' => $multiplier
        );
    }

    /**
     * Add points to user's MyCred balance
     */
    private function add_points_to_mycred($user_id, $points) {
        if (function_exists('mycred_add')) {
            mycred_add(
                'wheel_prize',
                $user_id,
                $points,
                'Wheel spin prize',
                null,
                array('ref_type' => 'wheel_spin')
            );
        }
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

        if (empty($log_entries)) {
            return new WP_Error('insufficient_points', 'No available points to use for spinning.');
        }

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

        if ($remaining_points > 0) {
            return new WP_Error('insufficient_points', 'Not enough points available for spinning.');
        }

        return true;
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