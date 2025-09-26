<?php
/**
 * Settings tab service contract.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

interface SettingsTabServiceInterface
{
    /**
     * Handle a settings tab submission.
     *
     * @param array<string,mixed> $data Raw request data.
     */
    public function handle(array $data): SettingsUpdateResult;
}
