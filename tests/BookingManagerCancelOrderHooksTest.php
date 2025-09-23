<?php
declare(strict_types=1);

namespace {
    use FP\Esperienze\Booking\BookingManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', false);
    }

    /** @var array<string, array<int, array{callback: callable, accepted: int}>> $registered_hooks */
    $registered_hooks = [];
    /** @var array<int, array{product:int,date:string}> $invalidations */
    $invalidations = [];
    /** @var array<int, array{product:int,date:string}> $notifications */
    $notifications = [];

    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
    {
        global $registered_hooks;

        if (!isset($registered_hooks[$hook])) {
            $registered_hooks[$hook] = [];
        }

        if (!isset($registered_hooks[$hook][$priority])) {
            $registered_hooks[$hook][$priority] = [];
        }

        $registered_hooks[$hook][$priority][] = [
            'callback' => $callback,
            'accepted' => $accepted_args,
        ];
    }

    function do_action(string $hook, ...$args): void
    {
        global $registered_hooks;

        if (empty($registered_hooks[$hook])) {
            return;
        }

        ksort($registered_hooks[$hook]);

        foreach ($registered_hooks[$hook] as $callbacks) {
            foreach ($callbacks as $entry) {
                $accepted = $entry['accepted'];
                $parameters = $accepted > 0 ? array_slice($args, 0, $accepted) : [];
                \call_user_func_array($entry['callback'], $parameters);
            }
        }
    }

    function current_time(string $type, bool $gmt = false)
    {
        if ($type === 'mysql') {
            return '2024-05-06 11:00:00';
        }

        return 1714983600;
    }

    function wc_get_order($order_id)
    {
        return null;
    }

    function wc_get_product($product_id)
    {
        return null;
    }

    function apply_filters($tag, $value)
    {
        return $value;
    }

    class WC_Order
    {
    }

    class WC_Order_Item_Product
    {
    }

    require_once __DIR__ . '/../includes/Booking/BookingManager.php';

    class BookingCancellationWpdbStub
    {
        public string $prefix = 'wp_';

        /** @var array<int, array<string, mixed>> */
        public array $rows = [];

        /** @var array<int, array<string, mixed>> */
        public array $update_log = [];

        /**
         * @param array<int, array<string, mixed>> $seed
         */
        public function __construct(array $seed)
        {
            foreach ($seed as $entry) {
                $id = (int) ($entry['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $this->rows[$id] = $entry;
            }
        }

        /**
         * @return array{0:string,1:array<int, mixed>}
         */
        public function prepare(string $query, ...$args): array
        {
            return [$query, $args];
        }

        /**
         * @param array{0:string,1:array<int, mixed>}|string $prepared
         * @return array<int, object>
         */
        public function get_results($prepared): array
        {
            if (!is_array($prepared)) {
                return [];
            }

            [$query, $args] = $prepared;

            if (!str_contains($query, 'fp_bookings')) {
                return [];
            }

            $order_id = isset($args[0]) ? (int) $args[0] : 0;

            $results = [];
            foreach ($this->rows as $row) {
                if ((int) ($row['order_id'] ?? 0) !== $order_id) {
                    continue;
                }

                if (str_contains($query, 'status != %s') || str_contains($query, "status != 'cancelled'")) {
                    if (($row['status'] ?? '') === 'cancelled') {
                        continue;
                    }
                }

                $results[] = (object) $row;
            }

            return $results;
        }

        /**
         * @param array{0:string,1:array<int, mixed>}|string $prepared
         */
        public function get_row($prepared)
        {
            if (!is_array($prepared)) {
                return null;
            }

            [$query, $args] = $prepared;

            if (str_contains($query, 'order_id = %d AND order_item_id = %d')) {
                $order_id = isset($args[0]) ? (int) $args[0] : 0;
                $order_item_id = isset($args[1]) ? (int) $args[1] : 0;

                foreach ($this->rows as $row) {
                    if ((int) ($row['order_id'] ?? 0) === $order_id && (int) ($row['order_item_id'] ?? 0) === $order_item_id) {
                        return (object) $row;
                    }
                }

                return null;
            }

            if (str_contains($query, 'WHERE id = %d')) {
                $id = isset($args[0]) ? (int) $args[0] : 0;

                if ($id > 0 && isset($this->rows[$id])) {
                    return (object) $this->rows[$id];
                }
            }

            return null;
        }

        /**
         * @param array<string, mixed> $data
         * @param array<string, mixed> $where
         */
        public function update(string $table, array $data, array $where, ?array $format = null, ?array $where_format = null)
        {
            if (!str_contains($table, 'fp_bookings')) {
                return 0;
            }

            if (isset($where['id'])) {
                $id = (int) $where['id'];
                if (!isset($this->rows[$id])) {
                    return 0;
                }

                $this->rows[$id] = array_merge($this->rows[$id], $data);
                $this->update_log[] = ['id' => $id, 'data' => $data];

                return 1;
            }

            if (isset($where['order_id'], $where['order_item_id'])) {
                $order_id = (int) $where['order_id'];
                $order_item_id = (int) $where['order_item_id'];

                foreach ($this->rows as $id => $row) {
                    if ((int) ($row['order_id'] ?? 0) === $order_id && (int) ($row['order_item_id'] ?? 0) === $order_item_id) {
                        $this->rows[$id] = array_merge($row, $data);
                        $this->update_log[] = ['id' => $id, 'data' => $data];

                        return 1;
                    }
                }
            }

            return 0;
        }
    }

    $seed = [
        [
            'id' => 31,
            'order_id' => 9001,
            'order_item_id' => 4001,
            'product_id' => 510,
            'booking_date' => '2024-06-10',
            'status' => 'confirmed',
            'updated_at' => '2024-05-01 08:00:00',
        ],
        [
            'id' => 32,
            'order_id' => 9001,
            'order_item_id' => 4002,
            'product_id' => 511,
            'booking_date' => '2024-06-11',
            'status' => 'confirmed',
            'updated_at' => '2024-05-01 08:05:00',
        ],
    ];

    $wpdb = new BookingCancellationWpdbStub($seed);
    $GLOBALS['wpdb'] = $wpdb;

    add_action('fp_esperienze_booking_cancelled', static function (int $product_id, string $date) use (&$invalidations): void {
        $invalidations[] = ['product' => $product_id, 'date' => $date];
    }, 10, 2);

    add_action('fp_esperienze_booking_cancelled', static function (int $product_id, string $date) use (&$notifications): void {
        $notifications[] = ['product' => $product_id, 'date' => $date];
    }, 10, 2);

    $reflection = new \ReflectionClass(BookingManager::class);
    /** @var BookingManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $manager->cancelBookingsFromOrder(9001);

    if (count($wpdb->update_log) !== 2) {
        echo "Expected two booking updates during cancellation\n";
        exit(1);
    }

    foreach ([31, 32] as $id) {
        if (($wpdb->rows[$id]['status'] ?? '') !== 'cancelled') {
            echo "Booking {$id} was not marked cancelled\n";
            exit(1);
        }
    }

    if (count($invalidations) !== 2) {
        echo "Cache invalidation hook not executed for each booking\n";
        exit(1);
    }

    $expected = [
        ['product' => 510, 'date' => '2024-06-10'],
        ['product' => 511, 'date' => '2024-06-11'],
    ];

    if ($invalidations !== $expected) {
        echo "Cache invalidation arguments mismatch\n";
        exit(1);
    }

    if ($notifications !== $expected) {
        echo "Cancellation notifications not scheduled for all bookings\n";
        exit(1);
    }

    echo "BookingManager order cancellation hook test passed\n";
}
