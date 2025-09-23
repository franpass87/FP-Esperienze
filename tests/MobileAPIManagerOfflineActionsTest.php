<?php
declare(strict_types=1);

use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\REST\MobileAPIManager;

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

        public function header(string $name, string $value): void
        {
        }

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
}

if (!class_exists('WP_REST_Request')) {
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
}

if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void
    {
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order
    {
        public function __construct(
            private int $id,
            private string $email,
            private string $first_name,
            private string $last_name,
            private string $phone,
            private int $customer_id = 0,
            private int $user_id = 0
        ) {
        }

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_customer_id(): int
        {
            return $this->customer_id;
        }

        public function get_user_id(): int
        {
            return $this->user_id;
        }

        public function get_billing_email(): string
        {
            return $this->email;
        }

        public function get_billing_first_name(): string
        {
            return $this->first_name;
        }

        public function get_billing_last_name(): string
        {
            return $this->last_name;
        }

        public function get_formatted_billing_full_name(): string
        {
            $name = trim($this->first_name . ' ' . $this->last_name);

            return $name !== '' ? $name : $this->email;
        }

        public function get_billing_phone(): string
        {
            return $this->phone;
        }

        public function get_currency(): string
        {
            return 'EUR';
        }

        public function get_item(int $item_id)
        {
            return null;
        }
    }
}

$captured_actions = [];

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        global $captured_actions;

        if (!isset($captured_actions[$hook])) {
            $captured_actions[$hook] = [];
        }

        $captured_actions[$hook][] = $args;
    }
}

$current_time_value = '2024-06-01 09:30:00';

if (!function_exists('current_time')) {
    function current_time(string $type, int $gmt = 0): string
    {
        global $current_time_value;

        return $current_time_value;
    }
}

$test_orders = [];

if (!function_exists('wc_get_order')) {
    function wc_get_order(int $order_id)
    {
        global $test_orders;

        return $test_orders[$order_id] ?? null;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}

class OfflineActionsWPDBStub
{
    public string $prefix = 'wp_';

    /** @var array<int, array{0: string, 1: array}> */
    public array $prepared = [];

    public $query_result = 0;

    public string $last_error = '';

    public $last_query = null;

    public $last_update = null;

    /** @var array<int, object> */
    public array $bookings = [];

    public bool $force_update_error = false;

    public function prepare(string $query, ...$args): array
    {
        $prepared = [$query, $args];
        $this->prepared[] = $prepared;

        return $prepared;
    }

    public function query($prepared)
    {
        $this->last_query = $prepared;

        return $this->query_result;
    }

    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        $this->last_update = [$table, $data, $where, $format, $where_format];

        if ($this->force_update_error) {
            $this->force_update_error = false;

            return false;
        }

        if ($table !== $this->prefix . 'fp_bookings') {
            return 0;
        }

        if (isset($where['id'])) {
            $id = (int) $where['id'];

            if (!isset($this->bookings[$id])) {
                return 0;
            }

            $booking = $this->bookings[$id];
            $status = $data['status'] ?? $booking->status;
            $updated_at = $data['updated_at'] ?? ($booking->updated_at ?? '');
            $changed = false;

            if ($booking->status !== $status) {
                $booking->status = $status;
                $changed = true;
            }

            $booking->updated_at = $updated_at;
            $this->bookings[$id] = $booking;

            return $changed ? 1 : 0;
        }

        if (isset($where['order_id'], $where['order_item_id'])) {
            foreach ($this->bookings as $id => $booking) {
                if ((int) ($booking->order_id ?? 0) === (int) $where['order_id']
                    && (int) ($booking->order_item_id ?? 0) === (int) $where['order_item_id']
                ) {
                    $status = $data['status'] ?? $booking->status;
                    $updated_at = $data['updated_at'] ?? ($booking->updated_at ?? '');
                    $changed = false;

                    if ($booking->status !== $status) {
                        $booking->status = $status;
                        $changed = true;
                    }

                    $booking->updated_at = $updated_at;
                    $this->bookings[$id] = $booking;

                    return $changed ? 1 : 0;
                }
            }

            return 0;
        }

        return 0;
    }

    public function get_row($prepared)
    {
        $this->last_query = $prepared;

        if (!is_array($prepared) || !isset($prepared[1])) {
            return null;
        }

        $args = $prepared[1];

        if (count($args) === 1) {
            $id = (int) $args[0];

            return $this->bookings[$id] ?? null;
        }

        if (count($args) === 2) {
            $order_id = (int) $args[0];
            $order_item_id = (int) $args[1];

            foreach ($this->bookings as $booking) {
                if ((int) ($booking->order_id ?? 0) === $order_id && (int) ($booking->order_item_id ?? 0) === $order_item_id) {
                    return $booking;
                }
            }
        }

        return null;
    }
}

$wpdb = new OfflineActionsWPDBStub();
$GLOBALS['wpdb'] = $wpdb;

require_once __DIR__ . '/../includes/Booking/BookingManager.php';
require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

BookingManager::setInstanceForTesting(null);

class OfflineTestBookingManager extends BookingManager
{
    public function __construct()
    {
    }
}

$bookingManager = new OfflineTestBookingManager();
BookingManager::setInstanceForTesting($bookingManager);

$reflection = new ReflectionClass(MobileAPIManager::class);
/** @var MobileAPIManager $manager */
$manager = $reflection->newInstanceWithoutConstructor();

$checkinMethod = $reflection->getMethod('processOfflineCheckin');
$checkinMethod->setAccessible(true);

$statusMethod = $reflection->getMethod('processOfflineStatusUpdate');
$statusMethod->setAccessible(true);

$wpdb->query_result = 1;
$checkinSuccess = $checkinMethod->invoke($manager, 123, 42);

if (($checkinSuccess['success'] ?? null) !== true) {
    echo "Check-in should succeed when a row is updated\n";
    exit(1);
}

if (isset($checkinSuccess['message'])) {
    echo "Successful check-in should not include an error message\n";
    exit(1);
}

$wpdb->query_result = 0;
$checkinFailure = $checkinMethod->invoke($manager, 456, 42);

if (($checkinFailure['success'] ?? null) !== false) {
    echo "Check-in should fail when no rows are updated\n";
    exit(1);
}

$expectedCheckinMessage = 'Booking not found or already checked in.';
if (($checkinFailure['message'] ?? '') !== $expectedCheckinMessage) {
    echo "Unexpected check-in failure message\n";
    exit(1);
}

$booking_id = 789;
$wpdb->bookings[$booking_id] = (object) [
    'id' => $booking_id,
    'status' => 'pending',
    'product_id' => 321,
    'booking_date' => '2024-06-10',
    'booking_time' => '09:00:00',
    'order_id' => 987,
    'order_item_id' => 654,
    'booking_number' => 'FP-20240610-0001',
    'experience_name' => 'Sunrise Kayak',
    'experience_url' => 'https://example.com/sunrise-kayak',
    'updated_at' => '2024-06-01 08:00:00',
];

$test_orders[987] = new WC_Order(987, 'jane@example.com', 'Jane', 'Doe', '+123456789', 42, 42);

$captured_actions = [];
$statusConfirmed = $statusMethod->invoke($manager, $booking_id, 'confirmed', 55);

if (($statusConfirmed['success'] ?? null) !== true) {
    echo "Status update to confirmed should succeed when a row changes\n";
    exit(1);
}

if (!isset($captured_actions['fp_booking_confirmed'][0])) {
    echo "Confirmed status update should trigger fp_booking_confirmed\n";
    exit(1);
}

[$confirmedId, $confirmedPayload] = $captured_actions['fp_booking_confirmed'][0];

if ($confirmedId !== $booking_id) {
    echo "Confirmed hook should receive the booking ID\n";
    exit(1);
}

if (($confirmedPayload['status'] ?? '') !== 'confirmed') {
    echo "Confirmed payload should include the updated status\n";
    exit(1);
}

if (($confirmedPayload['order_id'] ?? 0) !== 987) {
    echo "Confirmed payload should include the related order ID\n";
    exit(1);
}

if (($confirmedPayload['customer_email'] ?? '') !== 'jane@example.com') {
    echo "Confirmed payload should include customer email data\n";
    exit(1);
}

if ($wpdb->bookings[$booking_id]->status !== 'confirmed') {
    echo "Booking record should be updated to confirmed\n";
    exit(1);
}

$current_time_value = '2024-06-01 10:00:00';
$captured_actions = [];
$statusCompleted = $statusMethod->invoke($manager, $booking_id, 'completed', 55);

if (($statusCompleted['success'] ?? null) !== true) {
    echo "Status update to completed should succeed when a row changes\n";
    exit(1);
}

if (!isset($captured_actions['fp_booking_completed'][0])) {
    echo "Completed status update should trigger fp_booking_completed\n";
    exit(1);
}

if (isset($captured_actions['fp_booking_confirmed'][0])) {
    echo "Completed status update should not trigger additional confirmation hooks\n";
    exit(1);
}

[$completedId, $completedPayload] = $captured_actions['fp_booking_completed'][0];

if ($completedId !== $booking_id) {
    echo "Completed hook should receive the booking ID\n";
    exit(1);
}

if (($completedPayload['status'] ?? '') !== 'completed') {
    echo "Completed payload should include the updated status\n";
    exit(1);
}

if (($completedPayload['order_id'] ?? 0) !== 987) {
    echo "Completed payload should include the related order ID\n";
    exit(1);
}

if ($wpdb->bookings[$booking_id]->status !== 'completed') {
    echo "Booking record should be updated to completed\n";
    exit(1);
}

$captured_actions = [];
$statusNoChange = $statusMethod->invoke($manager, $booking_id, 'completed', 55);

if (($statusNoChange['success'] ?? null) !== false) {
    echo "Status update should fail when the booking already has the requested status\n";
    exit(1);
}

$expectedStatusMessage = 'Booking status was not updated. The booking may not exist or already has this status.';
if (($statusNoChange['message'] ?? '') !== $expectedStatusMessage) {
    echo "Unexpected message when status update makes no changes\n";
    exit(1);
}

if (!empty($captured_actions)) {
    echo "No hooks should run when the booking status does not change\n";
    exit(1);
}

$invalidStatus = $statusMethod->invoke($manager, $booking_id, 'invalid', 55);

if (($invalidStatus['success'] ?? true) !== false) {
    echo "Invalid status updates should be rejected\n";
    exit(1);
}

if (($invalidStatus['error'] ?? '') !== 'Invalid status') {
    echo "Invalid status updates should return the expected error message\n";
    exit(1);
}

$captured_actions = [];
$wpdb->force_update_error = true;
$dbError = $statusMethod->invoke($manager, $booking_id, 'cancelled', 55);

if (($dbError['success'] ?? null) !== false) {
    echo "Database failures should return a failed status update\n";
    exit(1);
}

if (isset($captured_actions['fp_esperienze_booking_cancelled'])) {
    echo "Failed status updates should not trigger cancellation hooks\n";
    exit(1);
}

echo "Offline action processing tests passed\n";
