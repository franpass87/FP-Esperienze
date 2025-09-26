<?php
/**
 * Gift settings tab service.
 *
 * @package FP\Esperienze\Admin\Settings\Services
 */

namespace FP\Esperienze\Admin\Settings\Services;

class GiftSettingsService implements SettingsTabServiceInterface
{
    private const DEFAULT_EXPIRY_MONTHS = 12;
    private const DEFAULT_BRAND_COLOR  = '#ff6b35';

    public function handle(array $data): SettingsUpdateResult
    {
        $settings = [
            'fp_esperienze_gift_default_exp_months' => absint(wp_unslash($data['gift_default_exp_months'] ?? self::DEFAULT_EXPIRY_MONTHS)),
            'fp_esperienze_gift_pdf_logo' => esc_url_raw(wp_unslash($data['gift_pdf_logo'] ?? '')),
            'fp_esperienze_gift_pdf_brand_color' => sanitize_hex_color(wp_unslash($data['gift_pdf_brand_color'] ?? self::DEFAULT_BRAND_COLOR))
                ?: self::DEFAULT_BRAND_COLOR,
            'fp_esperienze_gift_email_sender_name' => sanitize_text_field(wp_unslash($data['gift_email_sender_name'] ?? '')),
            'fp_esperienze_gift_email_sender_email' => sanitize_email(wp_unslash($data['gift_email_sender_email'] ?? '')),
            'fp_esperienze_gift_terms' => sanitize_textarea_field(wp_unslash($data['gift_terms'] ?? '')),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        $messages = [];

        if (!empty($data['regenerate_secret'])) {
            $newSecret = bin2hex(random_bytes(32));
            update_option('fp_esperienze_gift_secret_hmac', $newSecret);
            $messages[] = __('A new gift voucher secret was generated.', 'fp-esperienze');
        }

        return SettingsUpdateResult::success($messages);
    }
}
