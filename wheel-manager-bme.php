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
        // Check if required plugins are active
        if (!$this->check_dependencies()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('This plugin requires MyCred and Optin Wheel to be installed and activated.');
        }

        // Create database tables
        $this->create_tables();

        // Set version
        update_option('wheel_manager_bme_version', WHEEL_MANAGER_BME_VERSION);
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
        // Check dependencies
        if (!$this->check_dependencies()) {
            add_action('admin_notices', array($this, 'dependency_notice'));
            return;
        }

        // Load text domain
        load_plugin_textdomain('wheel-manager-bme', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Load dependencies
        $this->load_dependencies();

        // Initialize components
        $this->init_components();
    }

    /**
     * Check if required plugins are active
     */
    private function check_dependencies() {
        return (
            class_exists('myCRED_Core') &&
            class_exists('OptinWheel')
        );
    }

    /**
     * Display dependency notice
     */
    public function dependency_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Wheel Manager BME requires MyCred and Optin Wheel to be installed and activated.', 'wheel-manager-bme'); ?></p>
        </div>
        <?php
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

        // Wheel points table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wheel_points (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points decimal(10,2) NOT NULL,
            multiplier decimal(10,2) DEFAULT 1.0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize MyCred integration
        wheel_manager_bme_mycred_integration();

        // Initialize wheel integration
        wheel_manager_bme_wheel_integration();

        // Initialize admin dashboard
        if (is_admin()) {
            wheel_manager_bme_admin_dashboard();
            wheel_manager_bme_ajax_handlers();
        }
    }
}

// Initialize the plugin
function wheel_manager_bme() {
    return Wheel_Manager_BME::get_instance();
}

wheel_manager_bme(); 