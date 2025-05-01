<?php
/**
 * Plugin Name: Wheel Manager BME
 * Plugin URI: https://abolfazlnajafi.com/wheel-manager-bme
 * Description: Integration plugin for myCred and WP Optin Wheel. Manage your wheel spins using myCred points system.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.2
 * Author: Abolfazl Najafi
 * Author URI: https://abolfazlnajafi.com
 * Text Domain: wheel-manager-bme
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package Wheel_Manager_BME
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WHEEL_MANAGER_BME_VERSION', '1.0.0');
define('WHEEL_MANAGER_BME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WHEEL_MANAGER_BME_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WHEEL_MANAGER_BME_BASENAME', plugin_basename(__FILE__));

class Wheel_Manager_BME {
    private static $instance = null;
    private $points_bridge;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        $this->load_dependencies();
        $this->init();
    }

    private function load_dependencies() {
        // Load required files
        require_once WHEEL_MANAGER_BME_PLUGIN_DIR . 'includes/class-wheel-manager-bme-points-bridge.php';
    }

    private function init() {
        // Initialize points bridge
        $this->points_bridge = new Wheel_Manager_BME_Points_Bridge();

        // Add init hook
        add_action('init', array($this, 'init_plugin'));

        // Add plugin action links
        add_filter('plugin_action_links_' . WHEEL_MANAGER_BME_BASENAME, array($this, 'add_action_links'));
    }

    public function activate() {
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.2', '<')) {
            deactivate_plugins(WHEEL_MANAGER_BME_BASENAME);
            wp_die(
                __('Wheel Manager BME requires PHP version 7.2 or higher.', 'wheel-manager-bme'),
                __('Plugin Activation Error', 'wheel-manager-bme'),
                array('back_link' => true)
            );
        }

        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(WHEEL_MANAGER_BME_BASENAME);
            wp_die(
                __('Wheel Manager BME requires WordPress version 5.8 or higher.', 'wheel-manager-bme'),
                __('Plugin Activation Error', 'wheel-manager-bme'),
                array('back_link' => true)
            );
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    public function init_plugin() {
        // Load translations
        load_plugin_textdomain('wheel-manager-bme', false, dirname(WHEEL_MANAGER_BME_BASENAME) . '/languages');

        // Check required plugins
        if (!$this->check_required_plugins()) {
            add_action('admin_notices', array($this, 'admin_notice_missing_plugins'));
            return;
        }
    }

    private function check_required_plugins() {
        return class_exists('myCRED_Core') && 
               class_exists('MABEL_WOF_LITE\\Wheel_Of_Fortune');
    }

    public function admin_notice_missing_plugins() {
        $message = __('Wheel Manager BME requires the following plugins to be installed and activated: ', 'wheel-manager-bme');
        $missing = array();

        if (!class_exists('myCRED_Core')) {
            $missing[] = 'myCRED';
        }
        if (!class_exists('MABEL_WOF_LITE\\Wheel_Of_Fortune')) {
            $missing[] = 'WP Optin Wheel';
        }

        printf(
            '<div class="notice notice-error"><p>%s<strong>%s</strong></p></div>',
            esc_html($message),
            esc_html(implode(', ', $missing))
        );
    }

    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="https://abolfazlnajafi.com/docs/wheel-manager-bme" target="_blank">' . __('Documentation', 'wheel-manager-bme') . '</a>'
        );
        return array_merge($plugin_links, $links);
    }
}

// Initialize the plugin
function wheel_manager_bme() {
    return Wheel_Manager_BME::get_instance();
}

// Start the plugin
wheel_manager_bme(); 