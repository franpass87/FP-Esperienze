<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    /** @var array<int, array<int, array<string, mixed>>> $productExtraMetaStore */
    $productExtraMetaStore = [
        101 => [
            11 => ['note' => 'Include photo delivery via email'],
            12 => ['note' => 'Driver waits 15 minutes'],
        ],
    ];

    function get_post_meta(int $post_id, string $key, bool $single = false)
    {
        global $productExtraMetaStore;

        if ($key === '_fp_product_extra_meta') {
            return $productExtraMetaStore[$post_id] ?? [];
        }

        return '';
    }

    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value, ...$args)
        {
            return $value;
        }
    }

    class WPDBMobileExtrasStub
    {
        public string $prefix = 'wp_';

        public string $last_error = '';

        /** @var array<int, array<int, object>> */
        public array $extraResults = [];

        /**
         * @return array{0: string, 1: array}
         */
        public function prepare(string $query, ...$args): array
        {
            return [$query, $args];
        }

        /**
         * @param array{0: string, 1: array} $prepared
         * @return array<int, object>
         */
        public function get_results($prepared): array
        {
            [$query, $args] = $prepared;

            if (str_contains($query, $this->prefix . 'fp_product_extras')) {
                $product_id = (int) ($args[0] ?? 0);

                return $this->extraResults[$product_id] ?? [];
            }

            return [];
        }
    }

    $wpdb = new WPDBMobileExtrasStub();
    $GLOBALS['wpdb'] = $wpdb;

    $wpdb->extraResults[101] = [
        (object) [
            'id' => 11,
            'name' => 'Photography',
            'description' => 'Professional photographer on tour',
            'price' => '25.00',
            'billing_type' => 'per_person',
            'tax_class' => '',
            'is_required' => 0,
            'max_quantity' => 2,
            'sort_order' => 1,
        ],
        (object) [
            'id' => 12,
            'name' => 'Private transfer',
            'description' => 'Return transfer to hotel',
            'price' => '50.00',
            'billing_type' => 'per_booking',
            'tax_class' => 'reduced-rate',
            'is_required' => 1,
            'max_quantity' => 1,
            'sort_order' => 2,
        ],
    ];

    require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

    $reflection = new \ReflectionClass(\FP\Esperienze\REST\MobileAPIManager::class);
    /** @var \FP\Esperienze\REST\MobileAPIManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $extrasMethod = $reflection->getMethod('getExperienceExtras');
    $extrasMethod->setAccessible(true);

    $extras = $extrasMethod->invoke($manager, 101);

    if (!is_array($extras) || count($extras) !== 2) {
        echo "Expected two extras for product 101\n";
        exit(1);
    }

    $first = $extras[0];
    if ($first['id'] !== 11) {
        echo "Unexpected first extra identifier\n";
        exit(1);
    }

    if ($first['price'] !== 25.0) {
        echo "First extra price mismatch\n";
        exit(1);
    }

    if ($first['billing_type'] !== 'per_person' || $first['type'] !== 'per_person') {
        echo "First extra billing type mismatch\n";
        exit(1);
    }

    if ($first['is_required'] !== false) {
        echo "First extra should not be required\n";
        exit(1);
    }

    if ($first['max_quantity'] !== 2) {
        echo "First extra max quantity mismatch\n";
        exit(1);
    }

    if ($first['metadata'] !== ['note' => 'Include photo delivery via email']) {
        echo "First extra metadata mismatch\n";
        exit(1);
    }

    $second = $extras[1];
    if ($second['id'] !== 12) {
        echo "Unexpected second extra identifier\n";
        exit(1);
    }

    if ($second['is_required'] !== true) {
        echo "Second extra should be required\n";
        exit(1);
    }

    if ($second['metadata'] !== ['note' => 'Driver waits 15 minutes']) {
        echo "Second extra metadata mismatch\n";
        exit(1);
    }

    if ($wpdb->last_error !== '') {
        echo "SQL error encountered while loading extras\n";
        exit(1);
    }

    echo "Mobile API extras tests passed\n";
}
