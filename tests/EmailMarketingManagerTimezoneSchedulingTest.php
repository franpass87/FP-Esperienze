<?php
declare(strict_types=1);

namespace FP\Esperienze\Integrations {
    class BrevoManager
    {
        public function __construct()
        {
        }

        public function isEnabled(): bool
        {
            return false;
        }
    }
}

namespace {
    use FP\Esperienze\Integrations\EmailMarketingManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    /**
     * @var array<int, array{hook:string,args:array,timestamp:int}> $scheduled_events
     */
    $scheduled_events = [];

    /**
     * @var int $local_now
     */
    $local_now = 0;

    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
    }

    function get_option(string $name, $default = false)
    {
        return $default;
    }

    function wp_schedule_event($timestamp, $recurrence, $hook)
    {
        return true;
    }

    function current_time(string $type)
    {
        global $local_now;

        if ($type === 'timestamp') {
            return $local_now;
        }

        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s', $local_now);
        }

        return '';
    }

    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('America/New_York');
    }

    function wp_schedule_single_event($timestamp, $hook, $args = [])
    {
        global $scheduled_events;

        $scheduled_events[] = [
            'timestamp' => (int) $timestamp,
            'hook' => (string) $hook,
            'args' => $args,
        ];

        return true;
    }

    function wp_next_scheduled($hook, $args = [], $timestamp = null)
    {
        global $scheduled_events;

        foreach ($scheduled_events as $event) {
            if ($event['hook'] !== $hook) {
                continue;
            }

            if (!empty($args) && $event['args'] != $args) {
                continue;
            }

            if ($timestamp !== null && (int) $event['timestamp'] !== (int) $timestamp) {
                continue;
            }

            return (int) $event['timestamp'];
        }

        return false;
    }

    require_once __DIR__ . '/../includes/Integrations/EmailMarketingManager.php';

    /**
     * @param mixed  $expected
     * @param mixed  $actual
     * @param string $message
     */
    function assert_same($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            echo $message, "\n";
            echo 'Expected: ' . var_export($expected, true) . "\n";
            echo 'Actual: ' . var_export($actual, true) . "\n";
            exit(1);
        }
    }

    function assert_true(bool $condition, string $message): void
    {
        if (!$condition) {
            echo $message, "\n";
            exit(1);
        }
    }

    $manager = new EmailMarketingManager();

    global $scheduled_events;
    $scheduled_events = [];

    global $local_now;
    $timezone = wp_timezone();
    $local_now = (new \DateTimeImmutable('2005-03-20 08:15:00', $timezone))->getTimestamp();

    $reflection = new \ReflectionClass($manager);
    $reviewMethod = $reflection->getMethod('scheduleReviewRequestEmail');
    $reviewMethod->setAccessible(true);

    $booking_id = 321;
    $booking_data = ['customer_email' => 'visitor@example.com'];

    $reviewMethod->invoke($manager, $booking_id, $booking_data);

    $reviewEvents = array_values(array_filter(
        $scheduled_events,
        static fn(array $event): bool => $event['hook'] === 'fp_send_review_request'
    ));

    assert_same(1, count($reviewEvents), 'Review request event was not scheduled');

    $expected_review_timestamp = $local_now + (2 * DAY_IN_SECONDS);
    assert_same($expected_review_timestamp, $reviewEvents[0]['timestamp'], 'Review request timestamp mismatch');

    $reviewDate = (new \DateTimeImmutable('@' . $reviewEvents[0]['timestamp']))->setTimezone($timezone);
    assert_same('08:15', $reviewDate->format('H:i'), 'Review request scheduled at wrong local hour');

    $reviewMethod->invoke($manager, $booking_id, $booking_data);
    $reviewEvents = array_values(array_filter(
        $scheduled_events,
        static fn(array $event): bool => $event['hook'] === 'fp_send_review_request'
    ));
    assert_same(1, count($reviewEvents), 'Review request scheduled multiple times despite guard');

    $upsellMethod = $reflection->getMethod('scheduleUpsellingEmail');
    $upsellMethod->setAccessible(true);

    $local_now = (new \DateTimeImmutable('2005-03-20 12:45:00', $timezone))->getTimestamp();

    $upsellMethod->invoke($manager, $booking_id, $booking_data);

    $upsellEvents = array_values(array_filter(
        $scheduled_events,
        static fn(array $event): bool => $event['hook'] === 'fp_send_upselling_email'
    ));
    assert_same(1, count($upsellEvents), 'Upselling email event was not scheduled');

    $expected_upsell_timestamp = $local_now + (7 * DAY_IN_SECONDS);
    assert_same($expected_upsell_timestamp, $upsellEvents[0]['timestamp'], 'Upselling timestamp mismatch');

    $upsellDate = (new \DateTimeImmutable('@' . $upsellEvents[0]['timestamp']))->setTimezone($timezone);
    assert_same('12:45', $upsellDate->format('H:i'), 'Upselling email scheduled at wrong local hour');

    $upsellMethod->invoke($manager, $booking_id, $booking_data);
    $upsellEvents = array_values(array_filter(
        $scheduled_events,
        static fn(array $event): bool => $event['hook'] === 'fp_send_upselling_email'
    ));
    assert_same(1, count($upsellEvents), 'Upselling email scheduled multiple times despite guard');

    echo "EmailMarketingManager timezone scheduling test passed\n";
}
