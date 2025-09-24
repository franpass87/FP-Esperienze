<?php
/**
 * WP-CLI production readiness checks for FP Esperienze.
 *
 * @package FP\Esperienze\CLI
 */

namespace FP\Esperienze\CLI;

use FP\Esperienze\Core\ProductionValidator;
use WP_CLI;
use WP_CLI_Command;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Run production readiness validation from the command line.
 */
class ProductionCheckCommand extends WP_CLI_Command {
    /**
     * Run the production readiness validation checks.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Render the results in a particular format. Supported options: table, json.
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     * ---
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze production-check
     *     wp fp-esperienze production-check --format=json
     *
     * @param array<int, string> $args       Positional arguments supplied to the command.
     * @param array<string, string> $assoc_args Named arguments supplied to the command.
     */
    public function __invoke(array $args, array $assoc_args): void {
        $format = strtolower($assoc_args['format'] ?? 'table');

        $results = ProductionValidator::validateProductionReadiness();
        $status  = $results['overall_status'] ?? 'warning';

        if ($format === 'json') {
            WP_CLI::print_value($results, ['format' => 'json']);
            $this->exitForStatus($status);
            return;
        }

        WP_CLI::log('== FP Esperienze Production Readiness ==');
        WP_CLI::log(sprintf('Status: %s', $this->getStatusLabel($status)));

        if (isset($results['checks']) && count($results['checks']) > 0) {
            WP_CLI::log('\nChecks:');
            foreach ($results['checks'] as $check) {
                WP_CLI::log(' - ' . $check);
            }
        }

        if (isset($results['warnings']) && count($results['warnings']) > 0) {
            WP_CLI::warning('Warnings detected:');
            foreach ($results['warnings'] as $warning) {
                WP_CLI::log(' - ' . $warning);
            }
        }

        if (isset($results['critical_issues']) && count($results['critical_issues']) > 0) {
            WP_CLI::error_multi_line(array_merge(['Critical issues detected:'], $results['critical_issues']));
            $this->exitForStatus('fail');
            return;
        }

        $this->exitForStatus($status);
    }

    /**
     * Format the status for display.
     *
     * @param string $status Overall status from the validator.
     * @return string
     */
    private function getStatusLabel(string $status): string {
        return match ($status) {
            'pass'    => 'PASS (production ready)',
            'warning' => 'WARNING (review recommended)',
            default   => 'FAIL (action required)',
        };
    }

    /**
     * Exit the command based on the reported status.
     *
     * @param string $status Overall status from the validator.
     */
    private function exitForStatus(string $status): void {
        if ($status === 'pass') {
            WP_CLI::success('FP Esperienze passed all production readiness checks.');
            return;
        }

        if ($status === 'warning') {
            WP_CLI::warning('Production readiness completed with warnings. Review recommended.');
            return;
        }

        WP_CLI::error('FP Esperienze is not production ready. Resolve the critical issues above.', false);
    }
}

