<?php
/**
 * WP-CLI onboarding helpers for FP Esperienze.
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
 * Provide onboarding utilities through WP-CLI.
 */
class OnboardingCommand extends WP_CLI_Command {
    /**
     * Display the onboarding checklist with completion status.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render the output in a particular format. Accepted: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze onboarding checklist
     *     wp fp-esperienze onboarding checklist --format=json
     *
     * @param array<int,string>       $args       Positional arguments.
     * @param array<string,string>    $assoc_args Named arguments.
     */
    public function checklist(array $args, array $assoc_args): void {
        $format = strtolower($assoc_args['format'] ?? 'table');

        $items = OnboardingHelper::getChecklistItems();
        $summary = OnboardingHelper::getCompletionSummary();

        if ($format === 'json') {
            WP_CLI::print_value([
                'items' => $items,
                'summary' => $summary,
            ], ['format' => 'json']);
            return;
        }

        $rows = [];
        foreach ($items as $item) {
            $rows[] = [
                'task' => $item['label'] ?? '',
                'status' => (isset($item['completed']) && (bool) $item['completed']) ? '✅' : '⏳',
                'details' => ($item['description'] ?? ''),
            ];
        }

        WP_CLI::log('== FP Esperienze Onboarding Checklist ==');
        WP_CLI::log(sprintf(
            'Progress: %d/%d completed (%.2f%%)',
            $summary['completed'],
            $summary['total'],
            $summary['percentage']
        ));
        WP_CLI::log('');

        if (function_exists('\\WP_CLI\\Utils\\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, ['task', 'status', 'details']);
        } else {
            foreach ($rows as $row) {
                WP_CLI::log(sprintf('%s - %s', $row['status'], $row['task']));
            }
        }
    }

    /**
     * Seed demo meeting points, experiences, and schedules.
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze onboarding seed-data
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     */
    public function seed_data(array $args, array $assoc_args): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $result = OnboardingHelper::seedDemoContent();

        $status = $result['status'];
        $message = $result['message'];

        switch ($status) {
            case 'success':
                WP_CLI::success($message);
                break;
            case 'warning':
                WP_CLI::warning($message);
                break;
            case 'error':
                WP_CLI::error($message, false);
                break;
            default:
                WP_CLI::log($message);
                break;
        }
    }

    /**
     * Print a daily booking report summarising participants and revenue.
     *
     * ## OPTIONS
     *
     * [--days=<days>]
     * : Number of days to include (defaults to 1).
     * ---
     * default: 1
     * ---
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze onboarding daily-report
     *     wp fp-esperienze onboarding daily-report --days=7
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     */
    public function daily_report(array $args, array $assoc_args): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $days = isset($assoc_args['days']) ? absint($assoc_args['days']) : 1;
        $report = OnboardingHelper::getDailyReportData($days);

        $currency = get_option('woocommerce_currency', 'EUR');
        $formatRevenue = static function($amount) use ($currency): string {
            if (function_exists('wc_price')) {
                return trim(wp_strip_all_tags(wc_price((float) $amount, ['currency' => $currency])));
            }

            return sprintf('%s %.2f', $currency, (float) $amount);
        };

        $rows = [];
        foreach ($report['by_day'] as $day => $data) {
            $rows[] = [
                'day' => $day,
                'bookings' => $data['bookings'] ?? 0,
                'participants' => $data['participants'] ?? 0,
                'revenue' => $formatRevenue($data['revenue'] ?? 0),
                'confirmed' => $data['statuses']['confirmed'] ?? 0,
                'pending' => $data['statuses']['pending'] ?? 0,
                'cancelled' => $data['statuses']['cancelled'] ?? 0,
            ];
        }

        if ($rows === []) {
            WP_CLI::warning(__('No bookings found for the selected period.', 'fp-esperienze'));
        } else {
            if (function_exists('\\WP_CLI\\Utils\\format_items')) {
                \WP_CLI\Utils\format_items('table', $rows, ['day', 'bookings', 'participants', 'revenue', 'confirmed', 'pending', 'cancelled']);
            } else {
                WP_CLI::log(__('Install WP-CLI formatting utilities to render the table output.', 'fp-esperienze'));
            }
        }

        $overall = $report['overall'];
        WP_CLI::log('');
        WP_CLI::log(sprintf(
            __('Total bookings: %1$d (%2$d participants) – Revenue: %3$s', 'fp-esperienze'),
            $overall['total_bookings'],
            $overall['participants'],
            $formatRevenue($overall['revenue'])
        ));

        $statuses = $overall['by_status'] ?? [];
        if ($statuses !== []) {
            WP_CLI::log(__('Breakdown by status:', 'fp-esperienze'));
            foreach ($statuses as $status => $count) {
                WP_CLI::log(sprintf(' - %s: %d', ucfirst((string) $status), (int) $count));
            }
        }
    }

    /**
     * Send the operational digest via configured channels.
     *
     * ## OPTIONS
     *
     * [--channel=<channel>]
     * : Delivery channel (email, slack, all). Defaults to all.
     * ---
     * default: all
     * options:
     *   - email
     *   - slack
     *   - all
     * ---
     *
     * [--days=<days>]
     * : Number of days to include (falls back to settings page value).
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze onboarding send-digest
     *     wp fp-esperienze onboarding send-digest --channel=slack --days=3
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     */
    public function send_digest(array $args, array $assoc_args): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $channel = strtolower($assoc_args['channel'] ?? 'all');
        if (!in_array($channel, ['email', 'slack', 'all'], true)) {
            WP_CLI::error(__('Invalid channel. Use email, slack, or all.', 'fp-esperienze'));
        }

        $days = isset($assoc_args['days']) ? absint($assoc_args['days']) : (int) get_option(OperationalAlerts::OPTION_LOOKBACK_DAYS, 1);
        $days = max(1, $days);

        $result = OperationalAlerts::dispatchDigest($channel, $days);

        switch ($result['status']) {
            case 'success':
                WP_CLI::success($result['message']);
                break;
            case 'warning':
                WP_CLI::warning($result['message']);
                break;
            default:
                WP_CLI::error($result['message'], false);
        }
    }
}
