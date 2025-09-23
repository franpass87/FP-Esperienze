<?php
declare(strict_types=1);

namespace {
    use FP\Esperienze\Booking\BookingManager;
    use FP\Esperienze\REST\MobileAPIManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    $GLOBALS['__test_wp_filter'] = [];

    /**
     * Register a callback for a hook.
     *
     * @param callable $callback Callback to register.
     */
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
    {
        global $__test_wp_filter;

        if (!isset($__test_wp_filter[$hook])) {
            $__test_wp_filter[$hook] = [];
        }

        if (!isset($__test_wp_filter[$hook][$priority])) {
            $__test_wp_filter[$hook][$priority] = [];
        }

        $__test_wp_filter[$hook][$priority][] = [
            'function' => $callback,
            'accepted_args' => $accepted_args,
        ];
    }

    /**
     * Check if a hook has the provided callback registered.
     *
     * @param callable|false $callback Callback to inspect, or false to simply check for the hook.
     * @return false|int
     */
    function has_action(string $hook, $callback = false)
    {
        global $__test_wp_filter;

        if (!isset($__test_wp_filter[$hook])) {
            return false;
        }

        if ($callback === false) {
            $priorities = array_keys($__test_wp_filter[$hook]);
            sort($priorities);

            return $priorities[0] ?? false;
        }

        foreach ($__test_wp_filter[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $registered) {
                if (callbacks_are_equal($registered['function'], $callback)) {
                    return $priority;
                }
            }
        }

        return false;
    }

    /**
     * Helper to compare callbacks for equality.
     *
     * @param mixed $left
     * @param mixed $right
     */
    function callbacks_are_equal($left, $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if (is_string($left) && is_string($right)) {
            return $left === $right;
        }

        if (is_array($left) && is_array($right) && count($left) === 2 && count($right) === 2) {
            [$left_object, $left_method] = $left;
            [$right_object, $right_method] = $right;

            if ($left_method !== $right_method) {
                return false;
            }

            if (is_object($left_object) && is_object($right_object)) {
                return spl_object_hash($left_object) === spl_object_hash($right_object);
            }

            return $left_object === $right_object;
        }

        if ($left instanceof \Closure && $right instanceof \Closure) {
            return $left === $right;
        }

        return false;
    }

    /**
     * Count the number of callbacks registered for a hook.
     */
    function count_hook_callbacks(string $hook): int
    {
        global $__test_wp_filter;

        if (!isset($__test_wp_filter[$hook])) {
            return 0;
        }

        $count = 0;
        foreach ($__test_wp_filter[$hook] as $callbacks) {
            $count += count($callbacks);
        }

        return $count;
    }

    function register_rest_route(string $namespace, string $route, array $args): bool
    {
        return true;
    }

    function did_action(string $hook): bool
    {
        return false;
    }

    function sanitize_text_field($value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    function sanitize_textarea_field($value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    function __(string $text, $domain = null): string
    {
        return $text;
    }

    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }

    function get_woocommerce_currency(): string
    {
        return 'EUR';
    }

    function wc_get_product(int $product_id)
    {
        return new class {
            public function get_id(): int
            {
                return 1;
            }
        };
    }

    function wp_json_encode($data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    function wp_salt(string $scheme = 'auth'): string
    {
        return 'unit-test-salt';
    }

    function wp_hash(string $data): string
    {
        return hash('sha256', 'legacy-' . $data);
    }

    function get_user_meta(int $user_id, string $key, bool $single = false)
    {
        return 0;
    }

    function metadata_exists(string $type, int $id, string $key): bool
    {
        return true;
    }

    function wp_validate_boolean($value): bool
    {
        return (bool) $value;
    }

    function wp_get_attachment_image_url(int $attachment_id, string $size): string
    {
        return '';
    }

    function get_post_meta(int $id, string $key, bool $single = false)
    {
        return '';
    }

    function get_option(string $option, $default = '')
    {
        return $default;
    }

    class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public array $data = []
        ) {
        }
    }

    class WP_REST_Response
    {
        public function __construct(private $data = null)
        {
        }

        public function get_data()
        {
            return $this->data;
        }
    }

    class WP_REST_Request
    {
        /**
         * @param array<string, mixed> $params
         * @param array<string, string> $headers
         */
        public function __construct(private array $params = [], private array $headers = [])
        {
        }

        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, $value): void
        {
            $this->params[$key] = $value;
        }

        public function get_header(string $key): ?string
        {
            $target = strtolower($key);

            foreach ($this->headers as $header => $value) {
                if (strtolower($header) === $target) {
                    return $value;
                }
            }

            return null;
        }
    }

    require_once __DIR__ . '/../includes/Booking/BookingManager.php';
    require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

    BookingManager::setInstanceForTesting(null);

    $bookingManager = new class extends BookingManager {
        public function __construct()
        {
            BookingManager::setInstanceForTesting(null);
            parent::__construct();
            BookingManager::setInstanceForTesting($this);
        }

        public function createCustomerBooking(int $user_id, array $data)
        {
            return new WP_Error('stub_error', 'Stubbed booking manager');
        }
    };

    $hooksToCheck = [
        'woocommerce_order_status_processing' => 'createBookingsFromOrder',
        'woocommerce_order_status_completed' => 'createBookingsFromOrder',
        'woocommerce_order_status_cancelled' => 'cancelBookingsFromOrder',
        'woocommerce_order_status_refunded' => 'cancelBookingsFromOrder',
    ];

    foreach ($hooksToCheck as $hook => $method) {
        $priority = has_action($hook, [$bookingManager, $method]);
        if ($priority !== 10) {
            echo "Expected {$hook} hook priority 10 before request\n";
            exit(1);
        }

        if (count_hook_callbacks($hook) !== 1) {
            echo "Expected single callback registered for {$hook} before request\n";
            exit(1);
        }
    }

    $apiManager = new MobileAPIManager();

    $reflection = new \ReflectionClass(MobileAPIManager::class);
    $tokenMethod = $reflection->getMethod('generateMobileToken');
    $tokenMethod->setAccessible(true);
    $token = $tokenMethod->invoke($apiManager, 123);

    $request = new WP_REST_Request(
        [
            'product_id' => 45,
            'booking_date' => '2024-05-01',
            'booking_time' => '10:30',
            'participants' => ['adults' => 1, 'children' => 0],
            'extras' => [],
            'notes' => 'Test note',
        ],
        ['Authorization' => 'Bearer ' . $token]
    );

    $result = $apiManager->createMobileBooking($request);
    if (!$result instanceof WP_Error || $result->code !== 'stub_error') {
        echo "Expected stub error from booking manager\n";
        exit(1);
    }

    foreach ($hooksToCheck as $hook => $method) {
        $priority = has_action($hook, [$bookingManager, $method]);
        if ($priority !== 10) {
            echo "Expected {$hook} hook priority 10 after request\n";
            exit(1);
        }

        if (count_hook_callbacks($hook) !== 1) {
            echo "Duplicate callbacks detected for {$hook} after request\n";
            exit(1);
        }
    }

    echo "Mobile booking hook registration test passed\n";
}
