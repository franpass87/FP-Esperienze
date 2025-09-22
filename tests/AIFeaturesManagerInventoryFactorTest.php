<?php
declare(strict_types=1);

namespace FP\Esperienze\Data {
    /**
     * Test stub for the availability aggregator used by AI features.
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
    use FP\Esperienze\AI\AIFeaturesManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!function_exists('wp_timezone')) {
        function wp_timezone(): \DateTimeZone
        {
            return new \DateTimeZone('UTC');
        }
    }

    require_once __DIR__ . '/../includes/AI/AIFeaturesManager.php';

    $reflection = new \ReflectionClass(AIFeaturesManager::class);
    /** @var AIFeaturesManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $settingsProperty = $reflection->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settingsProperty->setValue($manager, []);

    $inventoryMethod = $reflection->getMethod('calculateInventoryFactor');
    $inventoryMethod->setAccessible(true);
    $availableMethod = $reflection->getMethod('getAvailableSlots');
    $availableMethod->setAccessible(true);

    $baseDate = new \DateTimeImmutable('today', wp_timezone());
    $dates = [];
    for ($i = 0; $i < 3; $i++) {
        $dates[] = $baseDate->modify("+{$i} days")->format('Y-m-d');
    }

    // Scenario 1: constrained inventory, heavy bookings and holds across days.
    \FP\Esperienze\Data\Availability::$data = [
        321 => [
            $dates[0] => [
                ['capacity' => 10, 'booked' => 8, 'held_count' => 1],
                ['capacity' => 8, 'booked' => 7, 'held_count' => 0],
            ],
            $dates[1] => [
                ['capacity' => 12, 'booked' => 10, 'held_count' => 1],
            ],
            $dates[2] => [
                ['capacity' => 6, 'booked' => 5, 'held_count' => 0],
            ],
        ],
    ];

    $availabilityMetrics = $availableMethod->invoke($manager, 321, 7);
    if (($availabilityMetrics['total_capacity'] ?? 0) !== 36) {
        echo "Aggregated capacity should total 36 seats across configured slots\n";
        exit(1);
    }

    if (($availabilityMetrics['available_capacity'] ?? 0) !== 4) {
        echo "Residual availability should consider booked seats and holds\n";
        exit(1);
    }

    if (($availabilityMetrics['days_evaluated'] ?? 0) !== 3) {
        echo "Day counter should reflect scheduled inventory only\n";
        exit(1);
    }

    $lowInventoryFactor = $inventoryMethod->invoke($manager, 321);
    if (abs($lowInventoryFactor - 1.8) > 0.001) {
        echo "Inventory factor should surge when remaining capacity is scarce\n";
        exit(1);
    }

    // Scenario 2: abundant availability encourages gentler pricing.
    \FP\Esperienze\Data\Availability::$data = [
        321 => [
            $dates[0] => [
                ['capacity' => 10, 'booked' => 0, 'held_count' => 0, 'available' => 10],
            ],
            $dates[1] => [
                ['capacity' => 8, 'booked' => 0, 'held_count' => 0, 'available' => 8],
            ],
        ],
    ];

    $highInventoryFactor = $inventoryMethod->invoke($manager, 321);
    if (abs($highInventoryFactor - 0.7) > 0.001) {
        echo "Inventory factor should relax pricing when plenty of seats remain\n";
        exit(1);
    }

    echo "AI inventory factor availability regression passed\n";
}
