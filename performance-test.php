<?php
/**
 * Performance Measurement Script
 *
 * Run this script to measure the performance improvements
 * Usage: php performance-test.php
 */

require_once __DIR__ . '/../../wp-load.php';

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

class PerformanceTest {
    
    private $results = [];
    
    public function runTests(): void {
        echo "FP Esperienze Performance Tests\n";
        echo "==============================\n\n";
        
        // Test 1: Availability Cache Performance
        $this->testAvailabilityCache();
        
        // Test 2: Database Query Performance
        $this->testDatabaseQueries();
        
        // Test 3: Archive Filter Performance
        $this->testArchiveFilters();
        
        // Test 4: Memory Usage
        $this->testMemoryUsage();
        
        // Summary
        $this->printSummary();
    }
    
    private function testAvailabilityCache(): void {
        echo "Testing Availability Cache Performance...\n";
        
        // Get a test product
        $products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                ['key' => '_product_type', 'value' => 'experience']
            ],
            'posts_per_page' => 1,
            'fields' => 'ids'
        ]);
        
        if (empty($products)) {
            echo "⚠️  No experience products found - creating test data would be needed\n\n";
            return;
        }
        
        $product_id = $products[0];
        $test_date = date('Y-m-d', strtotime('+7 days'));
        
        // Clear cache first
        wp_cache_flush();
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_availability_%'");
        
        // Test without cache (cold)
        $start_time = microtime(true);
        $start_queries = $wpdb->num_queries;
        
        $cold_result = \FP\Esperienze\Data\Availability::forDay($product_id, $test_date);
        
        $cold_time = (microtime(true) - $start_time) * 1000;
        $cold_queries = $wpdb->num_queries - $start_queries;
        
        // Test with cache (warm)
        $start_time = microtime(true);
        $start_queries = $wpdb->num_queries;
        
        $warm_result = \FP\Esperienze\Data\Availability::forDay($product_id, $test_date);
        
        $warm_time = (microtime(true) - $start_time) * 1000;
        $warm_queries = $wpdb->num_queries - $start_queries;
        
        $improvement = $cold_time > 0 ? (($cold_time - $warm_time) / $cold_time) * 100 : 0;
        
        echo sprintf("  Cold call: %.2fms (%d queries)\n", $cold_time, $cold_queries);
        echo sprintf("  Warm call: %.2fms (%d queries)\n", $warm_time, $warm_queries);
        echo sprintf("  Improvement: %.1f%% faster\n", $improvement);
        
        $this->results['availability_cache'] = [
            'cold_time' => $cold_time,
            'warm_time' => $warm_time,
            'cold_queries' => $cold_queries,
            'warm_queries' => $warm_queries,
            'improvement' => $improvement
        ];
        
        echo "\n";
    }
    
    private function testDatabaseQueries(): void {
        echo "Testing Database Query Performance...\n";
        
        global $wpdb;
        
        // Test booking count query performance
        $test_date = date('Y-m-d', strtotime('+1 day'));
        $test_time = '10:00:00';
        
        $products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                ['key' => '_product_type', 'value' => 'experience']
            ],
            'posts_per_page' => 3,
            'fields' => 'ids'
        ]);
        
        if (empty($products)) {
            echo "⚠️  No experience products found\n\n";
            return;
        }
        
        $total_time = 0;
        $total_queries = 0;
        
        foreach ($products as $product_id) {
            $start_time = microtime(true);
            $start_queries = $wpdb->num_queries;
            
            // Simulate the booking count query used in availability calculation
            $wpdb->get_var($wpdb->prepare(
                "SELECT SUM(adults + children) 
                 FROM {$wpdb->prefix}fp_bookings 
                 WHERE product_id = %d 
                 AND booking_date = %s 
                 AND booking_time = %s 
                 AND status IN ('confirmed', 'pending')",
                $product_id,
                $test_date,
                $test_time
            ));
            
            $time = (microtime(true) - $start_time) * 1000;
            $queries = $wpdb->num_queries - $start_queries;
            
            $total_time += $time;
            $total_queries += $queries;
        }
        
        $avg_time = $total_time / count($products);
        echo sprintf("  Average booking query: %.2fms (%d queries per product)\n", $avg_time, $total_queries / count($products));
        
        $this->results['db_queries'] = [
            'avg_time' => $avg_time,
            'total_queries' => $total_queries
        ];
        
        echo "\n";
    }
    
    private function testArchiveFilters(): void {
        echo "Testing Archive Filter Performance...\n";
        
        global $wpdb;
        
        $test_date = date('Y-m-d', strtotime('+3 days'));
        
        // Clear archive cache
        delete_transient('fp_available_products_' . $test_date);
        
        // Test cold archive filter
        $start_time = microtime(true);
        $start_queries = $wpdb->num_queries;
        
        $shortcode = new \FP\Esperienze\Frontend\Shortcodes();
        $reflection = new ReflectionClass($shortcode);
        $method = $reflection->getMethod('getAvailableProductsForDate');
        $method->setAccessible(true);
        
        $cold_result = $method->invoke($shortcode, $test_date);
        
        $cold_time = (microtime(true) - $start_time) * 1000;
        $cold_queries = $wpdb->num_queries - $start_queries;
        
        // Test warm archive filter
        $start_time = microtime(true);
        $start_queries = $wpdb->num_queries;
        
        $warm_result = $method->invoke($shortcode, $test_date);
        
        $warm_time = (microtime(true) - $start_time) * 1000;
        $warm_queries = $wpdb->num_queries - $start_queries;
        
        $improvement = $cold_time > 0 ? (($cold_time - $warm_time) / $cold_time) * 100 : 0;
        
        echo sprintf("  Cold archive filter: %.2fms (%d queries)\n", $cold_time, $cold_queries);
        echo sprintf("  Warm archive filter: %.2fms (%d queries)\n", $warm_time, $warm_queries);
        echo sprintf("  Products found: %d\n", count($cold_result));
        echo sprintf("  Improvement: %.1f%% faster\n", $improvement);
        
        $this->results['archive_filter'] = [
            'cold_time' => $cold_time,
            'warm_time' => $warm_time,
            'cold_queries' => $cold_queries,
            'warm_queries' => $warm_queries,
            'products_found' => count($cold_result),
            'improvement' => $improvement
        ];
        
        echo "\n";
    }
    
    private function testMemoryUsage(): void {
        echo "Testing Memory Usage...\n";
        
        $memory_start = memory_get_usage();
        $memory_peak_start = memory_get_peak_usage();
        
        // Simulate loading multiple availability days
        $products = get_posts([
            'post_type' => 'product',
            'meta_query' => [
                ['key' => '_product_type', 'value' => 'experience']
            ],
            'posts_per_page' => 5,
            'fields' => 'ids'
        ]);
        
        if (!empty($products)) {
            foreach ($products as $product_id) {
                for ($i = 1; $i <= 7; $i++) {
                    $test_date = date('Y-m-d', strtotime("+{$i} days"));
                    \FP\Esperienze\Data\Availability::forDay($product_id, $test_date);
                }
            }
        }
        
        $memory_used = (memory_get_usage() - $memory_start) / 1024 / 1024;
        $memory_peak = (memory_get_peak_usage() - $memory_peak_start) / 1024 / 1024;
        
        echo sprintf("  Memory used: %.2f MB\n", $memory_used);
        echo sprintf("  Peak memory: %.2f MB\n", $memory_peak);
        
        $this->results['memory'] = [
            'used' => $memory_used,
            'peak' => $memory_peak
        ];
        
        echo "\n";
    }
    
    private function printSummary(): void {
        echo "Performance Summary\n";
        echo "==================\n\n";
        
        if (isset($this->results['availability_cache'])) {
            $cache = $this->results['availability_cache'];
            echo "✅ Availability Cache: {$cache['improvement']}% improvement\n";
            echo "   Query reduction: {$cache['cold_queries']} → {$cache['warm_queries']}\n\n";
        }
        
        if (isset($this->results['archive_filter'])) {
            $archive = $this->results['archive_filter'];
            echo "✅ Archive Filter: {$archive['improvement']}% improvement\n";
            echo "   Found {$archive['products_found']} available products\n\n";
        }
        
        if (isset($this->results['memory'])) {
            $memory = $this->results['memory'];
            echo "📊 Memory Usage: {$memory['used']}MB used, {$memory['peak']}MB peak\n\n";
        }
        
        // Cache statistics
        if (class_exists('\FP\Esperienze\Core\CacheManager')) {
            $cache_stats = \FP\Esperienze\Core\CacheManager::getCacheStatistics();
            echo "📈 Cache Statistics:\n";
            echo "   Total caches: {$cache_stats['total_caches']}\n";
            echo "   Availability caches: {$cache_stats['availability_caches']}\n";
            echo "   Archive caches: {$cache_stats['archive_caches']}\n\n";
        }
        
        echo "Performance test completed successfully! 🚀\n";
    }
}
// Run the test if called directly
if (php_sapi_name() === 'cli') {
$test = new PerformanceTest();
$test->runTests();
}
