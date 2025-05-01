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
    private $points_history_table = 'wheel_points_history';

    public function __construct() {
        // Initialize myCRED
        $this->mycred = mycred();

        // Create points history table
        add_action('init', array($this, 'create_points_history_table'));

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
     * Create points history table
     */
    public function create_points_history_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . $this->points_history_table;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                points_used int(11) NOT NULL,
                spin_count int(11) NOT NULL,
                bonus_percentage int(11) NOT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Get available spins based on points
     */
    private function get_available_spins($user_id) {
        global $wpdb;
        
        // Get total points from myCRED
        $total_points = $this->mycred->get_users_balance($user_id);
        
        // Get used points from history
        $table_name = $wpdb->prefix . $this->points_history_table;
        $used_points = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_used) FROM $table_name WHERE user_id = %d",
            $user_id
        ));
        
        $available_points = $total_points - ($used_points ?: 0);
        
        // Calculate spins based on points
        if ($available_points >= 100) {
            return array(
                'spins' => 15,
                'bonus' => 50
            );
        } elseif ($available_points >= 50) {
            return array(
                'spins' => 6,
                'bonus' => 20
            );
        } elseif ($available_points >= 10) {
            return array(
                'spins' => 1,
                'bonus' => 0
            );
        }
        
        return array(
            'spins' => 0,
            'bonus' => 0
        );
    }

    /**
     * Check if user has enough points to spin
     */
    public function check_points_for_spin($can_spin, $user_id) {
        if (!$user_id) return $can_spin;

        $available_spins = $this->get_available_spins($user_id);
        return $can_spin && ($available_spins['spins'] > 0);
    }

    /**
     * Deduct points when user spins the wheel
     */
    public function deduct_points_for_spin($wheel_id, $user_id) {
        if (!$user_id) return;

        $available_spins = $this->get_available_spins($user_id);
        if ($available_spins['spins'] <= 0) return;

        global $wpdb;
        $table_name = $wpdb->prefix . $this->points_history_table;
        
        // Calculate points to deduct based on available spins
        $points_to_deduct = 0;
        if ($available_spins['spins'] == 15) {
            $points_to_deduct = 100;
        } elseif ($available_spins['spins'] == 6) {
            $points_to_deduct = 50;
        } else {
            $points_to_deduct = 10;
        }

        // Record the points usage
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'points_used' => $points_to_deduct,
                'spin_count' => 1,
                'bonus_percentage' => $available_spins['bonus'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%d', '%d', '%s')
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

        $user_id = get_current_user_id();
        $available_spins = $this->get_available_spins($user_id);
        
        if ($available_spins['spins'] > 0) {
            echo '<div class="wheel-points-info">';
            echo sprintf(
                __('You have %d spins available with %d%% bonus!', 'wheel-manager-bme'),
                $available_spins['spins'],
                $available_spins['bonus']
            );
            echo '</div>';
        } else {
            echo '<div class="wheel-points-info">';
            echo __('You need more points to spin the wheel!', 'wheel-manager-bme');
            echo '</div>';
        }
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