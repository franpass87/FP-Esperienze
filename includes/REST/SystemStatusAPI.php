<?php
/**
 * System Status REST API endpoint.
 *
 * @package FP\Esperienze\REST
*/

namespace FP\Esperienze\REST;

use FP\Esperienze\Admin\OnboardingHelper;
use FP\Esperienze\Admin\OperationalAlerts;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\ProductionValidator;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined('ABSPATH') || exit;

/**
 * Expose operational and onboarding insights via the REST API for monitoring tools.
 */
class SystemStatusAPI {
    /**
     * Hook REST route registration.
     */
    public function __construct() {
        $this->registerRoutes();
    }

    /**
     * Register the system status endpoint.
     */
    public function registerRoutes(): void {
        register_rest_route('fp-exp/v1', '/system-status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getStatus'],
            'permission_callback' => [$this, 'permissionsCheck'],
        ]);
    }

    /**
     * Ensure the requester has permission to view operational data.
     *
     * @return bool|WP_Error
     */
    public function permissionsCheck() {
        if (CapabilityManager::canManageFPEsperienze()) {
            return true;
        }

        return new WP_Error(
            'fp_esperienze_forbidden',
            __('You do not have permission to view the FP Esperienze system status.', 'fp-esperienze'),
            ['status' => rest_authorization_required_code()]
        );
    }

    /**
     * Return the aggregated system status payload.
     */
    public function getStatus(WP_REST_Request $request): WP_REST_Response {
        $payload = [
            'generated_at' => current_time('mysql'),
            'onboarding'   => $this->getOnboardingStatus(),
            'production'   => $this->getProductionStatus(),
            'operations'   => $this->getOperationalStatus(),
        ];

        return new WP_REST_Response($payload, 200);
    }

    /**
     * Collect onboarding checklist progress.
     *
     * @return array<string,mixed>
     */
    private function getOnboardingStatus(): array {
        if (!class_exists(OnboardingHelper::class)) {
            return [
                'error' => __('Onboarding tools are unavailable.', 'fp-esperienze'),
            ];
        }

        try {
            $items   = OnboardingHelper::getChecklistItems();
            $summary = OnboardingHelper::getCompletionSummary();
        } catch (Throwable $exception) {
            return [
                'error'   => __('Unable to determine onboarding progress.', 'fp-esperienze'),
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'summary' => $summary,
            'items'   => array_map(
                static function(array $item): array {
                    return [
                        'id'          => (string) ($item['id'] ?? ''),
                        'label'       => (string) ($item['label'] ?? ''),
                        'description' => (string) ($item['description'] ?? ''),
                        'completed'   => (bool) ($item['completed'] ?? false),
                        'count'       => (int) ($item['count'] ?? 0),
                    ];
                },
                $items
            ),
        ];
    }

    /**
     * Collect production readiness information.
     *
     * @return array<string,mixed>
     */
    private function getProductionStatus(): array {
        if (!class_exists(ProductionValidator::class)) {
            return [
                'error' => __('Production readiness checks are unavailable.', 'fp-esperienze'),
            ];
        }

        try {
            return ProductionValidator::validateProductionReadiness();
        } catch (Throwable $exception) {
            return [
                'overall_status' => 'warning',
                'critical_issues' => [],
                'warnings' => [
                    __('Failed to evaluate production readiness. Check the logs for details.', 'fp-esperienze'),
                ],
                'checks' => [],
                'error' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Collect operational alert configuration and health.
     *
     * @return array<string,mixed>
     */
    private function getOperationalStatus(): array {
        if (!class_exists(OperationalAlerts::class)) {
            return [
                'error' => __('Operational alerting is not initialised.', 'fp-esperienze'),
            ];
        }

        try {
            $channels   = OperationalAlerts::getEnabledChannels();
            $next_run   = OperationalAlerts::getNextDigestTimestamp();
            $lastStatus = OperationalAlerts::getLastStatus();
        } catch (Throwable $exception) {
            return [
                'error'   => __('Unable to load operational alert status.', 'fp-esperienze'),
                'message' => $exception->getMessage(),
            ];
        }

        $human_next = null;
        if ($next_run) {
            $human_next = wp_date(
                get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i'),
                $next_run,
                wp_timezone()
            );
        }

        return [
            'channels'     => $channels,
            'next_run_gmt' => $next_run,
            'next_run_human' => $human_next,
            'last_status'  => $lastStatus,
        ];
    }
}
