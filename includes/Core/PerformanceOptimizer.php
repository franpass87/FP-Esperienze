<?php
/**
 * Performance Optimization Class
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Performance optimizer class for database and query optimization
 */
class PerformanceOptimizer {
    
    /**
     * Query cache storage
     */
    private static $query_cache = [];
    
    /**
     * Performance metrics
     */
    private static $metrics = [];
    
    /**
     * Initialize performance optimizations
     */
    public static function init(): void {
        // Database optimization hooks
        add_action('init', [__CLASS__, 'initDatabaseOptimizations'], 5);
        
        // Query optimization
        add_filter('fp_esperienze_cache_query', [__CLASS__, 'cacheQuery'], 10, 3);
        add_filter('fp_esperienze_get_cached_query', [__CLASS__, 'getCachedQuery'], 10, 2);
        
        // Bulk operations optimization
        add_action('fp_esperienze_bulk_operation', [__CLASS__, 'optimizeBulkOperation'], 10, 3);
        
        // Memory optimization
        add_action('wp_footer', [__CLASS__, 'cleanupMemory'], 999);
        add_action('admin_footer', [__CLASS__, 'cleanupMemory'], 999);
        
        // Performance monitoring in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', [__CLASS__, 'outputPerformanceMetrics'], 999);
            add_action('shutdown', [__CLASS__, 'logPerformanceMetrics']);
        }
    }
    
    /**
     * Initialize database optimizations
     */
    public static function initDatabaseOptimizations(): void {
        // Add missing indexes for better query performance
        add_action('admin_init', [__CLASS__, 'maybeAddOptimizedIndexes'], 5);
        
        // Optimize database queries hooks
        add_filter('posts_clauses', [__CLASS__, 'optimizeExperienceQueries'], 10, 2);
        
        // Database maintenance scheduled task
        if (!wp_next_scheduled('fp_esperienze_db_optimization')) {
            wp_schedule_event(time(), 'weekly', 'fp_esperienze_db_optimization');
        }
        add_action('fp_esperienze_db_optimization', [__CLASS__, 'performDatabaseMaintenance']);
    }
    
    /**
     * Add optimized database indexes for better performance
     */
    public static function maybeAddOptimizedIndexes(): void {
        $indexes_version = get_option('fp_esperienze_optimized_indexes_version', '0.0.0');
        $current_version = FP_ESPERIENZE_VERSION;
        
        if (version_compare($indexes_version, $current_version, '<')) {
            self::addOptimizedIndexes();
            update_option('fp_esperienze_optimized_indexes_version', $current_version);
        }
    }
    
    /**
     * Add optimized database indexes
     */
    private static function addOptimizedIndexes(): void {
        global $wpdb;
        
        $optimized_indexes = [
            // Post meta optimizations for experience products
            $wpdb->postmeta => [
                'experience_meta_composite' => 'ADD INDEX idx_experience_meta (post_id, meta_key(50), meta_value(10))',
                'meta_key_value_optimized' => 'ADD INDEX idx_meta_key_value_opt (meta_key(50), meta_value(50))',
            ],
            
            // WooCommerce order items optimization
            $wpdb->prefix . 'woocommerce_order_items' => [
                'order_type_name' => 'ADD INDEX idx_order_type_name (order_id, order_item_type, order_item_name(50))',
            ],
            
            // Additional booking table optimizations
            $wpdb->prefix . 'fp_bookings' => [
                'booking_lookup_optimized' => 'ADD INDEX idx_booking_lookup (product_id, booking_date, booking_time, status)',
                'customer_bookings' => 'ADD INDEX idx_customer_bookings (customer_email(50), booking_date)',
                'performance_status_date' => 'ADD INDEX idx_perf_status_date (status, booking_date, created_at)',
            ],
            
            // Schedule table optimizations for availability queries
            $wpdb->prefix . 'fp_schedules' => [
                'availability_lookup' => 'ADD INDEX idx_availability_lookup (product_id, day_of_week, is_active, start_time, end_time)',
                'schedule_performance' => 'ADD INDEX idx_schedule_perf (is_active, product_id, day_of_week)',
            ],
            
            // Override table optimizations
            $wpdb->prefix . 'fp_overrides' => [
                'override_lookup_optimized' => 'ADD INDEX idx_override_lookup (product_id, date, is_closed)',
                'date_range_queries' => 'ADD INDEX idx_date_range (date, is_closed, product_id)',
            ],
            
            // Voucher table optimizations (if exists)
            $wpdb->prefix . 'fp_vouchers' => [
                'voucher_code_status' => 'ADD INDEX idx_voucher_code_status (code(20), status)',
                'voucher_validity' => 'ADD INDEX idx_voucher_validity (valid_from, valid_until, status)',
                'redemption_lookup' => 'ADD INDEX idx_redemption_lookup (product_id, status, valid_from)',
            ],
        ];
        
        foreach ($optimized_indexes as $table => $indexes) {
            // Check if table exists first
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if (!$table_exists) {
                continue;
            }
            
            foreach ($indexes as $index_name => $index_sql) {
                // Check if index already exists
                $existing_index = $wpdb->get_var($wpdb->prepare(
                    "SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
                    $index_name
                ));
                
                if (!$existing_index) {
                    try {
                        $full_sql = "ALTER TABLE `{$table}` {$index_sql}";
                        $wpdb->query($full_sql);
                        
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("FP Esperienze: Added optimized index {$index_name} to {$table}");
                        }
                    } catch (\Exception $e) {
                        error_log("FP Esperienze: Failed to add index {$index_name} to {$table}: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Cache query results with intelligent invalidation
     *
     * @param string $query_key Unique query identifier
     * @param callable $query_callback Query execution callback
     * @param int $cache_time Cache time in seconds
     * @return mixed Query results
     */
    public static function cacheQuery(string $query_key, callable $query_callback, int $cache_time = 300) {
        $cache_key = 'fp_query_' . md5($query_key);
        
        // Check in-memory cache first
        if (isset(self::$query_cache[$cache_key])) {
            self::recordMetric('query_cache_hit', $query_key);
            return self::$query_cache[$cache_key];
        }
        
        // Check persistent cache
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            self::$query_cache[$cache_key] = $cached_result;
            self::recordMetric('query_cache_hit', $query_key);
            return $cached_result;
        }
        
        // Execute query and cache result
        $start_time = microtime(true);
        $result = $query_callback();
        $execution_time = microtime(true) - $start_time;
        
        // Only cache successful results
        if ($result !== false && !is_wp_error($result)) {
            self::$query_cache[$cache_key] = $result;
            set_transient($cache_key, $result, $cache_time);
            
            self::recordMetric('query_cache_miss', $query_key, $execution_time);
        }
        
        return $result;
    }
    
    /**
     * Get cached query result
     *
     * @param string $query_key Query identifier
     * @param mixed $default Default value if not cached
     * @return mixed
     */
    public static function getCachedQuery(string $query_key, $default = null) {
        $cache_key = 'fp_query_' . md5($query_key);
        
        // Check in-memory cache first
        if (isset(self::$query_cache[$cache_key])) {
            return self::$query_cache[$cache_key];
        }
        
        // Check persistent cache
        $cached_result = get_transient($cache_key);
        if ($cached_result !== false) {
            self::$query_cache[$cache_key] = $cached_result;
            return $cached_result;
        }
        
        return $default;
    }
    
    /**
     * Optimize bulk operations to reduce database load
     *
     * @param string $operation Operation type
     * @param array $data Data to process
     * @param array $options Operation options
     */
    public static function optimizeBulkOperation(string $operation, array $data, array $options = []): void {
        global $wpdb;
        
        $batch_size = $options['batch_size'] ?? 100;
        $batches = array_chunk($data, $batch_size);
        
        foreach ($batches as $batch) {
            switch ($operation) {
                case 'insert_bookings':
                    self::bulkInsertBookings($batch);
                    break;
                    
                case 'update_availability':
                    self::bulkUpdateAvailability($batch);
                    break;
                    
                case 'cleanup_expired_holds':
                    self::bulkCleanupHolds($batch);
                    break;
                    
                default:
                    do_action('fp_esperienze_bulk_operation_' . $operation, $batch, $options);
                    break;
            }
            
            // Small delay between batches to prevent overwhelming the server
            if (count($batches) > 1) {
                usleep(10000); // 10ms delay
            }
        }
    }
    
    /**
     * Bulk insert bookings for better performance
     *
     * @param array $bookings Array of booking data
     */
    private static function bulkInsertBookings(array $bookings): void {
        global $wpdb;
        
        if (empty($bookings)) {
            return;
        }
        
        $table = $wpdb->prefix . 'fp_bookings';
        $values = [];
        $placeholders = [];
        
        foreach ($bookings as $booking) {
            $placeholders[] = '(%s, %d, %s, %s, %d, %s, %s, %s, %s, %s, %s, %s)';
            $values = array_merge($values, [
                $booking['booking_code'] ?? '',
                $booking['product_id'] ?? 0,
                $booking['booking_date'] ?? '',
                $booking['booking_time'] ?? '',
                $booking['participants'] ?? 1,
                $booking['customer_name'] ?? '',
                $booking['customer_email'] ?? '',
                $booking['customer_phone'] ?? '',
                $booking['status'] ?? 'confirmed',
                $booking['order_id'] ?? null,
                $booking['order_item_id'] ?? null,
                current_time('mysql')
            ]);
        }
        
        $sql = "INSERT INTO {$table} 
                (booking_code, product_id, booking_date, booking_time, participants, 
                 customer_name, customer_email, customer_phone, status, order_id, order_item_id, created_at) 
                VALUES " . implode(', ', $placeholders);
        
        $wpdb->query($wpdb->prepare($sql, $values));
    }
    
    /**
     * Optimize experience product queries
     *
     * @param array $clauses Query clauses
     * @param \WP_Query $query Query object
     * @return array
     */
    public static function optimizeExperienceQueries(array $clauses, \WP_Query $query): array {
        global $wpdb;
        
        // Only optimize for experience product queries
        if (!$query->get('post_type') === 'product' || !$query->get('meta_query')) {
            return $clauses;
        }
        
        // Check if this is an experience product query
        $meta_query = $query->get('meta_query');
        $is_experience_query = false;
        
        if (is_array($meta_query)) {
            foreach ($meta_query as $meta_clause) {
                if (isset($meta_clause['key']) && $meta_clause['key'] === '_product_type' && 
                    isset($meta_clause['value']) && $meta_clause['value'] === 'experience') {
                    $is_experience_query = true;
                    break;
                }
            }
        }
        
        if ($is_experience_query) {
            // Add index hints for better performance
            $clauses['join'] = str_replace(
                "INNER JOIN {$wpdb->postmeta}",
                "INNER JOIN {$wpdb->postmeta} USE INDEX (idx_meta_key_value_opt)",
                $clauses['join']
            );
        }
        
        return $clauses;
    }
    
    /**
     * Perform database maintenance tasks
     */
    public static function performDatabaseMaintenance(): void {
        global $wpdb;
        
        // Optimize tables
        $tables = [
            $wpdb->prefix . 'fp_bookings',
            $wpdb->prefix . 'fp_schedules',
            $wpdb->prefix . 'fp_overrides',
            $wpdb->prefix . 'fp_meeting_points',
            $wpdb->prefix . 'fp_extras',
            $wpdb->prefix . 'fp_exp_holds'
        ];
        
        foreach ($tables as $table) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($table_exists) {
                $wpdb->query("OPTIMIZE TABLE {$table}");
            }
        }
        
        // Clean up expired transients
        $wpdb->query("
            DELETE FROM {$wpdb->options} 
            WHERE option_name LIKE '_transient_timeout_%' 
            AND option_value < UNIX_TIMESTAMP()
        ");
        
        // Clean up orphaned meta
        $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.ID IS NULL
        ");
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FP Esperienze: Database maintenance completed');
        }
    }
    
    /**
     * Clean up memory usage
     */
    public static function cleanupMemory(): void {
        // Clear internal caches
        self::$query_cache = [];
        
        // Force garbage collection if memory usage is high
        $memory_usage = memory_get_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        
        if ($memory_usage > ($memory_limit * 0.8)) {
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }
    }
    
    /**
     * Record performance metric
     *
     * @param string $type Metric type
     * @param string $context Context
     * @param float $value Metric value
     */
    private static function recordMetric(string $type, string $context, float $value = 1.0): void {
        if (!isset(self::$metrics[$type])) {
            self::$metrics[$type] = [];
        }
        
        self::$metrics[$type][] = [
            'context' => $context,
            'value' => $value,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Output performance metrics in debug mode
     */
    public static function outputPerformanceMetrics(): void {
        if (empty(self::$metrics) || !current_user_can('manage_options')) {
            return;
        }
        
        echo "<!-- FP Esperienze Performance Metrics\n";
        foreach (self::$metrics as $type => $metrics) {
            echo "  {$type}: " . count($metrics) . " events\n";
            if ($type === 'query_cache_miss') {
                $total_time = array_sum(array_column($metrics, 'value'));
                echo "    Total query time: " . number_format($total_time * 1000, 2) . "ms\n";
            }
        }
        echo "-->\n";
    }
    
    /**
     * Log performance metrics
     */
    public static function logPerformanceMetrics(): void {
        if (empty(self::$metrics)) {
            return;
        }
        
        $summary = [];
        foreach (self::$metrics as $type => $metrics) {
            $summary[$type] = count($metrics);
            if ($type === 'query_cache_miss') {
                $summary['total_query_time'] = array_sum(array_column($metrics, 'value'));
            }
        }
        
        Log::write('performance', [
            'timestamp' => current_time('mysql'),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_current' => memory_get_usage(true),
            'metrics' => $summary
        ]);
    }
    
    /**
     * Get performance statistics
     *
     * @return array
     */
    public static function getPerformanceStats(): array {
        global $wpdb;
        
        $stats = [];
        
        // Database table sizes
        $tables = [
            'bookings' => $wpdb->prefix . 'fp_bookings',
            'schedules' => $wpdb->prefix . 'fp_schedules',
            'overrides' => $wpdb->prefix . 'fp_overrides',
            'holds' => $wpdb->prefix . 'fp_exp_holds'
        ];
        
        foreach ($tables as $name => $table) {
            $size = $wpdb->get_var($wpdb->prepare(
                "SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) 
                 FROM information_schema.TABLES 
                 WHERE table_schema = DATABASE() AND table_name = %s",
                $table
            ));
            $stats['table_sizes'][$name] = $size ? $size . ' MB' : 'N/A';
        }
        
        // Cache statistics
        $cache_keys = $wpdb->get_results(
            "SELECT option_name FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_fp_%' 
             OR option_name LIKE '_transient_timeout_fp_%'"
        );
        $stats['cache_entries'] = count($cache_keys);
        
        // Memory usage
        $stats['memory_usage'] = [
            'current' => size_format(memory_get_usage(true)),
            'peak' => size_format(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit')
        ];
        
        return $stats;
    }
}