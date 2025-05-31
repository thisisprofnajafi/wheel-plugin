<?php
/**
 * Admin Dashboard Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_Admin_Dashboard {
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        // Add main menu
        add_menu_page(
            'مدیریت چرخ شانس',
            'مدیریت چرخ شانس',
            'manage_options',
            'wheel-manager',
            array($this, 'display_dashboard'),
            'dashicons-admin-generic',
            30
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wheel-manager') === false) {
            return;
        }

        wp_enqueue_style(
            'wheel-manager-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            array(),
            WHEEL_MANAGER_BME_VERSION
        );

        wp_enqueue_script(
            'wheel-manager-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            array('jquery'),
            WHEEL_MANAGER_BME_VERSION,
            true
        );
    }

    /**
     * Display main dashboard
     */
    public function display_dashboard() {
        ?>
        <div class="wrap">
            <h1>داشبورد مدیریت چرخ شانس</h1>
            <div class="wheel-manager-recent">
                <h2>تاریخچه چرخش‌ها</h2>
                <?php $this->display_wheel_history(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display wheel history
     */
    private function display_wheel_history() {
        global $wpdb;
        
        $spins = $wpdb->get_results("
            SELECT 
                wsh.*,
                u.display_name as user_name
            FROM {$wpdb->prefix}wheel_spin_history wsh
            LEFT JOIN {$wpdb->users} u ON wsh.user_id = u.ID
            ORDER BY wsh.created_at DESC
            LIMIT 10
        ");

        if (empty($spins)) {
            echo '<p>No spin history available.</p>';
            return;
        }
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Points Used</th>
                    <th>Prize</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spins as $spin): ?>
                    <tr>
                        <td><?php echo esc_html($spin->user_name); ?></td>
                        <td><?php echo esc_html($spin->points_used); ?></td>
                        <td><?php echo esc_html($spin->final_prize); ?></td>
                        <td><?php echo esc_html($spin->created_at); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

function wheel_manager_bme_admin_dashboard() {
    return Wheel_Manager_BME_Admin_Dashboard::get_instance();
}

wheel_manager_bme_admin_dashboard(); 