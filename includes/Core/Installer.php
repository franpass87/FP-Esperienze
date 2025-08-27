<?php
/**
 * Plugin Installer
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Installer class for database tables and plugin setup
 */
class Installer {

    /**
     * Plugin activation
     */
    public static function activate(): void {
        self::createTables();
        self::createDefaultOptions();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Update version option
        update_option('fp_esperienze_version', FP_ESPERIENZE_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public static function deactivate(): void {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private static function createTables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Meeting Points table
        $table_meeting_points = $wpdb->prefix . 'fp_meeting_points';
        $sql_meeting_points = "CREATE TABLE $table_meeting_points (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text NOT NULL,
            lat decimal(10,8) DEFAULT NULL,
            lng decimal(11,8) DEFAULT NULL,
            place_id varchar(255) DEFAULT NULL,
            note text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY place_id (place_id)
        ) $charset_collate;";

        // Extras table
        $table_extras = $wpdb->prefix . 'fp_extras';
        $sql_extras = "CREATE TABLE $table_extras (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            price decimal(10,2) NOT NULL DEFAULT 0.00,
            is_required tinyint(1) NOT NULL DEFAULT 0,
            max_quantity int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        // Schedules table
        $table_schedules = $wpdb->prefix . 'fp_schedules';
        $sql_schedules = "CREATE TABLE $table_schedules (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            day_of_week tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, etc.',
            start_time time NOT NULL,
            duration_min int(11) NOT NULL DEFAULT 60,
            capacity int(11) NOT NULL DEFAULT 1,
            lang varchar(10) DEFAULT 'en',
            meeting_point_id bigint(20) unsigned DEFAULT NULL,
            price_adult decimal(10,2) NOT NULL DEFAULT 0.00,
            price_child decimal(10,2) NOT NULL DEFAULT 0.00,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY day_of_week (day_of_week),
            KEY meeting_point_id (meeting_point_id)
        ) $charset_collate;";

        // Overrides table
        $table_overrides = $wpdb->prefix . 'fp_overrides';
        $sql_overrides = "CREATE TABLE $table_overrides (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            date date NOT NULL,
            is_closed tinyint(1) NOT NULL DEFAULT 0,
            capacity_override int(11) DEFAULT NULL,
            price_override_json text DEFAULT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY date (date),
            UNIQUE KEY product_date (product_id, date)
        ) $charset_collate;";

        // Bookings table
        $table_bookings = $wpdb->prefix . 'fp_bookings';
        $sql_bookings = "CREATE TABLE $table_bookings (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            order_item_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            adults int(11) NOT NULL DEFAULT 0,
            children int(11) NOT NULL DEFAULT 0,
            meeting_point_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            customer_notes text DEFAULT NULL,
            admin_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY booking_date (booking_date),
            KEY status (status)
        ) $charset_collate;";

        // Vouchers table
        $table_vouchers = $wpdb->prefix . 'fp_vouchers';
        $sql_vouchers = "CREATE TABLE $table_vouchers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            voucher_code varchar(50) NOT NULL,
            qr_code_data text NOT NULL,
            pdf_path varchar(255) DEFAULT NULL,
            is_used tinyint(1) NOT NULL DEFAULT 0,
            used_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY voucher_code (voucher_code),
            KEY is_used (is_used)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_meeting_points);
        dbDelta($sql_extras);
        dbDelta($sql_schedules);
        dbDelta($sql_overrides);
        dbDelta($sql_bookings);
        dbDelta($sql_vouchers);
    }

    /**
     * Create default options
     */
    private static function createDefaultOptions(): void {
        $default_options = [
            'fp_esperienze_currency' => get_woocommerce_currency(),
            'fp_esperienze_default_duration' => 60,
            'fp_esperienze_default_capacity' => 10,
            'fp_esperienze_booking_cutoff_hours' => 2,
            'fp_esperienze_confirmation_email' => 1,
        ];

        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }
}