<?php

class Wheel_Manager_BME_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Wheel Manager BME', 'wheel-manager-bme'),
            __('Wheel Manager', 'wheel-manager-bme'),
            'manage_options',
            'wheel-manager-bme',
            array($this, 'display_admin_page'),
            'dashicons-marker',
            30
        );
    }

    public function register_settings() {
        register_setting('wheel_manager_bme_options', 'wheel_manager_bme_points_settings');

        add_settings_section(
            'wheel_manager_bme_points_section',
            __('Points Settings', 'wheel-manager-bme'),
            array($this, 'points_section_callback'),
            'wheel_manager_bme_options'
        );

        // Purchase points settings
        add_settings_field(
            'points_under_1m',
            __('Points for purchases under 1M Toman', 'wheel-manager-bme'),
            array($this, 'number_field_callback'),
            'wheel_manager_bme_options',
            'wheel_manager_bme_points_section',
            array('field' => 'points_under_1m')
        );

        add_settings_field(
            'points_1m_to_2m',
            __('Points for purchases 1M-2M Toman', 'wheel-manager-bme'),
            array($this, 'number_field_callback'),
            'wheel_manager_bme_options',
            'wheel_manager_bme_points_section',
            array('field' => 'points_1m_to_2m')
        );

        add_settings_field(
            'points_over_2m',
            __('Points for purchases over 2M Toman', 'wheel-manager-bme'),
            array($this, 'number_field_callback'),
            'wheel_manager_bme_options',
            'wheel_manager_bme_points_section',
            array('field' => 'points_over_2m')
        );

        // Other activity points
        add_settings_field(
            'points_registration',
            __('Points for Registration', 'wheel-manager-bme'),
            array($this, 'number_field_callback'),
            'wheel_manager_bme_options',
            'wheel_manager_bme_points_section',
            array('field' => 'points_registration')
        );

        add_settings_field(
            'points_referral',
            __('Points for Successful Referral', 'wheel-manager-bme'),
            array($this, 'number_field_callback'),
            'wheel_manager_bme_options',
            'wheel_manager_bme_points_section',
            array('field' => 'points_referral')
        );

        add_settings_field(
            'points_profile_completion',
            __('Points for Profile Completion', 'wheel-manager-bme'),
            array($this, 'number_field_callback'),
            'wheel_manager_bme_options',
            'wheel_manager_bme_points_section',
            array('field' => 'points_profile_completion')
        );
    }

    public function points_section_callback() {
        echo '<p>' . __('Configure the points awarded for different activities.', 'wheel-manager-bme') . '</p>';
    }

    public function number_field_callback($args) {
        $options = get_option('wheel_manager_bme_points_settings');
        $value = isset($options[$args['field']]) ? $options[$args['field']] : '';
        ?>
        <input type="number" 
               min="0" 
               name="wheel_manager_bme_points_settings[<?php echo esc_attr($args['field']); ?>]" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text">
        <?php
    }

    public function display_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wheel-manager-admin-container">
                <form action="options.php" method="post">
                    <?php
                    settings_fields('wheel_manager_bme_options');
                    do_settings_sections('wheel_manager_bme_options');
                    submit_button(__('Save Settings', 'wheel-manager-bme'));
                    ?>
                </form>

                <div class="wheel-manager-stats-container">
                    <h2><?php _e('System Statistics', 'wheel-manager-bme'); ?></h2>
                    <?php $this->display_statistics(); ?>
                </div>
            </div>
        </div>

        <style>
            .wheel-manager-admin-container {
                display: flex;
                gap: 30px;
                margin-top: 20px;
            }
            .wheel-manager-admin-container form {
                flex: 2;
                background: #fff;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .wheel-manager-stats-container {
                flex: 1;
                background: #fff;
                padding: 20px;
                border-radius: 5px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .form-table td input[type="number"] {
                width: 150px;
            }
        </style>
        <?php
    }

    private function display_statistics() {
        global $wpdb;
        
        // Get total users with points
        $total_users = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}wheel_manager_user_stats");
        
        // Get total spins today
        $today = date('Y-m-d');
        $total_spins_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}wheel_manager_spin_history WHERE DATE(spin_date) = %s",
            $today
        ));

        // Display stats
        ?>
        <div class="stats-grid">
            <div class="stat-box">
                <h3><?php _e('Total Active Users', 'wheel-manager-bme'); ?></h3>
                <p class="stat-number"><?php echo esc_html($total_users); ?></p>
            </div>
            <div class="stat-box">
                <h3><?php _e('Spins Today', 'wheel-manager-bme'); ?></h3>
                <p class="stat-number"><?php echo esc_html($total_spins_today); ?></p>
            </div>
        </div>

        <style>
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin-top: 15px;
            }
            .stat-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                text-align: center;
            }
            .stat-box h3 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #666;
            }
            .stat-number {
                font-size: 24px;
                font-weight: bold;
                margin: 0;
                color: #2271b1;
            }
        </style>
        <?php
    }
} 