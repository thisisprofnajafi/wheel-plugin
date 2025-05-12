<?php
/**
 * Plugin Name: Wheel Manager BME
 * Plugin URI: https://example.com/wheel-manager-bme
 * Description: Integrates MyCred points with Optin Wheel functionality
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: wheel-manager-bme
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WHEEL_MANAGER_BME_VERSION', '1.0.0');
define('WHEEL_MANAGER_BME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WHEEL_MANAGER_BME_PLUGIN_URL', plugin_dir_url(__FILE__));

class Wheel_Manager_BME {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();

        // Set version
        update_option('wheel_manager_bme_version', WHEEL_MANAGER_BME_VERSION);
        
        // Log activation
        error_log('Wheel Manager BME - Plugin activated');
        error_log('Wheel Manager BME - Version: ' . WHEEL_MANAGER_BME_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Cleanup if necessary
    }

    /**
     * Initialize plugin
     */
    public function init() {
        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        $this->init_mycred_integration();
        $this->init_wheel_integration();
        $this->init_admin_dashboard();

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
    }

    /**
     * Check if required plugins are active
     */
    private function check_dependencies() {
        // Check if MyCred is active
        $mycred_active = false;
        if (class_exists('myCRED_Core') || function_exists('mycred_get_settings')) {
            $mycred_active = true;
        }

        // Check if Mabel Wheel of Fortune is active
        $wof_active = false;
        if (class_exists('MABEL_WOF_LITE\Wheel_Of_Fortune')) {
            $wof_active = true;
        }

        return ($mycred_active && $wof_active);
    }

    /**
     * Display dependency notice
     */
    public function dependency_notice() {
        $missing_plugins = array();

        // Check MyCred
        if (!class_exists('myCRED_Core') && !function_exists('mycred_get_settings')) {
            $missing_plugins[] = 'MyCred';
        }

        // Check Mabel Wheel of Fortune
        if (!class_exists('MABEL_WOF_LITE\Wheel_Of_Fortune')) {
            $missing_plugins[] = 'Mabel Wheel of Fortune';
        }

        if (!empty($missing_plugins)) {
            ?>
            <div class="notice notice-error">
                <p><?php 
                    printf(
                        __('Wheel Manager BME requires the following plugins to be installed and activated: %s', 'wheel-manager-bme'),
                        '<strong>' . implode(', ', $missing_plugins) . '</strong>'
                    ); 
                ?></p>
            </div>
            <?php
        }
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Wheel spin history table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_spin_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            wheel_id bigint(20) NOT NULL,
            mycred_log_id bigint(20) DEFAULT NULL,
            points_used decimal(10,2) DEFAULT 0,
            original_prize decimal(10,2) DEFAULT 0,
            final_prize decimal(10,2) DEFAULT 0,
            multiplier decimal(10,2) DEFAULT 1.0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY wheel_id (wheel_id),
            KEY mycred_log_id (mycred_log_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Log table creation
        error_log('Wheel Manager BME - Creating/updating database tables');
        error_log('Wheel Manager BME - SQL: ' . $sql);
    }

    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Include required files
        require_once WHEEL_MANAGER_BME_PLUGIN_DIR . 'includes/class-mycred-integration.php';
        require_once WHEEL_MANAGER_BME_PLUGIN_DIR . 'includes/class-wheel-integration.php';
        require_once WHEEL_MANAGER_BME_PLUGIN_DIR . 'includes/class-admin-dashboard.php';
        require_once WHEEL_MANAGER_BME_PLUGIN_DIR . 'includes/class-ajax-handlers.php';
        require_once WHEEL_MANAGER_BME_PLUGIN_DIR . 'includes/class-optin-wheel-integration.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_mycred_integration() {
        // Initialize MyCred integration
        wheel_manager_bme_mycred_integration();
    }

    private function init_wheel_integration() {
        // Initialize wheel integration
        wheel_manager_bme_wheel_integration();
        
        // Initialize Optin Wheel extension
        if (class_exists('MABEL_WOF_LITE\Wheel_Of_Fortune')) {
            wheel_manager_bme_optin_wheel_extension();
        }
    }

    private function init_admin_dashboard() {
        // Initialize admin dashboard
        if (is_admin()) {
            wheel_manager_bme_admin_dashboard();
            wheel_manager_bme_ajax_handlers();
        }
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        if (!is_user_logged_in()) {
            return;
        }

        wp_enqueue_script(
            'wheel-manager-bme-integration',
            plugins_url('assets/js/wheel-integration.js', __FILE__),
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script('wheel-manager-bme-integration', 'wheel_manager_bme', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wheel_manager_bme'),
            'user_id' => get_current_user_id()
        ));
    }
}

// Initialize the plugin
function wheel_manager_bme() {
    return Wheel_Manager_BME::get_instance();
}

wheel_manager_bme(); 