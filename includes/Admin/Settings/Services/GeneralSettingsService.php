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
        $autoSend    = rest_sanitize_boolean(wp_unslash($data['wpml_auto_send'] ?? false));

        update_option('fp_esperienze_archive_page_id', $archivePage);
        update_option('fp_esperienze_wpml_auto_send', (bool) $autoSend);

        return SettingsUpdateResult::success();
    }
}
