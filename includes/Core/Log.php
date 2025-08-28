<?php
/**
 * Logging Utility
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Centralized logging utility with proper timing calculations
 */
class Log {
    
    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function info(string $message, array $context = []): void {
        self::writeLog('INFO', $message, $context);
    }
    
    /**
     * Log a debug message (only when FP_ESP_DEBUG is true)
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    public static function debug(string $message, array $context = []): void {
        if (!defined('FP_ESP_DEBUG') || !FP_ESP_DEBUG) {
            return;
        }
        self::writeLog('DEBUG', $message, $context);
    }
    
    /**
     * Log a performance message for slow operations
     *
     * @param string $operation Operation name
     * @param float $start_time Start time from microtime(true)
     * @param float $threshold Threshold in seconds (default: 2.0)
     */
    public static function performance(string $operation, float $start_time, float $threshold = 2.0): void {
        $execution_time = microtime(true) - $start_time;
        
        if ($execution_time > $threshold) {
            self::writeLog('PERFORMANCE', "SLOW EXECUTION ({$operation}): Request took " . number_format($execution_time, 4) . " seconds");
        }
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array $context Additional context data
     */
    public static function error(string $message, array $context = []): void {
        self::writeLog('ERROR', $message, $context);
    }
    
    /**
     * Write log entry
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private static function writeLog(string $level, string $message, array $context = []): void {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_message = "[FP Esperienze] [{$level}] {$message}";
        
        if (!empty($context)) {
            $log_message .= ' | Context: ' . wp_json_encode($context);
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_message);
        }
    }
}