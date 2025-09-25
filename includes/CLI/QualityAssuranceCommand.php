<?php
/**
 * WP-CLI automation for the FP Esperienze manual test checklist.
 *
 * @package FP\Esperienze\CLI
 */

namespace FP\Esperienze\CLI;

use FP\Esperienze\Admin\OnboardingHelper;
use FP\Esperienze\Admin\OperationalAlerts;
use Throwable;
use WP_CLI;
use WP_CLI_Command;

defined('ABSPATH') || exit;

/**
 * Provide automated quality assurance checks through WP-CLI.
 */
class QualityAssuranceCommand extends WP_CLI_Command {
    /**
     * Run the automated smoke checks that mirror the manual QA list.
     *
     * ## OPTIONS
     *
     * [--only=<ids>]
     * : Comma separated list of check identifiers to run (defaults to all).
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
     *     wp fp-esperienze qa run
     *     wp fp-esperienze qa run --only=experience_product_type,digest_schedule
     *     wp fp-esperienze qa run --format=json
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     */
    public function run(array $args, array $assoc_args): void {
        $format = strtolower($assoc_args['format'] ?? 'table');
        $only = isset($assoc_args['only']) ? $this->parseOnlyArgument($assoc_args['only']) : [];

        $checks = $this->getChecks();
        $executed = [];

        foreach ($checks as $check) {
            if ($only !== [] && !in_array($check['id'], $only, true)) {
                continue;
            }

            $result = $this->executeCheck($check['callback']);

            $executed[] = [
                'id' => $check['id'],
                'label' => $check['label'],
                'description' => $check['description'],
                'status' => $result['status'],
                'message' => $result['message'],
            ];
        }

        if ($executed === []) {
            WP_CLI::warning(__('No matching QA checks were executed.', 'fp-esperienze'));
            return;
        }

        $overall = $this->determineOverallStatus($executed);

        if ($format === 'json') {
            WP_CLI::print_value([
                'overall_status' => $overall,
                'checks' => $executed,
            ], ['format' => 'json']);
            $this->exitForStatus($overall);
            return;
        }

        WP_CLI::log('== FP Esperienze QA Automation ==');
        WP_CLI::log(sprintf('Overall status: %s', $this->formatStatusLabel($overall)));
        WP_CLI::log('');

        $rows = [];
        foreach ($executed as $result) {
            $rows[] = [
                'check' => $result['label'],
                'status' => sprintf('%s %s', $this->getStatusIcon($result['status']), strtoupper($result['status'])),
                'details' => $result['message'],
            ];
        }

        if (function_exists('\\WP_CLI\\Utils\\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, ['check', 'status', 'details']);
        } else {
            foreach ($rows as $row) {
                WP_CLI::log(sprintf('%s - %s', $row['status'], $row['check']));
                if (!empty($row['details'])) {
                    WP_CLI::log('  ' . $row['details']);
                }
            }
        }

        $this->exitForStatus($overall);
    }

    /**
     * List the available QA checks.
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze qa list
     *
     * @param array<int,string>    $args       Positional arguments.
     * @param array<string,string> $assoc_args Named arguments.
     */
    public function list_checks(array $args, array $assoc_args): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        $rows = [];
        foreach ($this->getChecks() as $check) {
            $rows[] = [
                'id' => $check['id'],
                'check' => $check['label'],
                'description' => $check['description'],
            ];
        }

        if (function_exists('\\WP_CLI\\Utils\\format_items')) {
            \WP_CLI\Utils\format_items('table', $rows, ['id', 'check', 'description']);
        } else {
            foreach ($rows as $row) {
                WP_CLI::log(sprintf('%s: %s', $row['id'], $row['check']));
                if (!empty($row['description'])) {
                    WP_CLI::log('  ' . $row['description']);
                }
            }
        }
    }

    /**
     * Parse the --only option into a sanitised list of identifiers.
     *
     * @param string $value Raw --only argument.
     * @return array<int,string>
     */
    private function parseOnlyArgument(string $value): array {
        $parts = array_filter(array_map('trim', explode(',', $value)));

        return array_values(array_unique($parts));
    }

    /**
     * Execute a QA check safely.
     *
     * @param callable $callback Check callback.
     * @return array{status:string,message:string}
     */
    private function executeCheck(callable $callback): array {
        try {
            $result = call_user_func($callback);
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'message' => sprintf('Check failed with error: %s', $e->getMessage()),
            ];
        }

        $status = $result['status'] ?? 'warning';
        $message = $result['message'] ?? '';

        return [
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * Determine the overall run status.
     *
     * @param array<int,array<string,string>> $results Individual check outcomes.
     * @return string
     */
    private function determineOverallStatus(array $results): string {
        $overall = 'pass';

        foreach ($results as $result) {
            $status = $result['status'];
            if ($status === 'fail' || $status === 'error') {
                return 'fail';
            }

            if ($status === 'warning') {
                $overall = 'warning';
            }
        }

        return $overall;
    }

    /**
     * Exit the command with an appropriate severity.
     *
     * @param string $status Overall status.
     */
    private function exitForStatus(string $status): void {
        if ($status === 'pass') {
            WP_CLI::success(__('All QA checks passed.', 'fp-esperienze'));
            return;
        }

        if ($status === 'warning') {
            WP_CLI::warning(__('QA checks completed with warnings. Review the table above.', 'fp-esperienze'));
            return;
        }

        WP_CLI::error(__('QA checks failed. Resolve the failing items before releasing.', 'fp-esperienze'), false);
    }

    /**
     * Format a status label for CLI output.
     *
     * @param string $status Status identifier.
     * @return string
     */
    private function formatStatusLabel(string $status): string {
        return match ($status) {
            'pass' => __('PASS – production ready', 'fp-esperienze'),
            'warning' => __('WARNING – needs review', 'fp-esperienze'),
            default => __('FAIL – action required', 'fp-esperienze'),
        };
    }

    /**
     * Map statuses to icons.
     *
     * @param string $status Status identifier.
     * @return string
     */
    private function getStatusIcon(string $status): string {
        return match ($status) {
            'pass' => '✅',
            'warning' => '⚠️',
            'fail', 'error' => '❌',
            default => 'ℹ️',
        };
    }

    /**
     * Provide the QA check definitions.
     *
     * @return array<int,array<string,mixed>>
     */
    private function getChecks(): array {
        return [
            [
                'id' => 'experience_product_type',
                'label' => __('Experience product type registered', 'fp-esperienze'),
                'description' => __('Ensures the WooCommerce "experience" product type is available so bookings can be sold.', 'fp-esperienze'),
                'callback' => [$this, 'checkExperienceProductType'],
            ],
            [
                'id' => 'onboarding_checklist',
                'label' => __('Onboarding checklist complete', 'fp-esperienze'),
                'description' => __('Verifies that the core onboarding prerequisites are satisfied.', 'fp-esperienze'),
                'callback' => [$this, 'checkOnboardingChecklist'],
            ],
            [
                'id' => 'demo_content',
                'label' => __('Demo content seeded', 'fp-esperienze'),
                'description' => __('Confirms the optional demo meeting point, experience and schedule have been generated for training.', 'fp-esperienze'),
                'callback' => [$this, 'checkDemoContent'],
            ],
            [
                'id' => 'digest_schedule',
                'label' => __('Operational digest scheduled', 'fp-esperienze'),
                'description' => __('Checks that at least one operational alert channel is configured and the cron event is scheduled.', 'fp-esperienze'),
                'callback' => [$this, 'checkDigestSchedule'],
            ],
            [
                'id' => 'rest_routes',
                'label' => __('REST API routes registered', 'fp-esperienze'),
                'description' => __('Ensures the public REST API endpoints used by the widget and integrations are available.', 'fp-esperienze'),
                'callback' => [$this, 'checkRestRoutes'],
            ],
        ];
    }

    /**
     * Ensure the experience product type exists.
     *
     * @return array{status:string,message:string}
     */
    private function checkExperienceProductType(): array {
        if (!function_exists('wc_get_product_types')) {
            return [
                'status' => 'fail',
                'message' => __('WooCommerce functions are unavailable. Activate WooCommerce before running QA checks.', 'fp-esperienze'),
            ];
        }

        $types = wc_get_product_types();
        if (isset($types['experience'])) {
            return [
                'status' => 'pass',
                'message' => __('The experience product type is registered.', 'fp-esperienze'),
            ];
        }

        return [
            'status' => 'fail',
            'message' => __('Experience product type missing. Visit the plugin settings to trigger a reinitialisation.', 'fp-esperienze'),
        ];
    }

    /**
     * Validate onboarding checklist completion.
     *
     * @return array{status:string,message:string}
     */
    private function checkOnboardingChecklist(): array {
        if (!class_exists(OnboardingHelper::class)) {
            return [
                'status' => 'warning',
                'message' => __('Onboarding helper unavailable. Load the admin environment to generate checklist data.', 'fp-esperienze'),
            ];
        }

        $summary = OnboardingHelper::getCompletionSummary();
        if (($summary['total'] ?? 0) === 0) {
            return [
                'status' => 'warning',
                'message' => __('No onboarding tasks were detected. Verify the helper configuration.', 'fp-esperienze'),
            ];
        }

        if ($summary['completed'] >= $summary['total']) {
            return [
                'status' => 'pass',
                'message' => __('All onboarding prerequisites are satisfied.', 'fp-esperienze'),
            ];
        }

        return [
            'status' => 'warning',
            'message' => sprintf(
                /* translators: 1: completed tasks, 2: total tasks, 3: percentage */
                __('%1$d of %2$d onboarding tasks complete (%.2f%%). Finish the checklist before going live.', 'fp-esperienze'),
                (int) $summary['completed'],
                (int) $summary['total'],
                (float) $summary['percentage']
            ),
        ];
    }

    /**
     * Confirm demo content seeding state.
     *
     * @return array{status:string,message:string}
     */
    private function checkDemoContent(): array {
        $flag = (int) get_option('fp_esperienze_demo_content_created', 0);
        if ($flag === 1) {
            return [
                'status' => 'pass',
                'message' => __('Demo content is available for training and walkthroughs.', 'fp-esperienze'),
            ];
        }

        return [
            'status' => 'warning',
            'message' => __('Demo content not found. Run "wp fp-esperienze onboarding seed-data" to generate sample data.', 'fp-esperienze'),
        ];
    }

    /**
     * Ensure the operational digest cron is configured.
     *
     * @return array{status:string,message:string}
     */
    private function checkDigestSchedule(): array {
        $emailEnabled = (bool) get_option(OperationalAlerts::OPTION_EMAIL_ENABLED, false);
        $hasSlack = (bool) get_option(OperationalAlerts::OPTION_SLACK_WEBHOOK, '') !== '';

        if (!$emailEnabled && !$hasSlack) {
            return [
                'status' => 'warning',
                'message' => __('No operational alert channels configured. Enable email or Slack digests.', 'fp-esperienze'),
            ];
        }

        if (!function_exists('wp_next_scheduled')) {
            return [
                'status' => 'warning',
                'message' => __('wp_next_scheduled() unavailable. Cron may be disabled in this environment.', 'fp-esperienze'),
            ];
        }

        $timestamp = wp_next_scheduled(OperationalAlerts::CRON_HOOK);
        if ($timestamp === false) {
            return [
                'status' => 'fail',
                'message' => __('Operational digest not scheduled. Save the alert settings to reschedule the cron event.', 'fp-esperienze'),
            ];
        }

        $formatted = function_exists('wp_date')
            ? wp_date(get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'), $timestamp)
            : date_i18n('Y-m-d H:i', $timestamp);

        return [
            'status' => 'pass',
            'message' => sprintf(
                /* translators: %s: formatted datetime */
                __('Operational digest scheduled for %s.', 'fp-esperienze'),
                $formatted
            ),
        ];
    }

    /**
     * Verify key REST API routes are present.
     *
     * @return array{status:string,message:string}
     */
    private function checkRestRoutes(): array {
        if (!function_exists('rest_get_server')) {
            return [
                'status' => 'warning',
                'message' => __('REST API server unavailable. Ensure the REST API is enabled.', 'fp-esperienze'),
            ];
        }

        $server = rest_get_server();
        if (!$server) {
            return [
                'status' => 'warning',
                'message' => __('REST API server not initialised. Trigger rest_api_init before running the QA checks.', 'fp-esperienze'),
            ];
        }

        $routes = array_keys($server->get_routes());
        $required = [
            '/fp-exp/v1/availability',
            '/fp-exp/v1/bookings',
            '/fp-exp/v1/widget/data/(?P<product_id>\\d+)',
            '/fp-esperienze/v1/events',
        ];

        $missing = [];
        foreach ($required as $route) {
            if (!in_array($route, $routes, true)) {
                $missing[] = $route;
            }
        }

        if ($missing === []) {
            return [
                'status' => 'pass',
                'message' => __('Core REST API routes are registered.', 'fp-esperienze'),
            ];
        }

        return [
            'status' => 'fail',
            'message' => sprintf(
                /* translators: %s: comma separated route list */
                __('Missing REST API routes: %s', 'fp-esperienze'),
                implode(', ', $missing)
            ),
        ];
    }
}
