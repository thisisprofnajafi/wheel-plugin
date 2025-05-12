<?php
/**
 * AJAX Handlers Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wheel_Manager_BME_Ajax_Handlers {
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
        add_action('wp_ajax_wheel_manager_refresh_stats', array($this, 'refresh_stats'));
        add_action('wp_ajax_wheel_manager_export', array($this, 'export_data'));
        add_action('wp_ajax_wheel_manager_filter_spins', array($this, 'filter_spins'));
    }

    /**
     * Refresh dashboard statistics
     */
    public function refresh_stats() {
        check_ajax_referer('wheel_manager_bme', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        $stats = array(
            'total_spins' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wheel_spin_history"),
            'total_points' => number_format($wpdb->get_var("SELECT SUM(final_prize) FROM {$wpdb->prefix}wheel_spin_history"), 2),
            'active_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wheel_spin_history")
        );

        wp_send_json_success($stats);
    }

    /**
     * Export data to CSV
     */
    public function export_data() {
        check_ajax_referer('wheel_manager_bme', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        
        if (!in_array($type, array('points', 'spins'))) {
            wp_send_json_error('Invalid export type');
        }

        $filename = 'wheel-manager-' . $type . '-' . date('Y-m-d') . '.csv';
        $filepath = wp_upload_dir()['path'] . '/' . $filename;
        
        $fp = fopen($filepath, 'w');
        
        if ($type === 'points') {
            $this->export_points_data($fp);
        } else {
            $this->export_spins_data($fp);
        }
        
        fclose($fp);

        $fileurl = wp_upload_dir()['url'] . '/' . $filename;
        
        wp_send_json_success(array('url' => $fileurl));
    }

    /**
     * Export points data to CSV
     */
    private function export_points_data($fp) {
        global $wpdb;
        
        // Write headers
        fputcsv($fp, array('User', 'Total Points', 'Available Points', 'Used Points', 'Available Spins'));
        
        // Get users
        $users = get_users();
        foreach ($users as $user) {
            $total_points = $this->mycred_integration->get_user_mycred_points(0, $user->ID);
            $available_points = $this->mycred_integration->get_user_available_points($user->ID);
            $used_points = $total_points - $available_points;
            $available_spins = $this->mycred_integration->get_available_spins($user->ID);
            
            fputcsv($fp, array(
                $user->display_name,
                $total_points,
                $available_points,
                $used_points,
                $available_spins
            ));
        }
    }

    /**
     * Export spins data to CSV
     */
    private function export_spins_data($fp) {
        global $wpdb;
        
        // Write headers
        fputcsv($fp, array('User', 'Original Prize', 'Final Prize', 'Multiplier', 'Date'));
        
        // Get spins
        $spins = $wpdb->get_results(
            "SELECT h.*, u.display_name 
            FROM {$wpdb->prefix}wheel_spin_history h
            JOIN {$wpdb->users} u ON h.user_id = u.ID
            ORDER BY h.created_at DESC"
        );
        
        foreach ($spins as $spin) {
            fputcsv($fp, array(
                $spin->display_name,
                $spin->original_prize,
                $spin->final_prize,
                $spin->multiplier,
                $spin->created_at
            ));
        }
    }

    /**
     * Filter spins by date range
     */
    public function filter_spins() {
        check_ajax_referer('wheel_manager_bme', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $range = isset($_POST['range']) ? sanitize_text_field($_POST['range']) : 'all';
        
        global $wpdb;
        
        $where = '';
        switch ($range) {
            case 'today':
                $where = "WHERE DATE(h.created_at) = CURDATE()";
                break;
            case 'week':
                $where = "WHERE h.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $where = "WHERE h.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
        }
        
        $spins = $wpdb->get_results(
            "SELECT h.*, u.display_name 
            FROM {$wpdb->prefix}wheel_spin_history h
            JOIN {$wpdb->users} u ON h.user_id = u.ID
            {$where}
            ORDER BY h.created_at DESC
            LIMIT 5"
        );
        
        ob_start();
        foreach ($spins as $spin) {
            ?>
            <tr>
                <td><?php echo esc_html($spin->display_name); ?></td>
                <td><?php echo number_format($spin->final_prize, 2); ?></td>
                <td><?php echo number_format($spin->multiplier, 2); ?>x</td>
                <td><?php echo date('Y-m-d H:i:s', strtotime($spin->created_at)); ?></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
}

// Initialize the AJAX handlers
function wheel_manager_bme_ajax_handlers() {
    return Wheel_Manager_BME_Ajax_Handlers::get_instance();
}

wheel_manager_bme_ajax_handlers(); 