<?php

class Wheel_Manager_BME_Activator {

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Points Activity Log Table
        $table_activity = $wpdb->prefix . 'wheel_manager_activity_log';
        $sql_activity = "CREATE TABLE IF NOT EXISTS $table_activity (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            activity_type varchar(50) NOT NULL,
            points int(11) NOT NULL,
            reference_id bigint(20) DEFAULT NULL,
            reference_type varchar(50) DEFAULT NULL,
            amount decimal(15,2) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY activity_type (activity_type)
        ) $charset_collate;";

        // Spin History Table
        $table_spins = $wpdb->prefix . 'wheel_manager_spin_history';
        $sql_spins = "CREATE TABLE IF NOT EXISTS $table_spins (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            points_used int(11) NOT NULL,
            result_type varchar(50) NOT NULL,
            result_value int(11) NOT NULL,
            spin_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Raffle Codes Table
        $table_raffle = $wpdb->prefix . 'wheel_manager_raffle_codes';
        $sql_raffle = "CREATE TABLE IF NOT EXISTS $table_raffle (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            raffle_code varchar(50) NOT NULL,
            total_chances int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY raffle_code (raffle_code),
            KEY user_id (user_id)
        ) $charset_collate;";

        // User Stats Table
        $table_stats = $wpdb->prefix . 'wheel_manager_user_stats';
        $sql_stats = "CREATE TABLE IF NOT EXISTS $table_stats (
            user_id bigint(20) NOT NULL,
            total_points int(11) DEFAULT 0,
            available_spins int(11) DEFAULT 0,
            total_chances bigint(20) DEFAULT 0,
            last_spin_date datetime DEFAULT NULL,
            spins_today int(11) DEFAULT 0,
            last_spin_reset_date date DEFAULT NULL,
            PRIMARY KEY  (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_activity);
        dbDelta($sql_spins);
        dbDelta($sql_raffle);
        dbDelta($sql_stats);

        // Set version in options
        add_option('wheel_manager_bme_db_version', '1.0');
    }
} 