<?php
/**
 * Integrations settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class IntegrationsSettingsService implements SettingsTabServiceInterface
{
    public function handle(array $data): SettingsUpdateResult
    {
        $integrations = [
            'ga4_measurement_id'    => sanitize_text_field(wp_unslash($data['ga4_measurement_id'] ?? '')),
            'ga4_ecommerce'         => !empty($data['ga4_ecommerce']),
            'gads_conversion_id'    => sanitize_text_field(wp_unslash($data['gads_conversion_id'] ?? '')),
            'gads_purchase_label'   => sanitize_text_field(wp_unslash($data['gads_purchase_label'] ?? '')),
            'meta_pixel_id'         => sanitize_text_field(wp_unslash($data['meta_pixel_id'] ?? '')),
            'meta_capi_enabled'     => !empty($data['meta_capi_enabled']),
            'meta_access_token'     => sanitize_text_field(wp_unslash($data['meta_access_token'] ?? '')),
            'meta_dataset_id'       => sanitize_text_field(wp_unslash($data['meta_dataset_id'] ?? '')),
            'brevo_api_key'         => sanitize_text_field(wp_unslash($data['brevo_api_key'] ?? '')),
            'brevo_list_id_it'      => absint(wp_unslash($data['brevo_list_id_it'] ?? 0)),
            'brevo_list_id_en'      => absint(wp_unslash($data['brevo_list_id_en'] ?? 0)),
            'gplaces_api_key'       => sanitize_text_field(wp_unslash($data['gplaces_api_key'] ?? '')),
            'gplaces_reviews_enabled' => !empty($data['gplaces_reviews_enabled']),
            'gplaces_reviews_limit' => max(1, min(10, absint(wp_unslash($data['gplaces_reviews_limit'] ?? 5)))),
            'gplaces_cache_ttl'     => max(5, min(1440, absint(wp_unslash($data['gplaces_cache_ttl'] ?? 60)))),
            'gbp_client_id'         => sanitize_text_field(wp_unslash($data['gbp_client_id'] ?? '')),
            'gbp_client_secret'     => sanitize_text_field(wp_unslash($data['gbp_client_secret'] ?? '')),
            'consent_mode_enabled'  => !empty($data['consent_mode_enabled']),
            'consent_cookie_name'   => sanitize_text_field(wp_unslash($data['consent_cookie_name'] ?? 'marketing_consent')),
            'consent_js_function'   => sanitize_text_field(wp_unslash($data['consent_js_function'] ?? '')),
        ];

        update_option('fp_esperienze_integrations', $integrations);

        return SettingsUpdateResult::success();
    }
}
