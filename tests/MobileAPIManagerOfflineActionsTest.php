<?php
declare(strict_types=1);

use FP\Esperienze\REST\MobileAPIManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_Error')) {
    class WP_Error {}
}

if (!class_exists('WP_REST_Response')) {
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
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function __construct(private array $params = [], private array $headers = []) {}

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

$current_time_value = '2024-06-01 09:30:00';

if (!function_exists('current_time')) {
    function current_time(string $type, int $gmt = 0): string
    {
        global $current_time_value;

        return $current_time_value;
    }
}

class OfflineActionsWPDBStub
{
    public string $prefix = 'wp_';

    /** @var array<int, array{0: string, 1: array}> */
    public array $prepared = [];

    public $query_result = 0;

    public int|false $update_result = 0;

    public string $last_error = '';

    public $last_query = null;

    public $last_update = null;

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

        return $this->update_result;
    }
}

$wpdb = new OfflineActionsWPDBStub();
$GLOBALS['wpdb'] = $wpdb;

require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

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

$wpdb->update_result = 1;
$statusSuccess = $statusMethod->invoke($manager, 789, 'confirmed', 55);

if (($statusSuccess['success'] ?? null) !== true) {
    echo "Status update should succeed when a row is updated\n";
    exit(1);
}

if (isset($statusSuccess['message'])) {
    echo "Successful status update should not include an error message\n";
    exit(1);
}

$wpdb->update_result = 0;
$statusFailure = $statusMethod->invoke($manager, 789, 'completed', 55);

if (($statusFailure['success'] ?? null) !== false) {
    echo "Status update should fail when no rows are updated\n";
    exit(1);
}

$expectedStatusMessage = 'Booking status was not updated. The booking may not exist or already has this status.';
if (($statusFailure['message'] ?? '') !== $expectedStatusMessage) {
    echo "Unexpected status update failure message\n";
    exit(1);
}

echo "Offline action result handling test passed\n";
