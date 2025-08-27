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
        
        // Enable query saving for performance analysis
        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }
        
        // Hook to analyze queries after page load
        add_action('wp_footer', [__CLASS__, 'analyzeQueries'], 999);
        add_action('admin_footer', [__CLASS__, 'analyzeQueries'], 999);
        add_action('wp_footer', [__CLASS__, 'logStatistics'], 1000);
        add_action('admin_footer', [__CLASS__, 'logStatistics'], 1000);
    }
    
    /**
     * Analyze all executed queries for performance issues
     */
    public static function analyzeQueries(): void {
        global $wpdb;
        
        if (!isset($wpdb->queries) || empty($wpdb->queries)) {
            return;
        }
        
        foreach ($wpdb->queries as $query_data) {
            if (!is_array($query_data) || count($query_data) < 3) {
                continue;
            }
            
            $query = $query_data[0];
            $execution_time = (float) $query_data[1] * 1000; // Convert to milliseconds
            $caller = $query_data[2] ?? 'unknown';
            
            // Only analyze FP Esperienze queries
            if (!self::isFpEsperienzeQuery($query)) {
                continue;
            }
            
            self::recordQueryStats($query, $execution_time);
            
            // Log slow queries
            if ($execution_time > self::SLOW_QUERY_THRESHOLD) {
                self::logSlowQuery($query, $execution_time, $caller);
            }
        }
    }
    
    /**
     * Record query statistics for performance tracking
     *
     * @param string $query SQL query
     * @param float $execution_time Execution time in milliseconds
     */
    private static function recordQueryStats(string $query, float $execution_time): void {
        self::$query_stats['total_queries']++;
        self::$query_stats['total_time'] += $execution_time;
        
        if ($execution_time > self::SLOW_QUERY_THRESHOLD) {
            self::$query_stats['slow_queries']++;
        }
    }
    
    /**
     * Log slow query details
     *
     * @param string $query SQL query
     * @param float $execution_time Execution time in milliseconds  
     * @param string $caller Query caller information
     */
    private static function logSlowQuery(string $query, float $execution_time, string $caller): void {
        if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) {
            return;
        }
        
        $log_message = sprintf(
            "[FP Esperienze] Slow Query (%.2fms): %s | Caller: %s",
            $execution_time,
            self::sanitizeQueryForLog($query),
            $caller
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_message);
        }
        
        // Also log EXPLAIN plan for optimization insights
        global $wpdb;
        if (strpos(strtoupper(trim($query)), 'SELECT') === 0) {
            // Basic safety check: ensure query doesn't contain potential malicious content
            if (!preg_match('/[;\'"\\\\]/', $query)) {
                $explain = $wpdb->get_results("EXPLAIN " . $query, ARRAY_A);
                if ($explain && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[FP Esperienze] Query Plan: " . wp_json_encode($explain));
                }
            }
        }
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log($log_message);
        }
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