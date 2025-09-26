<?php
/**
 * Development runtime logger for capturing notices and warnings.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use Throwable;

defined('ABSPATH') || exit;

/**
 * Captures runtime notices, warnings, and errors for debugging purposes.
 */
class RuntimeLogger {

    /**
     * Whether the logger has been bootstrapped.
     *
     * @var bool
     */
    private static $initialized = false;

    /**
     * Stored log entries waiting to be flushed.
     *
     * @var array<int, array<string, mixed>>
     */
    private static $entries = array();

    /**
     * Previously registered error handler.
     *
     * @var callable|null
     */
    private static $previous_error_handler = null;

    /**
     * Previously registered exception handler.
     *
     * @var callable|null
     */
    private static $previous_exception_handler = null;

    /**
     * Optional override for the log file path.
     *
     * @var string|null
     */
    private static $log_path_override = null;

    /**
     * Resolved log file path.
     *
     * @var string|null
     */
    private static $log_file_path = null;

    /**
     * Initialize the runtime logger.
     */
    public static function init(): void {
        if (self::$initialized || !self::shouldEnable()) {
            return;
        }

        self::$log_file_path = self::resolveLogFilePath();

        self::$previous_error_handler = set_error_handler(array(__CLASS__, 'handleError'));

        if (function_exists('set_exception_handler')) {
            self::$previous_exception_handler = set_exception_handler(array(__CLASS__, 'handleException'));
        }

        register_shutdown_function(array(__CLASS__, 'handleShutdown'));

        if (function_exists('add_action')) {
            add_action('admin_footer', array(__CLASS__, 'renderFooterLog'), 1000);
            add_action('wp_footer', array(__CLASS__, 'renderFooterLog'), 1000);
        }

        self::$initialized = true;
    }

    /**
     * Allow overriding the log file path (useful for testing or tooling).
     *
     * @param string|null $path Absolute path to the desired log file.
     */
    public static function setLogFilePath(?string $path): void {
        self::$log_path_override = $path;
        if (null !== $path) {
            self::$log_file_path = $path;
        }
    }

    /**
     * Determine whether the logger should be active.
     *
     * @return bool
     */
    private static function shouldEnable(): bool {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return false;
        }

        if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
            return false;
        }

        return true;
    }

    /**
     * Error handler callback to capture notices and warnings.
     *
     * @param int    $severity Error severity.
     * @param string $message  Error message.
     * @param string $file     File path.
     * @param int    $line     Line number.
     *
     * @return bool
     */
    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool {
        $handled = false;

        if ((error_reporting() & $severity) && self::isPluginFile($file)) {
            $handled = true;
            self::logEntry(self::severityToString($severity), $message, $file, $line);
        }

        if (self::$previous_error_handler) {
            return (bool) call_user_func(self::$previous_error_handler, $severity, $message, $file, $line);
        }

        return !$handled;
    }

    /**
     * Exception handler callback.
     *
     * @param Throwable $exception Captured exception.
     */
    public static function handleException($exception): void { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterName
        if ($exception instanceof Throwable && self::isPluginFile($exception->getFile())) {
            self::logEntry('exception', $exception->getMessage(), $exception->getFile(), (int) $exception->getLine());
        }

        if (self::$previous_exception_handler) {
            call_user_func(self::$previous_exception_handler, $exception);
        }
    }

    /**
     * Shutdown handler to capture fatal errors.
     */
    public static function handleShutdown(): void {
        $error = error_get_last();

        if (is_array($error)) {
            $type = isset($error['type']) ? (int) $error['type'] : 0;
            $file = isset($error['file']) ? (string) $error['file'] : '';

            if (self::isFatalError($type) && self::isPluginFile($file)) {
                $message = isset($error['message']) ? (string) $error['message'] : '';
                $line    = isset($error['line']) ? (int) $error['line'] : 0;
                self::logEntry('fatal', $message, $file, $line);
            }
        }

        self::writeToLogFile();
    }

    /**
     * Manually record a log entry (useful for integration tests).
     *
     * @param string $level   Severity level.
     * @param string $message Log message.
     * @param string $file    File path.
     * @param int    $line    Line number.
     * @param array  $context Additional context.
     */
    public static function logManual(string $level, string $message, string $file = '', int $line = 0, array $context = array()): void {
        self::logEntry($level, $message, $file, $line, $context);
    }

    /**
     * Flush pending log entries to disk immediately.
     */
    public static function flush(): void {
        self::writeToLogFile();
    }

    /**
     * Determine whether an error type is fatal.
     *
     * @param int $type Error type.
     *
     * @return bool
     */
    private static function isFatalError(int $type): bool {
        return in_array($type, array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true);
    }

    /**
     * Convert PHP severity constants to readable strings.
     *
     * @param int $severity Severity constant.
     *
     * @return string
     */
    private static function severityToString(int $severity): string {
        switch ($severity) {
            case E_ERROR:
            case E_USER_ERROR:
                return 'error';
            case E_WARNING:
            case E_USER_WARNING:
                return 'warning';
            case E_PARSE:
                return 'parse';
            case E_NOTICE:
            case E_USER_NOTICE:
                return 'notice';
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                return 'core_error';
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
                return 'core_warning';
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return 'deprecated';
            default:
                return 'info';
        }
    }

    /**
     * Log a structured entry in memory.
     *
     * @param string $level   Log level.
     * @param string $message Message contents.
     * @param string $file    File path.
     * @param int    $line    Line number.
     * @param array  $context Additional context.
     */
    private static function logEntry(string $level, string $message, string $file, int $line, array $context = array()): void {
        $entry = array(
            'timestamp' => self::currentTimestamp(),
            'level'     => $level,
            'message'   => $message,
            'file'      => self::shortenPath($file),
            'line'      => $line,
            'context'   => $context,
        );

        self::$entries[] = $entry;
    }

    /**
     * Render a lightweight overlay in admin/frontend footers for privileged users.
     */
    public static function renderFooterLog(): void {
        if (empty(self::$entries) || !self::shouldDisplayOverlay()) {
            return;
        }

        $entries = array_slice(self::$entries, -5);

        echo '<div class="fp-esperienze-runtime-log" style="position:fixed;bottom:24px;right:24px;z-index:99999;background:rgba(17,17,17,0.95);color:#fff;padding:16px 20px;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,0.2);max-width:420px;font:400 13px/1.5 -apple-system,BlinkMacSystemFont,\"Segoe UI\",sans-serif;">';
        echo '<strong style="display:block;font-size:14px;margin-bottom:8px;">FP Esperienze – Runtime alerts</strong>';
        echo '<ol style="margin:0;padding-left:18px;max-height:200px;overflow:auto;">';

        foreach ($entries as $entry) {
            $message = self::escapeHtml(self::truncate($entry['message']));
            $meta    = self::escapeHtml(sprintf('%s · %s:%d', $entry['level'], $entry['file'], $entry['line']));
            echo '<li style="margin-bottom:6px;">';
            echo '<div style="font-weight:600;">' . $message . '</div>';
            echo '<div style="opacity:0.75;font-size:12px;">' . $meta . '</div>';
            echo '</li>';
        }

        echo '</ol>';
        echo '<p style="margin:8px 0 0;font-size:12px;opacity:0.7;">Logs also saved to ' . self::escapeHtml(self::$log_file_path ?? '') . '</p>';
        echo '</div>';
    }

    /**
     * Determine whether the overlay should be shown to the current visitor.
     *
     * @return bool
     */
    private static function shouldDisplayOverlay(): bool {
        if (!self::shouldEnable()) {
            return false;
        }

        if (function_exists('is_user_logged_in') && function_exists('current_user_can')) {
            if (!is_user_logged_in() || !current_user_can('manage_options')) {
                return false;
            }
        }

        if (function_exists('is_admin') && is_admin()) {
            return true;
        }

        return true;
    }

    /**
     * Resolve the log file path, preferring the uploads directory when available.
     *
     * @return string
     */
    private static function resolveLogFilePath(): string {
        if (null !== self::$log_path_override) {
            return self::$log_path_override;
        }

        $directory = defined('WP_CONTENT_DIR') ? rtrim(WP_CONTENT_DIR, '/\\') . '/fp-esperienze-logs' : sys_get_temp_dir() . '/fp-esperienze-logs';

        if (function_exists('wp_upload_dir')) {
            $uploads = wp_upload_dir(null, false);
            if (is_array($uploads) && !empty($uploads['basedir'])) {
                $directory = rtrim($uploads['basedir'], '/\\') . '/fp-esperienze-logs';
            }
        }

        return rtrim($directory, '/\\') . '/runtime.log';
    }

    /**
     * Ensure the destination directory exists.
     *
     * @param string $path Target file path.
     *
     * @return bool
     */
    private static function ensureLogDirectory(string $path): bool {
        $directory = dirname($path);

        if (is_dir($directory)) {
            return is_writable($directory);
        }

        if (function_exists('wp_mkdir_p')) {
            return wp_mkdir_p($directory);
        }

        return mkdir($directory, 0775, true);
    }

    /**
     * Persist pending entries to disk.
     */
    private static function writeToLogFile(): void {
        if (empty(self::$entries)) {
            return;
        }

        $path = self::$log_file_path ?? self::resolveLogFilePath();

        if (!self::ensureLogDirectory($path)) {
            return;
        }

        $payload = array();

        foreach (self::$entries as $entry) {
            $payload[] = self::encodeEntry($entry);
        }

        file_put_contents($path, implode(PHP_EOL, $payload) . PHP_EOL, FILE_APPEND | LOCK_EX);

        self::$entries = array();
    }

    /**
     * Encode an entry as a JSON string.
     *
     * @param array<string, mixed> $entry Log entry.
     *
     * @return string
     */
    private static function encodeEntry(array $entry): string {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($entry);
        }

        return json_encode($entry, JSON_UNESCAPED_SLASHES); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.json_encode_json_encode
    }

    /**
     * Determine whether a file belongs to this plugin.
     *
     * @param string $file File path.
     *
     * @return bool
     */
    private static function isPluginFile(string $file): bool {
        if ('' === $file) {
            return false;
        }

        $normalized = str_replace(chr(92), '/', $file);
        $plugin_dir = str_replace(chr(92), '/', FP_ESPERIENZE_PLUGIN_DIR);

        return strpos($normalized, $plugin_dir) === 0;
    }

    /**
     * Shorten an absolute path for human-readable logs.
     *
     * @param string $path Absolute path.
     *
     * @return string
     */
    private static function shortenPath(string $path): string {
        $plugin_dir = str_replace(chr(92), '/', FP_ESPERIENZE_PLUGIN_DIR);
        $normalized = str_replace(chr(92), '/', $path);

        if (strpos($normalized, $plugin_dir) === 0) {
            return ltrim(substr($normalized, strlen($plugin_dir)), '/');
        }

        return $normalized;
    }

    /**
     * Generate a timestamp string.
     *
     * @return string
     */
    private static function currentTimestamp(): string {
        if (function_exists('current_time')) {
            return (string) current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s');
    }

    /**
     * Escape HTML output without requiring WordPress helpers.
     *
     * @param string $value Raw string.
     *
     * @return string
     */
    private static function escapeHtml(string $value): string {
        if (function_exists('esc_html')) {
            return esc_html($value);
        }

        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Truncate a string for overlay display.
     *
     * @param string $value Raw string.
     *
     * @return string
     */
    private static function truncate(string $value): string {
        if (strlen($value) <= 160) {
            return $value;
        }

        return substr($value, 0, 157) . '…';
    }
}
