<?php
/**
 * Wheel Integration Class
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
        // Add hooks for Mabel Wheel of Fortune
        add_filter('mabel_wof_lite_can_spin', array($this, 'check_spin_eligibility'), 10, 2);
        add_action('mabel_wof_lite_before_spin', array($this, 'before_spin'), 10, 2);
        add_action('mabel_wof_lite_after_spin', array($this, 'after_spin'), 10, 3);
        add_filter('mabel_wof_lite_prize_multiplier', array($this, 'apply_points_multiplier'), 10, 2);
        
        // Add custom hooks for wheel display
        add_action('mabel_wof_lite_before_display', array($this, 'before_wheel_display'), 10, 1);
        add_action('mabel_wof_lite_after_display', array($this, 'after_wheel_display'), 10, 1);
        
        // Add custom hooks for prize calculation
        add_filter('mabel_wof_lite_calculate_prize', array($this, 'calculate_final_prize'), 10, 3);

        // Add filter for wheel visibility
        add_filter('wof_active_wheels', array($this, 'filter_active_wheels'), 10, 1);

        // Add action for wheel initialization
        add_action('wp_footer', array($this, 'initialize_wheel'), 100);
    }

    /**
     * Initialize wheel display
     */
    public function initialize_wheel() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        
        error_log('Wheel Manager BME - Initializing wheel display');
        error_log('Wheel Manager BME - User ID: ' . $user_id . ', Available Points: ' . $available_points);

        if ($available_points >= $this->min_points_for_spin) {
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                error_log('Wheel Manager BME - Setting up wheel display');
                
                // Force wheel display
                if (typeof WOF !== 'undefined') {
                    // Show wheel immediately
                    WOF.Dispatcher.subscribe('wof-before-display', function(wheel) {
                        error_log('Wheel Manager BME - Wheel before display event');
                        wheel.appeartype = 'immediately';
                        wheel.appeardelay = 0;
                        return true;
                    });

                    WOF.Dispatcher.subscribe('wof-after-display', function(wheel) {
                        error_log('Wheel Manager BME - Wheel after display event');
                    });

                    // Show wheel overlay
                    $('.wof-overlay').show();
                    
                    // Show wheel container
                    $('.wof-wheels').show();
                }

                // Add custom CSS for wheel visibility
                $('<style>')
                    .text(`
                        .wof-wheel { display: block !important; }
                        .wof-overlay { display: block !important; }
                        .wof-wheels { display: block !important; }
                    `)
                    .appendTo('head');
            });
            </script>
            <?php
        }
    }

    /**
     * Filter active wheels based on user points
     */
    public function filter_active_wheels($wheels) {
        error_log('Wheel Manager BME - Filtering active wheels');
        error_log('Wheel Manager BME - Number of wheels before filter: ' . count($wheels));

        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, hiding all wheels');
            return array();
        }

        $user_id = get_current_user_id();
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        error_log('Wheel Manager BME - User ID: ' . $user_id . ', Available Points: ' . $available_points);

        if ($available_points < $this->min_points_for_spin) {
            error_log('Wheel Manager BME - User has insufficient points, hiding all wheels');
            return array();
        }

        error_log('Wheel Manager BME - User has sufficient points, showing wheels');
        
        // Ensure wheel is active and visible
        foreach ($wheels as $wheel) {
            $wheel->active = 1;
            $wheel->appeartype = 'immediately';
            $wheel->appeardelay = 0;
            $wheel->usage = 'popup';
        }
        
        return $wheels;
    }

    /**
     * Check if user can spin the wheel
     */
    public function check_spin_eligibility($can_spin, $wheel_id) {
        error_log('Wheel Manager BME - Checking spin eligibility for wheel ID: ' . $wheel_id);
        
        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, cannot spin');
            return false;
        }

        $user_id = get_current_user_id();
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        error_log('Wheel Manager BME - User ID: ' . $user_id . ', Available Points: ' . $available_points);
        
        $can_spin = $available_points >= $this->min_points_for_spin;
        error_log('Wheel Manager BME - Can user spin? ' . ($can_spin ? 'Yes' : 'No'));
        
        return $can_spin;
    }

    /**
     * Actions before wheel spin
     */
    public function before_spin($wheel_id, $user_id) {
        error_log('Wheel Manager BME - Before spin for wheel ID: ' . $wheel_id . ', User ID: ' . $user_id);
        
        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, cannot spin');
            return false;
        }

        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        $points_cost = $this->calculate_points_cost($available_points);
        error_log('Wheel Manager BME - Available Points: ' . $available_points . ', Points Cost: ' . $points_cost);

        if ($available_points < $points_cost) {
            error_log('Wheel Manager BME - Insufficient points for spin');
            return false;
        }

        // Deduct points
        $this->mycred_integration->deduct_points($user_id, $points_cost, 'wheel_spin');
        error_log('Wheel Manager BME - Points deducted successfully');
        return true;
    }

    /**
     * Actions after wheel spin
     */
    public function after_spin($wheel_id, $user_id, $prize) {
        error_log('Wheel Manager BME - After spin for wheel ID: ' . $wheel_id . ', User ID: ' . $user_id . ', Prize: ' . $prize);
        
        if (!is_user_logged_in()) {
            error_log('Wheel Manager BME - User not logged in, cannot process spin');
            return;
        }

        $multiplier = $this->apply_points_multiplier(1, $user_id);
        $final_prize = $prize * $multiplier;
        error_log('Wheel Manager BME - Multiplier: ' . $multiplier . ', Final Prize: ' . $final_prize);

        // Log the spin
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'wheel_spin_history',
            array(
                'user_id' => $user_id,
                'wheel_id' => $wheel_id,
                'points_used' => $this->calculate_points_cost($this->mycred_integration->get_user_available_points($user_id)),
                'original_prize' => $prize,
                'final_prize' => $final_prize,
                'multiplier' => $multiplier,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%f', '%f', '%f', '%f', '%s')
        );
        error_log('Wheel Manager BME - Spin logged in database');

        // Add points to MyCred
        $this->mycred_integration->add_points_to_mycred($user_id, $final_prize);
        error_log('Wheel Manager BME - Points added to MyCred');

        return array(
            'final_prize' => $final_prize,
            'available_points' => $this->mycred_integration->get_user_available_points($user_id),
            'available_spins' => $this->mycred_integration->get_available_spins($user_id)
        );
    }

    /**
     * Apply points multiplier to prize
     */
    public function apply_points_multiplier($multiplier, $user_id) {
        if (!is_user_logged_in()) {
            return $multiplier;
        }

        $settings = get_option('wheel_manager_bme_settings', array(
            'enable_multiplier' => true,
            'multiplier_threshold' => 100,
            'multiplier_value' => 1.5
        ));

        if (!$settings['enable_multiplier']) {
            return $multiplier;
        }

        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        if ($available_points >= $settings['multiplier_threshold']) {
            return $settings['multiplier_value'];
        }

        return $multiplier;
    }

    /**
     * Calculate final prize with multiplier
     */
    public function calculate_final_prize($prize, $wheel_id, $user_id) {
        if (!is_user_logged_in()) {
            return $prize;
        }

        $multiplier = $this->apply_points_multiplier(1, $user_id);
        return $prize * $multiplier;
    }

    /**
     * Calculate points cost based on available points
     */
    private function calculate_points_cost($available_points) {
        if ($available_points >= $this->points_for_fifteen_spins) {
            return $this->points_for_fifteen_spins / 15;
        } elseif ($available_points >= $this->points_for_six_spins) {
            return $this->points_for_six_spins / 6;
        }
        return $this->min_points_for_spin;
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
        $available_spins = $this->mycred_integration->get_available_spins($user_id);
        $available_points = $this->mycred_integration->get_user_available_points($user_id);
        ?>
        <div class="wheel-manager-points-info">
            <p>Available Points: <strong><?php echo number_format($available_points, 2); ?></strong></p>
            <p>Available Spins: <strong><?php echo $available_spins; ?></strong></p>
        </div>
        <?php
    }
}

// Initialize the wheel integration
function wheel_manager_bme_wheel_integration() {
    return Wheel_Manager_BME_Wheel_Integration::get_instance();
}

wheel_manager_bme_wheel_integration(); 