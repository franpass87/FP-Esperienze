<?php
/**
 * Site Health integration for FP Esperienze.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\Admin\OnboardingHelper;
use FP\Esperienze\Admin\OperationalAlerts;
use FP\Esperienze\Core\ProductionValidator;
use Throwable;

use function esc_html;
use function esc_html__;
use function esc_url;

defined('ABSPATH') || exit;

/**
 * Adds custom Site Health tests to highlight plugin requirements.
 */
class SiteHealth {

    /**
     * Register Site Health integration.
     */
    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_filter('site_status_tests', array(__CLASS__, 'registerTests'));
    }

    /**
     * Register the plugin specific tests.
     *
     * @param array $tests Existing tests.
     *
     * @return array Modified tests list.
     */
    public static function registerTests(array $tests): array {
        $tests['direct']['fp_esperienze_dependencies'] = array(
            'label' => esc_html__('FP Esperienze dependencies', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runDependencyTest'),
        );

        $tests['direct']['fp_esperienze_filesystem'] = array(
            'label' => esc_html__('FP Esperienze filesystem access', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runFilesystemTest'),
        );

        $tests['direct']['fp_esperienze_scheduled_events'] = array(
            'label' => esc_html__('FP Esperienze scheduled events', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runCronTest'),
        );

        $tests['direct']['fp_esperienze_onboarding'] = array(
            'label' => esc_html__('FP Esperienze onboarding progress', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runOnboardingTest'),
        );

        $tests['direct']['fp_esperienze_operational_alerts'] = array(
            'label' => esc_html__('FP Esperienze operational alerts', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runOperationalAlertsTest'),
        );

        $tests['direct']['fp_esperienze_production_readiness'] = array(
            'label' => esc_html__('FP Esperienze production readiness', 'fp-esperienze'),
            'test'  => array(__CLASS__, 'runProductionReadinessTest'),
        );

        return $tests;
    }

    /**
     * Check mandatory dependencies.
     *
     * @return array
     */
    public static function runDependencyTest(): array {
        $result = self::getBaseResult(
            'fp_esperienze_dependencies',
            esc_html__('All dependencies detected.', 'fp-esperienze')
        );

        $issues = array();

        if (!class_exists('WooCommerce')) {
            $issues[] = esc_html__('WooCommerce must be installed and active for FP Esperienze to work.', 'fp-esperienze');
        } elseif (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
            $issues[] = esc_html__('WooCommerce 8.0 or newer is required.', 'fp-esperienze');
        }

        if (!file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php')) {
            $issues[] = esc_html__('Composer dependencies are missing. Run composer install --no-dev.', 'fp-esperienze');
        }

        if (!empty($issues)) {
            $result['status']      = 'critical';
            $result['description'] = self::buildDescription($issues);
            $result['actions']     = sprintf(
                '<p><a href="%s" class="button">%s</a></p>',
                esc_url(admin_url('plugins.php')),
                esc_html__('Open Plugins screen', 'fp-esperienze')
            );
        }

        return $result;
    }

    /**
     * Validate filesystem prerequisites.
     *
     * @return array
     */
    public static function runFilesystemTest(): array {
        $result = self::getBaseResult(
            'fp_esperienze_filesystem',
            esc_html__('Required directories are writable.', 'fp-esperienze')
        );

        $paths = array(
            WP_CONTENT_DIR . '/fp-private' => esc_html__('The fp-private directory must be writable.', 'fp-esperienze'),
            FP_ESPERIENZE_ICS_DIR          => esc_html__('The ICS directory must be writable.', 'fp-esperienze'),
        );

        $issues = array();

        foreach ($paths as $path => $message) {
            if (!file_exists($path)) {
                // translators: 1: error message, 2: filesystem path.
                $issues[] = sprintf(esc_html__('%1$s (missing path: %2$s)', 'fp-esperienze'), $message, esc_html($path));
                continue;
            }

            if (!wp_is_writable($path)) {
                // translators: 1: error message, 2: filesystem path.
                $issues[] = sprintf(esc_html__('%1$s (not writable: %2$s)', 'fp-esperienze'), $message, esc_html($path));
            }
        }

        if (!empty($issues)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription($issues);
        }

        return $result;
    }

    /**
     * Ensure scheduled events required by the plugin are present.
     *
     * @return array
     */
    public static function runCronTest(): array {
        $result = self::getBaseResult(
            'fp_esperienze_scheduled_events',
            esc_html__('Scheduled events are registered.', 'fp-esperienze')
        );

        $events = array(
            TranslationQueue::CRON_HOOK,
            'fp_cleanup_push_tokens',
        );

        $issues = array();

        foreach ($events as $hook) {
            if (!wp_next_scheduled($hook)) {
                $issues[] = sprintf(
                    esc_html__('The %s scheduled event is missing. Visit the FP Esperienze settings page to re-schedule it.', 'fp-esperienze'),
                    esc_html($hook)
                );
            }
        }

        if (!empty($issues)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription($issues);
        }

        return $result;
    }

    /**
     * Highlight onboarding checklist completion in Site Health.
     */
    public static function runOnboardingTest(): array {
        $result = self::getBaseResult(
            'fp_esperienze_onboarding',
            esc_html__('Onboarding checklist completed.', 'fp-esperienze')
        );

        if (!class_exists(OnboardingHelper::class)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription(
                array(
                    esc_html__('Onboarding helpers are unavailable. Update FP Esperienze to the latest version.', 'fp-esperienze'),
                )
            );

            return $result;
        }

        try {
            $summary = OnboardingHelper::getCompletionSummary();
            $items   = OnboardingHelper::getChecklistItems();
        } catch (Throwable $exception) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription(
                array(
                    esc_html__('Unable to evaluate onboarding progress.', 'fp-esperienze'),
                    esc_html($exception->getMessage()),
                )
            );

            return $result;
        }

        $completed = isset($summary['completed']) ? (int) $summary['completed'] : 0;
        $total     = isset($summary['total']) ? (int) $summary['total'] : 0;
        $percent   = isset($summary['percentage']) ? (int) $summary['percentage'] : 0;

        $messages = array(
            sprintf(
                esc_html__('%1$d of %2$d onboarding tasks completed (%3$d%%).', 'fp-esperienze'),
                $completed,
                $total,
                $percent
            ),
        );

        $pending = array();
        foreach ($items as $item) {
            $is_complete = !empty($item['completed']);
            if ($is_complete) {
                continue;
            }

            $label = '';
            if (isset($item['label']) && $item['label'] !== '') {
                $label = (string) $item['label'];
            } elseif (isset($item['id']) && $item['id'] !== '') {
                $label = (string) $item['id'];
            }

            if ($label !== '') {
                $pending[] = esc_html($label);
            }
        }

        if (!empty($pending)) {
            $result['status'] = 'recommended';
            $messages[]       = sprintf(
                esc_html__('Pending tasks: %s', 'fp-esperienze'),
                implode(', ', $pending)
            );
        }

        $result['description'] = self::buildDescription($messages);

        return $result;
    }

    /**
     * Confirm operational alerts are configured and healthy.
     */
    public static function runOperationalAlertsTest(): array {
        $result = self::getBaseResult(
            'fp_esperienze_operational_alerts',
            esc_html__('Operational digests configured.', 'fp-esperienze')
        );

        if (!class_exists(OperationalAlerts::class)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription(
                array(
                    esc_html__('Operational alerts are unavailable. Activate the latest plugin build.', 'fp-esperienze'),
                )
            );

            return $result;
        }

        $messages = array();

        $channels = OperationalAlerts::getEnabledChannels();
        if (empty($channels)) {
            $result['status'] = 'recommended';
            $messages[]       = esc_html__('No operational digest channels are configured.', 'fp-esperienze');
        } else {
            $channel_labels = array_map('esc_html', $channels);
            $messages[]      = sprintf(
                esc_html__('Enabled channels: %s', 'fp-esperienze'),
                implode(', ', $channel_labels)
            );
        }

        $next_run = OperationalAlerts::getNextDigestTimestamp();
        if ($next_run === null) {
            $result['status'] = 'recommended';
            $messages[]       = esc_html__('The daily digest cron event is not scheduled.', 'fp-esperienze');
        } else {
            $messages[] = sprintf(
                esc_html__('Next digest scheduled for %s.', 'fp-esperienze'),
                esc_html(
                    wp_date(
                        get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'),
                        $next_run
                    )
                )
            );
        }

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            $result['status'] = $result['status'] === 'critical' ? 'critical' : 'recommended';
            $messages[]       = esc_html__('WP-Cron is disabled. Ensure a real cron job triggers the digest schedule.', 'fp-esperienze');
        }

        $last_status = OperationalAlerts::getLastStatus();
        if (!empty($last_status)) {
            $last_state = strtolower((string) ($last_status['status'] ?? ''));
            $timestamp  = isset($last_status['timestamp']) ? esc_html((string) $last_status['timestamp']) : '';
            $message    = isset($last_status['message']) ? esc_html((string) $last_status['message']) : '';

            if ($timestamp !== '' && $message !== '') {
                $messages[] = sprintf(
                    esc_html__('Last dispatch (%1$s): %2$s', 'fp-esperienze'),
                    $timestamp,
                    $message
                );
            } elseif ($timestamp !== '') {
                $messages[] = sprintf(
                    esc_html__('Last dispatch at %s.', 'fp-esperienze'),
                    $timestamp
                );
            } elseif ($message !== '') {
                $messages[] = sprintf(
                    esc_html__('Last dispatch message: %s', 'fp-esperienze'),
                    $message
                );
            }

            if ($last_state === 'error') {
                $result['status'] = 'critical';
            } elseif ($last_state === 'warning' && $result['status'] !== 'critical') {
                $result['status'] = 'recommended';
            }
        }

        if (empty($messages)) {
            $messages[] = esc_html__('Operational digests configured.', 'fp-esperienze');
        }

        $result['description'] = self::buildDescription($messages);

        return $result;
    }

    /**
     * Surface production readiness from the validator inside Site Health.
     */
    public static function runProductionReadinessTest(): array {
        $result = self::getBaseResult(
            'fp_esperienze_production_readiness',
            esc_html__('All production readiness checks passed.', 'fp-esperienze')
        );

        if (!class_exists(ProductionValidator::class)) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription(
                array(
                    esc_html__('Production readiness checks are unavailable. Ensure the plugin core is loaded.', 'fp-esperienze'),
                )
            );

            return $result;
        }

        try {
            $validation = ProductionValidator::validateProductionReadiness();
        } catch (Throwable $exception) {
            $result['status']      = 'recommended';
            $result['description'] = self::buildDescription(
                array(
                    esc_html__('Unable to evaluate production readiness.', 'fp-esperienze'),
                    esc_html($exception->getMessage()),
                )
            );

            return $result;
        }

        $status = strtolower((string) ($validation['overall_status'] ?? 'warning'));

        if ($status === 'fail') {
            $result['status'] = 'critical';
        } elseif ($status === 'warning') {
            $result['status'] = 'recommended';
        }

        $messages = array();

        if (!empty($validation['critical_issues'])) {
            foreach ((array) $validation['critical_issues'] as $issue) {
                $messages[] = esc_html((string) $issue);
            }
        }

        if (!empty($validation['warnings'])) {
            foreach ((array) $validation['warnings'] as $warning) {
                $messages[] = esc_html((string) $warning);
            }
        }

        if (empty($messages)) {
            $messages[] = esc_html__('All production readiness checks passed.', 'fp-esperienze');
        } else {
            $checks_count = isset($validation['checks']) ? count((array) $validation['checks']) : 0;
            if ($checks_count > 0) {
                $messages[] = sprintf(
                    esc_html__('Checks executed: %d', 'fp-esperienze'),
                    $checks_count
                );
            }
        }

        $result['description'] = self::buildDescription($messages);

        return $result;
    }

    /**
     * Prepare a base result array compliant with Site Health expectations.
     *
     * @param string $test Test identifier.
     *
     * @return array
     */
    private static function getBaseResult(string $test, string $message): array {
        return array(
            'label'       => esc_html__('FP Esperienze', 'fp-esperienze'),
            'status'      => 'good',
            'badge'       => array(
                'label' => esc_html__('FP Esperienze', 'fp-esperienze'),
                'color' => 'blue',
            ),
            'description' => self::buildDescription(
                array(
                    $message,
                )
            ),
            'test'        => $test,
        );
    }

    /**
     * Build HTML description paragraphs from messages.
     *
     * @param array<int, string> $messages List of messages.
     *
     * @return string
     */
    private static function buildDescription(array $messages): string {
        $output = '';

        foreach ($messages as $message) {
            $output .= '<p>' . $message . '</p>';
        }

        return $output;
    }
}
