<?php
/**
 * Bridge between MyCred points and WP Optin Wheel
 *
 * @package Wheel_Manager_BME
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_Points_Bridge {
    private $mycred;
    private $default_points_cost = 100;

    public function __construct() {
        // Initialize myCRED
        $this->mycred = mycred();

        // Add hooks
        add_filter('mabel_wof_lite_can_spin', array($this, 'check_points_for_spin'), 10, 2);
        add_action('mabel_wof_lite_before_spin', array($this, 'deduct_points_for_spin'), 10, 2);
        add_filter('mabel_wof_lite_wheel_settings', array($this, 'add_points_settings'));
        add_action('mabel_wof_lite_after_win', array($this, 'maybe_award_points'), 10, 3);
        
        // Add points info display
        add_action('mabel_wof_lite_before_wheel', array($this, 'display_points_info'));
        
        // Add AJAX handlers for real-time points update
        add_action('wp_ajax_get_updated_points', array($this, 'get_updated_points'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Check if user has enough points to spin
     */
    public function check_points_for_spin($can_spin, $user_id) {
        if (!$user_id) return $can_spin;

        $points_needed = $this->get_spin_cost();
        $current_points = $this->mycred->get_users_balance($user_id);

        return $can_spin && ($current_points >= $points_needed);
    }

    /**
     * Deduct points when user spins the wheel
     */
    public function deduct_points_for_spin($wheel_id, $user_id) {
        if (!$user_id) return;

        $points_needed = $this->get_spin_cost($wheel_id);
        $this->mycred->subtract(
            'wheel_spin',
            $user_id,
            $points_needed,
            __('Wheel of Fortune spin', 'wheel-manager-bme'),
            $wheel_id
        );
    }

    /**
     * Add points cost setting to wheel settings
     */
    public function add_points_settings($settings) {
        $settings['points_cost'] = array(
            'label' => __('Points Cost per Spin', 'wheel-manager-bme'),
            'type' => 'number',
            'default' => $this->default_points_cost,
            'description' => __('Number of points required for one spin', 'wheel-manager-bme')
        );

        $settings['points_reward'] = array(
            'label' => __('Points Reward', 'wheel-manager-bme'),
            'type' => 'number',
            'default' => 0,
            'description' => __('Number of points to award on winning (0 to disable)', 'wheel-manager-bme')
        );

        return $settings;
    }

    /**
     * Award points when user wins
     */
    public function maybe_award_points($wheel_id, $user_id, $prize) {
        if (!$user_id) return;

        $points_reward = $this->get_points_reward($wheel_id);
        if ($points_reward > 0) {
            $this->mycred->add(
                'wheel_win',
                $user_id,
                $points_reward,
                __('Wheel of Fortune win', 'wheel-manager-bme'),
                $wheel_id
            );
        }
    }

    /**
     * Display points information before the wheel
     */
    public function display_points_info($wheel_id) {
        if (!is_user_logged_in()) {
            return;
        }

        $wheel_settings = get_post_meta($wheel_id, 'wof_settings', true);
        include WHEEL_MANAGER_BME_PLUGIN_DIR . 'templates/points-info.php';
    }

    /**
     * AJAX handler for getting updated points
     */
    public function get_updated_points() {
        check_ajax_referer('wheel_points_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error();
        }

        $current_points = $this->mycred->get_users_balance($user_id);
        wp_send_json_success(array('points' => $current_points));
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        if (!is_user_logged_in()) {
            return;
        }

        wp_enqueue_script(
            'wheel-points-updater',
            WHEEL_MANAGER_BME_PLUGIN_URL . 'assets/js/points-updater.js',
            array('jquery'),
            WHEEL_MANAGER_BME_VERSION,
            true
        );

        wp_localize_script('wheel-points-updater', 'wheelPoints', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wheel_points_nonce')
        ));
    }

    /**
     * Get points cost for spinning
     */
    private function get_spin_cost($wheel_id = null) {
        $settings = get_post_meta($wheel_id, 'wof_settings', true);
        return !empty($settings['points_cost']) ? 
            absint($settings['points_cost']) : 
            $this->default_points_cost;
    }

    /**
     * Get points reward for winning
     */
    private function get_points_reward($wheel_id) {
        $settings = get_post_meta($wheel_id, 'wof_settings', true);
        return !empty($settings['points_reward']) ? 
            absint($settings['points_reward']) : 
            0;
    }
} 