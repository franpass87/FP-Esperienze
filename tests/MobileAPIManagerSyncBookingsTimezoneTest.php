<?php
declare(strict_types=1);

use FP\Esperienze\REST\MobileAPIManager;

date_default_timezone_set('UTC');

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public array $data = []
        ) {
        }
    }
}

if (!class_exists('WP_REST_Response')) {
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
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        public function __construct(private array $params = [])
        {
        }

        public function get_param(string $key)
        {
            return $this->params[$key] ?? null;
        }
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('sanitize_text_field')) {
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
}

if (!function_exists('wp_salt')) {
    function wp_salt(string $scheme = 'auth'): string
    {
        return 'unit-test-salt';
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data): string
    {
        return json_encode($data);
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type, int $gmt = 0): string
    {
        return '2024-06-01 12:00:00';
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        $timezone = $GLOBALS['__wp_timezone_string'] ?? 'UTC';

        return new DateTimeZone($timezone);
    }
}

class SyncBookingsWPDBStub
{
    public string $prefix = 'wp_';

    public string $users = 'wp_users';

    public string $posts = 'wp_posts';

    /** @var array<int, object> */
    public array $results = [];

    /** @var array{0: string, 1: array}|null */
    public ?array $last_prepare = null;

    public function prepare(string $query, ...$args): array
    {
        $this->last_prepare = [$query, $args];

        return [$query, $args];
    }

    public function get_results($prepared)
    {
        return $this->results;
    }
}

$GLOBALS['__wp_timezone_string'] = 'Pacific/Auckland';

$wpdb = new SyncBookingsWPDBStub();
$wpdb->results = [
    (object) [
        'id' => 101,
        'booking_number' => 'BK-001',
        'customer_name' => 'Alice Example',
        'customer_email' => 'alice@example.com',
        'experience_name' => 'Harbor Cruise',
        'booking_date' => '2024-06-03',
        'booking_time' => '09:00:00',
        'participants' => '4',
        'status' => 'confirmed',
        'checked_in_at' => null,
        'updated_at' => '2024-06-01 10:00:00',
    ],
];

$GLOBALS['wpdb'] = $wpdb;

if (date_default_timezone_get() === wp_timezone()->getName()) {
    echo "Test requires differing site and server timezones\n";
    exit(1);
}

require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

$reflection = new ReflectionClass(MobileAPIManager::class);
/** @var MobileAPIManager $manager */
$manager = $reflection->newInstanceWithoutConstructor();

$defaultRequest = new WP_REST_Request();

$beforeNow = new DateTimeImmutable('now', wp_timezone());
$defaultResponse = $manager->getSyncBookings($defaultRequest);
$afterNow = new DateTimeImmutable('now', wp_timezone());

if (!$defaultResponse instanceof WP_REST_Response) {
    echo "Default sync request should return a REST response object\n";
    exit(1);
}

if ($wpdb->last_prepare === null) {
    echo "Expected the sync query to be prepared for the default date range\n";
    exit(1);
}

[$query, $args] = $wpdb->last_prepare;
$startArg = $args[0] ?? null;
$endArg = $args[1] ?? null;

$startCandidates = array_values(array_unique([
    $beforeNow->format('Y-m-d'),
    $afterNow->format('Y-m-d'),
]));

$endCandidates = array_values(array_unique([
    $beforeNow->add(new DateInterval('P7D'))->format('Y-m-d'),
    $afterNow->add(new DateInterval('P7D'))->format('Y-m-d'),
]));

if (!in_array($startArg, $startCandidates, true)) {
    echo "Default sync range should start on the site's current local date\n";
    exit(1);
}

if (!in_array($endArg, $endCandidates, true)) {
    echo "Default sync range should end seven days after the local date\n";
    exit(1);
}

$defaultData = $defaultResponse->get_data();

if (!is_array($defaultData)) {
    echo "Default sync response payload should be an array\n";
    exit(1);
}

if (($defaultData['total'] ?? null) !== 1) {
    echo "Default sync response should report one booking from the stub dataset\n";
    exit(1);
}

$booking = $defaultData['bookings'][0] ?? null;

if (!is_array($booking) || ($booking['booking_number'] ?? '') !== 'BK-001') {
    echo "Default sync response should include the stub booking data\n";
    exit(1);
}

if (!is_string($booking['qr_data'] ?? null) || $booking['qr_data'] === '') {
    echo "QR payload should be generated for synced bookings\n";
    exit(1);
}

$GLOBALS['__wp_timezone_string'] = 'America/Los_Angeles';

$explicitWPDB = new SyncBookingsWPDBStub();
$GLOBALS['wpdb'] = $explicitWPDB;

$explicitRequest = new WP_REST_Request([
    'date_from' => ' 2024-05-01 ',
    'date_to' => '2024-05-05 ',
]);

$explicitResponse = $manager->getSyncBookings($explicitRequest);

if (!$explicitResponse instanceof WP_REST_Response) {
    echo "Explicit date range should return a REST response\n";
    exit(1);
}

$explicitPrepare = $explicitWPDB->last_prepare[1] ?? null;

if (!is_array($explicitPrepare) || ($explicitPrepare[0] ?? null) !== '2024-05-01') {
    echo "date_from parameter should be normalized and trimmed\n";
    exit(1);
}

if (($explicitPrepare[1] ?? null) !== '2024-05-05') {
    echo "date_to parameter should preserve the requested local date\n";
    exit(1);
}

$explicitData = $explicitResponse->get_data();

if (!is_array($explicitData) || ($explicitData['total'] ?? null) !== 0) {
    echo "Explicit sync range with no rows should report zero bookings\n";
    exit(1);
}

echo "Mobile sync booking timezone coverage passed\n";
