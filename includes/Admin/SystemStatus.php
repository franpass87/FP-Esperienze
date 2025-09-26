<?php
/**
 * System Status
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\Installer;
use FP\Esperienze\Core\ProductionValidator;
use FP\Esperienze\Admin\UI\AdminComponents;

defined('ABSPATH') || exit;

/**
 * System Status class for checking plugin health and requirements
 */
class SystemStatus {

    /**
     * Constructor
     */
    public function __construct() {
        MenuRegistry::instance()->registerPage([
            'slug'       => 'fp-esperienze-status',
            'page_title' => __('Status & Troubleshooting', 'fp-esperienze'),
            'menu_title' => __('Status & Troubleshooting', 'fp-esperienze'),
            'capability' => 'manage_options',
            'callback'   => [$this, 'systemStatusPage'],
            'order'      => 140,
            'aliases'    => ['fp-esperienze-system-status'],
        ]);

        add_action('admin_init', [$this, 'handleFixActions']);
    }

    /**
     * Handle fix actions
     */
    public function handleFixActions(): void {
        $requested_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $valid_pages = ['fp-esperienze-status', 'fp-esperienze-system-status'];

        if (!in_array($requested_page, $valid_pages, true)) {
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
                wp_safe_redirect(admin_url('admin.php?page=fp-esperienze-status&fixed=rewrite'));
                exit;
                break;
        }
    }

    /**
     * Create missing database tables
     */
    private function createMissingTables(): void {
        Installer::activate();
        wp_safe_redirect(admin_url('admin.php?page=fp-esperienze-status&fixed=tables'));
        exit;
    }

    /**
     * System status page
     */
    public function systemStatusPage(): void {
        $checks = $this->runSystemChecks();
        $production_readiness = null;

        if (class_exists(ProductionValidator::class) && method_exists(ProductionValidator::class, 'validateProductionReadiness')) {
            try {
                $production_readiness = ProductionValidator::validateProductionReadiness();
            } catch (\Throwable $exception) {
                $production_readiness = [
                    'overall_status'  => 'warning',
                    'critical_issues' => [],
                    'warnings'        => [
                        __('Unable to calculate production readiness. Check the error logs for more details.', 'fp-esperienze'),
                    ],
                    'checks'          => [],
                ];
            }
        }

        $fixed_message = '';
        if (isset($_GET['fixed'])) {
            $fixed = sanitize_text_field($_GET['fixed']);
            if ($fixed === 'tables') {
                $fixed_message = __('Database tables have been created successfully.', 'fp-esperienze');
            } elseif ($fixed === 'rewrite') {
                $fixed_message = __('Rewrite rules have been flushed successfully.', 'fp-esperienze');
            }
        }

        ?>
        <?php AdminComponents::skipLink('fp-admin-main-content'); ?>
        <div class="wrap fp-admin-page" id="fp-admin-main-content" tabindex="-1">
            <?php
            AdminComponents::pageHeader([
                'title' => __('FP Esperienze System Status', 'fp-esperienze'),
                'lead'  => __('Review environment readiness, scheduled tasks, and integration health to keep bookings flowing.', 'fp-esperienze'),
            ]);
            ?>

            <div class="fp-admin-stack">
                <?php if ($fixed_message !== '') : ?>
                    <?php
                    AdminComponents::notice([
                        'type'    => 'success',
                        'message' => $fixed_message,
                    ]);
                    ?>
                <?php endif; ?>

                <?php $this->renderSystemInfo(); ?>
                <?php $this->renderChecks($checks); ?>
                <?php if (!empty($production_readiness)) : ?>
                    <?php $this->renderProductionReadiness($production_readiness); ?>
                <?php endif; ?>
                <?php $this->renderDependencyStatus(); ?>
                <?php $this->renderDatabaseInfo(); ?>
                <?php $this->renderIntegrationStatus(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render optional dependency status summary.
     */
    private function renderDependencyStatus(): void {
        $dependency_checker_class = '\FP\Esperienze\Admin\DependencyChecker';

        if (!class_exists($dependency_checker_class)) {
            return;
        }

        $dependencies = $dependency_checker_class::checkAll();

        AdminComponents::openCard([
            'title' => __('Optional Dependencies', 'fp-esperienze'),
        ]);

        if (empty($dependencies)) {
            echo '<p class="fp-admin-helper-text">' . esc_html__('No optional dependencies detected.', 'fp-esperienze') . '</p>';
            AdminComponents::closeCard();
            return;
        }

        $missing = array_filter($dependencies, static function ($dependency) {
            return empty($dependency['available']);
        });

        echo '<ul class="fp-admin-dependency-list">';

        foreach ($dependencies as $dependency) {
            $available = ! empty($dependency['available']);
            $status_key = isset($dependency['status']) ? (string) $dependency['status'] : '';

            switch ($status_key) {
                case 'success':
                    $variant = 'success';
                    break;
                case 'warning':
                    $variant = 'warning';
                    break;
                case 'danger':
                case 'error':
                    $variant = 'danger';
                    break;
                default:
                    $variant = $available ? 'success' : 'warning';
                    break;
            }

            $status_label = $available
                ? __('Available', 'fp-esperienze')
                : __('Missing', 'fp-esperienze');

            $name = isset($dependency['name']) ? (string) $dependency['name'] : '';
            $description = isset($dependency['description']) ? (string) $dependency['description'] : '';
            $impact = isset($dependency['impact']) ? (string) $dependency['impact'] : '';

            echo '<li class="fp-admin-dependency">';
            echo '<div class="fp-admin-dependency__header">';
            printf(
                '<span class="fp-admin-badge fp-admin-badge--%1$s">%2$s</span>',
                esc_attr($variant),
                esc_html($status_label)
            );

            if ($name !== '') {
                printf('<span class="fp-admin-dependency__name">%s</span>', esc_html($name));
            }
            echo '</div>';

            if ($description !== '') {
                printf('<p class="fp-admin-helper-text">%s</p>', esc_html($description));
            }

            if (! $available && $impact !== '') {
                printf('<p class="fp-admin-helper-text fp-admin-dependency__impact">%s</p>', esc_html($impact));
            }

            echo '</li>';
        }

        echo '</ul>';

        $instructions = method_exists($dependency_checker_class, 'getInstallationInstructions')
            ? $dependency_checker_class::getInstallationInstructions()
            : '';

        if (! empty($missing) && ! empty($instructions)) {
            echo '<div class="fp-admin-helper-text fp-admin-dependency__instructions">' . wp_kses_post($instructions) . '</div>';
        } elseif (empty($missing)) {
            echo '<p class="fp-admin-helper-text fp-admin-dependency__success">' . esc_html__('All optional dependencies are installed. Great job!', 'fp-esperienze') . '</p>';
        }

        AdminComponents::closeCard();
    }

    /**
     * Render production readiness report
     */
    private function renderProductionReadiness(array $results): void {
        $status_map = [
            'pass'    => [
                'variant' => 'success',
                'label'   => __('Ready for production', 'fp-esperienze'),
            ],
            'warning' => [
                'variant' => 'warning',
                'label'   => __('Warnings detected', 'fp-esperienze'),
            ],
            'fail'    => [
                'variant' => 'danger',
                'label'   => __('Action required', 'fp-esperienze'),
            ],
        ];

        $status = $results['overall_status'] ?? 'warning';
        $variant = $status_map[$status]['variant'] ?? 'warning';
        $status_label = $status_map[$status]['label'] ?? __('Warnings detected', 'fp-esperienze');

        $critical = $results['critical_issues'] ?? [];
        $warnings = $results['warnings'] ?? [];
        $checks   = $results['checks'] ?? [];

        AdminComponents::openCard([
            'title' => __('Production Readiness', 'fp-esperienze'),
        ]);
        ?>
        <table class="fp-admin-table">
            <tbody>
                <tr>
                    <th><?php esc_html_e('Overall Status', 'fp-esperienze'); ?></th>
                    <td>
                        <span class="fp-admin-badge fp-admin-badge--<?php echo esc_attr($variant); ?>">
                            <?php echo esc_html($status_label); ?>
                        </span>
                    </td>
                </tr>
                <?php if (!empty($critical)) : ?>
                    <tr>
                        <th><?php esc_html_e('Critical Issues', 'fp-esperienze'); ?></th>
                        <td>
                            <ul class="fp-admin-status-list">
                                <?php foreach ($critical as $issue) : ?>
                                    <li><?php echo esc_html($issue); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($warnings)) : ?>
                    <tr>
                        <th><?php esc_html_e('Warnings', 'fp-esperienze'); ?></th>
                        <td>
                            <ul class="fp-admin-status-list">
                                <?php foreach ($warnings as $warning) : ?>
                                    <li><?php echo esc_html($warning); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($checks)) : ?>
                    <tr>
                        <th><?php esc_html_e('Checks Performed', 'fp-esperienze'); ?></th>
                        <td>
                            <ul class="fp-admin-status-list">
                                <?php foreach ($checks as $check) : ?>
                                    <li><?php echo esc_html($check); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
        AdminComponents::closeCard();
    }

    /**
     * Render system information
     */
    private function renderSystemInfo(): void {
        global $wp_version;

        AdminComponents::openCard([
            'title' => __('System Information', 'fp-esperienze'),
        ]);
        ?>
        <table class="fp-admin-table">
            <tbody>
                <tr>
                    <th><?php _e('WordPress Version', 'fp-esperienze'); ?></th>
                    <td>
                        <?php echo esc_html($wp_version); ?>
                        <?php if (version_compare($wp_version, '6.5', '>=')) : ?>
                            <span class="fp-admin-badge fp-admin-badge--success"><?php _e('Compatible', 'fp-esperienze'); ?></span>
                        <?php else : ?>
                            <span class="fp-admin-badge fp-admin-badge--danger"><?php _e('Requires WordPress 6.5+', 'fp-esperienze'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('WooCommerce Version', 'fp-esperienze'); ?></th>
                    <td>
                        <?php if (defined('WC_VERSION')) : ?>
                            <?php echo esc_html(WC_VERSION); ?>
                            <?php if (version_compare(WC_VERSION, '8.0', '>=')) : ?>
                                <span class="fp-admin-badge fp-admin-badge--success"><?php _e('Compatible', 'fp-esperienze'); ?></span>
                            <?php else : ?>
                                <span class="fp-admin-badge fp-admin-badge--danger"><?php _e('Requires WooCommerce 8.0+', 'fp-esperienze'); ?></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="fp-admin-badge fp-admin-badge--danger"><?php _e('WooCommerce not detected', 'fp-esperienze'); ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('PHP Version', 'fp-esperienze'); ?></th>
                    <td>
                        <?php echo esc_html(PHP_VERSION); ?>
                        <?php if (version_compare(PHP_VERSION, '8.1', '>=')) : ?>
                            <span class="fp-admin-badge fp-admin-badge--success"><?php _e('Compatible', 'fp-esperienze'); ?></span>
                        <?php else : ?>
                            <span class="fp-admin-badge fp-admin-badge--danger"><?php _e('Requires PHP 8.1+', 'fp-esperienze'); ?></span>
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
                            if (is_numeric($offset)) {
                                if (fmod($offset, 1) == 0) {
                                    echo esc_html(sprintf('UTC%+d', (int) $offset));
                                } else {
                                    echo esc_html(sprintf('UTC%+.1f', (float) $offset));
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
            </tbody>
        </table>
        <?php
        AdminComponents::closeCard();
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
        AdminComponents::openCard([
            'title' => __('System Checks', 'fp-esperienze'),
        ]);
        ?>
        <table class="fp-admin-table">
            <tbody>
                <?php foreach ($checks as $check_name => $check) : ?>
                    <?php
                    $status = $check['status'] ?? 'warning';
                    $variant = 'warning';
                    if ($status === 'ok') {
                        $variant = 'success';
                    } elseif ($status === 'error') {
                        $variant = 'danger';
                    }
                    ?>
                    <tr>
                        <th><?php echo esc_html($check['title']); ?></th>
                        <td>
                            <span class="fp-admin-badge fp-admin-badge--<?php echo esc_attr($variant); ?>">
                                <?php echo esc_html($check['message']); ?>
                            </span>
                            <?php if (!empty($check['action'])) : ?>
                                <a href="<?php echo esc_url($check['action']['url']); ?>"
                                   class="button button-secondary fp-fix-button">
                                    <?php echo esc_html($check['action']['label']); ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($check['description'])) : ?>
                                <p class="fp-admin-helper-text"><?php echo esc_html($check['description']); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        AdminComponents::closeCard();
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
            'Analytics Events' => $wpdb->prefix . 'fp_analytics_events',
        ];

        AdminComponents::openCard([
            'title' => __('Database Information', 'fp-esperienze'),
        ]);
        ?>
        <table class="fp-admin-table">
            <tbody>
                <?php foreach ($tables as $name => $table) : ?>
                    <?php
                    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
                    $count = $exists ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}`")) : 0;
                    ?>
                    <tr>
                        <th><?php echo esc_html($name); ?></th>
                        <td>
                            <?php if ($exists) : ?>
                                <span class="fp-admin-badge fp-admin-badge--success">
                                    <?php printf(__('Records: %s', 'fp-esperienze'), number_format_i18n($count)); ?>
                                </span>
                            <?php else : ?>
                                <span class="fp-admin-badge fp-admin-badge--danger"><?php _e('Table missing', 'fp-esperienze'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        AdminComponents::closeCard();
    }

    /**
     * Render integration status
     */
    private function renderIntegrationStatus(): void {
        $integrations = get_option('fp_esperienze_integrations', []);

        $integration_checks = [
            'Google Analytics 4' => [
                'key' => 'ga4_measurement_id',
                'docs' => 'https://support.google.com/analytics/answer/9539598',
            ],
            'Google Ads' => [
                'key' => 'gads_conversion_id',
                'docs' => 'https://support.google.com/google-ads/answer/2684489',
            ],
            'Meta Pixel' => [
                'key' => 'meta_pixel_id',
                'docs' => 'https://www.facebook.com/business/help/952192354843755',
            ],
            'Brevo API' => [
                'key' => 'brevo_api_key',
                'docs' => 'https://developers.brevo.com/docs/getting-started',
            ],
            'Google Places' => [
                'key' => 'gplaces_api_key',
                'docs' => 'https://developers.google.com/maps/documentation/places/web-service/get-api-key',
            ],
        ];

        AdminComponents::openCard([
            'title' => __('Integration Status', 'fp-esperienze'),
        ]);
        ?>
        <table class="fp-admin-table">
            <tbody>
                <?php foreach ($integration_checks as $name => $config) : ?>
                    <?php $is_configured = ! empty($integrations[$config['key']]); ?>
                    <tr>
                        <th><?php echo esc_html($name); ?></th>
                        <td>
                            <?php if ($is_configured) : ?>
                                <span class="fp-admin-badge fp-admin-badge--success"><?php _e('Configured', 'fp-esperienze'); ?></span>
                            <?php else : ?>
                                <span class="fp-admin-badge fp-admin-badge--warning"><?php _e('Not configured', 'fp-esperienze'); ?></span>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=fp-esperienze-settings&tab=integrations')); ?>"
                                   class="button button-secondary fp-fix-button">
                                    <?php _e('Configure', 'fp-esperienze'); ?>
                                </a>
                                <a href="<?php echo esc_url($config['docs']); ?>"
                                   class="button button-secondary fp-fix-button" target="_blank" rel="noopener noreferrer">
                                    <?php _e('Documentation', 'fp-esperienze'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        AdminComponents::closeCard();
    }

}
