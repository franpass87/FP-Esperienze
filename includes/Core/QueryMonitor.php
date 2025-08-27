<?php
/**
 * Query Performance Monitor
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Query Monitor for logging slow queries and performance metrics
 */
class QueryMonitor {
    
    /**
     * Slow query threshold in milliseconds
     */
    const SLOW_QUERY_THRESHOLD = 100;
    
    /**
     * Query statistics
     */
    private static $query_stats = [
        'total_queries' => 0,
        'slow_queries' => 0,
        'total_time' => 0,
    ];
    
    /**
     * Initialize query monitoring
     */
    public static function init(): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        // Hook into WordPress query logging
        add_filter('query', [__CLASS__, 'logQuery'], 10, 1);
        add_action('wp_footer', [__CLASS__, 'logStatistics'], 999);
        add_action('admin_footer', [__CLASS__, 'logStatistics'], 999);
    }
    
    /**
     * Log individual query performance
     *
     * @param string $query SQL query
     * @return string
     */
    public static function logQuery(string $query): string {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return $query;
        }
        
        $start_time = microtime(true);
        
        // We can't actually time the query here since we're in a filter
        // But we can log FP-specific queries for analysis
        if (self::isFpEsperienzeQuery($query)) {
            // Add a hook to capture timing after query execution
            add_action('shutdown', function() use ($query, $start_time) {
                self::analyzeQuery($query, $start_time);
            }, 1);
        }
        
        return $query;
    }
    
    /**
     * Check if query is related to FP Esperienze
     *
     * @param string $query SQL query
     * @return bool
     */
    private static function isFpEsperienzeQuery(string $query): bool {
        return (
            strpos($query, 'fp_bookings') !== false ||
            strpos($query, 'fp_schedules') !== false ||
            strpos($query, 'fp_overrides') !== false ||
            strpos($query, 'fp_meeting_points') !== false ||
            strpos($query, 'fp_extras') !== false ||
            strpos($query, 'fp_exp_holds') !== false ||
            strpos($query, 'fp_exp_vouchers') !== false ||
            strpos($query, 'fp_dynamic_pricing') !== false
        );
    }
    
    /**
     * Analyze query performance
     *
     * @param string $query SQL query
     * @param float $start_time Start time
     */
    private static function analyzeQuery(string $query, float $start_time): void {
        global $wpdb;
        
        $execution_time = (microtime(true) - $start_time) * 1000; // Convert to milliseconds
        
        self::$query_stats['total_queries']++;
        self::$query_stats['total_time'] += $execution_time;
        
        // Log slow queries
        if ($execution_time > self::SLOW_QUERY_THRESHOLD) {
            self::$query_stats['slow_queries']++;
            
            $log_message = sprintf(
                "[FP Esperienze] Slow Query (%.2fms): %s",
                $execution_time,
                self::sanitizeQueryForLog($query)
            );
            
            error_log($log_message);
            
            // Also log query plan for analysis
            if ($wpdb->last_query && defined('WP_DEBUG') && WP_DEBUG) {
                $explain = $wpdb->get_results("EXPLAIN " . $wpdb->last_query, ARRAY_A);
                if ($explain) {
                    error_log("[FP Esperienze] Query Plan: " . json_encode($explain));
                }
            }
        }
    }
    
    /**
     * Sanitize query for logging (remove sensitive data)
     *
     * @param string $query SQL query
     * @return string
     */
    private static function sanitizeQueryForLog(string $query): string {
        // Remove potential sensitive data
        $query = preg_replace('/\'[^\']*\'/', '\'***\'', $query);
        $query = preg_replace('/"[^"]*"/', '"***"', $query);
        
        // Limit length
        if (strlen($query) > 500) {
            $query = substr($query, 0, 497) . '...';
        }
        
        return $query;
    }
    
    /**
     * Log query statistics
     */
    public static function logStatistics(): void {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG || self::$query_stats['total_queries'] === 0) {
            return;
        }
        
        $avg_time = self::$query_stats['total_time'] / self::$query_stats['total_queries'];
        
        $log_message = sprintf(
            "[FP Esperienze] Query Stats - Total: %d, Slow: %d, Avg Time: %.2fms, Total Time: %.2fms",
            self::$query_stats['total_queries'],
            self::$query_stats['slow_queries'],
            $avg_time,
            self::$query_stats['total_time']
        );
        
        error_log($log_message);
    }
    
    /**
     * Get current query statistics
     *
     * @return array
     */
    public static function getStatistics(): array {
        return self::$query_stats;
    }
    
    /**
     * Reset query statistics
     */
    public static function resetStatistics(): void {
        self::$query_stats = [
            'total_queries' => 0,
            'slow_queries' => 0,
            'total_time' => 0,
        ];
    }
}