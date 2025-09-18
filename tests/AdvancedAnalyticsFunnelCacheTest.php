<?php
declare(strict_types=1);

use FP\Esperienze\Admin\AdvancedAnalytics;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
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
$transient_stats = [
    'get' => 0,
    'hit' => 0,
];

function get_transient(string $key) {
    global $transients, $transient_stats;

    $transient_stats['get']++;

    if (array_key_exists($key, $transients)) {
        $transient_stats['hit']++;
        return $transients[$key];
    }

    return false;
}

function set_transient(string $key, $value, int $ttl) {
    global $transients;

    $transients[$key] = $value;

    return true;
}

class WPDBStub {
    public string $prefix = 'wp_';
    public int $prepare_calls = 0;
    public int $get_var_calls = 0;

    /**
     * @return array{0: string, 1: array}
     */
    public function prepare(string $query, ...$args): array {
        $this->prepare_calls++;

        return [$query, $args];
    }

    public function get_var($prepared) {
        $this->get_var_calls++;

        return $this->get_var_calls === 1 ? 12 : 3456.78;
    }
}

$wpdb = new WPDBStub();
$GLOBALS['wpdb'] = $wpdb;

$reflection = new ReflectionClass(AdvancedAnalytics::class);
/** @var AdvancedAnalytics $analytics */
$analytics = $reflection->newInstanceWithoutConstructor();

$method = $reflection->getMethod('getConversionFunnelData');
$method->setAccessible(true);

$first_result = $method->invoke($analytics, '2024-01-01', '2024-01-31');
$first_db_calls = $wpdb->get_var_calls;

$second_result = $method->invoke($analytics, '2024-01-01', '2024-01-31');
$second_db_calls = $wpdb->get_var_calls;

if ($first_result !== $second_result) {
    echo "Cached funnel data mismatch\n";
    exit(1);
}

if ($second_db_calls !== $first_db_calls) {
    echo "Conversion funnel cache miss\n";
    exit(1);
}

if ($transient_stats['hit'] < 1) {
    echo "Transient cache was not hit\n";
    exit(1);
}

echo "Conversion funnel cache test passed\n";
