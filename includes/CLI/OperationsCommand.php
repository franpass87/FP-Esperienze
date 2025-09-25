<?php
/**
 * WP-CLI operations health checks for FP Esperienze.
 *
 * @package FP\Esperienze\CLI
 */

namespace FP\Esperienze\CLI;

use FP\Esperienze\Admin\OnboardingHelper;
use FP\Esperienze\Admin\OperationalAlerts;
use WP_CLI;
use WP_CLI_Command;

defined('ABSPATH') || exit;

/**
 * Provide operational tooling for site reliability tasks.
 */
class OperationsCommand extends WP_CLI_Command {
    /**
     * Run the operational health checks.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format. Accepted values: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze operations health-check
     *     wp fp-esperienze operations health-check --format=json
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     */
    public function health_check(array $args, array $assoc_args): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $format = strtolower($assoc_args['format'] ?? 'table');

        $checks = [];

        $wooActive = class_exists('WooCommerce');
        $checks[] = [
            'check' => __('WooCommerce active', 'fp-esperienze'),
            'result' => $wooActive ? 'pass' : 'fail',
            'details' => $wooActive
                ? __('WooCommerce detected and ready.', 'fp-esperienze')
                : __('WooCommerce is not active; activate it to process bookings.', 'fp-esperienze'),
        ];

        $channels = OperationalAlerts::getEnabledChannels();
        $channelLabels = array_map(
            static function(string $channel): string {
                switch ($channel) {
                    case 'email':
                        return __('Email', 'fp-esperienze');
                    case 'slack':
                        return __('Slack', 'fp-esperienze');
                    default:
                        return ucfirst($channel);
                }
            },
            $channels
        );
        $hasChannels = $channels !== [];
        $checks[] = [
            'check' => __('Operational digest channels', 'fp-esperienze'),
            'result' => $hasChannels ? 'pass' : 'warn',
            'details' => $hasChannels
                ? sprintf(__('Enabled: %s', 'fp-esperienze'), implode(', ', $channelLabels))
                : __('No delivery channel configured. Enable email or Slack alerts from the Operational Alerts page.', 'fp-esperienze'),
        ];

        $nextDigest = OperationalAlerts::getNextDigestTimestamp();
        $cronDisabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $digestResult = 'warn';
        if ($nextDigest !== null) {
            $digestResult = 'pass';
        } elseif ($hasChannels) {
            $digestResult = $cronDisabled ? 'warn' : 'fail';
        }

        $datetimeFormat = sprintf('%s %s', get_option('date_format', 'Y-m-d'), get_option('time_format', 'H:i'));
        $digestDetails = $nextDigest !== null
            ? sprintf(
                __('Next run at %s.', 'fp-esperienze'),
                wp_date($datetimeFormat, $nextDigest)
            )
            : __('Digest cron is not currently scheduled.', 'fp-esperienze');

        if ($cronDisabled) {
            $digestDetails .= ' ' . __('WP Cron is disabled; ensure a real cron job triggers wp-cron.php.', 'fp-esperienze');
        }

        $checks[] = [
            'check' => __('Daily digest schedule', 'fp-esperienze'),
            'result' => $digestResult,
            'details' => $digestDetails,
        ];

        $lastStatus = OperationalAlerts::getLastStatus();
        if ($lastStatus !== []) {
            $statusKey = strtolower($lastStatus['status'] ?? '');
            switch ($statusKey) {
                case 'success':
                    $statusResult = 'pass';
                    break;
                case 'warning':
                    $statusResult = 'warn';
                    break;
                case 'error':
                    $statusResult = 'fail';
                    break;
                default:
                    $statusResult = 'warn';
                    break;
            }

            $timestamp = $lastStatus['timestamp'] ?? '';
            $message = $lastStatus['message'] ?? '';
            $details = trim($timestamp !== '' ? sprintf('%s — %s', $timestamp, $message) : $message);
            if ($details === '') {
                $details = __('Last dispatch did not return a status message.', 'fp-esperienze');
            }

            $checks[] = [
                'check' => __('Last digest result', 'fp-esperienze'),
                'result' => $statusResult,
                'details' => $details,
            ];
        } else {
            $checks[] = [
                'check' => __('Last digest result', 'fp-esperienze'),
                'result' => 'warn',
                'details' => __('No digest has been sent yet. Trigger one from the Operational Alerts page or via WP-CLI.', 'fp-esperienze'),
            ];
        }

        $items = OnboardingHelper::getChecklistItems();
        $pending = array_filter($items, static function(array $item): bool {
            return empty($item['completed']);
        });
        if ($pending === []) {
            $checks[] = [
                'check' => __('Onboarding checklist', 'fp-esperienze'),
                'result' => 'pass',
                'details' => __('All onboarding prerequisites satisfied.', 'fp-esperienze'),
            ];
        } else {
            $labels = array_map(static function(array $item): string {
                return (string) ($item['label'] ?? $item['id'] ?? '');
            }, $pending);

            $checks[] = [
                'check' => __('Onboarding checklist', 'fp-esperienze'),
                'result' => 'warn',
                'details' => sprintf(
                    /* translators: %s: comma separated list of pending onboarding tasks */
                    __('Pending: %s', 'fp-esperienze'),
                    implode(', ', $labels)
                ),
            ];
        }

        $summary = [
            'pass' => 0,
            'warn' => 0,
            'fail' => 0,
        ];

        foreach ($checks as $check) {
            $key = $check['result'];
            if (!isset($summary[$key])) {
                $summary[$key] = 0;
            }
            $summary[$key]++;
        }

        if ($format === 'json') {
            WP_CLI::print_value([
                'checks' => $checks,
                'summary' => [
                    'pass' => $summary['pass'],
                    'warn' => $summary['warn'],
                    'fail' => $summary['fail'],
                ],
            ], ['format' => 'json']);
            return;
        }

        $iconMap = [
            'pass' => '✅',
            'warn' => '⚠️',
            'fail' => '❌',
        ];

        $rows = array_map(
            static function(array $check) use ($iconMap): array {
                $icon = $iconMap[$check['result']] ?? $iconMap['warn'];

                return [
                    'check' => $check['check'],
                    'status' => $icon,
                    'details' => $check['details'],
                ];
            },
            $checks
        );

        WP_CLI::log('== FP Esperienze Operational Health ==');
        WP_CLI::log('');

        if (function_exists('\\WP_CLI\\Utils\\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, ['check', 'status', 'details']);
        } else {
            foreach ($rows as $row) {
                WP_CLI::log(sprintf('%s %s — %s', $row['status'], $row['check'], $row['details']));
            }
        }

        WP_CLI::log('');
        WP_CLI::log(sprintf(
            __('Summary: %1$d pass · %2$d warnings · %3$d failures', 'fp-esperienze'),
            $summary['pass'],
            $summary['warn'],
            $summary['fail']
        ));
    }
}
