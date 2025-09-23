<?php
declare(strict_types=1);

namespace {
    use FP\Esperienze\REST\MobileAPIManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
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
        public function __construct(private $data = null) {}

        public function get_data()
        {
            return $this->data;
        }
    }

    class WP_REST_Request
    {
        public function __construct(private array $params = [], private array $headers = []) {}

        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }

        public function get_header(string $key): ?string
        {
            $target = strtolower($key);

            foreach ($this->headers as $header => $value) {
                if (strtolower((string) $header) === $target) {
                    return (string) $value;
                }
            }

            return null;
        }
    }

    class WC_Product_Stub
    {
        public function __construct(private int $product_id) {}

        public function get_name(): string
        {
            return 'Experience ' . $this->product_id;
        }

        public function get_image_id(): int
        {
            return 101;
        }
    }

    class WPDBBookingOrderStub
    {
        public string $prefix = 'wp_';

        public string $last_error = '';

        /** @var array<int, object> */
        public array $bookings = [];

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

            if (!str_contains($query, 'ORDER BY booking_date DESC, booking_time DESC')) {
                echo "Query missing expected booking order clause\n";
                exit(1);
            }

            $params = $args[0] ?? [];
            if (!is_array($params)) {
                $params = $args;
            }

            $user_id = (int) ($params[0] ?? 0);
            $status  = $params[1] ?? null;

            $results = array_filter(
                $this->bookings,
                static function (object $booking) use ($user_id, $status): bool {
                    if ((int) ($booking->customer_id ?? 0) !== $user_id) {
                        return false;
                    }

                    if ($status !== null && $status !== '') {
                        return (string) $booking->status === (string) $status;
                    }

                    return true;
                }
            );

            usort(
                $results,
                static function (object $left, object $right): int {
                    $dateComparison = strcmp((string) $right->booking_date, (string) $left->booking_date);
                    if (0 !== $dateComparison) {
                        return $dateComparison;
                    }

                    return strcmp((string) ($right->booking_time ?? ''), (string) ($left->booking_time ?? ''));
                }
            );

            return array_values($results);
        }

        /**
         * @param array{0: string, 1: array} $prepared
         */
        public function get_row($prepared): ?object
        {
            return null;
        }
    }

    function sanitize_text_field($value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    function wc_get_product(int $product_id): WC_Product_Stub
    {
        return new WC_Product_Stub($product_id);
    }

    function wp_get_attachment_image_url(int $attachment_id, string $size): string
    {
        return "image-{$attachment_id}-{$size}";
    }

    function get_woocommerce_currency(): string
    {
        return 'EUR';
    }

    function wp_salt(string $scheme = 'auth'): string
    {
        return 'test-salt-' . $scheme;
    }

    function wp_json_encode($data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    function get_user_meta(int $user_id, string $key, bool $single = false)
    {
        return 0;
    }

    function wp_hash($data): string
    {
        return hash('sha256', 'legacy-' . $data);
    }

    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }

    function current_time(string $type, int $gmt = 0): int
    {
        if ($type === 'timestamp') {
            return time();
        }

        return 0;
    }

    $wpdb = new WPDBBookingOrderStub();
    $GLOBALS['wpdb'] = $wpdb;

    require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

    $reflection = new \ReflectionClass(MobileAPIManager::class);
    /** @var MobileAPIManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $generateToken = $reflection->getMethod('generateMobileToken');
    $generateToken->setAccessible(true);

    $token = $generateToken->invoke($manager, 42);

    $future = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');
    $laterSameDay = (new \DateTimeImmutable('+2 days'))->format('Y-m-d');

    $wpdb->bookings = [
        (object) [
            'id' => 301,
            'booking_number' => 'B-301',
            'product_id' => 18,
            'customer_id' => 42,
            'booking_date' => $laterSameDay,
            'booking_time' => '09:30:00',
            'participants' => 2,
            'status' => 'confirmed',
            'total_amount' => '120.00',
            'currency' => 'EUR',
            'meeting_point_id' => null,
        ],
        (object) [
            'id' => 302,
            'booking_number' => 'B-302',
            'product_id' => 18,
            'customer_id' => 42,
            'booking_date' => $laterSameDay,
            'booking_time' => '14:00:00',
            'participants' => 4,
            'status' => 'confirmed',
            'total_amount' => '180.00',
            'currency' => 'EUR',
            'meeting_point_id' => null,
        ],
        (object) [
            'id' => 299,
            'booking_number' => 'B-299',
            'product_id' => 6,
            'customer_id' => 42,
            'booking_date' => $future,
            'booking_time' => '08:15:00',
            'participants' => 1,
            'status' => 'confirmed',
            'total_amount' => '80.00',
            'currency' => 'EUR',
            'meeting_point_id' => null,
        ],
    ];

    $request = new WP_REST_Request([], ['Authorization' => 'Bearer ' . $token]);
    $response = $manager->getMobileBookings($request);

    if (!$response instanceof WP_REST_Response) {
        echo "Unexpected response type from getMobileBookings\n";
        exit(1);
    }

    $payload = $response->get_data();

    if (!is_array($payload) || !isset($payload['bookings'])) {
        echo "Missing bookings payload in response\n";
        exit(1);
    }

    $bookings = $payload['bookings'];

    if (count($bookings) !== 3) {
        echo "Unexpected number of bookings returned\n";
        exit(1);
    }

    if ($bookings[0]['id'] !== 299) {
        echo "Bookings not ordered by most recent date first\n";
        exit(1);
    }

    if ($bookings[1]['id'] !== 302 || $bookings[2]['id'] !== 301) {
        echo "Same-day bookings not ordered by most recent time first\n";
        exit(1);
    }

    echo "Mobile API booking order test passed\n";
}
