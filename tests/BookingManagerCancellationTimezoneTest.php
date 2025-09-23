<?php
declare(strict_types=1);

namespace {
    use FP\Esperienze\Booking\BookingManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    $GLOBALS['__wp_timezone_string'] = 'Pacific/Auckland';

    if (!function_exists('__')) {
        function __(string $text, $domain = null): string
        {
            return $text;
        }
    }

    if (!function_exists('wp_timezone')) {
        function wp_timezone(): \DateTimeZone
        {
            $timezone = $GLOBALS['__wp_timezone_string'] ?? 'UTC';

            return new \DateTimeZone($timezone);
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false)
        {
            $map = $GLOBALS['__fp_cancel_meta'] ?? [];

            if (!isset($map[$post_id])) {
                return '';
            }

            if ($key === '_fp_exp_free_cancel_until_minutes') {
                return $map[$post_id]['free_until'];
            }

            if ($key === '_fp_exp_cancel_fee_percent') {
                return $map[$post_id]['fee'];
            }

            return '';
        }
    }

    class FakeWpdb
    {
        public string $prefix = 'wp_';

        /** @param array<int, object> $bookings */
        public function __construct(private array $bookings)
        {
        }

        public function prepare($query, ...$args)
        {
            return (int) ($args[0] ?? 0);
        }

        public function get_row($prepared)
        {
            $id = (int) $prepared;

            return $this->bookings[$id] ?? null;
        }
    }

    require_once __DIR__ . '/../includes/Booking/BookingManager.php';

    $previous_timezone = date_default_timezone_get();
    date_default_timezone_set('UTC');

    $timezone = wp_timezone();
    $now = new \DateTimeImmutable('now', $timezone);

    $future_free = $now->add(new \DateInterval('PT5H'));
    $future_paid = $now->add(new \DateInterval('PT45M'));

    $GLOBALS['__fp_booking_rows'] = [
        501 => (object) [
            'id' => 501,
            'product_id' => 301,
            'booking_date' => $future_free->format('Y-m-d'),
            'booking_time' => $future_free->format('H:i:s'),
            'status' => 'confirmed',
        ],
        502 => (object) [
            'id' => 502,
            'product_id' => 302,
            'booking_date' => $future_paid->format('Y-m-d'),
            'booking_time' => $future_paid->format('H:i:s'),
            'status' => 'confirmed',
        ],
    ];

    $GLOBALS['wpdb'] = new FakeWpdb($GLOBALS['__fp_booking_rows']);

    $GLOBALS['__fp_cancel_meta'] = [
        301 => ['free_until' => '180', 'fee' => '30'],
        302 => ['free_until' => '60', 'fee' => '50'],
    ];

    $result_free = BookingManager::checkCancellationRules(501);

    if (!is_array($result_free) || ($result_free['can_cancel'] ?? false) !== true) {
        echo "BookingManager cancellation timezone test failed: missing cancellation response\n";
        exit(1);
    }

    $expected_deadline_free = $future_free->sub(new \DateInterval('PT180M'));

    if (!($result_free['deadline'] ?? null) instanceof \DateTimeImmutable) {
        echo "BookingManager cancellation timezone test failed: free deadline is not immutable\n";
        exit(1);
    }

    if ($result_free['deadline']->getTimezone()->getName() !== $timezone->getName()) {
        echo "BookingManager cancellation timezone test failed: free deadline timezone mismatch\n";
        exit(1);
    }

    if ($result_free['deadline']->format('Y-m-d H:i:s') !== $expected_deadline_free->format('Y-m-d H:i:s')) {
        echo "BookingManager cancellation timezone test failed: free deadline mismatch\n";
        exit(1);
    }

    if (($result_free['is_free'] ?? null) !== true) {
        echo "BookingManager cancellation timezone test failed: free cancellation flag incorrect\n";
        exit(1);
    }

    $result_paid = BookingManager::checkCancellationRules(502);

    if (!is_array($result_paid) || ($result_paid['can_cancel'] ?? false) !== true) {
        echo "BookingManager cancellation timezone test failed: missing cancellation response for paid case\n";
        exit(1);
    }

    if (!($result_paid['deadline'] ?? null) instanceof \DateTimeImmutable) {
        echo "BookingManager cancellation timezone test failed: paid deadline is not immutable\n";
        exit(1);
    }

    if ($result_paid['deadline']->getTimezone()->getName() !== $timezone->getName()) {
        echo "BookingManager cancellation timezone test failed: paid deadline timezone mismatch\n";
        exit(1);
    }

    $expected_deadline_paid = $future_paid->sub(new \DateInterval('PT60M'));

    if ($result_paid['deadline']->format('Y-m-d H:i:s') !== $expected_deadline_paid->format('Y-m-d H:i:s')) {
        echo "BookingManager cancellation timezone test failed: paid deadline mismatch\n";
        exit(1);
    }

    if (($result_paid['is_free'] ?? null) !== false) {
        echo "BookingManager cancellation timezone test failed: paid cancellation flag incorrect\n";
        exit(1);
    }

    if (($result_paid['fee_percent'] ?? null) !== '50') {
        echo "BookingManager cancellation timezone test failed: fee percent incorrect\n";
        exit(1);
    }

    date_default_timezone_set($previous_timezone);

    echo "BookingManager cancellation timezone test passed\n";
}
