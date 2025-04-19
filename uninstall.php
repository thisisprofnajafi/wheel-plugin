<?php
/**
 * Uninstall Wheel Manager BME
 *
 * @package Wheel_Manager_BME
 * @author Abolfazl Najafi
 * @copyright 2024 Abolfazl Najafi
 * @license GPL-2.0+
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wheel_manager_bme_points_settings');
delete_option('wheel_manager_bme_db_version');

// Drop custom tables
global $wpdb;
$tables = array(
    $wpdb->prefix . 'wheel_manager_activity_log',
    $wpdb->prefix . 'wheel_manager_spin_history',
    $wpdb->prefix . 'wheel_manager_raffle_codes',
    $wpdb->prefix . 'wheel_manager_user_stats'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Delete the dashboard page
$page = get_page_by_path('wheel-dashboard');
if ($page) {
    wp_delete_post($page->ID, true);
}

// Clean up post meta
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_wheel_manager_points_awarded'");

// Clean up user meta
$wpdb->query("DELETE FROM $wpdb->usermeta WHERE meta_key LIKE '_wheel_manager_%'"); 