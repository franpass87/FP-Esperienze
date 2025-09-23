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

    if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
        define('FP_ESPERIENZE_PLUGIN_DIR', __DIR__ . '/../');
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    /** @var array<string, mixed> $options */
    $options = [];
    /** @var array<string, mixed> $transients */
    $transients = [];
    /** @var array<int, string> $deleted_transients */
    $deleted_transients = [];
    $object_cache_enabled = false;

    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
    }

    function wp_next_scheduled(string $hook): bool
    {
        return false;
    }

    function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool
    {
        return true;
    }

    function get_option(string $name, $default = false)
    {
        global $options;
        return $options[$name] ?? $default;
    }

    function update_option(string $name, $value, $autoload = null): bool
    {
        global $options;
        $options[$name] = $value;
        return true;
    }

    function set_transient(string $key, $value, int $ttl): bool
    {
        global $transients;
        $transients[$key] = $value;
        return true;
    }

    function get_transient(string $key)
    {
        global $transients;
        return $transients[$key] ?? false;
    }

    function delete_transient(string $key): bool
    {
        global $transients, $deleted_transients;
        $deleted_transients[] = $key;
        unset($transients[$key]);
        return true;
    }

    function wp_using_ext_object_cache(): bool
    {
        global $object_cache_enabled;
        return $object_cache_enabled;
    }

    function get_current_user_id(): int
    {
        return 0;
    }

    function sanitize_key($value): string
    {
        return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $value));
    }

    function current_time(string $type)
    {
        if ($type === 'mysql') {
            return '2024-01-01 00:00:00';
        }

        return time();
    }

    function get_user_meta(int $user_id, string $key, bool $single = false)
    {
        return '';
    }

    function wc_get_cart_url(): string
    {
        return 'https://example.com/cart';
    }

    class TestProduct
    {
        public function __construct(private string $name)
        {
        }

        public function get_type(): string
        {
            return 'experience';
        }

        public function get_name(): string
        {
            return $this->name;
        }
    }

    class TestCart
    {
        /**
         * @param array<int, array<string, mixed>> $items
         */
        public function __construct(private array $items = [], private float $total = 99.99)
        {
        }

        public function is_empty(): bool
        {
            return empty($this->items);
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function get_cart(): array
        {
            return $this->items;
        }

        /**
         * @return array<int, array<string, mixed>>
         */
        public function get_cart_contents(): array
        {
            return $this->items;
        }

        public function get_total(string $context = ''): float
        {
            return $this->total;
        }
    }

    class TestSession
    {
        public function get_customer_id(): string
        {
            return 'guest_session';
        }
    }

    class TestWC
    {
        public TestCart $cart;
        public TestSession $session;

        /**
         * @param array<int, array<string, mixed>> $items
         */
        public function __construct(array $items = [])
        {
            $this->cart = new TestCart($items);
            $this->session = new TestSession();
        }
    }

    $wc_instance = new TestWC();

    function WC(): TestWC
    {
        global $wc_instance;
        return $wc_instance;
    }

    class WPDBStub
    {
        public string $options = 'wp_options';

        /** @var array<int, object> */
        public array $results = [];

        public int $get_results_calls = 0;

        public function prepare($query, ...$args): array
        {
            return [$query, $args];
        }

        public function esc_like(string $text): string
        {
            return addslashes($text);
        }

        /**
         * @param mixed $query
         * @return array<int, object>
         */
        public function get_results($query): array
        {
            $this->get_results_calls++;
            return $this->results;
        }
    }

    $wpdb = new WPDBStub();

    require_once __DIR__ . '/../includes/Integrations/EmailMarketingManager.php';

    function reset_test_environment(): void
    {
        global $transients, $options, $deleted_transients, $object_cache_enabled, $wc_instance, $wpdb;

        $transients = [];
        $options = [];
        $deleted_transients = [];
        $object_cache_enabled = false;
        $wc_instance->cart = new TestCart();
        $wpdb->results = [];
        $wpdb->get_results_calls = 0;
    }

    function assert_true(bool $condition, string $message): void
    {
        if (!$condition) {
            echo $message, "\n";
            exit(1);
        }
    }

    // Database-backed transient scenario
    reset_test_environment();

    $product = new TestProduct('Experience A');
    $wc_instance->cart = new TestCart([
        ['data' => $product],
    ]);

    $manager = new EmailMarketingManager();
    $manager->trackCartActivity();

    $transient_key = 'fp_cart_activity_' . sanitize_key($wc_instance->session->get_customer_id());

    assert_true(isset($transients[$transient_key]), 'Transient not stored for database scenario');

    $transients[$transient_key]['last_activity'] = '2000-01-01 00:00:00';

    $wpdb->results = [
        (object) [
            'option_name' => '_transient_' . $transient_key,
            'option_value' => serialize($transients[$transient_key]),
        ],
    ];

    $manager->processAbandonedCarts();

    assert_true(in_array($transient_key, $deleted_transients, true), 'Transient not deleted for database scenario');
    assert_true($wpdb->get_results_calls === 1, 'Database should be queried when external object cache is disabled');

    $index = get_option('fp_cart_activity_index', []);
    assert_true(!in_array($transient_key, $index, true), 'Index not cleaned after database scenario');

    // External object cache scenario
    reset_test_environment();

    global $object_cache_enabled, $deleted_transients;

    $object_cache_enabled = true;

    $wc_instance->cart = new TestCart([
        ['data' => $product],
    ]);

    $manager = new EmailMarketingManager();
    $manager->trackCartActivity();

    $transient_key = 'fp_cart_activity_' . sanitize_key($wc_instance->session->get_customer_id());

    $transients[$transient_key]['last_activity'] = '2000-01-01 00:00:00';

    $deleted_transients = [];

    $manager->processAbandonedCarts();

    assert_true($wpdb->get_results_calls === 0, 'Database queried unexpectedly with external cache enabled');
    assert_true(in_array($transient_key, $deleted_transients, true), 'Transient not deleted for cache scenario');

    $index = get_option('fp_cart_activity_index', []);
    assert_true($index === [], 'Index not cleared after cache scenario');

    echo "EmailMarketingManager abandoned cart index tests passed\n";
}
