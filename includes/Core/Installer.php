<?php
/**
 * Plugin Installer
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use Exception;
use Throwable;
use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\TranslationQueue;
use FP\Esperienze\Core\PerformanceOptimizer;

defined('ABSPATH') || exit;

/**
 * Installer class for database tables and plugin setup
 */
class Installer {

    /**
     * Tracks whether the staff attendance table has been verified in this request
     *
     * @var bool
     */
    private static bool $staffAttendanceVerified = false;

    /**
     * Plugin activation
     *
     * @return bool|\WP_Error True on success or WP_Error on failure
     */
    public static function activate() {
        try {
            $result = self::createTables();
            if (is_wp_error($result)) {
                return $result;
            }

            $result = self::maybeCreateStaffAttendanceTable();
            if (is_wp_error($result)) {
                return $result;
            }

            $result = self::migrateBookingTable();
            if (is_wp_error($result)) {
                return $result;
            }

            $result = self::createDefaultOptions();
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Capability setup runs only on activation to avoid redundant role checks
            $result = self::addCapabilities();
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Execute schedule migration if feature flag is enabled
            if (defined('FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION') && FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION) {
                $result = self::migrateScheduleNullFields();
                if (is_wp_error($result)) {
                    return $result;
                }
            }
            
            // Execute event support migration
            $result = self::migrateForEventSupport();
            if (is_wp_error($result)) {
                return $result;
            }
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
            // Update version option
            update_option('fp_esperienze_version', FP_ESPERIENZE_VERSION);

            // Schedule translation queue processing
            if (!wp_next_scheduled(TranslationQueue::CRON_HOOK)) {
                wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', TranslationQueue::CRON_HOOK);
            }
            
            // Initialize performance optimizations on activation
            if (class_exists('FP\Esperienze\Core\PerformanceOptimizer')) {
            PerformanceOptimizer::maybeAddOptimizedIndexes();
        }
        
        // Set activation redirect transient (only if not already complete)
        if (!get_option('fp_esperienze_setup_complete', false)) {
            set_transient('fp_esperienze_activation_redirect', 1, 30);
        }

        // Ensure ICS directory exists and is protected
        if (!file_exists(FP_ESPERIENZE_ICS_DIR)) {
            wp_mkdir_p(FP_ESPERIENZE_ICS_DIR);
        }

        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!WP_Filesystem()) {
            $msg = 'Installer: WP_Filesystem initialization failed during activation.';
            error_log($msg);
            return new \WP_Error('fp_fs_init_failed', $msg);
        }

        $htaccess_path = FP_ESPERIENZE_ICS_DIR . '/.htaccess';
        if (!$wp_filesystem->exists($htaccess_path)) {
            if (!$wp_filesystem->put_contents($htaccess_path, "Deny from all\n", FS_CHMOD_FILE)) {
                $msg = 'Installer: Failed to create ICS .htaccess file.';
                error_log($msg);
                return new \WP_Error('fp_htaccess_write_failed', $msg);
            }
        }

        $index_path = FP_ESPERIENZE_ICS_DIR . '/index.php';
        if (!$wp_filesystem->exists($index_path)) {
            if (!$wp_filesystem->put_contents($index_path, "<?php\nstatus_header(403);\nexit;\n", FS_CHMOD_FILE)) {
                $msg = 'Installer: Failed to create ICS index.php file.';
                error_log($msg);
                return new \WP_Error('fp_index_write_failed', $msg);
            }
        }

        return true;
        
        } catch (Throwable $e) {
            error_log('FP Esperienze: Activation error: ' . $e->getMessage());
            return new \WP_Error('fp_activation_failed', 'Plugin activation failed: ' . $e->getMessage());
        }
    }

    /**
     * Plugin deactivation
     *
     * @return bool|\WP_Error True on success or WP_Error on failure
     */
    public static function deactivate() {
        $hooks = [
            'fp_esperienze_cleanup_holds',
            'fp_check_abandoned_carts',
            'fp_send_upselling_emails',
            'fp_daily_ai_analysis',
            'fp_esperienze_prebuild_availability',
            'fp_esperienze_retry_webhook',
            'fp_es_process_translation_queue',
            'fp_cleanup_push_tokens',
        ];

        // Clear all scheduled events for the plugin
        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);

            // Verify that no events remain scheduled for this hook
            while ($timestamp = wp_next_scheduled($hook)) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall - remove capabilities and plugin data
     *
     * @return bool|\WP_Error True on success or WP_Error on failure
     */
    public static function uninstall() {
        CapabilityManager::removeCapabilitiesFromRoles();

        if (defined('FP_ESPERIENZE_PRESERVE_DATA') && FP_ESPERIENZE_PRESERVE_DATA) {
            return true;
        }
        // Clear all scheduled events used by the plugin before removing data
        $hooks = [
            'fp_esperienze_cleanup_holds',
            'fp_check_abandoned_carts',
            'fp_send_upselling_emails',
            'fp_daily_ai_analysis',
            'fp_esperienze_prebuild_availability',
            'fp_esperienze_retry_webhook',
            TranslationQueue::CRON_HOOK,
            'fp_cleanup_push_tokens',
            'fp_esperienze_db_optimization',
        ];

        foreach ($hooks as $hook) {
            wp_clear_scheduled_hook($hook);

            // Verify that no events remain scheduled for this hook
            while ($timestamp = wp_next_scheduled($hook)) {
                wp_unschedule_event($timestamp, $hook);
            }
        }

        global $wpdb;

        $tables = [
            $wpdb->prefix . 'fp_meeting_points',
            $wpdb->prefix . 'fp_extras',
            $wpdb->prefix . 'fp_product_extras',
            $wpdb->prefix . 'fp_schedules',
            $wpdb->prefix . 'fp_overrides',
            $wpdb->prefix . 'fp_bookings',
            $wpdb->prefix . 'fp_exp_vouchers',
            $wpdb->prefix . 'fp_vouchers',
            $wpdb->prefix . 'fp_dynamic_pricing_rules',
            $wpdb->prefix . 'fp_exp_holds',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        $option_like = $wpdb->esc_like('fp_esperienze_') . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $option_like
            )
        );

        $transient_like = $wpdb->esc_like('_transient_fp_esperienze_') . '%';
        $timeout_like   = $wpdb->esc_like('_transient_timeout_fp_esperienze_') . '%';
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $transient_like,
                $timeout_like
            )
        );

        // Remove ICS directory and files
        $ics_dir = FP_ESPERIENZE_ICS_DIR;
        global $wp_filesystem;
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!WP_Filesystem()) {
            $msg = 'Installer: WP_Filesystem initialization failed during deactivation.';
            error_log($msg);
            return new \WP_Error('fp_fs_init_failed', $msg);
        }

        if ($wp_filesystem->is_dir($ics_dir)) {
            $files = glob($ics_dir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    if ($wp_filesystem->exists($file) && !$wp_filesystem->delete($file)) {
                        $msg = 'Installer: Failed to delete file ' . $file;
                        error_log($msg);
                        return new \WP_Error('fp_delete_failed', $msg);
                    }
                }
            }
            if (!$wp_filesystem->delete($ics_dir, true)) {
                $msg = 'Installer: Failed to remove ICS directory.';
                error_log($msg);
                return new \WP_Error('fp_dir_delete_failed', $msg);
            }
        }

        return true;
    }

    /**
     * Create database tables
     * 
     * @return bool|\WP_Error True on success or WP_Error on failure
     */
    private static function createTables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Check if dbDelta is available
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        if (!function_exists('dbDelta')) {
            return new \WP_Error('fp_dbdelta_missing', 'WordPress dbDelta function not available');
        }

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
            schedule_type enum('recurring', 'fixed') NOT NULL DEFAULT 'recurring' COMMENT 'Type of schedule: recurring weekly or fixed date',
            day_of_week tinyint(1) DEFAULT NULL COMMENT '0=Sunday, 1=Monday, etc. Used for recurring schedules',
            event_date date DEFAULT NULL COMMENT 'Specific date for fixed-date events',
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
            KEY event_date (event_date),
            KEY schedule_type (schedule_type),
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
            customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            booking_number varchar(191) DEFAULT NULL,
            product_id bigint(20) unsigned NOT NULL,
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            adults int(11) NOT NULL DEFAULT 0,
            children int(11) NOT NULL DEFAULT 0,
            participants int(11) NOT NULL DEFAULT 0,
            meeting_point_id bigint(20) unsigned DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'confirmed',
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            currency varchar(10) NOT NULL DEFAULT '',
            checked_in_at datetime DEFAULT NULL,
            checked_in_by bigint(20) unsigned DEFAULT NULL,
            customer_notes text DEFAULT NULL,
            admin_notes text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY booking_date (booking_date),
            KEY status (status),
            KEY idx_customer_id (customer_id),
            KEY idx_booking_number (booking_number),
            KEY idx_total_amount (total_amount),
            KEY idx_participants (participants),
            KEY idx_checked_in_at (checked_in_at),
            KEY idx_checked_in_by (checked_in_by),
            UNIQUE KEY order_item_unique (order_id, order_item_id)
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
        ) $charset_collate;";
        
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

        // Dynamic Pricing Rules table
        $table_dynamic_pricing = $wpdb->prefix . 'fp_dynamic_pricing_rules';
        $sql_dynamic_pricing = "CREATE TABLE $table_dynamic_pricing (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            rule_type enum('seasonal', 'weekend_weekday', 'early_bird', 'group') NOT NULL,
            rule_name varchar(255) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            priority int(11) NOT NULL DEFAULT 0,
            date_start date DEFAULT NULL,
            date_end date DEFAULT NULL,
            applies_to enum('weekend', 'weekday') DEFAULT NULL,
            days_before int(11) DEFAULT NULL,
            min_participants int(11) DEFAULT NULL,
            adjustment_type enum('percentage', 'fixed_amount') NOT NULL DEFAULT 'percentage',
            adult_adjustment decimal(10,2) NOT NULL DEFAULT 0.00,
            child_adjustment decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY rule_type (rule_type),
            KEY is_active (is_active),
            KEY priority (priority)
        ) $charset_collate;";

        // Capacity Holds table for optimistic locking
        $table_holds = $wpdb->prefix . 'fp_exp_holds';
        $sql_holds = "CREATE TABLE $table_holds (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(128) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            slot_start datetime NOT NULL,
            qty int(11) NOT NULL DEFAULT 1,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_slot (product_id, slot_start),
            KEY session_id (session_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        // Staff attendance tracking table
        $sql_staff_attendance = self::getStaffAttendanceTableSql($charset_collate);

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Execute table creation with error checking
        $tables_sql = [
            'meeting_points' => $sql_meeting_points,
            'extras' => $sql_extras,
            'product_extras' => $sql_product_extras,
            'schedules' => $sql_schedules,
            'overrides' => $sql_overrides,
            'bookings' => $sql_bookings,
            'exp_vouchers' => $sql_exp_vouchers,
            'vouchers' => $sql_vouchers,
            'dynamic_pricing' => $sql_dynamic_pricing,
            'holds' => $sql_holds,
            'staff_attendance' => $sql_staff_attendance
        ];

        foreach ($tables_sql as $table_name => $sql) {
            $result = dbDelta($sql);
            
            // Check if table was created successfully
            $expected_table = $wpdb->prefix . 'fp_' . $table_name;
            if ($table_name === 'dynamic_pricing') {
                $expected_table = $wpdb->prefix . 'fp_dynamic_pricing_rules';
            } elseif ($table_name === 'holds') {
                $expected_table = $wpdb->prefix . 'fp_exp_holds';
            }
            
            // Verify table exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $expected_table));
            if (!$table_exists) {
                error_log("FP Esperienze: Failed to create table $expected_table");
                return new \WP_Error('fp_table_creation_failed', "Failed to create table: $expected_table");
            }
        }
        
        return true;
    }

    /**
     * Ensure the staff attendance table exists for upgrades and runtime checks.
     *
     * @return bool|\WP_Error True when the table exists or WP_Error on failure.
     */
    public static function maybeCreateStaffAttendanceTable() {
        if (self::$staffAttendanceVerified) {
            return true;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_staff_attendance';
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

        if ($table_exists) {
            self::$staffAttendanceVerified = true;
            return true;
        }

        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        if (!function_exists('dbDelta')) {
            return new \WP_Error('fp_dbdelta_missing', 'WordPress dbDelta function not available');
        }

        $sql = self::getStaffAttendanceTableSql($wpdb->get_charset_collate());
        dbDelta($sql);

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if (!$table_exists) {
            $message = 'FP Esperienze: Failed to create fp_staff_attendance table during upgrade.';
            error_log($message);
            return new \WP_Error('fp_staff_attendance_creation_failed', 'Failed to create staff attendance table.');
        }

        self::$staffAttendanceVerified = true;

        return true;
    }

    /**
     * Generate SQL statement for the staff attendance table.
     */
    private static function getStaffAttendanceTableSql(string $charset_collate): string {
        global $wpdb;

        $table_staff_attendance = $wpdb->prefix . 'fp_staff_attendance';

        return "CREATE TABLE $table_staff_attendance (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            staff_id bigint(20) unsigned NOT NULL,
            action_type varchar(50) NOT NULL,
            `timestamp` datetime NOT NULL,
            location_data text DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY action_type (action_type),
            KEY idx_timestamp (`timestamp`)
        ) $charset_collate;";
    }

    /**
     * Ensure bookings table schema is up to date and perform backfill of new fields.
     *
     * @return bool|\WP_Error
     */
    private static function migrateBookingTable() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_bookings';
        $table_name_escaped = esc_sql($table_name);

        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
        if (!$table_exists) {
            return true;
        }

        $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name_escaped}`");
        if ($columns === null) {
            return new \WP_Error('fp_booking_migration_failed', 'Unable to inspect fp_bookings table.');
        }

        $existing_columns = wp_list_pluck($columns, 'Field');
        $alterations = [];

        if (!in_array('customer_id', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `customer_id` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `order_item_id`";
        }

        if (!in_array('booking_number', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `booking_number` varchar(191) DEFAULT NULL AFTER `customer_id`";
        }

        if (!in_array('participants', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `participants` int(11) NOT NULL DEFAULT 0 AFTER `children`";
        }

        if (!in_array('total_amount', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 AFTER `status`";
        }

        if (!in_array('currency', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `currency` varchar(10) NOT NULL DEFAULT '' AFTER `total_amount`";
        }

        if (!in_array('checked_in_at', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `checked_in_at` datetime DEFAULT NULL AFTER `currency`";
        }

        if (!in_array('checked_in_by', $existing_columns, true)) {
            $alterations[] = "ADD COLUMN `checked_in_by` bigint(20) unsigned DEFAULT NULL AFTER `checked_in_at`";
        }

        if (!empty($alterations)) {
            $alter_sql = "ALTER TABLE `{$table_name_escaped}` " . implode(', ', $alterations);
            $result = $wpdb->query($alter_sql);

            if ($result === false) {
                return new \WP_Error('fp_booking_migration_failed', 'Failed to alter fp_bookings table: ' . $wpdb->last_error);
            }
        }

        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table_name_escaped}`");
        $existing_index_names = [];

        foreach ($existing_indexes as $index) {
            if (isset($index->Key_name)) {
                $existing_index_names[$index->Key_name] = true;
            }
        }

        $index_definitions = [
            'idx_customer_id' => 'ADD INDEX idx_customer_id (customer_id)',
            'idx_booking_number' => 'ADD INDEX idx_booking_number (booking_number)',
            'idx_total_amount' => 'ADD INDEX idx_total_amount (total_amount)',
            'idx_participants' => 'ADD INDEX idx_participants (participants)',
            'idx_checked_in_at' => 'ADD INDEX idx_checked_in_at (checked_in_at)',
            'idx_checked_in_by' => 'ADD INDEX idx_checked_in_by (checked_in_by)',
        ];

        $index_alterations = [];

        foreach ($index_definitions as $index_name => $definition) {
            if (!isset($existing_index_names[$index_name])) {
                $index_alterations[] = $definition;
            }
        }

        if (!empty($index_alterations)) {
            $index_sql = "ALTER TABLE `{$table_name_escaped}` " . implode(', ', $index_alterations);
            $index_result = $wpdb->query($index_sql);

            if ($index_result === false) {
                return new \WP_Error('fp_booking_index_migration_failed', 'Failed to update booking indexes: ' . $wpdb->last_error);
            }
        }

        self::backfillBookingsData();

        return true;
    }

    /**
     * Populate new booking columns for existing installations.
     */
    private static function backfillBookingsData(): void {
        global $wpdb;

        $option_key = 'fp_esperienze_booking_backfill_completed';
        if (get_option($option_key)) {
            return;
        }

        $table_name = $wpdb->prefix . 'fp_bookings';
        $table_name_escaped = esc_sql($table_name);

        if (!function_exists('wc_get_order') && defined('WC_ABSPATH')) {
            include_once WC_ABSPATH . 'includes/wc-order-functions.php';
        }

        if (!function_exists('wc_get_order')) {
            return;
        }

        $batch_size = 200;
        $offset = 0;
        $orders_cache = [];

        do {
            $bookings = $wpdb->get_results($wpdb->prepare(
                "SELECT id, order_id, order_item_id, adults, children, participants, customer_id, total_amount, currency, booking_number
                 FROM `{$table_name_escaped}`
                 ORDER BY id ASC
                 LIMIT %d OFFSET %d",
                $batch_size,
                $offset
            ));

            if (empty($bookings)) {
                break;
            }

            foreach ($bookings as $booking) {
                $updates = [];
                $formats = [];

                $calculated_participants = max(0, (int) $booking->adults) + max(0, (int) $booking->children);
                if ((int) $booking->participants !== $calculated_participants) {
                    $updates['participants'] = $calculated_participants;
                    $formats[] = '%d';
                }

                $order = null;
                $order_id = (int) $booking->order_id;

                if ($order_id > 0) {
                    if (!array_key_exists($order_id, $orders_cache)) {
                        $orders_cache[$order_id] = wc_get_order($order_id) ?: false;
                    }

                    $order = $orders_cache[$order_id];
                }

                if ((int) $booking->customer_id <= 0 && $order) {
                    $customer_id = $order->get_customer_id() ?: $order->get_user_id() ?: 0;
                    if ($customer_id > 0) {
                        $updates['customer_id'] = (int) $customer_id;
                        $formats[] = '%d';
                    }
                }

                if (empty($booking->currency)) {
                    if ($order) {
                        $updates['currency'] = (string) $order->get_currency();
                    } elseif (function_exists('get_woocommerce_currency')) {
                        $updates['currency'] = (string) get_woocommerce_currency();
                    }

                    if (isset($updates['currency'])) {
                        $formats[] = '%s';
                    }
                }

                if (($booking->total_amount === null || (float) $booking->total_amount <= 0) && $order) {
                    $order_item = $order->get_item((int) $booking->order_item_id);
                    $total_amount = 0.0;

                    if ($order_item instanceof \WC_Order_Item_Product) {
                        $total_amount = (float) $order_item->get_total() + (float) $order_item->get_total_tax();

                        if ($total_amount <= 0) {
                            $total_amount = (float) $order_item->get_subtotal() + (float) $order_item->get_subtotal_tax();
                        }
                    } else {
                        $total_amount = (float) $order->get_total();
                    }

                    $updates['total_amount'] = round($total_amount, 2);
                    $formats[] = '%f';
                }

                if (empty($booking->booking_number)) {
                    $updates['booking_number'] = BookingManager::generateBookingNumber();
                    $formats[] = '%s';
                }

                if (!empty($updates)) {
                    $wpdb->update(
                        $table_name,
                        $updates,
                        ['id' => (int) $booking->id],
                        $formats,
                        ['%d']
                    );
                }
            }

            $offset += $batch_size;
        } while (count($bookings) === $batch_size);

        update_option($option_key, true);
    }

    /**
     * Migrate schedule override fields to allow NULL values for inheritance
     * Only executed when FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION is true
     */
    private static function migrateScheduleNullFields(): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $table_name_escaped = esc_sql($table_name);
        
        // Check if migration has already been applied
        $migration_applied = get_option('fp_esperienze_schedule_null_migration_applied', false);
        if ($migration_applied) {
            return;
        }
        
        // Check current column definitions to see if they're already nullable
        $duration_column = $wpdb->get_row(
            "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE 'duration_min'"
        );
        
        // If duration_min column is already nullable, migration was likely applied
        if ($duration_column && strpos($duration_column->Null, 'YES') !== false) {
            update_option('fp_esperienze_schedule_null_migration_applied', true);
            return;
        }
        
        try {
            // Step 1: Alter table structure to allow NULL values
            $wpdb->query("
                ALTER TABLE `{$table_name_escaped}`
                MODIFY `duration_min` int(11) NULL,
                MODIFY `capacity` int(11) NULL,
                MODIFY `lang` varchar(10) NULL,
                MODIFY `price_adult` decimal(10,2) NULL,
                MODIFY `price_child` decimal(10,2) NULL
            ");
            
            // Step 2: Set values to NULL where they match product defaults to enable inheritance
            // Get all products with schedules
            $products_with_schedules = $wpdb->get_results("
                SELECT DISTINCT s.product_id
                FROM `{$table_name_escaped}` s
                WHERE s.is_active = 1
            ");
            
            foreach ($products_with_schedules as $product) {
                # Product defaults removed; no migration needed.
            }
            
            // Mark migration as applied
            update_option('fp_esperienze_schedule_null_migration_applied', true);
            
            // Log successful migration
            error_log('FP Esperienze: Schedule NULL migration completed successfully.');
            
        } catch (Exception $e) {
            // Log error but don't fail activation
            error_log('FP Esperienze: Schedule NULL migration failed: ' . $e->getMessage());
        }
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
            'fp_esperienze_gift_pdf_brand_color' => '#3498db', // Default to a more neutral blue color
            'fp_esperienze_gift_email_sender_name' => get_bloginfo('name'),
            'fp_esperienze_gift_email_sender_email' => get_option('admin_email'),
            'fp_esperienze_gift_secret_hmac' => bin2hex(random_bytes(32)), // 256-bit cryptographically secure key
            'fp_esperienze_gift_terms' => __('This voucher is valid for one experience booking. Please present the QR code when redeeming.', 'fp-esperienze'),
            // Optimistic locking / holds settings
            'fp_esperienze_enable_holds' => 1,
            'fp_esperienze_hold_duration_minutes' => 15,
        ];

        foreach ($default_options as $option_name => $option_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $option_value);
            }
        }
    }

    /**
     * Add capabilities to user roles
     */
    private static function addCapabilities(): void {
        $capability_manager = new CapabilityManager();
        $capability_manager->addCapabilitiesToRoles();
    }

    /**
     * Migrate database for event support
     * Adds new fields to support fixed-date events alongside recurring experiences
     * 
     * @return bool|\WP_Error True on success or WP_Error on failure
     */
    private static function migrateForEventSupport() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_schedules';
        $table_name_escaped = esc_sql($table_name);

        // Check if migration has already been applied
        $migration_applied = get_option('fp_esperienze_event_support_migration_applied', false);
        if ($migration_applied) {
            return true;
        }
        
        try {
            // Check if the new columns already exist
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name_escaped}`");
            $existing_columns = wp_list_pluck($columns, 'Field');
            
            $alterations = [];
            
            // Add schedule_type column if it doesn't exist
            if (!in_array('schedule_type', $existing_columns)) {
                $alterations[] = "ADD COLUMN `schedule_type` enum('recurring', 'fixed') NOT NULL DEFAULT 'recurring' COMMENT 'Type of schedule: recurring weekly or fixed date'";
            }
            
            // Add event_date column if it doesn't exist  
            if (!in_array('event_date', $existing_columns)) {
                $alterations[] = "ADD COLUMN `event_date` date DEFAULT NULL COMMENT 'Specific date for fixed-date events'";
            }
            
            // Make day_of_week nullable for events (only required for recurring)
            $day_of_week_column = $wpdb->get_row(
                "SHOW COLUMNS FROM `{$table_name_escaped}` LIKE 'day_of_week'"
            );
            if ($day_of_week_column && strpos($day_of_week_column->Null, 'NO') !== false) {
                $alterations[] = "MODIFY COLUMN `day_of_week` tinyint(1) DEFAULT NULL COMMENT '0=Sunday, 1=Monday, etc. Used for recurring schedules'";
            }
            
            if (!empty($alterations)) {
                $alter_sql = "ALTER TABLE `{$table_name_escaped}` " . implode(', ', $alterations);
                $result = $wpdb->query($alter_sql);
                
                if ($result === false) {
                    return new \WP_Error('fp_event_migration_failed', 'Failed to alter schedules table for event support: ' . $wpdb->last_error);
                }
                
                // Add indexes for new columns
                $index_sql = "ALTER TABLE `{$table_name_escaped}`
                             ADD INDEX idx_event_date (event_date),
                             ADD INDEX idx_schedule_type (schedule_type)";
                $wpdb->query($index_sql);
            }
            
            // Mark migration as applied
            update_option('fp_esperienze_event_support_migration_applied', true);
            
            // Log successful migration
            error_log('FP Esperienze: Event support migration completed successfully.');
            
            return true;
            
        } catch (Exception $e) {
            error_log('FP Esperienze: Event support migration failed: ' . $e->getMessage());
            return new \WP_Error('fp_event_migration_error', 'Event support migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Add performance indexes for better query performance
     */
    public static function addPerformanceIndexes(): void {
        global $wpdb;

        // Add composite indexes for better performance on common queries
        $indexes = [
            // Bookings table performance indexes
            $wpdb->prefix . 'fp_bookings' => [
                'product_date_time' => 'ADD INDEX idx_product_date_time (product_id, booking_date, booking_time)',
                'date_status' => 'ADD INDEX idx_date_status (booking_date, status)',
                'product_status' => 'ADD INDEX idx_product_status (product_id, status)',
                'status_active' => 'ADD INDEX idx_status_active (status)',
                'customer_id' => 'ADD INDEX idx_customer_id (customer_id)',
                'booking_number' => 'ADD INDEX idx_booking_number (booking_number)',
                'total_amount' => 'ADD INDEX idx_total_amount (total_amount)',
                'participants' => 'ADD INDEX idx_participants (participants)',
                'checked_in_at' => 'ADD INDEX idx_checked_in_at (checked_in_at)',
                'checked_in_by' => 'ADD INDEX idx_checked_in_by (checked_in_by)',
                'order_item_unique' => 'ADD UNIQUE KEY order_item_unique (order_id, order_item_id)',
            ],
            
            // Schedules table performance indexes  
            $wpdb->prefix . 'fp_schedules' => [
                'product_day_active' => 'ADD INDEX idx_product_day_active (product_id, day_of_week, is_active)',
                'day_time' => 'ADD INDEX idx_day_time (day_of_week, start_time)',
            ],
            
            // Overrides table performance indexes
            $wpdb->prefix . 'fp_overrides' => [
                'product_date_closed' => 'ADD INDEX idx_product_date_closed (product_id, date, is_closed)',
                'date_closed' => 'ADD INDEX idx_date_closed (date, is_closed)',
            ],
            
            // Holds table performance indexes
            $wpdb->prefix . 'fp_exp_holds' => [
                'product_slot_expires' => 'ADD INDEX idx_product_slot_expires (product_id, slot_start, expires_at)',
                'session_expires' => 'ADD INDEX idx_session_expires (session_id, expires_at)',
            ],
        ];

        foreach ($indexes as $table => $table_indexes) {
            $table_escaped = esc_sql($table);
            foreach ($table_indexes as $index_name => $index_sql) {
                $key_name = ($index_name === 'order_item_unique') ? 'order_item_unique' : 'idx_' . $index_name;

                // Check if index already exists
                $existing_index = $wpdb->get_var($wpdb->prepare(
                    "SHOW INDEX FROM `{$table_escaped}` WHERE Key_name = %s",
                    $key_name
                ));

                if (!$existing_index) {
                    $full_sql = "ALTER TABLE `{$table_escaped}` {$index_sql}";
                    $wpdb->query($full_sql);

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("FP Performance: Added index {$key_name} to table {$table}");
                    }
                }
            }
        }
    }
}