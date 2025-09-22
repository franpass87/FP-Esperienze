<?php
declare(strict_types=1);

use FP\Esperienze\REST\MobileAPIManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public array $data = []
        ) {
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

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}

class StaffScheduleWPDBStub
{
    public string $prefix = 'wp_';

    public string $posts = 'wp_posts';

    /** @var array<int, array<string, mixed>> */
    public array $results = [];

    /** @var array{0: string, 1: array}|null */
    public ?array $last_prepare = null;

    public function prepare(string $query, ...$args): array
    {
        $this->last_prepare = [$query, $args];

        return [$query, $args];
    }

    /**
     * @param array{0: string, 1: array} $prepared
     * @param int|string $output
     * @return array<int, array<string, mixed>>
     */
    public function get_results($prepared, $output = OBJECT): array
    {
        return $this->results;
    }
}

$wpdb = new StaffScheduleWPDBStub();
$GLOBALS['wpdb'] = $wpdb;

require_once __DIR__ . '/../includes/Data/StaffScheduleManager.php';
require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

$wpdb->results = [
    [
        'assignment_id' => '10',
        'staff_id' => '99',
        'booking_id' => '200',
        'shift_start' => '2024-06-01 08:30:00',
        'shift_end' => '2024-06-01 12:30:00',
        'roles' => '["guide","driver"]',
        'notes' => 'Be on time',
        'booking_date' => '2024-06-01',
        'booking_time' => '09:00:00',
        'booking_status' => 'confirmed',
        'product_id' => '55',
        'participants' => '10',
        'meeting_point_id' => '5',
        'meeting_point_name' => 'Main Square',
        'meeting_point_address' => 'Central plaza',
        'meeting_point_lat' => '44.1234',
        'meeting_point_lng' => '11.5678',
        'product_name' => 'City Tour',
    ],
    [
        'assignment_id' => '11',
        'staff_id' => '99',
        'booking_id' => '201',
        'shift_start' => '2024-06-01 08:30:00',
        'shift_end' => '2024-06-01 12:30:00',
        'roles' => 'photographer',
        'notes' => '',
        'booking_date' => '2024-06-01',
        'booking_time' => '11:00:00',
        'booking_status' => 'confirmed',
        'product_id' => '56',
        'participants' => '4',
        'meeting_point_id' => null,
        'meeting_point_name' => null,
        'meeting_point_address' => null,
        'meeting_point_lat' => null,
        'meeting_point_lng' => null,
        'product_name' => 'Wine Tasting',
    ],
    [
        'assignment_id' => '12',
        'staff_id' => '99',
        'booking_id' => '300',
        'shift_start' => '2024-06-02 14:00:00',
        'shift_end' => '2024-06-02 18:00:00',
        'roles' => '["guide"]',
        'notes' => 'Evening shift',
        'booking_date' => '2024-06-02',
        'booking_time' => '15:00:00',
        'booking_status' => 'confirmed',
        'product_id' => '70',
        'participants' => '8',
        'meeting_point_id' => '9',
        'meeting_point_name' => 'Harbor',
        'meeting_point_address' => 'Dock 3',
        'meeting_point_lat' => '45.0000',
        'meeting_point_lng' => '12.1111',
        'product_name' => 'Sunset Cruise',
    ],
];

$reflection = new ReflectionClass(MobileAPIManager::class);
/** @var MobileAPIManager $manager */
$manager = $reflection->newInstanceWithoutConstructor();

$method = $reflection->getMethod('getStaffScheduleData');
$method->setAccessible(true);

$schedule = $method->invoke($manager, 99, '2024-06-01', '2024-06-02');

if (!is_array($schedule)) {
    echo "Schedule should be returned as an array\n";
    exit(1);
}

if (count($schedule) !== 2) {
    echo "Expected two shift entries in the schedule\n";
    exit(1);
}

if ($wpdb->last_prepare === null) {
    echo "Expected the assignments query to be prepared\n";
    exit(1);
}

$preparedArgs = $wpdb->last_prepare[1];

if (($preparedArgs[0] ?? null) !== 99 || ($preparedArgs[1] ?? null) !== '2024-06-01 00:00:00' || ($preparedArgs[2] ?? null) !== '2024-06-02 23:59:59') {
    echo "Unexpected query parameters for staff assignments\n";
    exit(1);
}

$firstShift = $schedule[0];

if (($firstShift['date'] ?? '') !== '2024-06-01') {
    echo "First shift should be on 2024-06-01\n";
    exit(1);
}

if (($firstShift['shift_start'] ?? '') !== '08:30' || ($firstShift['shift_end'] ?? '') !== '12:30') {
    echo "First shift times were not normalized correctly\n";
    exit(1);
}

$expectedRoles = ['guide', 'driver', 'photographer'];
if ($firstShift['roles'] !== $expectedRoles) {
    echo "First shift roles did not match expected unique list\n";
    exit(1);
}

$experiences = $firstShift['assigned_experiences'];

if (!is_array($experiences) || count($experiences) !== 2) {
    echo "Expected two experiences for the first shift\n";
    exit(1);
}

$firstExperience = $experiences[0];
if (($firstExperience['booking_id'] ?? null) !== 200 || ($firstExperience['product_id'] ?? null) !== 55) {
    echo "First experience booking information mismatch\n";
    exit(1);
}

if (($firstExperience['booking_time'] ?? '') !== '09:00') {
    echo "First experience time should be 09:00\n";
    exit(1);
}

if (($firstExperience['product_name'] ?? '') !== 'City Tour') {
    echo "First experience product name mismatch\n";
    exit(1);
}

if (($firstExperience['participants'] ?? null) !== 10) {
    echo "First experience participants should be 10\n";
    exit(1);
}

if (($firstExperience['notes'] ?? '') !== 'Be on time') {
    echo "First experience notes should be preserved\n";
    exit(1);
}

if ($firstExperience['roles'] !== ['guide', 'driver']) {
    echo "First experience roles mismatch\n";
    exit(1);
}

$meetingPoint = $firstExperience['meeting_point'] ?? [];
if (($meetingPoint['id'] ?? null) !== 5 || ($meetingPoint['name'] ?? '') !== 'Main Square') {
    echo "First experience meeting point information mismatch\n";
    exit(1);
}

if (abs(($meetingPoint['lat'] ?? 0.0) - 44.1234) > 0.0001 || abs(($meetingPoint['lng'] ?? 0.0) - 11.5678) > 0.0001) {
    echo "First experience meeting point coordinates mismatch\n";
    exit(1);
}

$secondExperience = $experiences[1];
if (($secondExperience['booking_id'] ?? null) !== 201 || ($secondExperience['product_id'] ?? null) !== 56) {
    echo "Second experience booking information mismatch\n";
    exit(1);
}

if (isset($secondExperience['meeting_point'])) {
    echo "Second experience should not include meeting point data\n";
    exit(1);
}

if ($secondExperience['roles'] !== ['photographer']) {
    echo "Second experience roles mismatch\n";
    exit(1);
}

$secondShift = $schedule[1];
if (($secondShift['date'] ?? '') !== '2024-06-02') {
    echo "Second shift date mismatch\n";
    exit(1);
}

if (count($secondShift['assigned_experiences']) !== 1) {
    echo "Second shift should contain a single experience\n";
    exit(1);
}

$secondShiftExperience = $secondShift['assigned_experiences'][0];
if (($secondShiftExperience['booking_time'] ?? '') !== '15:00') {
    echo "Second shift experience time mismatch\n";
    exit(1);
}

if (($secondShiftExperience['notes'] ?? '') !== 'Evening shift') {
    echo "Second shift notes should be preserved\n";
    exit(1);
}

if ($secondShiftExperience['roles'] !== ['guide']) {
    echo "Second shift roles mismatch\n";
    exit(1);
}

if (($secondShiftExperience['meeting_point']['name'] ?? '') !== 'Harbor') {
    echo "Second shift meeting point name mismatch\n";
    exit(1);
}

echo "Staff schedule data formatting test passed\n";
