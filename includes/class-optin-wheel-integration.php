<?php
/**
 * Optin Wheel Integration Extension
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_Optin_Wheel_Extension {
    private static $instance = null;
    private $wheel_manager;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->wheel_manager = wheel_manager_bme_wheel_integration();
        $this->init_hooks();
    }

    private function init_hooks() {
        // Add hooks to Optin Wheel
        add_filter('wof_can_spin', array($this, 'check_spin_eligibility'), 10, 2);
        add_action('wof_before_spin', array($this, 'before_spin'), 10, 2);
        add_action('wof_after_spin', array($this, 'after_spin'), 10, 3);
        add_filter('wof_prize_multiplier', array($this, 'apply_points_multiplier'), 10, 2);
        
        // Add custom hooks for wheel display
        add_action('wof_before_display', array($this, 'before_wheel_display'), 10, 1);
        add_action('wof_after_display', array($this, 'after_wheel_display'), 10, 1);
        
        // Add custom hooks for prize calculation
        add_filter('wof_calculate_prize', array($this, 'calculate_final_prize'), 10, 3);
    }

    /**
     * Check if user can spin the wheel
     */
    public function check_spin_eligibility($can_spin, $wheel_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();
        return $this->wheel_manager->should_show_wheel(true, $user_id);
    }

    /**
     * Actions before wheel spin
     */
    public function before_spin($wheel_id, $user_id) {
        if (!is_user_logged_in()) {
            return false;
        }

        return $this->wheel_manager->before_spin($wheel_id, $user_id);
    }

    /**
     * Actions after wheel spin
     */
    public function after_spin($wheel_id, $user_id, $prize) {
        if (!is_user_logged_in()) {
            return;
        }

        $result = $this->wheel_manager->after_spin($wheel_id, $user_id, $prize);
        
        // Update the wheel display with new points info
        if (is_array($result)) {
            wp_send_json_success(array(
                'prize' => $result['final_prize'],
                'points_info' => array(
                    'available_points' => $result['available_points'],
                    'available_spins' => $result['available_spins']
                )
            ));
        }
    }

    /**
     * Apply points multiplier to prize
     */
    public function apply_points_multiplier($multiplier, $user_id) {
        if (!is_user_logged_in()) {
            return $multiplier;
        }

        return $this->wheel_manager->apply_points_multiplier($multiplier, $user_id);
    }

    /**
     * Calculate final prize with multiplier
     */
    public function calculate_final_prize($prize, $wheel_id, $user_id) {
        if (!is_user_logged_in()) {
            return $prize;
        }

        $multiplier = $this->wheel_manager->apply_points_multiplier(1, $user_id);
        return $prize * $multiplier;
    }

    /**
     * Actions before wheel display
     */
    public function before_wheel_display($wheel_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        
        // Add eligibility check data
        wp_localize_script('wof-wheel', 'wheel_manager_bme', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wheel_manager_bme'),
            'user_id' => $user_id
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
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $available_spins = $this->wheel_manager->mycred_integration->get_available_spins($user_id);
        $available_points = $this->wheel_manager->mycred_integration->get_user_available_points($user_id);
        ?>
        <div class="wheel-manager-points-info">
            <p>Available Points: <strong><?php echo number_format($available_points, 2); ?></strong></p>
            <p>Available Spins: <strong><?php echo $available_spins; ?></strong></p>
        </div>
        <?php
    }
}

// Initialize the extension
function wheel_manager_bme_optin_wheel_extension() {
    return Wheel_Manager_BME_Optin_Wheel_Extension::get_instance();
}

// Hook into Optin Wheel initialization
add_action('wof_init', 'wheel_manager_bme_optin_wheel_extension'); 