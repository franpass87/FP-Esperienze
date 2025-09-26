<?php
declare(strict_types=1);

use FP\Esperienze\Admin\Settings\Services\BrandingSettingsService;
use FP\Esperienze\Admin\Settings\Services\BookingSettingsService;
use FP\Esperienze\Admin\Settings\Services\GeneralSettingsService;
use FP\Esperienze\Admin\Settings\Services\GiftSettingsService;
use FP\Esperienze\Admin\Settings\Services\IntegrationsSettingsService;
use FP\Esperienze\Admin\Settings\Services\NotificationsSettingsService;
use FP\Esperienze\Admin\Settings\Services\WebhookSettingsService;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../includes/Admin/Settings/Services/SettingsUpdateResult.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/SettingsTabServiceInterface.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/GeneralSettingsService.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/BrandingSettingsService.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/GiftSettingsService.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/BookingSettingsService.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/IntegrationsSettingsService.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/NotificationsSettingsService.php';
require_once __DIR__ . '/../includes/Admin/Settings/Services/WebhookSettingsService.php';

$options = [];

if (!function_exists('update_option')) {
    function update_option(string $name, $value) {
        global $options;
        $options[$name] = $value;
        return true;
    }
}

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false) {
        global $options;
        return $options[$name] ?? $default;
    }
}

if (!function_exists('absint')) {
    function absint($value): int
    {
        return (int) max(0, (int) $value);
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value)
    {
        return $value;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return is_string($value) ? trim($value) : '';
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value)
    {
        return is_string($value) ? trim($value) : '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email)
    {
        return is_string($email) ? trim($email) : '';
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return is_string($url) ? filter_var($url, FILTER_SANITIZE_URL) : '';
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($color)
    {
        if (!is_string($color)) {
            return '';
        }

        $color = trim($color);
        if (preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color) !== 1) {
            return '';
        }

        if (strlen($color) === 4) {
            $color = sprintf(
                '#%1$s%1$s%2$s%2$s%3$s%3$s',
                $color[1],
                $color[2],
                $color[3]
            );
        }

        return strtolower($color);
    }
}

if (!function_exists('is_email')) {
    function is_email($email)
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

$general = new GeneralSettingsService();
$result = $general->handle([
    'archive_page_id' => ' 24 ',
    'wpml_auto_send' => '1',
]);

if (!$result->isSuccess() || get_option('fp_esperienze_archive_page_id') !== 24) {
    echo "General settings update failed\n";
    exit(1);
}

if (get_option('fp_esperienze_wpml_auto_send') !== true) {
    echo "General auto-send flag not stored\n";
    exit(1);
}

$branding = new BrandingSettingsService();
$brandingResult = $branding->handle([
    'primary_font' => 'Comic Sans MS',
    'heading_font' => "'Roboto', sans-serif",
    'primary_color' => '#ABC',
    'secondary_color' => 'not-a-color',
]);

$brandingOptions = get_option('fp_esperienze_branding');
if (!$brandingResult->isSuccess()) {
    echo "Branding settings should succeed\n";
    exit(1);
}

if ($brandingOptions['primary_font'] !== 'inherit' || $brandingOptions['heading_font'] !== "'Roboto', sans-serif") {
    echo "Branding font validation failed\n";
    exit(1);
}

if ($brandingOptions['primary_color'] !== '#aabbcc' || $brandingOptions['secondary_color'] !== '#2c3e50') {
    echo "Branding color sanitization failed\n";
    exit(1);
}

$gift = new GiftSettingsService();
$giftResult = $gift->handle([
    'gift_default_exp_months' => '18',
    'gift_pdf_logo' => 'https://example.com/logo.png',
    'gift_pdf_brand_color' => 'invalid',
    'gift_email_sender_name' => '  Sender ',
    'gift_email_sender_email' => 'sender@example.com',
    'gift_terms' => " Terms \n",
    'regenerate_secret' => '1',
]);

if (!$giftResult->isSuccess()) {
    echo "Gift settings should succeed\n";
    exit(1);
}

if (strlen((string) get_option('fp_esperienze_gift_secret_hmac')) !== 64) {
    echo "Gift secret was not regenerated\n";
    exit(1);
}

if (get_option('fp_esperienze_gift_pdf_brand_color') !== '#ff6b35') {
    echo "Gift brand color fallback failed\n";
    exit(1);
}

$booking = new BookingSettingsService();
$booking->handle([
    'enable_holds' => '1',
    'hold_duration' => '120',
]);

if (get_option('fp_esperienze_enable_holds') !== true || get_option('fp_esperienze_hold_duration_minutes') !== 60) {
    echo "Booking limits not applied\n";
    exit(1);
}

$integrations = new IntegrationsSettingsService();
$integrations->handle([
    'ga4_measurement_id' => ' G-123 ',
    'ga4_ecommerce' => '1',
    'gplaces_reviews_limit' => '-2',
    'gplaces_cache_ttl' => '2000',
    'consent_cookie_name' => ' consent ',
]);

$storedIntegrations = get_option('fp_esperienze_integrations');
if ($storedIntegrations['ga4_measurement_id'] !== 'G-123' || $storedIntegrations['gplaces_reviews_limit'] !== 1) {
    echo "Integrations sanitization failed\n";
    exit(1);
}

if ($storedIntegrations['gplaces_cache_ttl'] !== 1440) {
    echo "Integrations TTL clamp failed\n";
    exit(1);
}

$notifications = new NotificationsSettingsService();
$notificationResult = $notifications->handle([
    'staff_notifications_enabled' => '1',
    'staff_emails' => "valid@example.com\ninvalid-email",
    'ics_attachment_enabled' => '',
]);

$storedNotifications = get_option('fp_esperienze_notifications');
if ($storedNotifications['staff_emails'] !== 'valid@example.com') {
    echo "Notification email filtering failed\n";
    exit(1);
}

if (empty($notificationResult->getErrors())) {
    echo "Notification service should report invalid emails\n";
    exit(1);
}

$webhooks = new WebhookSettingsService();
$webhooks->handle([
    'webhook_new_booking' => 'https://example.com/new ',
    'webhook_cancellation' => 'https://example.com/cancel',
    'webhook_reschedule' => 'https://example.com/reschedule',
    'webhook_secret' => ' secret ',
    'webhook_hide_pii' => '1',
]);

if (get_option('fp_esperienze_webhook_hide_pii') !== true) {
    echo "Webhook boolean not saved\n";
    exit(1);
}

if (get_option('fp_esperienze_webhook_secret') !== 'secret') {
    echo "Webhook secret sanitization failed\n";
    exit(1);
}

if ($notificationResult->isSuccess() && $notificationResult->getErrors() !== ['Invalid email address: invalid-email']) {
    echo "Notification errors did not match expectation\n";
    exit(1);
}

echo "Settings services tests passed\n";
