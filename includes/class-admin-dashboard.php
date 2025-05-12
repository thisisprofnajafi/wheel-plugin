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
        add_action('wp_ajax_wheel_manager_get_filtered_points', array($this, 'get_filtered_points'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(WHEEL_MANAGER_BME_PLUGIN_DIR . 'wheel-manager-bme.php'), array($this, 'add_settings_link'));
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="admin.php?page=wheel-manager-settings">' . __('Settings', 'wheel-manager-bme') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
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

        // Add dashboard submenu
        add_submenu_page(
            'wheel-manager',
            'داشبورد',
            'داشبورد',
            'manage_options',
            'wheel-manager',
            array($this, 'display_dashboard')
        );

        // Add points submenu
        add_submenu_page(
            'wheel-manager',
            'User Points',
            'User Points',
            'manage_options',
            'wheel-manager-points',
            array($this, 'display_points_table')
        );

        // Add spin history submenu
        add_submenu_page(
            'wheel-manager',
            'Spin History',
            'Spin History',
            'manage_options',
            'wheel-manager-history',
            array($this, 'display_spin_history')
        );

        // Add settings submenu
        add_submenu_page(
            'wheel-manager',
            'تنظیمات',
            'تنظیمات',
            'manage_options',
            'wheel-manager-settings',
            array($this, 'display_settings_page')
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
            array('jquery', 'jquery-ui-datepicker'),
            WHEEL_MANAGER_BME_VERSION,
            true
        );

        wp_localize_script('wheel-manager-admin', 'wheel_manager_bme', array(
            'nonce' => wp_create_nonce('wheel_manager_bme'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    /**
     * Display main dashboard
     */
    public function display_dashboard() {
        ?>
        <div class="wrap">
            <h1>داشبورد مدیریت چرخ شانس</h1>
            
            <div class="wheel-manager-stats">
                <div class="stat-box" data-stat="total_spins">
                    <h3>تعداد کل چرخش‌ها</h3>
                    <p><?php echo $this->get_total_spins(); ?></p>
                </div>
                
                <div class="stat-box" data-stat="total_points">
                    <h3>مجموع امتیازات اهدا شده</h3>
                    <p><?php echo $this->get_total_points_awarded(); ?></p>
                </div>
                
                <div class="stat-box" data-stat="active_users">
                    <h3>کاربران فعال</h3>
                    <p><?php echo $this->get_active_users_count(); ?></p>
                </div>
            </div>

            <div class="wheel-manager-recent">
                <h2>تاریخچه چرخش‌ها</h2>
                <?php $this->display_wheel_history(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display points table
     */
    public function display_points_table() {
        global $wpdb;
        
        // Get filter parameters
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'user';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'asc';
        
        // Build the query
        $query = "
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_email,
                COALESCE(mc.total_points, 0) as mycred_points,
                COALESCE(wp.total_points, 0) as wheel_points,
                COALESCE(mc.available_points, 0) as available_points,
                COALESCE(mc.used_points, 0) as used_points,
                COALESCE(mc.available_spins, 0) as available_spins
            FROM {$wpdb->users} u
            LEFT JOIN (
                SELECT 
                    user_id,
                    SUM(creds) as total_points,
                    SUM(CASE WHEN ref != 'wheel_spin' THEN creds ELSE 0 END) as available_points,
                    SUM(CASE WHEN ref = 'wheel_spin' THEN creds ELSE 0 END) as used_points,
                    FLOOR(SUM(CASE WHEN ref != 'wheel_spin' THEN creds ELSE 0 END) / 10) as available_spins
                FROM {$wpdb->prefix}mycred_log 
                GROUP BY user_id
            ) mc ON u.ID = mc.user_id
            LEFT JOIN (
                SELECT user_id, SUM(points) as total_points 
                FROM {$wpdb->prefix}wheel_points 
                GROUP BY user_id
            ) wp ON u.ID = wp.user_id
        ";

        // Add search condition
        if (!empty($search)) {
            $query .= $wpdb->prepare(
                " WHERE u.display_name LIKE %s OR u.user_email LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        // Add sorting
        $allowed_orderby = array('user', 'mycred_points', 'wheel_points', 'available_points', 'used_points', 'available_spins');
        $allowed_order = array('asc', 'desc');
        
        if (in_array($orderby, $allowed_orderby) && in_array($order, $allowed_order)) {
            $orderby_column = $orderby === 'user' ? 'u.display_name' : $orderby;
            $query .= " ORDER BY {$orderby_column} {$order}";
        }

        $users = $wpdb->get_results($query);
        ?>
        <div class="wrap">
            <h1>User Points</h1>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <form method="get">
                        <input type="hidden" name="page" value="wheel-manager-points">
                        <p class="search-box">
                            <label class="screen-reader-text" for="user-search-input">Search users:</label>
                            <input type="search" id="user-search-input" name="s" value="<?php echo esc_attr($search); ?>">
                            <input type="submit" id="search-submit" class="button" value="Search Users">
                        </p>
                    </form>
                </div>
                <div class="alignright">
                    <button class="button wheel-manager-export" data-type="points">Export to CSV</button>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column column-user sortable <?php echo $orderby === 'user' ? $order : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'user', 'order' => $orderby === 'user' && $order === 'asc' ? 'desc' : 'asc'))); ?>">
                                <span>User</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-mycred-points sortable <?php echo $orderby === 'mycred_points' ? $order : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'mycred_points', 'order' => $orderby === 'mycred_points' && $order === 'asc' ? 'desc' : 'asc'))); ?>">
                                <span>MyCred Points</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-wheel-points sortable <?php echo $orderby === 'wheel_points' ? $order : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'wheel_points', 'order' => $orderby === 'wheel_points' && $order === 'asc' ? 'desc' : 'asc'))); ?>">
                                <span>Wheel Points</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-available-points sortable <?php echo $orderby === 'available_points' ? $order : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'available_points', 'order' => $orderby === 'available_points' && $order === 'asc' ? 'desc' : 'asc'))); ?>">
                                <span>Available Points</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-used-points sortable <?php echo $orderby === 'used_points' ? $order : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'used_points', 'order' => $orderby === 'used_points' && $order === 'asc' ? 'desc' : 'asc'))); ?>">
                                <span>Used Points</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th class="manage-column column-available-spins sortable <?php echo $orderby === 'available_spins' ? $order : ''; ?>">
                            <a href="<?php echo esc_url(add_query_arg(array('orderby' => 'available_spins', 'order' => $orderby === 'available_spins' && $order === 'asc' ? 'desc' : 'asc'))); ?>">
                                <span>Available Spins</span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($user->display_name); ?></strong>
                                <br>
                                <small><?php echo esc_html($user->user_email); ?></small>
                            </td>
                            <td class="column-mycred-points"><?php echo number_format($user->mycred_points, 2); ?></td>
                            <td class="column-wheel-points"><?php echo number_format($user->wheel_points, 2); ?></td>
                            <td class="column-available-points"><?php echo number_format($user->available_points, 2); ?></td>
                            <td class="column-used-points"><?php echo number_format($user->used_points, 2); ?></td>
                            <td class="column-available-spins"><?php echo $user->available_spins; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Get filtered points data via AJAX
     */
    public function get_filtered_points() {
        check_ajax_referer('wheel_manager_bme', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $orderby = isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'user';
        $order = isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'asc';

        global $wpdb;
        
        $query = "
            SELECT 
                u.ID as user_id,
                u.display_name,
                u.user_email,
                COALESCE(mc.total_points, 0) as mycred_points,
                COALESCE(wp.total_points, 0) as wheel_points,
                COALESCE(mc.available_points, 0) as available_points,
                COALESCE(mc.used_points, 0) as used_points,
                COALESCE(mc.available_spins, 0) as available_spins
            FROM {$wpdb->users} u
            LEFT JOIN (
                SELECT 
                    user_id,
                    SUM(creds) as total_points,
                    SUM(CASE WHEN ref != 'wheel_spin' THEN creds ELSE 0 END) as available_points,
                    SUM(CASE WHEN ref = 'wheel_spin' THEN creds ELSE 0 END) as used_points,
                    FLOOR(SUM(CASE WHEN ref != 'wheel_spin' THEN creds ELSE 0 END) / 10) as available_spins
                FROM {$wpdb->prefix}mycred_log 
                GROUP BY user_id
            ) mc ON u.ID = mc.user_id
            LEFT JOIN (
                SELECT user_id, SUM(points) as total_points 
                FROM {$wpdb->prefix}wheel_points 
                GROUP BY user_id
            ) wp ON u.ID = wp.user_id
        ";

        if (!empty($search)) {
            $query .= $wpdb->prepare(
                " WHERE u.display_name LIKE %s OR u.user_email LIKE %s",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        $allowed_orderby = array('user', 'mycred_points', 'wheel_points', 'available_points', 'used_points', 'available_spins');
        $allowed_order = array('asc', 'desc');
        
        if (in_array($orderby, $allowed_orderby) && in_array($order, $allowed_order)) {
            $orderby_column = $orderby === 'user' ? 'u.display_name' : $orderby;
            $query .= " ORDER BY {$orderby_column} {$order}";
        }

        $users = $wpdb->get_results($query);
        
        ob_start();
        foreach ($users as $user) {
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html($user->display_name); ?></strong>
                    <br>
                    <small><?php echo esc_html($user->user_email); ?></small>
                </td>
                <td class="column-mycred-points"><?php echo number_format($user->mycred_points, 2); ?></td>
                <td class="column-wheel-points"><?php echo number_format($user->wheel_points, 2); ?></td>
                <td class="column-available-points"><?php echo number_format($user->available_points, 2); ?></td>
                <td class="column-used-points"><?php echo number_format($user->used_points, 2); ?></td>
                <td class="column-available-spins"><?php echo $user->available_spins; ?></td>
            </tr>
            <?php
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
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

    /**
     * Display settings page
     */
    public function display_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Save settings if form is submitted
        if (isset($_POST['wheel_manager_bme_settings_nonce']) && 
            wp_verify_nonce($_POST['wheel_manager_bme_settings_nonce'], 'wheel_manager_bme_save_settings')) {
            
            $settings = array(
                'min_points_for_spin' => isset($_POST['min_points_for_spin']) ? floatval($_POST['min_points_for_spin']) : 10,
                'points_for_six_spins' => isset($_POST['points_for_six_spins']) ? floatval($_POST['points_for_six_spins']) : 50,
                'points_for_fifteen_spins' => isset($_POST['points_for_fifteen_spins']) ? floatval($_POST['points_for_fifteen_spins']) : 100,
                'enable_multiplier' => isset($_POST['enable_multiplier']) ? true : false,
                'multiplier_threshold' => isset($_POST['multiplier_threshold']) ? floatval($_POST['multiplier_threshold']) : 100,
                'multiplier_value' => isset($_POST['multiplier_value']) ? floatval($_POST['multiplier_value']) : 1.5
            );

            update_option('wheel_manager_bme_settings', $settings);
            echo '<div class="notice notice-success"><p>' . __('تنظیمات با موفقیت ذخیره شد.', 'wheel-manager-bme') . '</p></div>';
        }

        // Get current settings
        $settings = get_option('wheel_manager_bme_settings', array(
            'min_points_for_spin' => 10,
            'points_for_six_spins' => 50,
            'points_for_fifteen_spins' => 100,
            'enable_multiplier' => true,
            'multiplier_threshold' => 100,
            'multiplier_value' => 1.5
        ));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('wheel_manager_bme_save_settings', 'wheel_manager_bme_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="min_points_for_spin"><?php _e('حداقل امتیاز برای چرخش', 'wheel-manager-bme'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="min_points_for_spin" id="min_points_for_spin" 
                                value="<?php echo esc_attr($settings['min_points_for_spin']); ?>" class="regular-text" step="0.01" min="0">
                            <p class="description"><?php _e('حداقل امتیاز مورد نیاز برای یک چرخش.', 'wheel-manager-bme'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="points_for_six_spins"><?php _e('امتیاز برای شش چرخش', 'wheel-manager-bme'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="points_for_six_spins" id="points_for_six_spins" 
                                value="<?php echo esc_attr($settings['points_for_six_spins']); ?>" class="regular-text" step="0.01" min="0">
                            <p class="description"><?php _e('امتیاز مورد نیاز برای شش چرخش (هزینه هر چرخش محاسبه خواهد شد).', 'wheel-manager-bme'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="points_for_fifteen_spins"><?php _e('امتیاز برای پانزده چرخش', 'wheel-manager-bme'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="points_for_fifteen_spins" id="points_for_fifteen_spins" 
                                value="<?php echo esc_attr($settings['points_for_fifteen_spins']); ?>" class="regular-text" step="0.01" min="0">
                            <p class="description"><?php _e('امتیاز مورد نیاز برای پانزده چرخش (هزینه هر چرخش محاسبه خواهد شد).', 'wheel-manager-bme'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="enable_multiplier"><?php _e('فعال‌سازی ضریب امتیاز', 'wheel-manager-bme'); ?></label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="enable_multiplier" id="enable_multiplier" 
                                    <?php checked($settings['enable_multiplier']); ?>>
                                <?php _e('فعال‌سازی ضریب امتیاز برای کاربران با امتیاز بالا', 'wheel-manager-bme'); ?>
                            </label>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="multiplier_threshold"><?php _e('آستانه ضریب', 'wheel-manager-bme'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="multiplier_threshold" id="multiplier_threshold" 
                                value="<?php echo esc_attr($settings['multiplier_threshold']); ?>" class="regular-text" step="0.01" min="0">
                            <p class="description"><?php _e('حداقل امتیاز مورد نیاز برای فعال‌سازی ضریب.', 'wheel-manager-bme'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="multiplier_value"><?php _e('مقدار ضریب', 'wheel-manager-bme'); ?></label>
                        </th>
                        <td>
                            <input type="number" name="multiplier_value" id="multiplier_value" 
                                value="<?php echo esc_attr($settings['multiplier_value']); ?>" class="regular-text" step="0.01" min="1">
                            <p class="description"><?php _e('مقدار ضریب اعمال شده به جوایز (مثلاً ۱.۵ برای ۵۰٪ پاداش).', 'wheel-manager-bme'); ?></p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('ذخیره تنظیمات', 'wheel-manager-bme')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Display wheel history table
     */
    public function display_wheel_history() {
        global $wpdb;
        
        // Get all spins with user information
        $spins = $wpdb->get_results(
            "SELECT 
                o.email,
                COUNT(*) as total_spins,
                SUM(
                    CASE 
                        WHEN o.segment_text REGEXP '^[0-9]+$' 
                        THEN CAST(o.segment_text AS UNSIGNED)
                        ELSE 0 
                    END
                ) as total_points_won,
                MAX(o.created_date) as last_spin_time
            FROM {$wpdb->prefix}wof_optins o
            WHERE o.segment_text IS NOT NULL 
            AND o.segment_text != ''
            GROUP BY o.email
            ORDER BY last_spin_time DESC"
        );
        
        echo '<div class="wrap">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('کاربر', 'wheel-manager-bme') . '</th>';
        echo '<th>' . __('امتیازات چرخ شانس', 'wheel-manager-bme') . '</th>';
        echo '<th>' . __('امتیازات MyCred', 'wheel-manager-bme') . '</th>';
        echo '<th>' . __('امتیازات تعدیل شده', 'wheel-manager-bme') . '</th>';
        echo '<th>' . __('تعداد چرخش', 'wheel-manager-bme') . '</th>';
        echo '<th>' . __('آخرین چرخش', 'wheel-manager-bme') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($spins as $spin) {
            // Get user by email
            $user = get_user_by('email', $spin->email);
            if (!$user) continue;

            // Get MyCred points
            $mycred_points = mycred_get_users_balance($user->ID);
            
            // Calculate adjusted points (MyCred points minus points deducted for spins)
            $points_deducted = ($spin->total_spins * 10);
            $adjusted_points = max(0, $mycred_points - $points_deducted);
            
            // Format the last spin time
            $last_spin = new DateTime($spin->last_spin_time);
            $last_spin->setTimezone(new DateTimeZone('Asia/Tehran'));
            $formatted_last_spin = $last_spin->format('Y-m-d H:i:s');
            
            echo '<tr>';
            echo '<td>' . esc_html($spin->email) . '</td>';
            echo '<td>' . esc_html(number_format($spin->total_points_won, 0)) . '</td>';
            echo '<td>' . esc_html(number_format($mycred_points, 0)) . '</td>';
            echo '<td>' . esc_html(number_format($adjusted_points, 0)) . '</td>';
            echo '<td>' . esc_html($spin->total_spins) . '</td>';
            echo '<td>' . esc_html($formatted_last_spin) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
}

// Initialize the admin dashboard
function wheel_manager_bme_admin_dashboard() {
    return Wheel_Manager_BME_Admin_Dashboard::get_instance();
}

wheel_manager_bme_admin_dashboard(); 