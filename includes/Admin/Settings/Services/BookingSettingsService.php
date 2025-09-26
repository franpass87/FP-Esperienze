<?php
/**
 * Booking settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class BookingSettingsService implements SettingsTabServiceInterface
{
    private const MIN_HOLD_MINUTES = 5;
    private const MAX_HOLD_MINUTES = 60;

    public function handle(array $data): SettingsUpdateResult
    {
        $enableHolds = !empty($data['enable_holds']);
        $holdDuration = absint(wp_unslash($data['hold_duration'] ?? self::MIN_HOLD_MINUTES));
        $holdDuration = max(self::MIN_HOLD_MINUTES, min(self::MAX_HOLD_MINUTES, $holdDuration));

        update_option('fp_esperienze_enable_holds', $enableHolds);
        update_option('fp_esperienze_hold_duration_minutes', $holdDuration);

        return SettingsUpdateResult::success();
    }
}
