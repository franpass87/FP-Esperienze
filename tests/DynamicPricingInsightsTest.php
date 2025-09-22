<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    $GLOBALS['__dp_test_time'] = strtotime('2024-05-01 12:00:00');
}

namespace FP\Esperienze\Data {
    function time(): int {
        return $GLOBALS['__dp_test_time'] ?? \time();
    }
}

namespace {

use FP\Esperienze\AI\AIFeaturesManager;

if (!function_exists('absint')) {
    function absint($value): int
    {
        return (int) max(0, (int) $value);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text)
    {
        if (!is_string($text)) {
            return '';
        }

        return preg_replace('/[^a-z0-9_\- ]/i', '', $text);
    }
}

$options_storage = [];

function get_option(string $name, $default = false)
{
    global $options_storage;
    return $options_storage[$name] ?? $default;
}

function update_option(string $name, $value, $autoload = null)
{
    global $options_storage;
    $options_storage[$name] = $value;
    return true;
}

if (!function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

require_once __DIR__ . '/../includes/Data/DynamicPricingManager.php';
require_once __DIR__ . '/../includes/AI/AIFeaturesManager.php';

$base_time = $GLOBALS['__dp_test_time'];

$history = [
    [
        'timestamp' => $base_time - (2 * 86400),
        'product_id' => 101,
        'price_type' => 'adult',
        'base_price' => 100.0,
        'final_price' => 120.0,
        'adjustment_amount' => 20.0,
        'adjustment_percent' => 20.0,
        'rules' => [
            [
                'rule_name' => 'Weekend Premium',
                'rule_type' => 'weekend_weekday',
                'adjustment' => 20.0,
                'adjustment_type' => 'percentage',
            ],
        ],
        'total_participants' => 2,
        'booking_date' => '2024-04-28',
    ],
    [
        'timestamp' => $base_time - (5 * 86400),
        'product_id' => 102,
        'price_type' => 'adult',
        'base_price' => 80.0,
        'final_price' => 68.0,
        'adjustment_amount' => -12.0,
        'adjustment_percent' => -15.0,
        'rules' => [
            [
                'rule_name' => 'Early Bird',
                'rule_type' => 'early_bird',
                'adjustment' => -15.0,
                'adjustment_type' => 'percentage',
            ],
        ],
        'total_participants' => 4,
        'booking_date' => '2024-04-25',
    ],
    [
        'timestamp' => $base_time - (10 * 86400),
        'product_id' => 103,
        'price_type' => 'child',
        'base_price' => 90.0,
        'final_price' => 99.0,
        'adjustment_amount' => 9.0,
        'adjustment_percent' => 10.0,
        'rules' => [
            [
                'rule_name' => 'Seasonal Lift',
                'rule_type' => 'seasonal',
                'adjustment' => 10.0,
                'adjustment_type' => 'percentage',
            ],
        ],
        'total_participants' => 3,
        'booking_date' => '2024-04-20',
    ],
];

update_option('fp_dynamic_pricing_history', $history);

$reflection = new \ReflectionClass(AIFeaturesManager::class);
/** @var AIFeaturesManager $manager */
$manager = $reflection->newInstanceWithoutConstructor();

$settings_property = $reflection->getProperty('settings');
$settings_property->setAccessible(true);
$settings_property->setValue($manager, [
    'dynamic_pricing_enabled' => true,
]);

$method = $reflection->getMethod('getPricingInsights');
$method->setAccessible(true);
$result = $method->invoke($manager, '30');

if (!is_array($result)) {
    echo "Pricing insights did not return an array\n";
    exit(1);
}

if (($result['dynamic_pricing_active'] ?? false) !== true) {
    echo "Dynamic pricing flag mismatch\n";
    exit(1);
}

if (($result['price_adjustments'] ?? 0) !== 3) {
    echo "Unexpected adjustment count\n";
    exit(1);
}

if (($result['revenue_impact'] ?? '') !== '+6.3%') {
    echo 'Unexpected revenue impact: ' . ($result['revenue_impact'] ?? 'missing') . "\n";
    exit(1);
}

$summary = $result['adjustment_summary'] ?? [];
if (!is_array($summary) || ($summary['increases'] ?? null) !== 2 || ($summary['decreases'] ?? null) !== 1) {
    echo "Adjustment summary mismatch\n";
    exit(1);
}

if (abs(($summary['average_percent'] ?? 0.0) - 5.0) > 0.01) {
    echo 'Unexpected average percent: ' . ($summary['average_percent'] ?? 'missing') . "\n";
    exit(1);
}

$expected_recommendations = [
    'Analyzed 3 adjustments in the last 30 days.',
    'Positive revenue impact detected (6.3%) with 2 price increases versus 1 decrease.',
    'Weekend pricing rules drive most adjustments — keep weekend premiums optimized.',
];

if (($result['recommendations'] ?? []) !== $expected_recommendations) {
    echo "Recommendations output mismatch\n";
    var_export($result['recommendations'] ?? null);
    exit(1);
}

echo "Dynamic pricing insights deterministic test passed\n";
}
