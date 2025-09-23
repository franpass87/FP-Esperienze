<?php
/**
 * Translation Logger.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Lightweight logger for translation-related events.
 */
class TranslationLogger {
    /**
     * Prefix added to each log entry for quick identification.
     */
    private const PREFIX = '[FP Esperienze Translation]';

    /**
     * Log a message when debug logging is enabled.
     *
     * @param string $message Message to log.
     */
    public static function log(string $message): void {
        $message = trim($message);

        if ('' === $message) {
            return;
        }

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }

        error_log(sprintf('%s %s', self::PREFIX, $message)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}
