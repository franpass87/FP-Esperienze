<?php
declare(strict_types=1);

namespace FP\Esperienze\Data {
    class Availability
    {
        /** @var array<int, array<string, array<int, array<string, mixed>>>> */
        public static array $slots = [];

        public static function getSlotsForDate(int $product_id, string $date): array
        {
            return self::$slots[$product_id][$date] ?? [];
        }
    }

    class HoldManager
    {
        public static function isEnabled(): bool
        {
            return false;
        }

        /**
         * @param array<string, mixed> $booking
         * @return array{success:bool}
         */
        public static function convertHoldToBooking(int $product_id, string $slot_start, string $session_id, array $booking): array
        {
            return ['success' => false];
        }
    }

    class MeetingPointManager
    {
        public static function getMeetingPoint(int $id): ?object
        {
            return null;
        }
    }
}

namespace {
    use FP\Esperienze\Booking\BookingManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
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

    function absint($value)
    {
        return abs((int) $value);
    }

    function sanitize_text_field($value)
    {
        return is_string($value) ? trim($value) : '';
    }

    function sanitize_textarea_field($value)
    {
        return is_string($value) ? trim($value) : '';
    }

    function __(string $text, $domain = null)
    {
        return $text;
    }

    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }

    function apply_filters($tag, $value, ...$args)
    {
        return $value;
    }

    function current_time(string $type)
    {
        if ($type === 'mysql') {
            return '2024-01-01 00:00:00';
        }

        if ($type === 'timestamp') {
            return 1704067200;
        }

        return '2024-01-01 00:00:00';
    }

    function get_woocommerce_currency()
    {
        return 'EUR';
    }

    function get_userdata(int $user_id)
    {
        return (object) [
            'user_email' => 'user@example.com',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'display_name' => 'Jane Doe',
        ];
    }

    function get_user_meta(int $user_id, string $key, bool $single = false)
    {
        return '';
    }

    function get_post_meta(int $post_id, string $key, bool $single = false)
    {
        return 0;
    }

    function date_i18n(string $format, int $timestamp)
    {
        return date($format, $timestamp);
    }

    function wp_rand(int $min = 0, int $max = 0)
    {
        static $counter = 0;

        if ($max <= $min) {
            return $min;
        }

        $value = $min + ($counter % ($max - $min + 1));
        $counter++;

        return $value;
    }

    function wc_get_product(int $product_id)
    {
        return new class($product_id) {
            public function __construct(private int $id)
            {
            }

            public function get_type(): string
            {
                return 'experience';
            }

            public function get_id(): int
            {
                return $this->id;
            }
        };
    }

    function do_action($tag, ...$args)
    {
        return null;
    }

    /**
     * Minimal wpdb stub capturing inserts for assertions.
     */
    class WPDBBookingStub
    {
        public string $prefix = 'wp_';

        public string $last_error = '';

        public int $insert_id = 0;

        /** @var array<int, array<string, mixed>> */
        public array $bookings = [];

        /**
         * @return array{0:string,1:array}
         */
        public function prepare(string $query, ...$args): array
        {
            return [$query, $args];
        }

        public function get_var($prepared)
        {
            if (is_array($prepared)) {
                [$query, $args] = $prepared;
                if (str_contains($query, 'booking_number') && isset($args[0])) {
                    $target = (string) $args[0];
                    foreach ($this->bookings as $booking) {
                        if (($booking['booking_number'] ?? null) === $target) {
                            return $booking['id'];
                        }
                    }
                }
            }

            return null;
        }

        public function insert(string $table, array $data)
        {
            if (str_contains($table, 'fp_bookings')) {
                $orderId = $data['order_id'] ?? null;
                $orderItemId = $data['order_item_id'] ?? null;

                if ($orderId !== null && $orderItemId !== null) {
                    foreach ($this->bookings as $existing) {
                        if ($existing['order_id'] === $orderId && $existing['order_item_id'] === $orderItemId) {
                            $this->last_error = 'Duplicate entry for key order_item_unique';

                            return false;
                        }
                    }
                }

                $this->insert_id = count($this->bookings) + 1;
                $data['id'] = $this->insert_id;
                $this->bookings[] = $data;

                return true;
            }

            return true;
        }

        public function delete(string $table, array $where, array $formats = [])
        {
            return true;
        }

        public function get_row($prepared)
        {
            if (!is_array($prepared)) {
                return null;
            }

            [$query, $args] = $prepared;
            if (str_contains($query, 'fp_bookings') && isset($args[0])) {
                $target = (int) $args[0];
                foreach ($this->bookings as $booking) {
                    if ((int) ($booking['id'] ?? 0) === $target) {
                        return (object) $booking;
                    }
                }
            }

            return null;
        }
    }

    $wpdb = new WPDBBookingStub();
    $GLOBALS['wpdb'] = $wpdb;

    \FP\Esperienze\Data\Availability::$slots = [
        101 => [
            '2099-12-31' => [
                [
                    'start_time' => '10:00',
                    'available' => 10,
                    'meeting_point_id' => null,
                    'adult_price' => 50.0,
                    'child_price' => 25.0,
                ],
            ],
        ],
    ];

    require_once __DIR__ . '/../includes/Booking/BookingManager.php';

    $reflection = new \ReflectionClass(BookingManager::class);
    /** @var BookingManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $payload = [
        'product_id' => 101,
        'booking_date' => '2099-12-31',
        'booking_time' => '10:00',
        'participants' => ['adults' => 1, 'children' => 0],
        'customer_notes' => 'Initial booking',
        'extras' => [],
    ];

    $first = $manager->createCustomerBooking(5, $payload);
    if (!is_int($first) || $first !== 1) {
        echo "Expected first customer booking to return ID 1\n";
        exit(1);
    }

    $payload['customer_notes'] = 'Follow-up booking';
    $second = $manager->createCustomerBooking(5, $payload);
    if (!is_int($second) || $second !== 2) {
        echo "Second customer booking did not succeed\n";
        exit(1);
    }

    if (count($wpdb->bookings) !== 2) {
        echo "Expected two stored bookings\n";
        exit(1);
    }

    foreach ($wpdb->bookings as $row) {
        if (!array_key_exists('order_id', $row) || $row['order_id'] !== null) {
            echo "Customer booking order_id should be NULL\n";
            exit(1);
        }

        if (!array_key_exists('order_item_id', $row) || $row['order_item_id'] !== null) {
            echo "Customer booking order_item_id should be NULL\n";
            exit(1);
        }
    }

    echo "BookingManager mobile booking regression passed\n";
}
