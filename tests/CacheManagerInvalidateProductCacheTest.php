<?php
declare(strict_types=1);

use FP\Esperienze\Core\CacheManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../includes/Core/CacheManager.php';

$object_cache = [
    'transient' => [],
];
$options_store = [];

function wp_using_ext_object_cache(): bool {
    return true;
}

function wp_cache_set(string $key, $value, string $group = '', int $expiration = 0): bool {
    global $object_cache;

    if (!isset($object_cache[$group])) {
        $object_cache[$group] = [];
    }

    $object_cache[$group][$key] = $value;

    return true;
}

function wp_cache_get(string $key, string $group = '') {
    global $object_cache;

    if (isset($object_cache[$group][$key])) {
        return $object_cache[$group][$key];
    }

    return false;
}

function wp_cache_get_multiple(array $keys, string $group = ''): array {
    $results = [];

    foreach ($keys as $key) {
        $results[$key] = wp_cache_get($key, $group);
    }

    return $results;
}

function wp_cache_delete(string $key, string $group = ''): bool {
    global $object_cache;

    if (isset($object_cache[$group][$key])) {
        unset($object_cache[$group][$key]);
        return true;
    }

    return false;
}

function set_transient(string $key, $value, int $ttl): bool {
    return wp_cache_set($key, $value, 'transient', $ttl);
}

function get_transient(string $key) {
    return wp_cache_get($key, 'transient');
}

function delete_transient(string $key): bool {
    return wp_cache_delete($key, 'transient');
}

function get_option(string $key, $default = false) {
    global $options_store;

    return $options_store[$key] ?? $default;
}

function update_option(string $key, $value): bool {
    global $options_store;

    $options_store[$key] = $value;

    return true;
}

function delete_option(string $key): bool {
    global $options_store;

    if (array_key_exists($key, $options_store)) {
        unset($options_store[$key]);
        return true;
    }

    return false;
}

$product_id = 101;
$dates = ['2024-07-01', '2024-07-02'];

foreach ($dates as $date) {
    $data = [
        'slots' => [
            ['id' => 1, 'is_available' => true, 'available' => 4],
        ],
    ];

    if (!CacheManager::setAvailabilityCache($product_id, $date, $data, 300)) {
        echo "Failed to prime availability cache\n";
        exit(1);
    }
}

foreach ($dates as $date) {
    $cache_key = 'fp_availability_' . $product_id . '_' . $date;
    if (false === get_transient($cache_key)) {
        echo "Availability cache missing for {$date}\n";
        exit(1);
    }
}

$index = get_option('fp_esperienze_availability_cache_index', []);
if (!isset($index[$product_id]) || count($index[$product_id]) !== count($dates)) {
    echo "Availability index tracking failed\n";
    exit(1);
}

CacheManager::invalidateProductCache($product_id);

foreach ($dates as $date) {
    $cache_key = 'fp_availability_' . $product_id . '_' . $date;
    if (false !== get_transient($cache_key)) {
        echo "Transient {$cache_key} still present\n";
        exit(1);
    }
}

$index_after = get_option('fp_esperienze_availability_cache_index', []);
if (!empty($index_after)) {
    echo "Availability index not cleared for product {$product_id}\n";
    exit(1);
}

echo "CacheManager invalidateProductCache object cache test passed\n";

