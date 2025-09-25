<?php
/**
 * Production Readiness Validator
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Provides lightweight production readiness checks that power the System Status screen.
 */
class ProductionValidator {

    /**
     * Run the production readiness checks and return a structured result.
     */
    public static function validateProductionReadiness(): array {
        $results = [
            'overall_status'  => 'pass',
            'critical_issues' => [],
            'warnings'        => [],
            'checks'          => [],
        ];

        self::checkWooCommerce($results);
        self::checkTemplates($results);
        self::checkDatabaseTables($results);
        self::checkScheduledEvents($results);
        self::checkRestEndpoints($results);

        if (!empty($results['critical_issues'])) {
            $results['overall_status'] = 'fail';
        } elseif (!empty($results['warnings'])) {
            $results['overall_status'] = 'warning';
        }

        return $results;
    }

    /**
     * Ensure WooCommerce is available because the plugin depends on it.
     */
    private static function checkWooCommerce(array &$results): void {
        if (class_exists('WooCommerce')) {
            $results['checks'][] = self::message('WooCommerce detected.');
            return;
        }

        $results['critical_issues'][] = self::message('WooCommerce is required but not active.');
    }

    /**
     * Confirm that bundled template overrides exist.
     */
    private static function checkTemplates(array &$results): void {
        $base_dir = defined('FP_ESPERIENZE_PLUGIN_DIR')
            ? FP_ESPERIENZE_PLUGIN_DIR
            : dirname(dirname(__DIR__)) . '/';

        if (!function_exists('trailingslashit')) {
            require_once ABSPATH . 'wp-includes/formatting.php';
        }

        $base_dir = trailingslashit($base_dir);
        $template_dir = $base_dir . 'templates/';
        $required      = [
            'single-experience.php',
            'voucher-form.php',
        ];

        foreach ($required as $file) {
            $path = $template_dir . $file;
            if (file_exists($path)) {
                $results['checks'][] = self::message('Template "%s" found.', $file);
            } else {
                $results['warnings'][] = self::message('Template "%s" is missing.', $file);
            }
        }
    }

    /**
     * Verify essential database tables are present.
     */
    private static function checkDatabaseTables(array &$results): void {
        global $wpdb;

        if (!isset($wpdb)) {
            $results['warnings'][] = self::message('Unable to verify database tables.');
            return;
        }

        $tables  = [
            'fp_meeting_points',
            'fp_extras',
            'fp_product_extras',
            'fp_schedules',
            'fp_overrides',
            'fp_bookings',
        ];
        $missing = [];

        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists     = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

            if (!$exists) {
                $missing[] = $table_name;
            }
        }

        if (!empty($missing)) {
            $results['critical_issues'][] = self::message(
                'Missing database tables: %s.',
                implode(', ', $missing)
            );
        } else {
            $results['checks'][] = self::message('All required database tables are present.');
        }
    }

    /**
     * Confirm background jobs that keep the plugin healthy are registered.
     */
    private static function checkScheduledEvents(array &$results): void {
        if (!function_exists('wp_next_scheduled')) {
            $results['warnings'][] = self::message('Unable to inspect scheduled events.');
            return;
        }

        $events  = [
            'fp_cleanup_push_tokens' => self::message('Push token cleanup task'),
        ];
        $missing = [];

        foreach ($events as $hook => $label) {
            if (!wp_next_scheduled($hook)) {
                $missing[] = $label;
            }
        }

        if (!empty($missing)) {
            $results['warnings'][] = self::message(
                'The following scheduled events are not registered: %s.',
                implode(', ', $missing)
            );
        } else {
            $results['checks'][] = self::message('Required scheduled events are registered.');
        }
    }

    /**
     * Check that the REST API endpoints are available for integrations.
     */
    private static function checkRestEndpoints(array &$results): void {
        $endpoints = [
            'FP\\Esperienze\\REST\\AvailabilityAPI',
            'FP\\Esperienze\\REST\\BookingsAPI',
            'FP\\Esperienze\\REST\\ICSAPI',
        ];
        $missing   = [];

        foreach ($endpoints as $class) {
            if (class_exists($class)) {
                $results['checks'][] = self::message(
                    'REST endpoint "%s" available.',
                    self::getShortClassName($class)
                );
            } else {
                $missing[] = self::getShortClassName($class);
            }
        }

        if (!empty($missing)) {
            $results['warnings'][] = self::message(
                'Missing REST API classes: %s.',
                implode(', ', $missing)
            );
        }
    }

    /**
     * Extract a friendly class name from a fully qualified class string.
     */
    private static function getShortClassName(string $class): string {
        $position = strrpos($class, '\\');

        if ($position === false) {
            return $class;
        }

        return substr($class, $position + 1);
    }

    /**
     * Translate and format a message safely regardless of localisation availability.
     */
    private static function message(string $text, ...$args): string {
        $translated = function_exists('__') ? __($text, 'fp-esperienze') : $text;

        if (empty($args)) {
            return $translated;
        }

        return vsprintf($translated, $args);
    }
}
