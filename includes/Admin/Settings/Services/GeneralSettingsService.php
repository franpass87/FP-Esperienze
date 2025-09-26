<?php
/**
 * General settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class GeneralSettingsService implements SettingsTabServiceInterface
{
    public function handle(array $data): SettingsUpdateResult
    {
        $archivePage = absint(wp_unslash($data['archive_page_id'] ?? 0));
        $autoSend    = !empty($data['wpml_auto_send']);

        update_option('fp_esperienze_archive_page_id', $archivePage);
        update_option('fp_esperienze_wpml_auto_send', $autoSend);

        return SettingsUpdateResult::success();
    }
}
