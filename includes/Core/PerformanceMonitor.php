<?php
/**
 * Enhanced Performance Monitor
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Performance monitoring and optimization
 */
class PerformanceMonitor {
    
    private static $start_time;
    private static $queries_start;
    private static $memory_start;
    
    /**
     * Start performance monitoring
     */
    public static function start(): void {
        self::$start_time = microtime(true);
        self::$queries_start = get_num_queries();
        self::$memory_start = memory_get_usage(true);
    }
    
    /**
     * End performance monitoring and log results
     *
     * @param string $operation Operation name for logging
     */
    public static function end(string $operation = 'operation'): void {
        if (self::$start_time === null) {
            return;
        }
        
        $end_time = microtime(true);
        $execution_time = ($end_time - self::$start_time) * 1000; // ms
        $queries_count = get_num_queries() - self::$queries_start;
        $memory_used = memory_get_usage(true) - self::$memory_start;
        
        // Log performance data
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FP Esperienze Performance [%s]: %0.2fms, %d queries, %s memory',
                $operation,
                $execution_time,
                $queries_count,
                size_format($memory_used)
            ));
        }
        
        // Store in option for admin monitoring (if execution time > 1s or queries > 10)
        if ($execution_time > 1000 || $queries_count > 10) {
            $slow_operations = get_option('fp_esperienze_slow_operations', []);
            $slow_operations[] = [
                'operation' => $operation,
                'time' => $execution_time,
                'queries' => $queries_count,
                'memory' => $memory_used,
                'timestamp' => current_time('timestamp')
            ];
            
            // Keep only last 50 entries
            $slow_operations = array_slice($slow_operations, -50);
            update_option('fp_esperienze_slow_operations', $slow_operations);
        }
        
        // Reset
        self::$start_time = null;
        self::$queries_start = null;
        self::$memory_start = null;
    }
    
    /**
     * Get performance statistics
     *
     * @return array Performance data
     */
    public static function getStats(): array {
        $slow_operations = get_option('fp_esperienze_slow_operations', []);
        
        return [
            'total_slow_operations' => count($slow_operations),
            'avg_execution_time' => $slow_operations ? array_sum(array_column($slow_operations, 'time')) / count($slow_operations) : 0,
            'max_execution_time' => $slow_operations ? max(array_column($slow_operations, 'time')) : 0,
            'recent_operations' => array_slice($slow_operations, -10)
        ];
    }
    
    /**
     * Clear performance logs
     */
    public static function clearLogs(): void {
        delete_option('fp_esperienze_slow_operations');
    }
}