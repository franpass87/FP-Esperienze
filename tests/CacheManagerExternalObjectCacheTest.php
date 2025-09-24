<?php
declare(strict_types=1);

use FP\Esperienze\Core\CacheManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

require_once __DIR__ . '/../includes/Core/CacheManager.php';

$object_cache_enabled = false;
$availability_index = [
    123 => ['fp_availability_123_2024-01-01'],
];
$deleted_options = [];
$deleted_transients = [];

function wp_using_ext_object_cache(): bool {
    global $object_cache_enabled;

    return $object_cache_enabled;
}

function get_option(string $key, $default = false) {
    global $availability_index;

    if ('fp_esperienze_availability_cache_index' === $key) {
        return $availability_index;
    }

    return $default;
}

function update_option(string $key, $value): bool {
    global $availability_index;

    if ('fp_esperienze_availability_cache_index' === $key) {
        $availability_index = $value;
    }

    return true;
}

function delete_option(string $key): bool {
    global $deleted_options;

    $deleted_options[] = $key;

    return true;
}

function delete_transient(string $key): bool {
    global $deleted_transients;

    $deleted_transients[] = $key;

    return true;
}

class CacheManagerObjectCacheWpdbStub {
    public string $options = 'wp_options';
    public int $prepare_calls = 0;
    public int $get_col_calls = 0;
    private array $initial_results;
    private array $results;

    public function __construct()
    {
        $this->initial_results = [
            ['_transient_fp_availability_123_2024-01-02'],
            ['_transient_timeout_fp_availability_123_2024-01-02'],
        ];
        $this->results = $this->initial_results;
    }

    public function reset(): void
    {
        $this->prepare_calls = 0;
        $this->get_col_calls = 0;
        $this->results = $this->initial_results;
    }

    public function esc_like(string $text): string
    {
        return $text;
    }

    public function prepare(string $query, ...$args): string
    {
        $this->prepare_calls++;

        return $query;
    }

    public function get_col(string $query): array
    {
        $this->get_col_calls++;

        return array_shift($this->results) ?? [];
    }
}

global $wpdb;
$wpdb = new CacheManagerObjectCacheWpdbStub();

CacheManager::invalidateProductCache(123);

if (0 === $wpdb->get_col_calls) {
    echo "Expected database lookups when object cache disabled\n";
    exit(1);
}

$transient_option_deletes = array_values(array_filter(
    $deleted_options,
    static fn(string $name): bool => str_starts_with($name, '_transient_')
));

if (empty($transient_option_deletes)) {
    echo "Expected transient option deletions when object cache disabled\n";
    exit(1);
}

$availability_index = [
    123 => ['fp_availability_123_2024-01-01'],
];
$deleted_options = [];
$deleted_transients = [];
$object_cache_enabled = true;
$wpdb->reset();

CacheManager::invalidateProductCache(123);

if (0 !== $wpdb->get_col_calls) {
    echo "Database lookup executed despite external object cache\n";
    exit(1);
}

$transient_option_deletes = array_values(array_filter(
    $deleted_options,
    static fn(string $name): bool => str_starts_with($name, '_transient_')
));

if (!empty($transient_option_deletes)) {
    echo "Transient options deleted despite external object cache\n";
    exit(1);
}

echo "CacheManager external object cache regression test passed\n";
