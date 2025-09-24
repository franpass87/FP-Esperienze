<?php
/**
 * Translation Logger utility.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use WP_Error;

use function current_time;
use function error_log;
use function function_exists;
use function gmdate;
use function is_bool;
use function is_int;
use function is_string;
use function is_writable;
use function json_encode;
use function trailingslashit;
use function wp_json_encode;
use function wp_mkdir_p;
use function wp_upload_dir;
use function wp_is_writable;
use function is_wp_error;
use function sprintf;

defined('ABSPATH') || exit;

/**
 * Handles structured logging for the translation subsystem.
 */
class TranslationLogger {

    /**
     * Option flag used to toggle the logger.
     */
    private const OPTION_ENABLE = 'fp_lt_enable_log';

    /**
     * Maximum size in bytes for the log file before rotation.
     *
     * @var int
     */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB.

    /**
     * Relative directory inside the uploads folder used to store logs.
     */
    private const LOG_DIRECTORY = 'fp-esperienze/logs';

    /**
     * Log file name.
     */
    private const LOG_FILE = 'translation.log';

    /**
     * Record a log entry.
     *
     * @param string               $message Log message.
     * @param array<string, mixed> $context Additional contextual information.
     *
     * @return bool|WP_Error True on success or WP_Error on failure.
     */
    public static function log(string $message, array $context = []) {
        if (!self::isLoggingEnabled()) {
            return true;
        }

        $log_file = self::prepareLogFile();
        if (is_wp_error($log_file)) {
            self::fallbackErrorLog($message, $context, $log_file->get_error_message());
            return $log_file;
        }

        $formatted = self::formatMessage($message, $context);

        $write_result = self::writeToFile($log_file, $formatted);
        if (is_wp_error($write_result)) {
            self::fallbackErrorLog($message, $context, $write_result->get_error_message());
            return $write_result;
        }

        return true;
    }

    /**
     * Determine whether logging is enabled.
     */
    private static function isLoggingEnabled(): bool {
        $enabled = get_option(self::OPTION_ENABLE, '0');

        if (is_bool($enabled)) {
            return $enabled;
        }

        if (is_int($enabled)) {
            return 1 === $enabled;
        }

        if (is_string($enabled)) {
            $normalized = strtolower(trim($enabled));
            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * Ensure the log file exists and is writable.
     *
     * @return string|WP_Error Absolute path to the log file or error on failure.
     */
    private static function prepareLogFile() {
        $uploads = wp_upload_dir();
        $upload_error = $uploads['error'];
        if (is_string($upload_error) && $upload_error !== '') {
            return new WP_Error('fp_logger_upload_dir_unavailable', $upload_error);
        }

        $directory = trailingslashit($uploads['basedir']) . self::LOG_DIRECTORY;

        if (!wp_mkdir_p($directory)) {
            return new WP_Error('fp_logger_create_directory_failed', sprintf('Unable to create log directory %s', $directory));
        }

        if (function_exists('wp_is_writable')) {
            $writable = wp_is_writable($directory);
        } else {
            $writable = is_writable($directory);
        }

        if (!$writable) {
            return new WP_Error('fp_logger_directory_not_writable', sprintf('Log directory not writable: %s', $directory));
        }

        $file = trailingslashit($directory) . self::LOG_FILE;

        $rotation_result = self::rotateLogIfNeeded($file);
        if (is_wp_error($rotation_result)) {
            return $rotation_result;
        }

        $filesystem = self::getFilesystem();
        if (is_wp_error($filesystem)) {
            return $filesystem;
        }

        if (!$filesystem->exists($file)) {
            $chmod = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : false;

            if (!$filesystem->put_contents($file, '', $chmod)) {
                return new WP_Error('fp_logger_file_create_failed', sprintf('Unable to create log file %s', $file));
            }
        }

        if (function_exists('wp_is_writable')) {
            $file_writable = wp_is_writable($file);
        } else {
            $file_writable = is_writable($file);
        }

        if (!$file_writable) {
            return new WP_Error('fp_logger_file_not_writable', sprintf('Log file not writable: %s', $file));
        }

        return $file;
    }

    /**
     * Rotate the log file when it exceeds the maximum allowed size.
     *
     * @param string $file Log file path.
     *
     * @return bool|WP_Error
     */
    private static function rotateLogIfNeeded(string $file) {
        $filesystem = self::getFilesystem();
        if (is_wp_error($filesystem)) {
            return $filesystem;
        }

        if (!$filesystem->exists($file)) {
            return true;
        }

        $size = $filesystem->size($file);
        if ($size === false || $size < self::MAX_FILE_SIZE) {
            return true;
        }

        $archive = $file . '.' . gmdate('YmdHis');
        if (!$filesystem->move($file, $archive, true)) {
            return new WP_Error('fp_logger_rotate_failed', sprintf('Unable to rotate log file %s', $file));
        }

        return true;
    }

    /**
     * Format the message and context into a single log line.
     *
     * @param string               $message Log message.
     * @param array<string, mixed> $context Context array.
     */
    private static function formatMessage(string $message, array $context = []): string {
        $timestamp = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        $payload   = [
            'timestamp' => $timestamp,
            'message'   => $message,
        ];

        if ($context !== []) {
            $payload['context'] = $context;
        }

        $encoded = wp_json_encode($payload);
        if (!is_string($encoded)) {
            $encoded = json_encode($payload);
        }

        if (!is_string($encoded)) {
            $encoded = '';
        }

        return $encoded . PHP_EOL;
    }

    /**
     * Append the message to the log file.
     *
     * @param string $file     Log file path.
     * @param string $contents Formatted log line.
     *
     * @return bool|WP_Error True on success, WP_Error otherwise.
     */
    private static function writeToFile(string $file, string $contents) {
        $filesystem = self::getFilesystem();
        if (is_wp_error($filesystem)) {
            return $filesystem;
        }

        $existing = '';
        if ($filesystem->exists($file)) {
            $existing = $filesystem->get_contents($file);
            if (!is_string($existing)) {
                return new WP_Error('fp_logger_read_failed', sprintf('Unable to read log file %s', $file));
            }
        }

        $chmod = defined('FS_CHMOD_FILE') ? FS_CHMOD_FILE : false;

        if (!$filesystem->put_contents($file, $existing . $contents, $chmod)) {
            return new WP_Error('fp_logger_write_failed', sprintf('Unable to write to log file %s', $file));
        }

        return true;
    }

    /**
     * Fallback logging using PHP's error_log when the dedicated log fails.
     *
     * @param string               $message Original message.
     * @param array<string, mixed> $context Context array.
     * @param string               $error   Error description.
     */
    private static function fallbackErrorLog(string $message, array $context, string $error): void {
        $payload = [
            'message' => $message,
            'error'   => $error,
        ];

        if ($context !== []) {
            $payload['context'] = $context;
        }

        $encoded = wp_json_encode($payload);
        if (!is_string($encoded)) {
            $encoded = json_encode($payload);
        }

        if (!is_string($encoded)) {
            $encoded = '';
        }

        error_log('FP Esperienze Translation Logger: ' . $encoded);
    }

    /**
     * Retrieve an initialized WP_Filesystem instance.
     *
     * @return \WP_Filesystem_Base|WP_Error
     */
    private static function getFilesystem() {
        global $wp_filesystem;

        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        if (!WP_Filesystem()) {
            return new WP_Error('fp_logger_filesystem_unavailable', 'Unable to initialize WP_Filesystem for logging.');
        }

        return $wp_filesystem;
    }
}
