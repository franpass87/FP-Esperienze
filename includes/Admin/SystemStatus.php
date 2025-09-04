<?php
/**
 * System Status
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\Installer;

defined('ABSPATH') || exit;

/**
 * System Status class for checking plugin health and requirements
 */
class SystemStatus {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addSystemStatusMenu']);
        add_action('admin_init', [$this, 'handleFixActions']);
    }

    /**
     * Add system status menu item
     */
    public function addSystemStatusMenu(): void {
        add_submenu_page(
            'fp-esperienze',
            __('System Status', 'fp-esperienze'),
            __('System Status', 'fp-esperienze'),
            'manage_options',
            'fp-esperienze-system-status',
            [$this, 'systemStatusPage']
        );
    }

    /**
     * Handle fix actions
     */
    public function handleFixActions(): void {
        if (!isset($_GET['page']) || sanitize_text_field($_GET['page']) !== 'fp-esperienze-system-status') {
            return;
        }

        if (!isset($_GET['action']) || !current_user_can('manage_options')) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $nonce = sanitize_text_field($_GET['_wpnonce'] ?? '');

        if (!wp_verify_nonce($nonce, 'fp_system_status_fix_' . $action)) {
            return;
        }

        switch ($action) {
            case 'create_tables':
                $this->createMissingTables();
                break;
            case 'flush_rewrite':
                flush_rewrite_rules();
                wp_redirect(admin_url('admin.php?page=fp-esperienze-system-status&fixed=rewrite'));
                exit;
                break;
        }
    }

    /**
     * Create missing database tables
     */
    private function createMissingTables(): void {
        Installer::activate();
        wp_redirect(admin_url('admin.php?page=fp-esperienze-system-status&fixed=tables'));
        exit;
    }

    /**
     * System status page
     */
    public function systemStatusPage(): void {
        $checks = $this->runSystemChecks();

        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze System Status', 'fp-esperienze'); ?></h1>

            <?php if (isset($_GET['fixed'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>
                        <?php
                        $fixed = sanitize_text_field($_GET['fixed']);
                        switch ($fixed) {
                            case 'tables':
                                _e('Database tables have been created successfully.', 'fp-esperienze');
                                break;
                            case 'rewrite':
                                _e('Rewrite rules have been flushed successfully.', 'fp-esperienze');
                                break;
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="fp-system-status">
                <?php $this->renderSystemInfo(); ?>
                <?php $this->renderPerformanceMetrics(); ?>
                <?php $this->renderChecks($checks); ?>
                <?php $this->renderDatabaseInfo(); ?>
                <?php $this->renderIntegrationStatus(); ?>
                <?php $this->renderOptimizationRecommendations($checks); ?>
            </div>

            <style>
            .fp-system-status {
                margin-top: 20px;
            }
            .fp-status-section {
                background: #fff;
                border: 1px solid #c3c4c7;
                margin-bottom: 20px;
                padding: 0;
            }
            .fp-status-section h2 {
                background: #f1f1f1;
                margin: 0;
                padding: 15px 20px;
                border-bottom: 1px solid #c3c4c7;
            }
            .fp-status-table {
                width: 100%;
                border-collapse: collapse;
            }
            .fp-status-table th,
            .fp-status-table td {
                padding: 12px 20px;
                text-align: left;
                border-bottom: 1px solid #f1f1f1;
            }
            .fp-status-table th {
                width: 30%;
                font-weight: 600;
            }
            .fp-status-ok {
                color: #00a32a;
            }
            .fp-status-warning {
                color: #dba617;
            }
            .fp-status-error {
                color: #d63638;
            }
            .fp-status-icon::before {
                font-family: dashicons;
                font-size: 16px;
                margin-right: 5px;
            }
            .fp-status-ok::before {
                content: '\f147'; /* dashicons-yes */
            }
            .fp-status-warning::before {
                content: '\f534'; /* dashicons-warning */
            }
            .fp-status-error::before {
                content: '\f158'; /* dashicons-no */
            }
            .fp-fix-button {
                margin-left: 10px;
            }
            .fp-recommendations {
                margin-top: 15px;
            }
            .fp-recommendation {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                margin-bottom: 15px;
                padding: 15px;
                position: relative;
            }
            .fp-recommendation.fp-priority-high {
                border-left: 4px solid #d63638;
            }
            .fp-recommendation.fp-priority-medium {
                border-left: 4px solid #dba617;
            }
            .fp-recommendation.fp-priority-low {
                border-left: 4px solid #00a32a;
            }
            .fp-priority-badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 10px;
                font-weight: bold;
                margin-right: 8px;
                text-transform: uppercase;
            }
            .fp-priority-badge.fp-priority-high {
                background: #d63638;
                color: white;
            }
            .fp-priority-badge.fp-priority-medium {
                background: #dba617;
                color: white;
            }
            .fp-priority-badge.fp-priority-low {
                background: #00a32a;
                color: white;
            }
            .fp-recommendation h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
            }
            .fp-recommendation p {
                margin: 8px 0;
                color: #646970;
            }
            .fp-action {
                font-size: 13px;
                background: #f6f7f7;
                padding: 8px;
                border-radius: 3px;
                margin-top: 10px !important;
            }
            .fp-status-table td {
                word-break: break-word;
            }
            @media (max-width: 782px) {
                .fp-status-table th,
                .fp-status-table td {
                    display: block;
                    width: 100%;
                    padding: 8px 20px;
                }
                .fp-status-table th {
                    background: #f9f9f9;
                    border-bottom: none;
                }
            }
            </style>
        </div>
        <?php
    }

    /**
     * Render system information
     */
    private function renderSystemInfo(): void {
        global $wp_version;

        ?>
        <div class="fp-status-section">
            <h2><?php _e('System Information', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <tr>
                    <th><?php _e('WordPress Version', 'fp-esperienze'); ?></th>
                    <td>
                        <?php echo esc_html($wp_version); ?>
                        <?php if (version_compare($wp_version, '6.5', '>=')) : ?>
                            <span class="fp-status-ok fp-status-icon"><?php _e('Compatible', 'fp-esperienze'); ?></span>
                        <?php else : ?>
                            <span class="fp-status-error fp-status-icon"><?php _e('Requires WordPress 6.5+', 'fp-esperienze'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('WooCommerce Version', 'fp-esperienze'); ?></th>
                    <td>
                        <?php if (defined('WC_VERSION')) : ?>
                            <?php echo esc_html(WC_VERSION); ?>
                            <?php if (version_compare(WC_VERSION, '8.0', '>=')) : ?>
                                <span class="fp-status-ok fp-status-icon"><?php _e('Compatible', 'fp-esperienze'); ?></span>
                            <?php else : ?>
                                <span class="fp-status-error fp-status-icon"><?php _e('Requires WooCommerce 8.0+', 'fp-esperienze'); ?></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="fp-status-error fp-status-icon"><?php _e('WooCommerce not detected', 'fp-esperienze'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('PHP Version', 'fp-esperienze'); ?></th>
                    <td>
                        <?php echo esc_html(PHP_VERSION); ?>
                        <?php if (version_compare(PHP_VERSION, '8.1', '>=')) : ?>
                            <span class="fp-status-ok fp-status-icon"><?php _e('Compatible', 'fp-esperienze'); ?></span>
                        <?php else : ?>
                            <span class="fp-status-error fp-status-icon"><?php _e('Requires PHP 8.1+', 'fp-esperienze'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Plugin Version', 'fp-esperienze'); ?></th>
                    <td><?php echo esc_html(FP_ESPERIENZE_VERSION); ?></td>
                </tr>
                <tr>
                    <th><?php _e('WordPress Timezone', 'fp-esperienze'); ?></th>
                    <td>
                        <?php 
                        $timezone = get_option('timezone_string');
                        if (empty($timezone)) {
                            $offset = get_option('gmt_offset');
                            // Handle decimal offsets (e.g., 5.5 for IST)
                            if (is_numeric($offset)) {
                                if (fmod($offset, 1) == 0) {
                                    echo sprintf('UTC%+d', (int)$offset);
                                } else {
                                    echo sprintf('UTC%+.1f', (float)$offset);
                                }
                            } else {
                                echo 'UTC+0';
                            }
                        } else {
                            echo esc_html($timezone);
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Run system checks
     */
    private function runSystemChecks(): array {
        $checks = [];

        // Check database tables
        $checks['database_tables'] = $this->checkDatabaseTables();

        // Check WordPress cron
        $checks['wp_cron'] = $this->checkWordPressCron();

        // Check remote requests
        $checks['remote_requests'] = $this->checkRemoteRequests();

        // Check file permissions
        $checks['file_permissions'] = $this->checkFilePermissions();

        // Check required PHP extensions
        $checks['php_extensions'] = $this->checkPHPExtensions();

        // Enhanced performance and health checks
        $checks['cache_performance'] = $this->checkCachePerformance();
        $checks['api_endpoints'] = $this->checkAPIEndpoints();
        $checks['database_performance'] = $this->checkDatabasePerformance();
        $checks['memory_usage'] = $this->checkMemoryUsage();
        $checks['frontend_performance'] = $this->checkFrontendPerformance();

        return $checks;
    }

    /**
     * Render system checks
     */
    private function renderChecks(array $checks): void {
        ?>
        <div class="fp-status-section">
            <h2><?php _e('System Checks', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <?php foreach ($checks as $check_name => $check) : ?>
                    <tr>
                        <th><?php echo esc_html($check['title']); ?></th>
                        <td>
                            <span class="fp-status-<?php echo esc_attr($check['status']); ?> fp-status-icon">
                                <?php echo esc_html($check['message']); ?>
                            </span>
                            <?php if (!empty($check['action'])) : ?>
                                <a href="<?php echo esc_url($check['action']['url']); ?>" 
                                   class="button button-secondary fp-fix-button">
                                    <?php echo esc_html($check['action']['label']); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($check['description'])) : ?>
                                <p class="description"><?php echo esc_html($check['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Check database tables
     */
    private function checkDatabaseTables(): array {
        global $wpdb;

        $required_tables = [
            'fp_meeting_points',
            'fp_extras',
            'fp_product_extras',
            'fp_schedules',
            'fp_overrides',
            'fp_bookings',
            'fp_exp_vouchers',
            'fp_vouchers'
        ];

        $missing_tables = [];
        foreach ($required_tables as $table) {
            $full_table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) !== $full_table_name) {
                $missing_tables[] = $table;
            }
        }

        if (empty($missing_tables)) {
            return [
                'title' => __('Database Tables', 'fp-esperienze'),
                'status' => 'ok',
                'message' => __('All required tables present', 'fp-esperienze')
            ];
        }

        return [
            'title' => __('Database Tables', 'fp-esperienze'),
            'status' => 'error',
            'message' => sprintf(__('%d missing tables', 'fp-esperienze'), count($missing_tables)),
            'description' => __('Missing tables: ', 'fp-esperienze') . implode(', ', $missing_tables),
            'action' => [
                'label' => __('Create Tables', 'fp-esperienze'),
                'url' => wp_nonce_url(
                    admin_url('admin.php?page=fp-esperienze-system-status&action=create_tables'),
                    'fp_system_status_fix_create_tables'
                )
            ]
        ];
    }

    /**
     * Check WordPress cron
     */
    private function checkWordPressCron(): array {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            return [
                'title' => __('WordPress Cron', 'fp-esperienze'),
                'status' => 'warning',
                'message' => __('Disabled via DISABLE_WP_CRON', 'fp-esperienze'),
                'description' => __('WordPress cron is disabled. Scheduled tasks may not work properly.', 'fp-esperienze')
            ];
        }

        return [
            'title' => __('WordPress Cron', 'fp-esperienze'),
            'status' => 'ok',
            'message' => __('Enabled', 'fp-esperienze')
        ];
    }

    /**
     * Check remote requests capability
     */
    private function checkRemoteRequests(): array {
        $test_url = 'https://httpbin.org/get';
        $response = wp_remote_get($test_url, [
            'timeout' => 10,
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            return [
                'title' => __('Remote Requests', 'fp-esperienze'),
                'status' => 'error',
                'message' => __('Failed', 'fp-esperienze'),
                'description' => $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return [
                'title' => __('Remote Requests', 'fp-esperienze'),
                'status' => 'warning',
                'message' => sprintf(__('Unexpected response code: %d', 'fp-esperienze'), $response_code)
            ];
        }

        return [
            'title' => __('Remote Requests', 'fp-esperienze'),
            'status' => 'ok',
            'message' => __('Working', 'fp-esperienze')
        ];
    }

    /**
     * Check file permissions
     */
    private function checkFilePermissions(): array {
        $upload_dir = wp_upload_dir();
        $uploads_writable = is_writable($upload_dir['basedir']);

        if (!$uploads_writable) {
            return [
                'title' => __('File Permissions', 'fp-esperienze'),
                'status' => 'error',
                'message' => __('Uploads directory not writable', 'fp-esperienze'),
                'description' => __('PDF vouchers cannot be generated without write access to uploads directory.', 'fp-esperienze')
            ];
        }

        return [
            'title' => __('File Permissions', 'fp-esperienze'),
            'status' => 'ok',
            'message' => __('Uploads directory writable', 'fp-esperienze')
        ];
    }

    /**
     * Check required PHP extensions
     */
    private function checkPHPExtensions(): array {
        $required_extensions = ['gd', 'mbstring', 'curl'];
        $missing_extensions = [];

        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }

        if (!empty($missing_extensions)) {
            return [
                'title' => __('PHP Extensions', 'fp-esperienze'),
                'status' => 'error',
                'message' => sprintf(__('%d missing extensions', 'fp-esperienze'), count($missing_extensions)),
                'description' => __('Missing extensions: ', 'fp-esperienze') . implode(', ', $missing_extensions)
            ];
        }

        return [
            'title' => __('PHP Extensions', 'fp-esperienze'),
            'status' => 'ok',
            'message' => __('All required extensions loaded', 'fp-esperienze')
        ];
    }

    /**
     * Render database information
     */
    private function renderDatabaseInfo(): void {
        global $wpdb;

        $tables = [
            'Meeting Points' => $wpdb->prefix . 'fp_meeting_points',
            'Extras' => $wpdb->prefix . 'fp_extras',
            'Product Extras' => $wpdb->prefix . 'fp_product_extras',
            'Schedules' => $wpdb->prefix . 'fp_schedules',
            'Overrides' => $wpdb->prefix . 'fp_overrides',
            'Bookings' => $wpdb->prefix . 'fp_bookings',
            'Experience Vouchers' => $wpdb->prefix . 'fp_exp_vouchers',
            'Legacy Vouchers' => $wpdb->prefix . 'fp_vouchers'
        ];

        ?>
        <div class="fp-status-section">
            <h2><?php _e('Database Information', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <?php foreach ($tables as $name => $table) : ?>
                    <?php 
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
                    $count = $exists ? $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}`")) : 0;
                    ?>
                    <tr>
                        <th><?php echo esc_html($name); ?></th>
                        <td>
                            <?php if ($exists) : ?>
                                <span class="fp-status-ok fp-status-icon">
                                    <?php printf(__('%d records', 'fp-esperienze'), $count); ?>
                                </span>
                            <?php else : ?>
                                <span class="fp-status-error fp-status-icon"><?php _e('Table missing', 'fp-esperienze'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Render integration status
     */
    private function renderIntegrationStatus(): void {
        $integrations = get_option('fp_esperienze_integrations', []);

        $integration_checks = [
            'Google Analytics 4' => [
                'key' => 'ga4_measurement_id',
                'docs' => 'https://support.google.com/analytics/answer/9539598'
            ],
            'Google Ads' => [
                'key' => 'gads_conversion_id',
                'docs' => 'https://support.google.com/google-ads/answer/2684489'
            ],
            'Meta Pixel' => [
                'key' => 'meta_pixel_id',
                'docs' => 'https://www.facebook.com/business/help/952192354843755'
            ],
            'Brevo API' => [
                'key' => 'brevo_api_key',
                'docs' => 'https://developers.brevo.com/docs/getting-started'
            ],
            'Google Places' => [
                'key' => 'gplaces_api_key',
                'docs' => 'https://developers.google.com/maps/documentation/places/web-service/get-api-key'
            ]
        ];

        ?>
        <div class="fp-status-section">
            <h2><?php _e('Integration Status', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <?php foreach ($integration_checks as $name => $config) : ?>
                    <?php $is_configured = !empty($integrations[$config['key']]); ?>
                    <tr>
                        <th><?php echo esc_html($name); ?></th>
                        <td>
                            <?php if ($is_configured) : ?>
                                <span class="fp-status-ok fp-status-icon"><?php _e('Configured', 'fp-esperienze'); ?></span>
                            <?php else : ?>
                                <span class="fp-status-warning fp-status-icon"><?php _e('Not configured', 'fp-esperienze'); ?></span>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-settings&tab=integrations')); ?>" 
                                   class="button button-secondary fp-fix-button">
                                    <?php _e('Configure', 'fp-esperienze'); ?>
                                </a>
                                <a href="<?php echo esc_url($config['docs']); ?>" 
                                   class="button button-secondary fp-fix-button" target="_blank">
                                    <?php _e('Documentation', 'fp-esperienze'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Check cache performance and statistics
     */
    private function checkCachePerformance(): array {
        if (!class_exists('\FP\Esperienze\Core\CacheManager')) {
            return [
                'title' => __('Cache Performance', 'fp-esperienze'),
                'status' => 'warning',
                'message' => __('CacheManager not available', 'fp-esperienze')
            ];
        }

        $cache_stats = \FP\Esperienze\Core\CacheManager::getCacheStats();
        $total_caches = $cache_stats['total_caches'] ?? 0;
        
        // Test cache write/read performance
        $test_key = 'fp_system_test_' . time();
        $test_data = ['test' => 'performance', 'timestamp' => time()];
        
        $start_time = microtime(true);
        set_transient($test_key, $test_data, 60);
        $cached_data = get_transient($test_key);
        $cache_time = (microtime(true) - $start_time) * 1000;
        delete_transient($test_key);
        
        if ($cached_data !== $test_data) {
            return [
                'title' => __('Cache Performance', 'fp-esperienze'),
                'status' => 'error',
                'message' => __('Cache read/write failed', 'fp-esperienze'),
                'description' => __('Object caching is not working properly.', 'fp-esperienze')
            ];
        }

        if ($cache_time > 10) { // 10ms threshold
            return [
                'title' => __('Cache Performance', 'fp-esperienze'),
                'status' => 'warning',
                'message' => sprintf(__('Slow cache (%.2fms)', 'fp-esperienze'), $cache_time),
                'description' => sprintf(__('%d total caches. Consider using Redis or Memcached for better performance.', 'fp-esperienze'), $total_caches)
            ];
        }

        return [
            'title' => __('Cache Performance', 'fp-esperienze'),
            'status' => 'ok',
            'message' => sprintf(__('Good (%.2fms, %d caches)', 'fp-esperienze'), $cache_time, $total_caches)
        ];
    }

    /**
     * Check API endpoints health
     */
    private function checkAPIEndpoints(): array {
        $endpoints_to_test = [
            '/wp-json/fp-exp/v1/availability' => 'Availability API',
            '/wp-json/fp-esperienze/v1/ics/product/1' => 'ICS API',
            '/wp-json/fp-esperienze/v1/events' => 'Events API'
        ];

        $failed_endpoints = [];
        $slow_endpoints = [];
        $total_time = 0;

        foreach ($endpoints_to_test as $endpoint => $name) {
            $url = home_url($endpoint . '?test=1');
            $start_time = microtime(true);
            
            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'FP-Esperienze-SystemCheck/1.0'
                ]
            ]);
            
            $response_time = (microtime(true) - $start_time) * 1000;
            $total_time += $response_time;

            if (is_wp_error($response)) {
                $failed_endpoints[] = $name;
                continue;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code >= 400) {
                $failed_endpoints[] = $name . " (HTTP {$status_code})";
                continue;
            }

            if ($response_time > 1000) { // 1 second threshold
                $slow_endpoints[] = $name . sprintf(' (%.0fms)', $response_time);
            }
        }

        if (!empty($failed_endpoints)) {
            return [
                'title' => __('API Endpoints', 'fp-esperienze'),
                'status' => 'error',
                'message' => sprintf(__('%d endpoints failed', 'fp-esperienze'), count($failed_endpoints)),
                'description' => __('Failed endpoints: ', 'fp-esperienze') . implode(', ', $failed_endpoints)
            ];
        }

        if (!empty($slow_endpoints)) {
            return [
                'title' => __('API Endpoints', 'fp-esperienze'),
                'status' => 'warning',
                'message' => sprintf(__('%d slow endpoints', 'fp-esperienze'), count($slow_endpoints)),
                'description' => __('Slow endpoints: ', 'fp-esperienze') . implode(', ', $slow_endpoints)
            ];
        }

        $avg_time = $total_time / count($endpoints_to_test);
        return [
            'title' => __('API Endpoints', 'fp-esperienze'),
            'status' => 'ok',
            'message' => sprintf(__('All endpoints healthy (avg %.0fms)', 'fp-esperienze'), $avg_time)
        ];
    }

    /**
     * Check database performance
     */
    private function checkDatabasePerformance(): array {
        global $wpdb;

        $start_time = microtime(true);
        $start_queries = $wpdb->num_queries;

        // Test common FP Esperienze database operations
        $test_queries = [
            "SELECT COUNT(*) FROM {$wpdb->prefix}fp_bookings WHERE status = 'confirmed'",
            "SELECT COUNT(*) FROM {$wpdb->prefix}fp_schedules",
            "SELECT COUNT(*) FROM {$wpdb->prefix}fp_meeting_points WHERE is_active = 1"
        ];

        $total_rows = 0;
        foreach ($test_queries as $query) {
            $result = $wpdb->get_var($query);
            $total_rows += (int) $result;
        }

        $execution_time = (microtime(true) - $start_time) * 1000;
        $query_count = $wpdb->num_queries - $start_queries;

        // Check for slow queries if QueryMonitor is available
        $slow_query_warning = '';
        if (class_exists('\FP\Esperienze\Core\QueryMonitor')) {
            $stats = \FP\Esperienze\Core\QueryMonitor::getStatistics();
            if (isset($stats['slow_queries']) && $stats['slow_queries'] > 0) {
                $slow_query_warning = sprintf(__(' (%d slow queries detected)', 'fp-esperienze'), $stats['slow_queries']);
            }
        }

        if ($execution_time > 100) { // 100ms threshold
            return [
                'title' => __('Database Performance', 'fp-esperienze'),
                'status' => 'warning',
                'message' => sprintf(__('Slow queries (%.2fms)', 'fp-esperienze'), $execution_time),
                'description' => sprintf(__('%d queries returned %d total rows%s', 'fp-esperienze'), $query_count, $total_rows, $slow_query_warning)
            ];
        }

        return [
            'title' => __('Database Performance', 'fp-esperienze'),
            'status' => 'ok',
            'message' => sprintf(__('Good performance (%.2fms)', 'fp-esperienze'), $execution_time),
            'description' => sprintf(__('%d queries, %d total rows%s', 'fp-esperienze'), $query_count, $total_rows, $slow_query_warning)
        ];
    }

    /**
     * Check memory usage
     */
    private function checkMemoryUsage(): array {
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        
        $usage_percentage = ($memory_usage / $memory_limit) * 100;
        $peak_percentage = ($memory_peak / $memory_limit) * 100;

        if ($peak_percentage > 80) {
            return [
                'title' => __('Memory Usage', 'fp-esperienze'),
                'status' => 'error',
                'message' => sprintf(__('High usage (%.1f%%)', 'fp-esperienze'), $peak_percentage),
                'description' => sprintf(__('Peak: %s / %s. Consider increasing memory_limit.', 'fp-esperienze'), 
                    size_format($memory_peak), size_format($memory_limit))
            ];
        }

        if ($peak_percentage > 60) {
            return [
                'title' => __('Memory Usage', 'fp-esperienze'),
                'status' => 'warning',
                'message' => sprintf(__('Moderate usage (%.1f%%)', 'fp-esperienze'), $peak_percentage),
                'description' => sprintf(__('Peak: %s / %s. Monitor for potential issues.', 'fp-esperienze'), 
                    size_format($memory_peak), size_format($memory_limit))
            ];
        }

        return [
            'title' => __('Memory Usage', 'fp-esperienze'),
            'status' => 'ok',
            'message' => sprintf(__('Normal usage (%.1f%%)', 'fp-esperienze'), $peak_percentage),
            'description' => sprintf(__('Current: %s, Peak: %s / %s', 'fp-esperienze'), 
                size_format($memory_usage), size_format($memory_peak), size_format($memory_limit))
        ];
    }

    /**
     * Check frontend performance indicators
     */
    private function checkFrontendPerformance(): array {
        // Check if AssetOptimizer is available and working
        $minified_available = false;
        $compression_ratio = 0;

        if (class_exists('\FP\Esperienze\Core\AssetOptimizer')) {
            $minified_available = \FP\Esperienze\Core\AssetOptimizer::hasMinifiedAssets();
            $stats = \FP\Esperienze\Core\AssetOptimizer::getOptimizationStats();
            $compression_ratio = $stats['compression_ratio'] ?? 0;
        }

        // Check asset file sizes
        $css_file = FP_ESPERIENZE_PLUGIN_DIR . 'assets/css/frontend.css';
        $js_file = FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/frontend.js';
        $admin_js_file = FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin.js';

        $total_size = 0;
        $files_checked = 0;
        
        foreach ([$css_file, $js_file, $admin_js_file] as $file) {
            if (file_exists($file)) {
                $total_size += filesize($file);
                $files_checked++;
            }
        }

        if ($total_size > 500000) { // 500KB threshold
            $status = 'warning';
            $message = sprintf(__('Large assets (%s)', 'fp-esperienze'), size_format($total_size));
            $description = $minified_available ? 
                sprintf(__('Minification enabled (%.1f%% compression)', 'fp-esperienze'), $compression_ratio) :
                __('Consider enabling asset minification for better performance.', 'fp-esperienze');
        } else {
            $status = $minified_available ? 'ok' : 'warning';
            $message = sprintf(__('Assets: %s', 'fp-esperienze'), size_format($total_size));
            $description = $minified_available ?
                sprintf(__('Minification enabled (%.1f%% compression)', 'fp-esperienze'), $compression_ratio) :
                __('Asset minification not enabled.', 'fp-esperienze');
        }

        return [
            'title' => __('Frontend Performance', 'fp-esperienze'),
            'status' => $status,
            'message' => $message,
            'description' => $description
        ];
    }

    /**
     * Render performance metrics section
     */
    private function renderPerformanceMetrics(): void {
        // Collect real-time performance data
        $memory_usage = memory_get_usage(true);
        $memory_peak = memory_get_peak_usage(true);
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        
        // Get cache statistics if available
        $cache_stats = [];
        if (class_exists('\FP\Esperienze\Core\CacheManager')) {
            $cache_stats = \FP\Esperienze\Core\CacheManager::getCacheStats();
        }

        // Get query statistics if available
        $query_stats = [];
        if (class_exists('\FP\Esperienze\Core\QueryMonitor')) {
            $query_stats = \FP\Esperienze\Core\QueryMonitor::getStatistics();
        }

        ?>
        <div class="fp-status-section">
            <h2><?php _e('Performance Metrics', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <tr>
                    <th><?php _e('Memory Usage', 'fp-esperienze'); ?></th>
                    <td>
                        <?php 
                        $usage_percent = ($memory_usage / $memory_limit) * 100;
                        $peak_percent = ($memory_peak / $memory_limit) * 100;
                        $status_class = $peak_percent > 80 ? 'error' : ($peak_percent > 60 ? 'warning' : 'ok');
                        ?>
                        <span class="fp-status-<?php echo esc_attr($status_class); ?> fp-status-icon">
                            <?php printf(__('Current: %s (%.1f%%) | Peak: %s (%.1f%%)', 'fp-esperienze'), 
                                size_format($memory_usage), $usage_percent,
                                size_format($memory_peak), $peak_percent); ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($cache_stats)) : ?>
                <tr>
                    <th><?php _e('Cache Statistics', 'fp-esperienze'); ?></th>
                    <td>
                        <span class="fp-status-<?php echo $cache_stats['total_caches'] > 0 ? 'ok' : 'warning'; ?> fp-status-icon">
                            <?php printf(__('Total: %d | Availability: %d | Archive: %d', 'fp-esperienze'), 
                                $cache_stats['total_caches'],
                                $cache_stats['availability_caches'],
                                $cache_stats['archive_caches']); ?>
                        </span>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($query_stats)) : ?>
                <tr>
                    <th><?php _e('Database Queries', 'fp-esperienze'); ?></th>
                    <td>
                        <span class="fp-status-<?php echo $query_stats['slow_queries'] > 0 ? 'warning' : 'ok'; ?> fp-status-icon">
                            <?php printf(__('Total: %d | Slow: %d | Avg Time: %.2fms', 'fp-esperienze'), 
                                $query_stats['total_queries'],
                                $query_stats['slow_queries'],
                                $query_stats['total_queries'] > 0 ? $query_stats['total_time'] / $query_stats['total_queries'] : 0); ?>
                        </span>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php _e('PHP Configuration', 'fp-esperienze'); ?></th>
                    <td>
                        <?php 
                        $max_execution_time = ini_get('max_execution_time');
                        $upload_max_filesize = ini_get('upload_max_filesize');
                        $post_max_size = ini_get('post_max_size');
                        ?>
                        <span class="fp-status-ok fp-status-icon">
                            <?php printf(__('Execution: %ds | Upload: %s | Post: %s', 'fp-esperienze'), 
                                $max_execution_time, $upload_max_filesize, $post_max_size); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Server Load', 'fp-esperienze'); ?></th>
                    <td>
                        <?php 
                        $load_average = '';
                        if (function_exists('sys_getloadavg')) {
                            $load = sys_getloadavg();
                            $load_average = sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
                            $status_class = $load[0] > 2 ? 'warning' : 'ok';
                        } else {
                            $load_average = __('Not available', 'fp-esperienze');
                            $status_class = 'warning';
                        }
                        ?>
                        <span class="fp-status-<?php echo esc_attr($status_class); ?> fp-status-icon">
                            <?php echo esc_html($load_average); ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Render optimization recommendations
     */
    private function renderOptimizationRecommendations(array $checks): void {
        $recommendations = [];

        // Analyze checks and generate recommendations
        foreach ($checks as $check_name => $check) {
            if ($check['status'] === 'error') {
                switch ($check_name) {
                    case 'cache_performance':
                        $recommendations[] = [
                            'priority' => 'high',
                            'title' => __('Improve Cache Performance', 'fp-esperienze'),
                            'description' => __('Consider implementing Redis or Memcached for better caching performance.', 'fp-esperienze'),
                            'action' => __('Install Redis/Memcached plugin', 'fp-esperienze')
                        ];
                        break;
                    case 'api_endpoints':
                        $recommendations[] = [
                            'priority' => 'high',
                            'title' => __('Fix API Endpoints', 'fp-esperienze'),
                            'description' => __('Some API endpoints are not responding correctly. This may affect booking functionality.', 'fp-esperienze'),
                            'action' => __('Check server logs and debug failing endpoints', 'fp-esperienze')
                        ];
                        break;
                    case 'memory_usage':
                        $recommendations[] = [
                            'priority' => 'high',
                            'title' => __('Increase Memory Limit', 'fp-esperienze'),
                            'description' => __('High memory usage detected. Increase PHP memory_limit to prevent issues.', 'fp-esperienze'),
                            'action' => __('Update php.ini or contact hosting provider', 'fp-esperienze')
                        ];
                        break;
                }
            } elseif ($check['status'] === 'warning') {
                switch ($check_name) {
                    case 'database_performance':
                        $recommendations[] = [
                            'priority' => 'medium',
                            'title' => __('Optimize Database Queries', 'fp-esperienze'),
                            'description' => __('Slow database queries detected. Consider adding indexes or optimizing queries.', 'fp-esperienze'),
                            'action' => __('Review Query Monitor logs and optimize slow queries', 'fp-esperienze')
                        ];
                        break;
                    case 'frontend_performance':
                        $recommendations[] = [
                            'priority' => 'medium',
                            'title' => __('Enable Asset Optimization', 'fp-esperienze'),
                            'description' => __('Asset minification is not enabled. This can improve page load times.', 'fp-esperienze'),
                            'action' => __('Go to Performance Settings and enable asset optimization', 'fp-esperienze')
                        ];
                        break;
                    case 'cache_performance':
                        $recommendations[] = [
                            'priority' => 'low',
                            'title' => __('Monitor Cache Performance', 'fp-esperienze'),
                            'description' => __('Cache performance is slower than optimal but still functional.', 'fp-esperienze'),
                            'action' => __('Monitor cache hit ratios and consider upgrading caching solution', 'fp-esperienze')
                        ];
                        break;
                }
            }
        }

        // Add general performance recommendations
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        if ($memory_limit < 256 * 1024 * 1024) { // Less than 256MB
            $recommendations[] = [
                'priority' => 'medium',
                'title' => __('Increase PHP Memory Limit', 'fp-esperienze'),
                'description' => sprintf(__('Current memory limit is %s. For optimal performance, consider increasing to at least 256MB.', 'fp-esperienze'), size_format($memory_limit)),
                'action' => __('Update memory_limit in php.ini', 'fp-esperienze')
            ];
        }

        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'low',
                'title' => __('System Running Optimally', 'fp-esperienze'),
                'description' => __('All systems are functioning well. Continue monitoring performance regularly.', 'fp-esperienze'),
                'action' => __('Schedule regular performance reviews', 'fp-esperienze')
            ];
        }

        if (!empty($recommendations)) :
        ?>
        <div class="fp-status-section">
            <h2><?php _e('Optimization Recommendations', 'fp-esperienze'); ?></h2>
            <div class="fp-recommendations">
                <?php foreach ($recommendations as $rec) : ?>
                    <div class="fp-recommendation fp-priority-<?php echo esc_attr($rec['priority']); ?>">
                        <h4>
                            <span class="fp-priority-badge fp-priority-<?php echo esc_attr($rec['priority']); ?>">
                                <?php echo esc_html(strtoupper($rec['priority'])); ?>
                            </span>
                            <?php echo esc_html($rec['title']); ?>
                        </h4>
                        <p><?php echo esc_html($rec['description']); ?></p>
                        <p class="fp-action"><strong><?php _e('Action:', 'fp-esperienze'); ?></strong> <?php echo esc_html($rec['action']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        endif;
    }
}