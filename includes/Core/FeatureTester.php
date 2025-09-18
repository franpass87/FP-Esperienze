<?php
/**
 * Feature Tester for new enhancements
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Feature tester class to verify new enhancements are working
 */
class FeatureTester {
    
    /**
     * Test all new enhancement features
     */
    public static function testAllFeatures(): array {
        $results = [];
        
        // Test SecurityEnhancer
        $results['security'] = self::testSecurityEnhancer();
        
        // Test PerformanceOptimizer
        $results['performance'] = self::testPerformanceOptimizer();
        
        // Test UXEnhancer
        $results['ux'] = self::testUXEnhancer();
        
        return $results;
    }
    
    /**
     * Test SecurityEnhancer functionality
     */
    private static function testSecurityEnhancer(): array {
        $results = [
            'class_exists' => class_exists('FP\Esperienze\Core\SecurityEnhancer'),
            'methods' => []
        ];
        
        if ($results['class_exists']) {
            $results['methods']['enhancedInputValidation'] = method_exists('FP\Esperienze\Core\SecurityEnhancer', 'enhancedInputValidation');
            $results['methods']['addSecurityHeaders'] = method_exists('FP\Esperienze\Core\SecurityEnhancer', 'addSecurityHeaders');
            $results['methods']['initRateLimiting'] = method_exists('FP\Esperienze\Core\SecurityEnhancer', 'initRateLimiting');
            
            // Test input validation
            try {
                $validation_result = SecurityEnhancer::enhancedInputValidation('test@example.com', 'email');
                $results['email_validation'] = ($validation_result === 'test@example.com');
            } catch (\Exception $e) {
                $results['email_validation'] = false;
                $results['email_validation_error'] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Test PerformanceOptimizer functionality
     */
    private static function testPerformanceOptimizer(): array {
        $results = [
            'class_exists' => class_exists('FP\Esperienze\Core\PerformanceOptimizer'),
            'methods' => []
        ];
        
        if ($results['class_exists']) {
            $results['methods']['init'] = method_exists('FP\Esperienze\Core\PerformanceOptimizer', 'init');
            $results['methods']['addOptimizedIndexes'] = method_exists('FP\Esperienze\Core\PerformanceOptimizer', 'addOptimizedIndexes');
            $results['methods']['cacheQuery'] = method_exists('FP\Esperienze\Core\PerformanceOptimizer', 'cacheQuery');
            
            // Test cache functionality
            try {
                $cache_result = PerformanceOptimizer::cacheQuery(
                    'test_query',
                    static function () {
                        global $wpdb;

                        return $wpdb->get_var('SELECT 1');
                    },
                    300
                );
                $results['cache_test'] = true;
            } catch (\Throwable $e) {
                $results['cache_test'] = false;
                $results['cache_error'] = $e->getMessage();
            }
        }
        
        return $results;
    }
    
    /**
     * Test UXEnhancer functionality
     */
    private static function testUXEnhancer(): array {
        $results = [
            'class_exists' => class_exists('FP\Esperienze\Core\UXEnhancer'),
            'methods' => []
        ];
        
        if ($results['class_exists']) {
            $results['methods']['init'] = method_exists('FP\Esperienze\Core\UXEnhancer', 'init');
            $results['methods']['enqueueUXScripts'] = method_exists('FP\Esperienze\Core\UXEnhancer', 'enqueueUXScripts');
            $results['methods']['enqueueAdminUXScripts'] = method_exists('FP\Esperienze\Core\UXEnhancer', 'enqueueAdminUXScripts');
            
            // Check if JS files exist
            $results['ux_js_exists'] = file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/ux-enhancer.js');
            $results['admin_ux_js_exists'] = file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin-ux-enhancer.js');
        }
        
        return $results;
    }
    
    /**
     * Display test results in admin
     */
    public static function displayTestResults(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $results = self::testAllFeatures();
        
        echo '<div class="notice notice-info"><p><strong>FP Esperienze Enhancement Features Test Results:</strong></p>';
        echo '<pre>' . wp_kses_post(print_r($results, true)) . '</pre>';
        echo '</div>';
    }
}