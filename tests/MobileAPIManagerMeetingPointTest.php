<?php
declare(strict_types=1);

use FP\Esperienze\REST\MobileAPIManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

class WP_Error {
    public function __construct(
        public string $code = '',
        public string $message = '',
        public array $data = []
    ) {
    }
}

class WP_REST_Response {
    public function __construct(private $data = null) {}

    public function header(string $name, string $value): void {}

    public function get_headers(): array
    {
        return [];
    }

    public function get_data()
    {
        return $this->data;
    }

    public function set_data($data): void
    {
        $this->data = $data;
    }
}

class WP_REST_Request {
    public function __construct(private array $params = [], private array $headers = []) {}

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

class WC_Product_Stub {
    public function __construct(private int $product_id) {}

    public function get_name(): string
    {
        return 'Experience ' . $this->product_id;
    }

    public function get_image_id(): int
    {
        return 42;
    }

    public function get_short_description(): string
    {
        return 'Short description for experience ' . $this->product_id;
    }
}

class WPDBStub
{
    public string $prefix = 'wp_';

    /** @var array<int, object> */
    public array $bookings = [];

    public ?object $singleBooking = null;

    /** @var array<int, object> */
    public array $meetingPoints = [];

    /** @var array<int, object> */
    public array $bookingExtras = [];

    public int $meetingPointQueryCount = 0;

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
        $query = $prepared[0];

        if (str_contains($query, $this->prefix . 'fp_bookings')) {
            return $this->bookings;
        }

        if (str_contains($query, $this->prefix . 'fp_booking_extras')) {
            return $this->bookingExtras;
        }

        return [];
    }

    /**
     * @param array{0: string, 1: array} $prepared
     */
    public function get_row($prepared): ?object
    {
        $query = $prepared[0];

        if (str_contains($query, $this->prefix . 'fp_bookings')) {
            return $this->singleBooking;
        }

        if (str_contains($query, $this->prefix . 'fp_meeting_points')) {
            $this->meetingPointQueryCount++;
            $id = $prepared[1][0] ?? null;

            return $this->meetingPoints[$id] ?? null;
        }

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

function sanitize_textarea_field($value): string
{
    return is_string($value) ? trim($value) : '';
}

function wp_salt(string $scheme = 'auth'): string
{
    return 'salt-' . $scheme;
}

function wp_json_encode($data): string
{
    return json_encode($data);
}

function get_woocommerce_currency(): string
{
    return 'EUR';
}

function wc_get_product(int $product_id): WC_Product_Stub
{
    return new WC_Product_Stub($product_id);
}

function wp_get_attachment_image_url(int $attachment_id, string $size): string
{
    return "image-{$attachment_id}-{$size}";
}

function get_post_meta(int $post_id, string $key, bool $single = false): string
{
    return match ($key) {
        '_experience_duration' => '2 hours',
        '_cancellation_policy' => 'Flexible policy',
        default => '',
    };
}

function get_option(string $name, $default = false)
{
    $options = [
        'admin_email' => 'admin@example.com',
        'fp_esperienze_contact_phone' => '+1234567890',
        'fp_esperienze_contact_email' => 'support@example.com',
        'fp_esperienze_whatsapp' => '+1987654321',
    ];

    return $options[$name] ?? $default;
}

function get_user_meta(int $user_id, string $key, bool $single = false)
{
    return 0;
}

function home_url(string $path = ''): string
{
    return 'https://example.com' . $path;
}

function wp_hash($data): string
{
    return 'legacy-' . md5((string) $data);
}

function __(string $text, string $domain = ''): string
{
    return $text;
}

$wpdb = new WPDBStub();
$GLOBALS['wpdb'] = $wpdb;

require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

$reflection = new ReflectionClass(MobileAPIManager::class);
/** @var MobileAPIManager $manager */
$manager = $reflection->newInstanceWithoutConstructor();

$generateToken = $reflection->getMethod('generateMobileToken');
$generateToken->setAccessible(true);
$token = $generateToken->invoke($manager, 77);

$meetingPointMethod = $reflection->getMethod('getMeetingPointById');
$meetingPointMethod->setAccessible(true);

$result = $meetingPointMethod->invoke($manager, null);
if ($result !== null) {
    echo "Expected null meeting point for null identifier\n";
    exit(1);
}

if ($wpdb->meetingPointQueryCount !== 0) {
    echo "Meeting point query executed for null identifier\n";
    exit(1);
}

$wpdb->meetingPointQueryCount = 0;

$booking = (object) [
    'id' => 501,
    'booking_number' => 'BK-501',
    'product_id' => 12,
    'booking_date' => '2024-06-01',
    'participants' => 3,
    'status' => 'confirmed',
    'total_amount' => '249.99',
    'currency' => '',
    'meeting_point_id' => null,
    'customer_notes' => 'Looking forward to it!',
];

$wpdb->bookings = [$booking];
$wpdb->singleBooking = $booking;
$wpdb->bookingExtras = [];

$request = new WP_REST_Request([], ['Authorization' => 'Bearer ' . $token]);

$bookingsResponse = $manager->getMobileBookings($request);
if (!$bookingsResponse instanceof WP_REST_Response) {
    echo "Unexpected bookings response type\n";
    exit(1);
}

$bookingsPayload = $bookingsResponse->get_data();
if (!is_array($bookingsPayload) || !isset($bookingsPayload['bookings'][0])) {
    echo "Bookings payload missing data\n";
    exit(1);
}

$bookingData = $bookingsPayload['bookings'][0];
if ($bookingData['meeting_point'] !== null) {
    echo "Expected null meeting point in bookings list\n";
    exit(1);
}

if ($wpdb->meetingPointQueryCount !== 0) {
    echo "Meeting point query executed during bookings fetch\n";
    exit(1);
}

$bookingRequest = new WP_REST_Request(['id' => $booking->id], ['Authorization' => 'Bearer ' . $token]);
$singleResponse = $manager->getMobileBooking($bookingRequest);

if (!$singleResponse instanceof WP_REST_Response) {
    echo "Unexpected booking detail response type\n";
    exit(1);
}

$singlePayload = $singleResponse->get_data();
if (!is_array($singlePayload) || !array_key_exists('meeting_point', $singlePayload)) {
    echo "Booking detail payload missing meeting point\n";
    exit(1);
}

if ($singlePayload['meeting_point'] !== null) {
    echo "Expected null meeting point in booking detail\n";
    exit(1);
}

if ($wpdb->meetingPointQueryCount !== 0) {
    echo "Meeting point query executed during booking detail fetch\n";
    exit(1);
}

echo "Mobile API meeting point null handling test passed\n";
