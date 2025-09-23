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
     * Filter name used to customize translation logging behaviour.
     */
    private const HANDLER_FILTER = 'fp_es_translation_logger_handler';

    /**
     * Log a message when debug logging is enabled.
     *
     * A custom handler can be supplied using the `fp_es_translation_logger_handler` filter. When
     * the filter returns a callable it receives the message and context array and bypasses the
     * default `error_log()` output.
     *
     * @param string $message Message to log.
     * @param array  $context Additional context values that help debugging.
     */
    public static function log(string $message, array $context = []): void {
        $message = trim($message);

        if ('' === $message) {
            return;
        }

        $handler = self::getCustomHandler($message, $context);

        if (is_callable($handler)) {
            try {
                $handler($message, $context);
                return;
            } catch (\Throwable $exception) {
                self::writeToErrorLog(self::formatMessage('Logger handler error: ' . $exception->getMessage()));
            }
        }

        self::writeToErrorLog(self::formatMessage($message, $context));
    }

    /**
     * Resolve a custom handler from WordPress filters when available.
     *
     * @param string $message Log message.
     * @param array  $context Context data for the log.
     *
     * @return callable|null
     */
    private static function getCustomHandler(string $message, array $context): ?callable {
        if (!function_exists('apply_filters')) {
            return null;
        }

        $handler = apply_filters(self::HANDLER_FILTER, null, $message, $context);

        return is_callable($handler) ? $handler : null;
    }

    /**
     * Determine whether we can write to the WordPress debug log.
     *
     * @return bool
     */
    private static function canWriteToDebugLog(): bool {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return false;
        }

        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }

        return true;
    }

    /**
     * Format a message with the translation prefix and optional context payload.
     *
     * @param string $message Log message.
     * @param array  $context Context data for the log entry.
     *
     * @return string
     */
    private static function formatMessage(string $message, array $context = []): string {
        $log_message = sprintf('%s %s', self::PREFIX, $message);

        if (!empty($context)) {
            $context_string = self::encodeContext($context);

            if ('' !== $context_string) {
                $log_message .= ' | Context: ' . $context_string;
            }
        }

        return $log_message;
    }

    /**
     * Encode the provided context using the best available JSON encoder.
     *
     * @param array $context Context data for the log entry.
     *
     * @return string
     */
    private static function encodeContext(array $context): string {
        if (function_exists('wp_json_encode')) {
            $encoded = wp_json_encode($context);
        } else {
            $encoded = json_encode($context);
        }

        if (!is_string($encoded) || '' === $encoded || 'null' === $encoded) {
            return '';
        }

        return $encoded;
    }

    /**
     * Write the prepared message to the debug log when enabled.
     *
     * @param string $log_message Final message to send to the debug log.
     */
    private static function writeToErrorLog(string $log_message): void {
        if ('' === $log_message) {
            return;
        }

        if (!self::canWriteToDebugLog()) {
            return;
        }

        error_log($log_message); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}
