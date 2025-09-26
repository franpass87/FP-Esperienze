<?php
/**
 * Upgrade Manager
 *
 * Handles version comparisons and executes database/file migrations when the
 * plugin version increases.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use Throwable;

defined('ABSPATH') || exit;

/**
 * Coordinates upgrade routines across plugin versions.
 */
class UpgradeManager {
    private const OPTION_VERSION        = 'fp_esperienze_version';
    private const OPTION_UPGRADE_ERROR  = 'fp_esperienze_upgrade_error';
    private const OPTION_FLUSH_REWRITE  = 'fp_esperienze_flush_rewrite';

    /**
     * Whether the upgrade check has already run during this request.
     */
    private static bool $checked = false;

    /**
     * Register hooks used to perform upgrade checks.
     */
    public static function init(): void {
        add_action('init', [self::class, 'maybeRunUpgrades'], 5);
        add_action('init', [self::class, 'maybeFlushRewrite'], 15);
        add_action('admin_notices', [self::class, 'renderAdminNotice']);
    }

    /**
     * Run pending migrations when a new plugin version is detected.
     */
    public static function maybeRunUpgrades(): void {
        if (self::$checked) {
            return;
        }

        self::$checked = true;

        $stored_version = get_option(self::OPTION_VERSION);
        if ($stored_version === false) {
            update_option(self::OPTION_VERSION, FP_ESPERIENZE_VERSION);
            delete_option(self::OPTION_UPGRADE_ERROR);

            return;
        }

        if (version_compare($stored_version, FP_ESPERIENZE_VERSION, '>=')) {
            delete_option(self::OPTION_UPGRADE_ERROR);

            return;
        }

        try {
            $result = self::runMigrations($stored_version);
        } catch (Throwable $exception) {
            $result = new \WP_Error('fp_upgrade_exception', $exception->getMessage());
            error_log('FP Esperienze: Upgrade exception - ' . $exception->getMessage());
        }

        if (is_wp_error($result)) {
            update_option(self::OPTION_UPGRADE_ERROR, $result->get_error_message());

            return;
        }

        delete_option(self::OPTION_UPGRADE_ERROR);
        update_option(self::OPTION_VERSION, FP_ESPERIENZE_VERSION);
        update_option(self::OPTION_FLUSH_REWRITE, 1, false);
    }

    /**
     * Execute all migrations greater than the supplied version.
     *
     * @param string $from_version Version currently stored in the database.
     *
     * @return true|\WP_Error
     */
    private static function runMigrations(string $from_version) {
        $migrations = [
            '1.1.0' => [self::class, 'upgradeTo110'],
        ];

        foreach ($migrations as $target_version => $callback) {
            if (version_compare($from_version, $target_version, '<')) {
                $result = call_user_func($callback, $from_version);
                if (is_wp_error($result)) {
                    return $result;
                }
            }
        }

        return true;
    }

    /**
     * Upgrade routines for version 1.1.0.
     *
     * @param string $from_version Previously installed version.
     *
     * @return true|\WP_Error
     */
    private static function upgradeTo110(string $from_version) {
        $operations = [
            [Installer::class, 'ensurePushTokenStorage'],
            [Installer::class, 'maybeCreateStaffAttendanceTable'],
            [Installer::class, 'maybeCreateStaffAssignmentsTable'],
            [Installer::class, 'maybeCreateBookingExtrasTable'],
            [Installer::class, 'migrateBookingTable'],
            [Installer::class, 'migrateForEventSupport'],
            [Installer::class, 'ensurePrivateStorageDirectory'],
        ];

        foreach ($operations as $operation) {
            $result = call_user_func($operation);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        Installer::ensureDefaultOptions();

        $capabilities = new CapabilityManager();
        $capabilities->addCapabilitiesToRoles();

        if (class_exists(PerformanceOptimizer::class)) {
            PerformanceOptimizer::maybeAddOptimizedIndexes();
        }

        if (!wp_next_scheduled(TranslationQueue::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', TranslationQueue::CRON_HOOK);
        }

        return true;
    }

    /**
     * Flush rewrite rules once after a successful upgrade.
     */
    public static function maybeFlushRewrite(): void {
        if (!get_option(self::OPTION_FLUSH_REWRITE)) {
            return;
        }

        flush_rewrite_rules(false);
        delete_option(self::OPTION_FLUSH_REWRITE);
    }

    /**
     * Display upgrade errors to administrators.
     */
    public static function renderAdminNotice(): void {
        if (!is_admin() || !current_user_can('manage_options')) {
            return;
        }

        $message = get_option(self::OPTION_UPGRADE_ERROR, '');
        if (empty($message)) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
}
