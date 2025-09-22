<?php
declare(strict_types=1);

use FP\Esperienze\Admin\AdvancedAnalytics;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (!class_exists('WP_Error')) {
    class WP_Error {}
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct($data = null) {}
        public function header($name, $value) {}
        public function get_headers() {
            return [];
        }
        public function get_data() {
            return null;
        }
    }
}

require_once __DIR__ . '/../includes/Admin/AdvancedAnalytics.php';

$transients = [];
function get_transient(string $key) {
    global $transients;
    return $transients[$key] ?? false;
}

function set_transient(string $key, $value, int $ttl) {
    global $transients;
    $transients[$key] = $value;
    return true;
}

class WPDBDeterministicStub {
    public string $prefix = 'wp_';
    public int $prepare_calls = 0;
    public int $get_var_calls = 0;
    public array $event_counts = [
        'visit' => 1000,
        'product_view' => 600,
        'add_to_cart' => 250,
        'checkout_start' => 120,
    ];
    public int $purchase_count = 90;
    public float $total_revenue = 18000.0;
    private bool $suppress_errors = false;

    /**
     * @return array{0: string, 1: array}
     */
    public function prepare(string $query, ...$args): array {
        $this->prepare_calls++;
        return [$query, $args];
    }

    public function get_var($prepared) {
        $this->get_var_calls++;

        if (is_array($prepared)) {
            [$query, $args] = $prepared;

            if (str_contains($query, 'SHOW TABLES LIKE')) {
                return $this->prefix . 'fp_analytics_events';
            }

            if (str_contains($query, 'fp_analytics_events')) {
                $event_type = $args[0] ?? '';
                return $this->event_counts[$event_type] ?? 0;
            }

            if (str_contains($query, 'SUM(total_amount)')) {
                return $this->total_revenue;
            }

            if (str_contains($query, 'COUNT(*)') && str_contains($query, 'wp_wc_orders')) {
                return $this->purchase_count;
            }
        }

        return 0;
    }

    public function suppress_errors($suppress = null) {
        $previous = $this->suppress_errors;
        if ($suppress !== null) {
            $this->suppress_errors = (bool) $suppress;
        }
        return $previous;
    }
}

$wpdb = new WPDBDeterministicStub();
$GLOBALS['wpdb'] = $wpdb;

$reflection = new ReflectionClass(AdvancedAnalytics::class);
/** @var AdvancedAnalytics $analytics */
$analytics = $reflection->newInstanceWithoutConstructor();

$method = $reflection->getMethod('getConversionFunnelData');
$method->setAccessible(true);

$result = $method->invoke($analytics, '2024-02-01', '2024-02-29');

$steps = $result['funnel_steps'] ?? [];
if (count($steps) !== 5) {
    echo "Unexpected number of funnel steps\n";
    exit(1);
}

[$visits, $views, $cart, $checkout, $purchases] = $steps;

if ($visits['count'] !== 1000 || $views['count'] !== 600 || $cart['count'] !== 250 || $checkout['count'] !== 120 || $purchases['count'] !== 90) {
    echo "Funnel counts do not match expected values\n";
    exit(1);
}

$expected_rates = [
    100.0,
    60.0,
    41.67,
    48.0,
    75.0,
];

foreach ($steps as $index => $step) {
    if (abs($step['conversion_rate'] - $expected_rates[$index]) > 0.01) {
        echo "Unexpected conversion rate for step {$step['step']}\n";
        exit(1);
    }
}

if ($result['overall_conversion_rate'] !== 9.0) {
    echo "Unexpected overall conversion rate\n";
    exit(1);
}

if ($result['average_order_value'] !== 200.0) {
    echo "Unexpected average order value\n";
    exit(1);
}

echo "Deterministic funnel test passed\n";
