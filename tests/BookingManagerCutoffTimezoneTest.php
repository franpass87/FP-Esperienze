<?php
declare(strict_types=1);

namespace {
    use FP\Esperienze\Booking\BookingManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    $GLOBALS['__fp_cutoff_meta'] = [
        501 => '120',
    ];
    $GLOBALS['__wp_timezone_string'] = 'Europe/Rome';

    if (!function_exists('wp_timezone')) {
        function wp_timezone(): \DateTimeZone
        {
            $timezone = $GLOBALS['__wp_timezone_string'] ?? 'UTC';

            return new \DateTimeZone($timezone);
        }
    }

    if (!function_exists('__')) {
        function __(string $text, $domain = null): string
        {
            return $text;
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false)
        {
            $map = $GLOBALS['__fp_cutoff_meta'] ?? [];
            if ('_fp_exp_cutoff_minutes' === $key && array_key_exists($post_id, $map)) {
                return $map[$post_id];
            }

            return '';
        }
    }

    require_once __DIR__ . '/../includes/Booking/BookingManager.php';

    $previous_timezone = date_default_timezone_get();
    date_default_timezone_set('America/New_York');

    $store_timezone = wp_timezone();
    $now = new \DateTimeImmutable('now', $store_timezone);
    $slot = $now->add(new \DateInterval('PT60M'));

    $slot_date = $slot->format('Y-m-d');
    $slot_time = $slot->format('H:i:s');

    $reflection = new \ReflectionClass(BookingManager::class);
    $method = $reflection->getMethod('validateCutoffTime');
    $method->setAccessible(true);

    $result = $method->invoke(null, 501, $slot_date, $slot_time);

    date_default_timezone_set($previous_timezone);

    if (!is_array($result) || !array_key_exists('valid', $result)) {
        echo "BookingManager cutoff timezone test failed: invalid result structure\n";
        exit(1);
    }

    if ($result['valid'] !== false) {
        echo "BookingManager cutoff timezone test failed: slot within cutoff should be rejected across timezones\n";
        exit(1);
    }

    if (strpos((string) ($result['message'] ?? ''), '120') === false) {
        echo "BookingManager cutoff timezone test failed: cutoff minutes missing from message\n";
        exit(1);
    }

    echo "BookingManager cutoff timezone test passed\n";
}
