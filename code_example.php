<?php
/**
 * Plugin Name: Student Spin Wheel & Points System
 * Description: A gamified point and spin system for biomedical engineering students.
 * Version: 1.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) exit;

// Register custom tables or post meta
register_activation_hook(__FILE__, 'spinwheel_plugin_activate');
function spinwheel_plugin_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'student_spin_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) NOT NULL,
        spin_result VARCHAR(255) NOT NULL,
        spin_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add points to users
function add_user_points($user_id, $points) {
    $current = (int)get_user_meta($user_id, '_student_points', true);
    update_user_meta($user_id, '_student_points', $current + $points);
}

// Convert points to spins
function convert_points_to_spins($user_id, $points) {
    $spins = 0;
    if ($points == 10) $spins = 1;
    elseif ($points == 50) $spins = 6;
    elseif ($points == 100) $spins = 15;

    if ($spins > 0) {
        $current_spins = (int)get_user_meta($user_id, '_spin_tokens', true);
        update_user_meta($user_id, '_spin_tokens', $current_spins + $spins);
        add_user_points($user_id, -$points); // deduct points
    }
}

// Spin wheel logic
function spin_the_wheel($user_id) {
    $today = date('Y-m-d');
    $spins_today = (int)get_user_meta($user_id, '_spins_today_' . $today, true);
    if ($spins_today >= 2) return "Limit reached";

    $rand = mt_rand(1, 100);
    if ($rand <= 70) {
        $result = rand(1000, 5000);
    } elseif ($rand <= 95) {
        $result = (rand(0,1) ? 7000 : 9000);
    } else {
        $result = 10000;
    }

    update_user_meta($user_id, '_spins_today_' . $today, $spins_today + 1);
    global $wpdb;
    $wpdb->insert($wpdb->prefix . 'student_spin_log', [
        'user_id' => $user_id,
        'spin_result' => $result,
    ]);
    return $result;
}
