<?php

class Wheel_Manager_BME_Frontend {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Add frontend page
        add_action('init', array($this, 'register_frontend_page'));
        add_filter('template_include', array($this, 'load_frontend_template'));
        
        // Add shortcode
        add_shortcode('wheel_manager_dashboard', array($this, 'dashboard_shortcode'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function register_frontend_page() {
        // Check if page exists
        $page_exists = get_page_by_path('wheel-dashboard');
        
        if (!$page_exists) {
            // Create page
            $page = array(
                'post_title' => __('Wheel Dashboard', 'wheel-manager-bme'),
                'post_content' => '[wheel_manager_dashboard]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'wheel-dashboard'
            );
            wp_insert_post($page);
        }
    }

    public function load_frontend_template($template) {
        if (is_page('wheel-dashboard')) {
            $new_template = WHEEL_MANAGER_BME_PLUGIN_DIR . 'templates/dashboard-template.php';
            if (file_exists($new_template)) {
                return $new_template;
            }
        }
        return $template;
    }

    public function enqueue_scripts() {
        if (!is_page('wheel-dashboard')) return;

        wp_enqueue_style(
            'wheel-manager-bme-frontend',
            WHEEL_MANAGER_BME_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $this->version
        );

        wp_enqueue_script(
            'wheel-manager-bme-frontend',
            WHEEL_MANAGER_BME_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('wheel-manager-bme-frontend', 'wheelManagerBME', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wheel_manager_bme_nonce')
        ));
    }

    public function dashboard_shortcode() {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="wheel-manager-login-required">%s</div>',
                __('Please log in to access your wheel dashboard.', 'wheel-manager-bme')
            );
        }

        $user_id = get_current_user_id();
        $user_stats = $this->get_user_stats($user_id);
        
        ob_start();
        ?>
        <div class="wheel-manager-dashboard">
            <div class="wheel-manager-header">
                <h1><?php _e('Your Wheel Dashboard', 'wheel-manager-bme'); ?></h1>
            </div>

            <div class="wheel-manager-stats-grid">
                <div class="stats-card">
                    <div class="stats-icon">🎯</div>
                    <div class="stats-content">
                        <h3><?php _e('Total Points', 'wheel-manager-bme'); ?></h3>
                        <p class="stats-value"><?php echo esc_html($user_stats->total_points); ?></p>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon">🎡</div>
                    <div class="stats-content">
                        <h3><?php _e('Available Spins', 'wheel-manager-bme'); ?></h3>
                        <p class="stats-value"><?php echo esc_html($user_stats->available_spins); ?></p>
                    </div>
                </div>

                <div class="stats-card">
                    <div class="stats-icon">🎲</div>
                    <div class="stats-content">
                        <h3><?php _e('Total Chances', 'wheel-manager-bme'); ?></h3>
                        <p class="stats-value"><?php echo esc_html($user_stats->total_chances); ?></p>
                    </div>
                </div>
            </div>

            <div class="wheel-manager-actions">
                <button class="convert-points-btn" data-points="10">
                    <?php _e('Convert 10 Points → 1 Spin', 'wheel-manager-bme'); ?>
                </button>
                <button class="convert-points-btn" data-points="50">
                    <?php _e('Convert 50 Points → 6 Spins (+20% bonus)', 'wheel-manager-bme'); ?>
                </button>
                <button class="convert-points-btn" data-points="100">
                    <?php _e('Convert 100 Points → 15 Spins (+50% bonus)', 'wheel-manager-bme'); ?>
                </button>
            </div>

            <?php if (class_exists('MABEL_WOF_LITE\\Wheel_Of_Fortune')) : ?>
            <div class="wheel-manager-wheel-section">
                <h2><?php _e('Spin the Wheel', 'wheel-manager-bme'); ?></h2>
                <!-- WP Optin Wheel will be loaded here -->
            </div>
            <?php endif; ?>

            <div class="wheel-manager-history">
                <h2><?php _e('Recent Activity', 'wheel-manager-bme'); ?></h2>
                <?php $this->display_user_history($user_id); ?>
            </div>

            <footer class="wheel-manager-footer">
                <p><?php _e('Made with ❤️ by ', 'wheel-manager-bme'); ?> 
                   <a href="https://abolfazlnajafi.com" target="_blank">Abolfazl Najafi</a>
                </p>
            </footer>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_user_stats($user_id) {
        global $wpdb;
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wheel_manager_user_stats WHERE user_id = %d",
            $user_id
        ));

        if (!$stats) {
            return (object) array(
                'total_points' => 0,
                'available_spins' => 0,
                'total_chances' => 0
            );
        }

        return $stats;
    }

    private function display_user_history($user_id) {
        global $wpdb;
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wheel_manager_activity_log 
            WHERE user_id = %d 
            ORDER BY created_at DESC 
            LIMIT 5",
            $user_id
        ));

        if (empty($activities)) {
            echo '<p class="no-activity">' . __('No recent activity found.', 'wheel-manager-bme') . '</p>';
            return;
        }

        echo '<ul class="activity-list">';
        foreach ($activities as $activity) {
            printf(
                '<li class="activity-item">
                    <span class="activity-points">%s%d</span>
                    <span class="activity-type">%s</span>
                    <span class="activity-date">%s</span>
                </li>',
                $activity->points >= 0 ? '+' : '',
                $activity->points,
                esc_html($this->get_activity_label($activity->activity_type)),
                date_i18n(get_option('date_format'), strtotime($activity->created_at))
            );
        }
        echo '</ul>';
    }

    private function get_activity_label($type) {
        $labels = array(
            'purchase' => __('Purchase reward', 'wheel-manager-bme'),
            'wheel_spin' => __('Wheel spin', 'wheel-manager-bme'),
            'points_conversion' => __('Points conversion', 'wheel-manager-bme'),
            'registration' => __('Registration bonus', 'wheel-manager-bme'),
            'referral' => __('Referral bonus', 'wheel-manager-bme')
        );

        return isset($labels[$type]) ? $labels[$type] : $type;
    }
} 