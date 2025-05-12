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
        add_menu_page(
            'Wheel Manager',
            'Wheel Manager',
            'manage_options',
            'wheel-manager',
            array($this, 'display_dashboard'),
            'dashicons-admin-generic',
            30
        );

        add_submenu_page(
            'wheel-manager',
            'User Points',
            'User Points',
            'manage_options',
            'wheel-manager-points',
            array($this, 'display_points_table')
        );

        add_submenu_page(
            'wheel-manager',
            'Spin History',
            'Spin History',
            'manage_options',
            'wheel-manager-history',
            array($this, 'display_spin_history')
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
            <h1>Wheel Manager Dashboard</h1>
            
            <div class="wheel-manager-stats">
                <div class="stat-box">
                    <h3>Total Spins</h3>
                    <?php echo $this->get_total_spins(); ?>
                </div>
                
                <div class="stat-box">
                    <h3>Total Points Awarded</h3>
                    <?php echo $this->get_total_points_awarded(); ?>
                </div>
                
                <div class="stat-box">
                    <h3>Active Users</h3>
                    <?php echo $this->get_active_users_count(); ?>
                </div>
            </div>

            <div class="wheel-manager-recent">
                <h2>Recent Spins</h2>
                <?php $this->display_recent_spins(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display points table
     */
    public function display_points_table() {
        ?>
        <div class="wrap">
            <h1>User Points</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Total Points</th>
                        <th>Available Points</th>
                        <th>Used Points</th>
                        <th>Available Spins</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = get_users();
                    foreach ($users as $user) {
                        $total_points = $this->mycred_integration->get_user_mycred_points(0, $user->ID);
                        $available_points = $this->mycred_integration->get_user_available_points($user->ID);
                        $used_points = $total_points - $available_points;
                        $available_spins = $this->mycred_integration->get_available_spins($user->ID);
                        ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo number_format($total_points, 2); ?></td>
                            <td><?php echo number_format($available_points, 2); ?></td>
                            <td><?php echo number_format($used_points, 2); ?></td>
                            <td><?php echo $available_spins; ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Display spin history
     */
    public function display_spin_history() {
        global $wpdb;
        
        $spins = $wpdb->get_results(
            "SELECT h.*, u.display_name 
            FROM {$wpdb->prefix}wheel_spin_history h
            JOIN {$wpdb->users} u ON h.user_id = u.ID
            ORDER BY h.created_at DESC
            LIMIT 100"
        );
        ?>
        <div class="wrap">
            <h1>Spin History</h1>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Original Prize</th>
                        <th>Final Prize</th>
                        <th>Multiplier</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spins as $spin) : ?>
                        <tr>
                            <td><?php echo esc_html($spin->display_name); ?></td>
                            <td><?php echo number_format($spin->original_prize, 2); ?></td>
                            <td><?php echo number_format($spin->final_prize, 2); ?></td>
                            <td><?php echo number_format($spin->multiplier, 2); ?>x</td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($spin->created_at)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get total number of spins
     */
    private function get_total_spins() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wheel_spin_history");
    }

    /**
     * Get total points awarded
     */
    private function get_total_points_awarded() {
        global $wpdb;
        $total = $wpdb->get_var("SELECT SUM(final_prize) FROM {$wpdb->prefix}wheel_spin_history");
        return number_format($total, 2);
    }

    /**
     * Get count of active users (users who have spun the wheel)
     */
    private function get_active_users_count() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wheel_spin_history");
    }

    /**
     * Display recent spins
     */
    private function display_recent_spins() {
        global $wpdb;
        
        $spins = $wpdb->get_results(
            "SELECT h.*, u.display_name 
            FROM {$wpdb->prefix}wheel_spin_history h
            JOIN {$wpdb->users} u ON h.user_id = u.ID
            ORDER BY h.created_at DESC
            LIMIT 5"
        );
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Prize</th>
                    <th>Multiplier</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($spins as $spin) : ?>
                    <tr>
                        <td><?php echo esc_html($spin->display_name); ?></td>
                        <td><?php echo number_format($spin->final_prize, 2); ?></td>
                        <td><?php echo number_format($spin->multiplier, 2); ?>x</td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($spin->created_at)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}

// Initialize the admin dashboard
function wheel_manager_bme_admin_dashboard() {
    return Wheel_Manager_BME_Admin_Dashboard::get_instance();
}

wheel_manager_bme_admin_dashboard(); 