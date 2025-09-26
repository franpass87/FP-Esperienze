<?php
/**
 * Feature Demo Page for testing new enhancements
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\SecurityEnhancer;
use FP\Esperienze\Core\PerformanceOptimizer;
use FP\Esperienze\Core\UXEnhancer;
use FP\Esperienze\Admin\MenuRegistry;

defined('ABSPATH') || exit;

/**
 * Feature demo page for showcasing new enhancements
 */
class FeatureDemoPage {
    
    /**
     * Initialize the feature demo page
     */
    public static function init(): void {
        MenuRegistry::instance()->registerPage([
            'slug'       => 'fp-esperienze-demo',
            'page_title' => __('Feature Demo', 'fp-esperienze'),
            'menu_title' => __('Feature Demo', 'fp-esperienze'),
            'capability' => 'manage_options',
            'callback'   => [__CLASS__, 'renderPage'],
            'order'      => 180,
        ]);

        add_action('wp_ajax_fp_test_security', [__CLASS__, 'testSecurityFeatures']);
        add_action('wp_ajax_fp_test_performance', [__CLASS__, 'testPerformanceFeatures']);
        add_action('wp_ajax_fp_test_ux', [__CLASS__, 'testUXFeatures']);
    }
    
    /**
     * Render the demo page
     */
    public static function renderPage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('FP Esperienze - Feature Enhancement Demo', 'fp-esperienze'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php esc_html_e('This page demonstrates the new enterprise-level features that have been added to FP Esperienze.', 'fp-esperienze'); ?></p>
            </div>
            
            <!-- Security Features Demo -->
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Security Enhancement Features', 'fp-esperienze'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('Test the enhanced security features including input validation, rate limiting, and CSP headers.', 'fp-esperienze'); ?></p>
                    
                    <button type="button" class="button" onclick="testSecurityFeatures()">
                        <?php esc_html_e('Test Security Features', 'fp-esperienze'); ?>
                    </button>
                    
                    <div id="security-results" style="margin-top: 10px;"></div>
                    
                    <h4><?php esc_html_e('Input Validation Demo', 'fp-esperienze'); ?></h4>
                    <input type="email" id="test-email" placeholder="test@example.com" style="width: 200px;">
                    <button type="button" class="button" onclick="validateEmail()">
                        <?php esc_html_e('Validate Email', 'fp-esperienze'); ?>
                    </button>
                    <div id="email-validation-result"></div>
                </div>
            </div>
            
            <!-- Performance Features Demo -->
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Performance Optimization Features', 'fp-esperienze'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('Test the performance optimization features including query caching and database indexing.', 'fp-esperienze'); ?></p>
                    
                    <button type="button" class="button" onclick="testPerformanceFeatures()">
                        <?php esc_html_e('Test Performance Features', 'fp-esperienze'); ?>
                    </button>
                    
                    <div id="performance-results" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <!-- UX Features Demo -->
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('User Experience Enhancement Features', 'fp-esperienze'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('Test the user experience enhancements including progressive loading and real-time validation.', 'fp-esperienze'); ?></p>
                    
                    <button type="button" class="button" onclick="testUXFeatures()">
                        <?php esc_html_e('Test UX Features', 'fp-esperienze'); ?>
                    </button>
                    
                    <button type="button" class="button" onclick="showLoadingDemo()">
                        <?php esc_html_e('Demo Loading Overlay', 'fp-esperienze'); ?>
                    </button>
                    
                    <button type="button" class="button" onclick="showNotificationDemo()">
                        <?php esc_html_e('Demo Notification', 'fp-esperienze'); ?>
                    </button>
                    
                    <div id="ux-results" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <!-- Feature Status -->
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Feature Status', 'fp-esperienze'); ?></h2>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Feature', 'fp-esperienze'); ?></th>
                                <th><?php esc_html_e('Status', 'fp-esperienze'); ?></th>
                                <th><?php esc_html_e('Description', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>SecurityEnhancer</td>
                                <td><span class="<?php echo class_exists('FP\Esperienze\Core\SecurityEnhancer') ? 'dashicons dashicons-yes' : 'dashicons dashicons-no'; ?>" style="color: <?php echo class_exists('FP\Esperienze\Core\SecurityEnhancer') ? 'green' : 'red'; ?>;"></span></td>
                                <td>CSP headers, rate limiting, enhanced input validation</td>
                            </tr>
                            <tr>
                                <td>PerformanceOptimizer</td>
                                <td><span class="<?php echo class_exists('FP\Esperienze\Core\PerformanceOptimizer') ? 'dashicons dashicons-yes' : 'dashicons dashicons-no'; ?>" style="color: <?php echo class_exists('FP\Esperienze\Core\PerformanceOptimizer') ? 'green' : 'red'; ?>;"></span></td>
                                <td>Query caching, database indexing, bulk operation optimization</td>
                            </tr>
                            <tr>
                                <td>UXEnhancer</td>
                                <td><span class="<?php echo class_exists('FP\Esperienze\Core\UXEnhancer') ? 'dashicons dashicons-yes' : 'dashicons dashicons-no'; ?>" style="color: <?php echo class_exists('FP\Esperienze\Core\UXEnhancer') ? 'green' : 'red'; ?>;"></span></td>
                                <td>Progressive loading, real-time validation, accessibility improvements</td>
                            </tr>
                            <tr>
                                <td>Frontend UX JS</td>
                                <td><span class="<?php echo file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/ux-enhancer.js') ? 'dashicons dashicons-yes' : 'dashicons dashicons-no'; ?>" style="color: <?php echo file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/ux-enhancer.js') ? 'green' : 'red'; ?>;"></span></td>
                                <td>Frontend JavaScript enhancements</td>
                            </tr>
                            <tr>
                                <td>Admin UX JS</td>
                                <td><span class="<?php echo file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin-ux-enhancer.js') ? 'dashicons dashicons-yes' : 'dashicons dashicons-no'; ?>" style="color: <?php echo file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin-ux-enhancer.js') ? 'green' : 'red'; ?>;"></span></td>
                                <td>Admin interface JavaScript enhancements</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
        function testSecurityFeatures() {
            jQuery('#security-results').html('<p>Testing security features...</p>');
            jQuery.post(ajaxurl, {
                action: 'fp_test_security',
                nonce: '<?php echo esc_js(wp_create_nonce('fp_demo_nonce')); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#security-results').html('<div class="notice notice-success inline"><p>Security features are working! ' + response.data.message + '</p></div>');
                } else {
                    jQuery('#security-results').html('<div class="notice notice-error inline"><p>Security test failed: ' + response.data + '</p></div>');
                }
            });
        }
        
        function testPerformanceFeatures() {
            jQuery('#performance-results').html('<p>Testing performance features...</p>');
            jQuery.post(ajaxurl, {
                action: 'fp_test_performance',
                nonce: '<?php echo esc_js(wp_create_nonce('fp_demo_nonce')); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#performance-results').html('<div class="notice notice-success inline"><p>Performance features are working! ' + response.data.message + '</p></div>');
                } else {
                    jQuery('#performance-results').html('<div class="notice notice-error inline"><p>Performance test failed: ' + response.data + '</p></div>');
                }
            });
        }
        
        function testUXFeatures() {
            jQuery('#ux-results').html('<p>Testing UX features...</p>');
            jQuery.post(ajaxurl, {
                action: 'fp_test_ux',
                nonce: '<?php echo esc_js(wp_create_nonce('fp_demo_nonce')); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#ux-results').html('<div class="notice notice-success inline"><p>UX features are working! ' + response.data.message + '</p></div>');
                } else {
                    jQuery('#ux-results').html('<div class="notice notice-error inline"><p>UX test failed: ' + response.data + '</p></div>');
                }
            });
        }
        
        function validateEmail() {
            var email = jQuery('#test-email').val();
            jQuery('#email-validation-result').html('<p>Validating...</p>');
            
            // Test the SecurityEnhancer validation via AJAX
            jQuery.post(ajaxurl, {
                action: 'fp_validate_form_field',
                field_type: 'email',
                field_value: email,
                nonce: '<?php echo esc_js(wp_create_nonce('fp_ux_nonce')); ?>'
            }, function(response) {
                if (response.success) {
                    jQuery('#email-validation-result').html('<div class="notice notice-success inline"><p>✓ Valid email</p></div>');
                } else {
                    jQuery('#email-validation-result').html('<div class="notice notice-error inline"><p>✗ ' + response.data.message + '</p></div>');
                }
            });
        }
        
        function showLoadingDemo() {
            if (window.FPAdminUXEnhancer && window.FPAdminUXEnhancer.showBulkProgress) {
                window.FPAdminUXEnhancer.showBulkProgress();
                setTimeout(function() {
                    window.FPAdminUXEnhancer.updateBulkProgress(50, 'Demo progress...');
                }, 1000);
                setTimeout(function() {
                    window.FPAdminUXEnhancer.hideBulkProgress();
                }, 3000);
            } else {
                alert('Admin UX Enhancer not loaded. This feature works when the admin UX scripts are loaded.');
            }
        }
        
        function showNotificationDemo() {
            // Create a test notification
            var notification = jQuery('<div class="fp-notification success" data-auto-dismiss="true">This is a demo notification! <button type="button" class="fp-notification-dismiss">&times;</button></div>');
            jQuery('body').append(notification);
            
            // Auto dismiss after 3 seconds
            setTimeout(function() {
                notification.fadeOut();
            }, 3000);
        }
        </script>
        <?php
    }
    
    /**
     * Test security features via AJAX
     */
    public static function testSecurityFeatures(): void {
        check_ajax_referer('fp_demo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $results = [];
        
        // Test input validation
        $email_test = SecurityEnhancer::enhancedInputValidation('test@example.com', 'email');
        $results['email_validation'] = !is_wp_error($email_test);
        
        $phone_test = SecurityEnhancer::enhancedInputValidation('+1234567890', 'phone');
        $results['phone_validation'] = !is_wp_error($phone_test);
        
        // Test rate limiting functionality exists
        $results['rate_limiting'] = method_exists('FP\Esperienze\Core\RateLimiter', 'checkRateLimit');
        
        $message = sprintf(
            'Email validation: %s, Phone validation: %s, Rate limiting: %s',
            $results['email_validation'] ? '✓' : '✗',
            $results['phone_validation'] ? '✓' : '✗',
            $results['rate_limiting'] ? '✓' : '✗'
        );
        
        wp_send_json_success(['message' => $message, 'details' => $results]);
    }
    
    /**
     * Test performance features via AJAX
     */
    public static function testPerformanceFeatures(): void {
        check_ajax_referer('fp_demo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $results = [];
        
        // Test query caching
        $cache_test = PerformanceOptimizer::cacheQuery('test_demo_query', 'SELECT 1 as test', 60);
        $results['query_caching'] = !is_wp_error($cache_test);
        
        // Test if optimization methods exist
        $results['database_optimization'] = method_exists('FP\Esperienze\Core\PerformanceOptimizer', 'addOptimizedIndexes');
        $results['bulk_optimization'] = method_exists('FP\Esperienze\Core\PerformanceOptimizer', 'optimizeBulkOperation');
        
        $message = sprintf(
            'Query caching: %s, Database optimization: %s, Bulk operations: %s',
            $results['query_caching'] ? '✓' : '✗',
            $results['database_optimization'] ? '✓' : '✗',
            $results['bulk_optimization'] ? '✓' : '✗'
        );
        
        wp_send_json_success(['message' => $message, 'details' => $results]);
    }
    
    /**
     * Test UX features via AJAX
     */
    public static function testUXFeatures(): void {
        check_ajax_referer('fp_demo_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $results = [];
        
        // Test UX enhancement methods
        $results['notification_system'] = method_exists('FP\Esperienze\Core\UXEnhancer', 'addNotification');
        $results['script_enqueuing'] = method_exists('FP\Esperienze\Core\UXEnhancer', 'enqueueUXScripts');
        $results['accessibility'] = method_exists('FP\Esperienze\Core\UXEnhancer', 'addAccessibilityStyles');
        
        // Test JS files exist
        $results['frontend_js'] = file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/ux-enhancer.js');
        $results['admin_js'] = file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin-ux-enhancer.js');
        
        // Test notification system
        try {
            UXEnhancer::addNotification('Test notification', 'success');
            $results['notification_test'] = true;
        } catch (\Exception $e) {
            $results['notification_test'] = false;
        }
        
        $message = sprintf(
            'Notifications: %s, Scripts: %s, Accessibility: %s, JS Files: %s/%s',
            $results['notification_system'] ? '✓' : '✗',
            $results['script_enqueuing'] ? '✓' : '✗',
            $results['accessibility'] ? '✓' : '✗',
            $results['frontend_js'] ? '✓' : '✗',
            $results['admin_js'] ? '✓' : '✗'
        );
        
        wp_send_json_success(['message' => $message, 'details' => $results]);
    }
}