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
        /** @var array<int, object> */
        public static array $points = [];

        public static function getMeetingPoint(int $id): ?object
        {
            return self::$points[$id] ?? null;
        }
    }

    class ExtraManager
    {
        public static function getExtra(int $id): ?object
        {
            return null;
        }
    }
}

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
        define('FP_ESPERIENZE_PLUGIN_DIR', __DIR__ . '/../');
    }

    if (!defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }

    if (!defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }

    if (!defined('WEEK_IN_SECONDS')) {
        define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
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

    function sanitize_email($value)
    {
        return is_string($value) ? trim($value) : '';
    }

    function sanitize_key($value)
    {
        return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $value));
    }

    function __(string $text, $domain = null)
    {
        return $text;
    }

    function is_wp_error($value)
    {
        return $value instanceof WP_Error;
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

        return time();
    }

    function date_i18n(string $format, int $timestamp)
    {
        return date($format, $timestamp);
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
        if ($key === 'billing_phone') {
            return '555-1234';
        }

        return '';
    }

    function get_post_meta(int $post_id, string $key, bool $single = false)
    {
        return '';
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

            public function get_name(): string
            {
                return 'Experience ' . $this->id;
            }

            public function get_permalink(): string
            {
                return 'https://example.com/experience/' . $this->id;
            }
        };
    }

    function wc_get_order($order_id)
    {
        return null;
    }

    function rest_url(string $path = ''): string
    {
        return 'https://example.com/wp-json/' . ltrim($path, '/');
    }

    function home_url(string $path = ''): string
    {
        return 'https://example.com' . $path;
    }

    function trailingslashit(string $value): string
    {
        return rtrim($value, "\\/") . '/';
    }

    function get_permalink(int $id)
    {
        return 'https://example.com/experience/' . $id;
    }

    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }

    function wp_schedule_event($timestamp, $recurrence, $hook, $args = [])
    {
        return true;
    }

    /** @var array<int, array{timestamp:int, hook:string, args:array}> $scheduled_events */
    $scheduled_events = [];

    function wp_schedule_single_event($timestamp, $hook, $args = [])
    {
        global $scheduled_events;

        $scheduled_events[] = [
            'timestamp' => (int) $timestamp,
            'hook' => $hook,
            'args' => $args,
        ];

        return true;
    }

    function wp_get_scheduled_event($hook, $args = [])
    {
        global $scheduled_events;

        foreach ($scheduled_events as $event) {
            if ($event['hook'] !== $hook) {
                continue;
            }

            if (!empty($args) && $event['args'] != $args) {
                continue;
            }

            return (object) [
                'timestamp' => (int) $event['timestamp'],
                'hook' => $event['hook'],
                'args' => $event['args'],
            ];
        }

        return false;
    }

    function wp_unschedule_event($timestamp, $hook, $args = [])
    {
        global $scheduled_events;

        foreach ($scheduled_events as $index => $event) {
            if ($event['hook'] !== $hook) {
                continue;
            }

            if (!empty($args) && $event['args'] != $args) {
                continue;
            }

            if ((int) $event['timestamp'] !== (int) $timestamp) {
                continue;
            }

            unset($scheduled_events[$index]);
            $scheduled_events = array_values($scheduled_events);

            return true;
        }

        return false;
    }

    function wp_next_scheduled($hook, $args = [], $timestamp = null)
    {
        global $scheduled_events;

        foreach ($scheduled_events as $event) {
            if ($event['hook'] !== $hook) {
                continue;
            }

            if (!empty($args) && $event['args'] != $args) {
                continue;
            }

            if ($timestamp !== null && (int) $event['timestamp'] !== (int) $timestamp) {
                continue;
            }

            return (int) $event['timestamp'];
        }

        return false;
    }

    /** @var array<string, array<int, array<int, mixed>>> $captured_actions */
    $captured_actions = [];

    function do_action($tag, ...$args)
    {
        global $captured_actions;

        $captured_actions[$tag][] = $args;
    }

    function get_option(string $key, $default = '')
    {
        return match ($key) {
            'admin_email' => 'admin@example.com',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            default => $default,
        };
    }

    function get_bloginfo(string $field)
    {
        return $field === 'name' ? 'Test Site' : '';
    }

    function wp_json_encode($value)
    {
        return json_encode($value);
    }

    function is_email($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @var array<int, array<string, mixed>> $sent_emails */
    $sent_emails = [];

    function wp_mail($to, $subject, $message, $headers = [])
    {
        global $sent_emails;

        $sent_emails[] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        ];

        return true;
    }

    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    function esc_url($url)
    {
        return (string) $url;
    }

    function esc_attr($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }

    function esc_html__($text, $domain = null)
    {
        return $text;
    }

    function wp_kses_post($text)
    {
        return $text;
    }

    function wp_get_attachment_image_url($id, $size)
    {
        return '';
    }

    function wp_trim_words($text, int $num_words = 55, $more = null)
    {
        $words = preg_split('/\s+/', trim((string) $text)) ?: [];
        if (count($words) <= $num_words) {
            return trim((string) $text);
        }

        $suffix = $more !== null ? (string) $more : '…';

        return implode(' ', array_slice($words, 0, $num_words)) . $suffix;
    }

    function wp_http_validate_url($url)
    {
        return $url;
    }

    function wp_safe_remote_post($url, $args = [])
    {
        return ['response' => ['code' => 201]];
    }

    function wp_remote_retrieve_response_code($response)
    {
        return $response['response']['code'] ?? 0;
    }

    class WC_Coupon
    {
        /** @var array<int, string> */
        public static array $saved = [];

        private string $code = '';

        public function set_code(string $code): void
        {
            $this->code = $code;
        }

        public function set_amount($amount): void
        {
        }

        public function set_discount_type($type): void
        {
        }

        public function set_individual_use($use): void
        {
        }

        public function set_usage_limit($limit): void
        {
        }

        public function set_usage_limit_per_user($limit): void
        {
        }

        public function set_date_expires($timestamp): void
        {
        }

        public function save(): void
        {
            self::$saved[] = $this->code;
        }
    }

    class WPDBBookingStub
    {
        public string $prefix = 'wp_';

        public string $last_error = '';

        public int $insert_id = 0;

        /** @var array<int, array<string, mixed>> */
        public array $bookings = [];

        /** @var array<int, array<string, mixed>> */
        public array $booking_extras = [];

        /**
         * @return array{0: string, 1: array}
         */
        public function prepare(string $query, ...$args): array
        {
            return [$query, $args];
        }

        public function get_var($prepared)
        {
            if (!is_array($prepared)) {
                return null;
            }

            [$query, $args] = $prepared;
            if (str_contains($query, 'booking_number') && isset($args[0])) {
                $target = (string) $args[0];
                foreach ($this->bookings as $booking) {
                    if (($booking['booking_number'] ?? null) === $target) {
                        return $booking['id'];
                    }
                }
            }

            return null;
        }

        public function insert(string $table, array $data)
        {
            if (str_contains($table, 'fp_bookings')) {
                $this->insert_id = count($this->bookings) + 1;
                $data['id'] = $this->insert_id;
                $this->bookings[] = $data;

                return true;
            }

            if (str_contains($table, 'fp_booking_extras')) {
                $this->booking_extras[] = $data;

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
                    'meeting_point_id' => 77,
                    'adult_price' => 60.0,
                    'child_price' => 30.0,
                ],
            ],
        ],
    ];

    \FP\Esperienze\Data\MeetingPointManager::$points[77] = (object) [
        'id' => 77,
        'name' => 'Main Square',
        'address' => 'Central Avenue 1',
        'note' => 'Look for the red flag',
    ];

    require_once __DIR__ . '/../includes/Booking/BookingManager.php';
    require_once __DIR__ . '/../includes/Integrations/BrevoManager.php';
    require_once __DIR__ . '/../includes/Integrations/EmailMarketingManager.php';

    $bookingReflection = new \ReflectionClass(\FP\Esperienze\Booking\BookingManager::class);
    /** @var \FP\Esperienze\Booking\BookingManager $bookingManager */
    $bookingManager = $bookingReflection->newInstanceWithoutConstructor();

    $payload = [
        'product_id' => 101,
        'booking_date' => '2099-12-31',
        'booking_time' => '10:00',
        'participants' => ['adults' => 2, 'children' => 1],
        'meeting_point_id' => 77,
        'customer_notes' => 'Test booking',
        'extras' => [],
    ];

    $bookingId = $bookingManager->createCustomerBooking(5, $payload);
    if (!is_int($bookingId)) {
        echo "Failed to create mobile booking\n";
        exit(1);
    }

    if (!isset($captured_actions['fp_booking_confirmed'][0])) {
        echo "Booking confirmed action not captured\n";
        exit(1);
    }

    [$actionBookingId, $bookingPayload] = $captured_actions['fp_booking_confirmed'][0];
    if ($actionBookingId !== $bookingId) {
        echo "Captured booking id mismatch\n";
        exit(1);
    }

    $emailReflection = new \ReflectionClass(\FP\Esperienze\Integrations\EmailMarketingManager::class);
    /** @var \FP\Esperienze\Integrations\EmailMarketingManager $emailManager */
    $emailManager = $emailReflection->newInstanceWithoutConstructor();

    $settingsProperty = $emailReflection->getProperty('settings');
    $settingsProperty->setAccessible(true);
    $settingsProperty->setValue($emailManager, []);

    $brevoProperty = $emailReflection->getProperty('brevo_manager');
    $brevoProperty->setAccessible(true);

    $brevoReflection = new \ReflectionClass(\FP\Esperienze\Integrations\BrevoManager::class);
    /** @var \FP\Esperienze\Integrations\BrevoManager $brevoManager */
    $brevoManager = $brevoReflection->newInstanceWithoutConstructor();
    $brevoSettings = $brevoReflection->getProperty('settings');
    $brevoSettings->setAccessible(true);
    $brevoSettings->setValue($brevoManager, []);

    $brevoProperty->setValue($emailManager, $brevoManager);

    $prepareMethod = $emailReflection->getMethod('prepareBookingEmailData');
    $prepareMethod->setAccessible(true);
    $prepared = $prepareMethod->invoke($emailManager, $bookingId, $bookingPayload);

    $expectedPublicLink = 'https://example.com/?fp-booking=' . $bookingId;
    $expectedInternalLink = 'https://example.com/wp-json/fp-esperienze/v2/mobile/bookings/' . $bookingId;

    if (!is_array($prepared)) {
        echo "Prepared data not array\n";
        exit(1);
    }

    if ($prepared['order_id'] !== null) {
        echo "Order id should be null for mobile booking\n";
        exit(1);
    }

    if (($prepared['customer_email'] ?? '') !== 'user@example.com') {
        echo "Customer email fallback missing\n";
        exit(1);
    }

    if (($prepared['meeting_point'] ?? '') !== 'Main Square') {
        echo "Meeting point data missing\n";
        exit(1);
    }

    if (($prepared['booking_details_url'] ?? '') !== $expectedPublicLink) {
        echo "Booking link fallback mismatch\n";
        exit(1);
    }

    if (($prepared['booking_link'] ?? '') !== $expectedPublicLink) {
        echo "Booking link alias mismatch\n";
        exit(1);
    }

    if (($prepared['booking_details_rest_url'] ?? '') !== $expectedInternalLink) {
        echo "Booking internal link mismatch\n";
        exit(1);
    }

    if (($prepared['participants'] ?? 0) !== 3) {
        echo "Participants count mismatch\n";
        exit(1);
    }

    $schedulePre = $emailReflection->getMethod('schedulePreExperienceEmail');
    $schedulePre->setAccessible(true);
    $schedulePre->invoke($emailManager, $bookingId, $bookingPayload);

    $scheduleUpsell = $emailReflection->getMethod('scheduleUpsellingEmail');
    $scheduleUpsell->setAccessible(true);
    $scheduleUpsell->invoke($emailManager, $bookingId, $bookingPayload);

    $preScheduled = false;
    $upsellScheduled = false;
    foreach ($scheduled_events as $event) {
        if ($event['hook'] === 'fp_send_pre_experience_email') {
            $preScheduled = true;
        }

        if ($event['hook'] === 'fp_send_upselling_email') {
            $upsellScheduled = true;
        }
    }

    if (!$preScheduled) {
        echo "Pre experience email was not scheduled\n";
        exit(1);
    }

    if (!$upsellScheduled) {
        echo "Upselling email was not scheduled\n";
        exit(1);
    }

    $sent_emails = [];
    $preResult = $emailManager->sendPreExperienceEmail($bookingId, $bookingPayload);
    if (!$preResult) {
        echo "Pre experience email send failed\n";
        exit(1);
    }

    if (count($sent_emails) !== 1) {
        echo "Pre experience email not captured\n";
        exit(1);
    }

    $preEmail = $sent_emails[0];
    if (strpos($preEmail['message'], 'Main Square') === false) {
        echo "Meeting point not present in email\n";
        exit(1);
    }

    if (strpos($preEmail['message'], $expectedPublicLink) === false) {
        echo "Booking link not present in email\n";
        exit(1);
    }

    $sent_emails = [];
    \WC_Coupon::$saved = [];
    $upsellResult = $emailManager->sendScheduledUpsellingEmail($bookingId, $bookingPayload);
    if (!$upsellResult) {
        echo "Upselling email send failed\n";
        exit(1);
    }

    if (count($sent_emails) !== 1) {
        echo "Upselling email not captured\n";
        exit(1);
    }

    if (empty(\WC_Coupon::$saved)) {
        echo "Discount code was not generated\n";
        exit(1);
    }

    if (strpos($sent_emails[0]['message'], 'COMEBACK') === false) {
        echo "Upsell email missing discount code\n";
        exit(1);
    }

    echo "Email marketing mobile booking fallback test passed\n";
}
