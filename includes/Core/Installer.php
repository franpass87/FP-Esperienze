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
        
        // Set activation redirect transient (only if not already complete)
        if (!get_option('fp_esperienze_setup_complete', false)) {
            set_transient('fp_esperienze_activation_redirect', 1, 30);
        }
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
            billing_type enum('per_person', 'per_booking') NOT NULL DEFAULT 'per_person',
            tax_class varchar(50) DEFAULT '',
            is_required tinyint(1) NOT NULL DEFAULT 0,
            max_quantity int(11) NOT NULL DEFAULT 1,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_active (is_active),
            KEY billing_type (billing_type)
        ) $charset_collate;";

        // Product-Extras association table
        $table_product_extras = $wpdb->prefix . 'fp_product_extras';
        $sql_product_extras = "CREATE TABLE $table_product_extras (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            extra_id bigint(20) unsigned NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY extra_id (extra_id),
            UNIQUE KEY product_extra (product_id, extra_id)
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

        // Gift Vouchers table (new structure for gift experience feature)
        $table_exp_vouchers = $wpdb->prefix . 'fp_exp_vouchers';
        $sql_exp_vouchers = "CREATE TABLE $table_exp_vouchers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(12) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            amount_type enum('full', 'value') NOT NULL DEFAULT 'full',
            amount decimal(10,2) NOT NULL DEFAULT 0.00,
            recipient_name varchar(255) NOT NULL,
            recipient_email varchar(255) NOT NULL,
            message text DEFAULT NULL,
            expires_on date NOT NULL,
            status enum('active', 'redeemed', 'expired', 'void') NOT NULL DEFAULT 'active',
            pdf_path varchar(255) DEFAULT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            order_item_id bigint(20) unsigned DEFAULT NULL,
            sender_name varchar(255) DEFAULT NULL,
            send_date date DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code),
            KEY product_id (product_id),
            KEY recipient_email (recipient_email),
            KEY status (status),
            KEY expires_on (expires_on),
            KEY order_id (order_id)
        ) $charset_collate;
        
        // Keep existing vouchers table for backward compatibility
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
        dbDelta($sql_product_extras);
        dbDelta($sql_schedules);
        dbDelta($sql_overrides);
        dbDelta($sql_bookings);
        dbDelta($sql_exp_vouchers);
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
            // Gift voucher settings
            'fp_esperienze_gift_default_exp_months' => 12,
            'fp_esperienze_gift_pdf_logo' => '',
            'fp_esperienze_gift_pdf_brand_color' => '#ff6b35',
            'fp_esperienze_gift_email_sender_name' => get_bloginfo('name'),
            'fp_esperienze_gift_email_sender_email' => get_option('admin_email'),
            'fp_esperienze_gift_secret_hmac' => wp_generate_password(32, false),
            'fp_esperienze_gift_terms' => __('This voucher is valid for one experience booking. Please present the QR code when redeeming.', 'fp-esperienze'),
        ];

        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }
}