<?php
declare(strict_types=1);

namespace FP\Esperienze\Data {
    /**
     * Test stub for availability data.
     */
    class Availability
    {
        /**
         * @var array<int, array<string, array<int, array<string, mixed>>>>
         */
        public static array $data = [];

        /**
         * @return array<int, array<string, mixed>>
         */
        public static function forDay(int $product_id, string $date): array
        {
            return self::$data[$product_id][$date] ?? [];
        }
    }
}

namespace {
    use FP\Esperienze\REST\MobileAPIManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!function_exists('wp_timezone')) {
        function wp_timezone(): \DateTimeZone
        {
            return new \DateTimeZone('UTC');
        }
    }

    if (!function_exists('wp_timezone_string')) {
        function wp_timezone_string(): string
        {
            return 'UTC';
        }
    }

    if (!function_exists('apply_filters')) {
        /**
         * @param mixed $value
         * @return mixed
         */
        function apply_filters($tag, $value, ...$args)
        {
            return $value;
        }
    }

    require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

    $reflection = new \ReflectionClass(MobileAPIManager::class);
    /** @var MobileAPIManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('getAvailableDates');
    $method->setAccessible(true);

    $base = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    $firstDate = $base->modify('+1 day')->format('Y-m-d');
    $secondDate = $base->modify('+2 days')->format('Y-m-d');
    $thirdDate = $base->modify('+3 days')->format('Y-m-d');

    \FP\Esperienze\Data\Availability::$data = [
        45 => [
            $firstDate => [
                [
                    'schedule_id' => 201,
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                    'capacity' => 12,
                    'booked' => 5,
                    'available' => 7,
                    'held_count' => 1,
                    'adult_price' => 65.0,
                    'child_price' => 45.0,
                    'languages' => ['en', 'it'],
                    'meeting_point_id' => 9,
                    'is_available' => true,
                ],
                [
                    'schedule_id' => 202,
                    'start_time' => '15:00',
                    'end_time' => '17:00',
                    'capacity' => 10,
                    'booked' => 10,
                    'available' => 0,
                    'held_count' => 0,
                    'adult_price' => 70.0,
                    'child_price' => 50.0,
                    'is_available' => false,
                ],
            ],
            $secondDate => [
                [
                    'schedule_id' => 203,
                    'start_time' => '09:30',
                    'end_time' => '11:30',
                    'capacity' => 6,
                    'booked' => 0,
                    'available' => 6,
                    'held_count' => 0,
                    'adult_price' => 60.0,
                    'child_price' => 40.0,
                    'is_available' => true,
                ],
            ],
            $thirdDate => [],
        ],
    ];

    $result = $method->invoke($manager, 45, $firstDate);

    if (!is_array($result) || count($result) !== 2) {
        echo "Expected two availability entries for configured planner window\n";
        exit(1);
    }

    $first = $result[0];
    if (($first['date'] ?? '') !== $firstDate) {
        echo "Unexpected first availability date\n";
        exit(1);
    }

    if (($first['remaining_capacity'] ?? null) !== 7) {
        echo "First day remaining capacity mismatch\n";
        exit(1);
    }

    if (($first['prices']['adult_from'] ?? null) !== 65.0) {
        echo "First day adult price mismatch\n";
        exit(1);
    }

    if (($first['prices']['child_from'] ?? null) !== 45.0) {
        echo "First day child price mismatch\n";
        exit(1);
    }

    if (!isset($first['slots']) || !is_array($first['slots']) || count($first['slots']) !== 2) {
        echo "First day slot normalization failed\n";
        exit(1);
    }

    $slotTimes = array_column($first['slots'], 'start_time');
    if ($slotTimes !== ['10:00', '15:00']) {
        echo "Slot ordering mismatch for first day\n";
        exit(1);
    }

    if (($first['slots'][1]['available'] ?? null) !== 0) {
        echo "Sold-out slot should report zero availability\n";
        exit(1);
    }

    if (($first['slots'][0]['held_count'] ?? null) !== 1) {
        echo "Held capacity was not preserved\n";
        exit(1);
    }

    if (($first['slots'][0]['meeting_point_id'] ?? null) !== 9) {
        echo "Meeting point identifier mismatch\n";
        exit(1);
    }

    if (($first['slots'][0]['languages'] ?? null) !== ['en', 'it']) {
        echo "Languages metadata mismatch\n";
        exit(1);
    }

    $second = $result[1];
    if (($second['date'] ?? '') !== $secondDate) {
        echo "Unexpected second availability date\n";
        exit(1);
    }

    if (($second['remaining_capacity'] ?? null) !== 6) {
        echo "Second day remaining capacity mismatch\n";
        exit(1);
    }

    if (($second['prices']['adult_from'] ?? null) !== 60.0) {
        echo "Second day adult price mismatch\n";
        exit(1);
    }

    if (($second['prices']['child_from'] ?? null) !== 40.0) {
        echo "Second day child price mismatch\n";
        exit(1);
    }

    if (($second['slots'][0]['start_time'] ?? null) !== '09:30') {
        echo "Second day slot start time mismatch\n";
        exit(1);
    }

    echo "Mobile API availability mapping tests passed\n";
}
