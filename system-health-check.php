<?php
/**
 * Comprehensive System Health Check
 * 
 * Run this script to perform a complete health check of the FP Esperienze system
 * Usage: php system-health-check.php [--detailed] [--fix-issues]
 */

// WordPress bootstrap
$wp_load_path = __DIR__ . '/../../wp-load.php';
if (!file_exists($wp_load_path)) {
    $wp_load_path = __DIR__ . '/../../../wp-load.php';
}

require_once $wp_load_path;

if (!defined('ABSPATH')) {
    die('WordPress not loaded properly');
}

class FPSystemHealthCheck {
    
    private $detailed = false;
    private $fix_issues = false;
    private $results = [];
    private $issues = [];
    
    public function __construct($args = []) {
        $this->detailed = in_array('--detailed', $args);
        $this->fix_issues = in_array('--fix-issues', $args);
    }
    
    public function runFullCheck(): void {
        echo "FP Esperienze - Comprehensive System Health Check\n";
        echo "================================================\n\n";
        
        // Run all health checks
        $this->checkSystemRequirements();
        $this->checkDatabaseHealth();
        $this->checkPerformanceHealth();
        $this->checkSecurityHealth();
        $this->checkIntegrationHealth();
        $this->checkCacheHealth();
        $this->checkAPIHealth();
        $this->checkFileSystemHealth();
        
        // Generate summary and recommendations
        $this->generateSummary();
        $this->generateRecommendations();
        
        if ($this->fix_issues) {
            $this->autoFixIssues();
        }
    }
    
    private function checkSystemRequirements(): void {
        echo "🔍 Checking System Requirements...\n";
        
        $checks = [
            'PHP Version' => version_compare(PHP_VERSION, '8.1', '>='),
            'WordPress Version' => version_compare(get_bloginfo('version'), '6.5', '>='),
            'WooCommerce' => class_exists('WooCommerce') && version_compare(WC_VERSION, '8.0', '>='),
            'Memory Limit' => wp_convert_hr_to_bytes(ini_get('memory_limit')) >= 256 * 1024 * 1024,
            'Max Execution Time' => ini_get('max_execution_time') >= 30,
            'Required Extensions' => $this->checkPHPExtensions()
        ];
        
        foreach ($checks as $name => $status) {
            $this->reportCheck($name, $status);
            if (!$status) {
                $this->issues[] = "System requirement failed: {$name}";
            }
        }
        
        echo "\n";
    }
    
    private function checkDatabaseHealth(): void {
        echo "🗄️  Checking Database Health...\n";
        global $wpdb;
        
        // Check database connection
        $db_connection = $wpdb->check_connection();
        $this->reportCheck('Database Connection', $db_connection);
        
        // Check required tables exist
        $required_tables = [
            'fp_bookings', 'fp_schedules', 'fp_overrides', 'fp_meeting_points',
            'fp_extras', 'fp_exp_vouchers', 'fp_vouchers'
        ];
        
        $missing_tables = [];
        foreach ($required_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) === $full_table;
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        $tables_ok = empty($missing_tables);
        $this->reportCheck('Required Tables', $tables_ok);
        if (!$tables_ok) {
            $this->issues[] = "Missing database tables: " . implode(', ', $missing_tables);
        }
        
        // Check database performance
        $start_time = microtime(true);
        $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}fp_bookings");
        $query_time = (microtime(true) - $start_time) * 1000;
        
        $db_performance = $query_time < 100; // 100ms threshold
        $this->reportCheck('Database Performance', $db_performance, sprintf('%.2fms', $query_time));
        
        if ($this->detailed) {
            echo "  📊 Query execution time: {$query_time}ms\n";
            echo "  📊 Missing tables: " . (empty($missing_tables) ? 'None' : implode(', ', $missing_tables)) . "\n";
        }
        
        echo "\n";
    }
    
    private function checkPerformanceHealth(): void {
        echo "⚡ Checking Performance Health...\n";
        
        // Memory usage check
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_ok = ($memory_peak / $memory_limit) < 0.8;
        
        $this->reportCheck('Memory Usage', $memory_ok, 
            sprintf('%.1f%% (Peak: %s)', ($memory_peak / $memory_limit) * 100, size_format($memory_peak)));
        
        // Cache performance check
        $cache_ok = true;
        $cache_info = 'Not tested';
        if (class_exists('\FP\Esperienze\Core\CacheManager')) {
            $test_key = 'fp_health_test_' . time();
            $start_time = microtime(true);
            set_transient($test_key, ['test' => true], 60);
            $cached = get_transient($test_key);
            $cache_time = (microtime(true) - $start_time) * 1000;
            delete_transient($test_key);
            
            $cache_ok = $cached !== false && $cache_time < 10;
            $cache_info = sprintf('%.2fms', $cache_time);
        }
        
        $this->reportCheck('Cache Performance', $cache_ok, $cache_info);
        
        // Query monitoring check
        $query_ok = true;
        if (class_exists('\FP\Esperienze\Core\QueryMonitor')) {
            $stats = \FP\Esperienze\Core\QueryMonitor::getStatistics();
            $query_ok = ($stats['slow_queries'] ?? 0) == 0;
        }
        
        $this->reportCheck('Query Performance', $query_ok);
        
        if ($this->detailed) {
            echo "  📊 Memory limit: " . size_format($memory_limit) . "\n";
            echo "  📊 Current usage: " . size_format($memory_usage) . "\n";
            echo "  📊 Peak usage: " . size_format($memory_peak) . "\n";
        }
        
        echo "\n";
    }
    
    private function checkSecurityHealth(): void {
        echo "🔒 Checking Security Health...\n";
        
        // File permissions check
        $upload_dir = wp_upload_dir();
        $uploads_writable = is_writable($upload_dir['basedir']);
        $this->reportCheck('Upload Directory Writable', $uploads_writable);
        
        // SSL check
        $ssl_ok = is_ssl();
        $this->reportCheck('SSL/HTTPS Enabled', $ssl_ok);
        
        // WordPress security headers
        $security_headers = $this->checkSecurityHeaders();
        $this->reportCheck('Security Headers', $security_headers['score'] > 0.7, 
            sprintf('%d/%d headers', $security_headers['found'], $security_headers['total']));
        
        // Plugin file integrity
        $plugin_files_ok = $this->checkPluginFileIntegrity();
        $this->reportCheck('Plugin File Integrity', $plugin_files_ok);
        
        if ($this->detailed) {
            echo "  📊 Upload directory: " . $upload_dir['basedir'] . "\n";
            echo "  📊 Security headers found: " . implode(', ', $security_headers['headers_found']) . "\n";
        }
        
        echo "\n";
    }
    
    private function checkIntegrationHealth(): void {
        echo "🔗 Checking Integration Health...\n";
        
        $integrations = get_option('fp_esperienze_integrations', []);
        
        $integration_checks = [
            'Google Analytics 4' => !empty($integrations['ga4_measurement_id']),
            'Google Ads' => !empty($integrations['gads_conversion_id']),
            'Meta Pixel' => !empty($integrations['meta_pixel_id']),
            'Brevo API' => !empty($integrations['brevo_api_key']),
            'Google Places' => !empty($integrations['gplaces_api_key'])
        ];
        
        foreach ($integration_checks as $name => $configured) {
            $this->reportCheck($name, $configured, $configured ? 'Configured' : 'Not configured');
        }
        
        // Test external connectivity
        $connectivity_ok = $this->testExternalConnectivity();
        $this->reportCheck('External Connectivity', $connectivity_ok);
        
        echo "\n";
    }
    
    private function checkCacheHealth(): void {
        echo "💾 Checking Cache Health...\n";
        
        if (!class_exists('\FP\Esperienze\Core\CacheManager')) {
            $this->reportCheck('Cache Manager', false, 'Not available');
            echo "\n";
            return;
        }
        
        $cache_stats = \FP\Esperienze\Core\CacheManager::getCacheStats();
        
        $this->reportCheck('Cache Manager', true, 'Available');
        $this->reportCheck('Cache Statistics', true, 
            sprintf('%d total caches', $cache_stats['total_caches']));
        
        // Test cache operations
        $cache_write_ok = $this->testCacheOperations();
        $this->reportCheck('Cache Operations', $cache_write_ok);
        
        if ($this->detailed) {
            echo "  📊 Availability caches: " . $cache_stats['availability_caches'] . "\n";
            echo "  📊 Archive caches: " . $cache_stats['archive_caches'] . "\n";
            echo "  📊 Pre-build days: " . $cache_stats['prebuild_days'] . "\n";
        }
        
        echo "\n";
    }
    
    private function checkAPIHealth(): void {
        echo "🌐 Checking API Health...\n";
        
        $api_endpoints = [
            '/wp-json/fp-exp/v1/availability' => 'Availability API',
            '/wp-json/fp-esperienze/v1/ics/product/1' => 'ICS API',
            '/wp-json/fp-esperienze/v1/events' => 'Events API'
        ];
        
        foreach ($api_endpoints as $endpoint => $name) {
            $url = home_url($endpoint . '?test=1');
            $start_time = microtime(true);
            
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => ['User-Agent' => 'FP-Health-Check/1.0']
            ]);
            
            $response_time = (microtime(true) - $start_time) * 1000;
            $success = !is_wp_error($response) && wp_remote_retrieve_response_code($response) < 400;
            
            $this->reportCheck($name, $success, sprintf('%.0fms', $response_time));
            
            if (!$success) {
                $error_msg = is_wp_error($response) ? $response->get_error_message() : 
                    'HTTP ' . wp_remote_retrieve_response_code($response);
                $this->issues[] = "API endpoint failed: {$name} - {$error_msg}";
            }
        }
        
        echo "\n";
    }
    
    private function checkFileSystemHealth(): void {
        echo "📁 Checking File System Health...\n";
        
        $plugin_dir = FP_ESPERIENZE_PLUGIN_DIR;
        
        // Check critical files exist
        $critical_files = [
            'fp-esperienze.php',
            'includes/Core/Plugin.php',
            'includes/Admin/SystemStatus.php',
            'assets/js/admin.js',
            'assets/css/admin.css'
        ];
        
        $missing_files = [];
        foreach ($critical_files as $file) {
            if (!file_exists($plugin_dir . $file)) {
                $missing_files[] = $file;
            }
        }
        
        $files_ok = empty($missing_files);
        $this->reportCheck('Critical Files', $files_ok);
        
        if (!$files_ok) {
            $this->issues[] = "Missing critical files: " . implode(', ', $missing_files);
        }
        
        // Check file permissions
        $permissions_ok = is_readable($plugin_dir) && is_readable($plugin_dir . 'fp-esperienze.php');
        $this->reportCheck('File Permissions', $permissions_ok);
        
        // Check disk space
        $free_bytes = disk_free_space($plugin_dir);
        $total_bytes = disk_total_space($plugin_dir);
        $disk_usage = 1 - ($free_bytes / $total_bytes);
        $disk_ok = $disk_usage < 0.9; // Less than 90% full
        
        $this->reportCheck('Disk Space', $disk_ok, 
            sprintf('%.1f%% used (%s free)', $disk_usage * 100, size_format($free_bytes)));
        
        if ($this->detailed) {
            echo "  📊 Plugin directory: {$plugin_dir}\n";
            echo "  📊 Missing files: " . (empty($missing_files) ? 'None' : implode(', ', $missing_files)) . "\n";
        }
        
        echo "\n";
    }
    
    private function generateSummary(): void {
        echo "📋 Health Check Summary\n";
        echo "======================\n\n";
        
        $total_checks = count($this->results);
        $passed_checks = count(array_filter($this->results));
        $success_rate = $total_checks > 0 ? ($passed_checks / $total_checks) * 100 : 0;
        
        echo sprintf("✅ Total checks: %d\n", $total_checks);
        echo sprintf("✅ Passed: %d\n", $passed_checks);
        echo sprintf("❌ Failed: %d\n", $total_checks - $passed_checks);
        echo sprintf("📊 Success rate: %.1f%%\n\n", $success_rate);
        
        if ($success_rate >= 90) {
            echo "🎉 System health is EXCELLENT!\n";
        } elseif ($success_rate >= 75) {
            echo "👍 System health is GOOD.\n";
        } elseif ($success_rate >= 60) {
            echo "⚠️  System health is FAIR - some issues need attention.\n";
        } else {
            echo "🚨 System health is POOR - immediate attention required!\n";
        }
        
        echo "\n";
    }
    
    private function generateRecommendations(): void {
        if (empty($this->issues)) {
            echo "🎯 No specific issues found - system is running optimally!\n\n";
            return;
        }
        
        echo "🎯 Recommendations\n";
        echo "==================\n\n";
        
        foreach ($this->issues as $index => $issue) {
            echo sprintf("%d. %s\n", $index + 1, $issue);
        }
        
        echo "\n";
        echo "💡 General optimization tips:\n";
        echo "   - Enable object caching (Redis/Memcached) for better performance\n";
        echo "   - Regularly update WordPress, plugins, and themes\n";
        echo "   - Monitor disk space and database size\n";
        echo "   - Consider CDN for static assets\n";
        echo "   - Enable gzip compression\n";
        echo "   - Optimize images and assets\n\n";
    }
    
    private function autoFixIssues(): void {
        echo "🔧 Auto-fixing Issues\n";
        echo "=====================\n\n";
        
        $fixed_count = 0;
        
        // Try to create missing database tables
        if (strpos(implode(' ', $this->issues), 'Missing database tables') !== false) {
            echo "Attempting to create missing database tables...\n";
            try {
                \FP\Esperienze\Core\Installer::activate();
                echo "✅ Database tables created successfully\n";
                $fixed_count++;
            } catch (Exception $e) {
                echo "❌ Failed to create database tables: " . $e->getMessage() . "\n";
            }
        }
        
        // Clear and rebuild caches
        if (class_exists('\FP\Esperienze\Core\CacheManager')) {
            echo "Clearing and rebuilding caches...\n";
            try {
                $cleared = \FP\Esperienze\Core\CacheManager::clearAllCaches();
                $cache_manager = new \FP\Esperienze\Core\CacheManager();
                $cache_manager->prebuildAvailability();
                echo "✅ Cleared {$cleared} cache entries and started pre-building\n";
                $fixed_count++;
            } catch (Exception $e) {
                echo "❌ Failed to manage caches: " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n";
        echo sprintf("🔧 Auto-fixed %d issues\n", $fixed_count);
        if ($fixed_count > 0) {
            echo "Please run the health check again to verify fixes.\n";
        }
        echo "\n";
    }
    
    private function reportCheck(string $name, bool $status, string $details = ''): void {
        $this->results[$name] = $status;
        $icon = $status ? '✅' : '❌';
        $details_str = $details ? " ({$details})" : '';
        echo sprintf("  %s %s%s\n", $icon, $name, $details_str);
    }
    
    private function checkPHPExtensions(): bool {
        $required = ['gd', 'mbstring', 'curl', 'json'];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                return false;
            }
        }
        return true;
    }
    
    private function checkSecurityHeaders(): array {
        $headers_to_check = [
            'X-Content-Type-Options',
            'X-Frame-Options', 
            'X-XSS-Protection',
            'Strict-Transport-Security',
            'Content-Security-Policy'
        ];
        
        $found = [];
        $response = wp_remote_get(home_url(), ['timeout' => 10]);
        
        if (!is_wp_error($response)) {
            $headers = wp_remote_retrieve_headers($response);
            foreach ($headers_to_check as $header) {
                if (isset($headers[$header]) || isset($headers[strtolower($header)])) {
                    $found[] = $header;
                }
            }
        }
        
        return [
            'total' => count($headers_to_check),
            'found' => count($found),
            'score' => count($found) / count($headers_to_check),
            'headers_found' => $found
        ];
    }
    
    private function checkPluginFileIntegrity(): bool {
        $main_file = FP_ESPERIENZE_PLUGIN_FILE;
        return file_exists($main_file) && is_readable($main_file) && filesize($main_file) > 1000;
    }
    
    private function testExternalConnectivity(): bool {
        $test_url = 'https://httpbin.org/get';
        $response = wp_remote_get($test_url, ['timeout' => 10]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
    
    private function testCacheOperations(): bool {
        $test_key = 'fp_health_cache_test_' . time();
        $test_data = ['health_check' => true, 'timestamp' => time()];
        
        // Test write
        set_transient($test_key, $test_data, 60);
        
        // Test read
        $cached_data = get_transient($test_key);
        
        // Test delete
        delete_transient($test_key);
        
        return $cached_data === $test_data;
    }
}

// Run the health check if called directly
if (php_sapi_name() === 'cli') {
    $health_check = new FPSystemHealthCheck($argv);
    $health_check->runFullCheck();
}