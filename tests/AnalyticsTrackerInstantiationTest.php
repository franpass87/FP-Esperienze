<?php
declare(strict_types=1);

use FP\Esperienze\Core\AnalyticsTracker;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('get_transient')) {
    function get_transient(string $key) {
        return false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient(string $key, $value, int $ttl) {
        return true;
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false) {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return 0;
    }
}

require_once __DIR__ . '/../includes/Core/AnalyticsTracker.php';

$tracker = new AnalyticsTracker();

$reflection = new ReflectionClass($tracker);
$property = $reflection->getProperty('tableName');
$property->setAccessible(true);
$table_name = $property->getValue($tracker);

if ($table_name !== '') {
    echo "Analytics tracker table name should default to an empty string when wpdb is unavailable\n";
    exit(1);
}

$availability_method = $reflection->getMethod('isTableAvailable');
$availability_method->setAccessible(true);
$table_available = $availability_method->invoke($tracker);

if ($table_available !== false) {
    echo "Analytics tracker should report the events table as unavailable when wpdb is missing\n";
    exit(1);
}

$record_method = $reflection->getMethod('recordEvent');
$record_method->setAccessible(true);
$record_method->invoke($tracker, 'test_event', 'session-123', 0, null, false);

echo "Analytics tracker instantiation test passed\n";
