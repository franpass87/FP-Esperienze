<?php
/**
 * System Status
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\Installer;
use FP\Esperienze\Core\ProductionValidator;

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
                wp_safe_redirect(admin_url('admin.php?page=fp-esperienze-system-status&fixed=rewrite'));
                exit;
                break;
        }
    }

    /**
     * Create missing database tables
     */
    private function createMissingTables(): void {
        Installer::activate();
        wp_safe_redirect(admin_url('admin.php?page=fp-esperienze-system-status&fixed=tables'));
        exit;
    }

    /**
     * System status page
     */
    public function systemStatusPage(): void {
        $checks               = $this->runSystemChecks();
        $production_readiness = ProductionValidator::validateProductionReadiness();

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
                <?php $this->renderChecks($checks); ?>
                <?php $this->renderProductionReadiness($production_readiness); ?>
                <?php $this->renderDependencyStatus(); ?>
                <?php $this->renderDatabaseInfo(); ?>
                <?php $this->renderIntegrationStatus(); ?>
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
            </style>
        </div>
        <?php
    }

    /**
     * Render optional dependency status summary.
     */
    private function renderDependencyStatus(): void {
        if (!class_exists(DependencyChecker::class)) {
            return;
        }

        $dependencies = DependencyChecker::checkAll();

        ?>
        <div class="fp-status-section">
            <h2><?php esc_html_e('Optional Dependencies', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <tbody>
                <?php foreach ($dependencies as $dependency) :
                    $status_class = !empty($dependency['available']) ? 'fp-status-ok' : 'fp-status-warning';
                    ?>
                    <tr>
                        <th><?php echo esc_html($dependency['name'] ?? ''); ?></th>
                        <td>
                            <span class="fp-status-icon <?php echo esc_attr($status_class); ?>">
                                <?php echo !empty($dependency['available']) ? esc_html__('Available', 'fp-esperienze') : esc_html__('Missing', 'fp-esperienze'); ?>
                            </span>
                            <?php if (!empty($dependency['description'])) : ?>
                                <div><?php echo esc_html($dependency['description']); ?></div>
                            <?php endif; ?>
                            <?php if (empty($dependency['available']) && !empty($dependency['impact'])) : ?>
                                <div><em><?php echo esc_html($dependency['impact']); ?></em></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            $instructions = DependencyChecker::getInstallationInstructions();
            if (!empty($instructions)) {
                echo wp_kses_post($instructions);
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render production readiness report
     */
    private function renderProductionReadiness(array $results): void {
        $status_map = [
            'pass'    => [
                'class' => 'fp-status-ok',
                'label' => __('Ready for production', 'fp-esperienze'),
            ],
            'warning' => [
                'class' => 'fp-status-warning',
                'label' => __('Warnings detected', 'fp-esperienze'),
            ],
            'fail'    => [
                'class' => 'fp-status-error',
                'label' => __('Action required', 'fp-esperienze'),
            ],
        ];

        $status       = $results['overall_status'] ?? 'warning';
        $status_class = $status_map[$status]['class'] ?? 'fp-status-warning';
        $status_label = $status_map[$status]['label'] ?? __('Warnings detected', 'fp-esperienze');

        $critical = $results['critical_issues'] ?? [];
        $warnings = $results['warnings'] ?? [];
        $checks   = $results['checks'] ?? [];

        ?>
        <div class="fp-status-section">
            <h2><?php esc_html_e('Production Readiness', 'fp-esperienze'); ?></h2>
            <table class="fp-status-table">
                <tr>
                    <th><?php esc_html_e('Overall Status', 'fp-esperienze'); ?></th>
                    <td>
                        <span class="<?php echo esc_attr($status_class); ?> fp-status-icon">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($critical)) : ?>
                    <tr>
                        <th><?php esc_html_e('Critical Issues', 'fp-esperienze'); ?></th>
                        <td>
                            <ul>
                                <?php foreach ($critical as $issue) : ?>
                                    <li class="fp-status-error fp-status-icon"><?php echo esc_html($issue); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($warnings)) : ?>
                    <tr>
                        <th><?php esc_html_e('Warnings', 'fp-esperienze'); ?></th>
                        <td>
                            <ul>
                                <?php foreach ($warnings as $warning) : ?>
                                    <li class="fp-status-warning fp-status-icon"><?php echo esc_html($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($checks)) : ?>
                    <tr>
                        <th><?php esc_html_e('Checks Performed', 'fp-esperienze'); ?></th>
                        <td>
                            <ul>
                                <?php foreach ($checks as $check) : ?>
                                    <li><?php echo esc_html($check); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
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
        $response = wp_safe_remote_get($test_url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $description   = $error_message;

            if (false !== strpos(strtolower($error_message), 'ssl') || false !== strpos(strtolower($error_message), 'certificate')) {
                $description = __('SSL certificate validation failed. Please verify your server configuration.', 'fp-esperienze');
            }

            return [
                'title' => __('Remote Requests', 'fp-esperienze'),
                'status' => 'error',
                'message' => __('Failed', 'fp-esperienze'),
                'description' => $description
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
            'Legacy Vouchers' => $wpdb->prefix . 'fp_vouchers',
            'Analytics Events' => $wpdb->prefix . 'fp_analytics_events'
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
}