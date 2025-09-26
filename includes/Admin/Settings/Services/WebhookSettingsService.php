<?php
/**
 * Webhook settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class WebhookSettingsService implements SettingsTabServiceInterface
{
    public function handle(array $data): SettingsUpdateResult
    {
        $settings = [
            'fp_esperienze_webhook_new_booking' => esc_url_raw(wp_unslash($data['webhook_new_booking'] ?? '')),
            'fp_esperienze_webhook_cancellation' => esc_url_raw(wp_unslash($data['webhook_cancellation'] ?? '')),
            'fp_esperienze_webhook_reschedule' => esc_url_raw(wp_unslash($data['webhook_reschedule'] ?? '')),
            'fp_esperienze_webhook_secret' => sanitize_text_field(wp_unslash($data['webhook_secret'] ?? '')),
            'fp_esperienze_webhook_hide_pii' => (bool) rest_sanitize_boolean(wp_unslash($data['webhook_hide_pii'] ?? false)),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        return SettingsUpdateResult::success();
    }
}
