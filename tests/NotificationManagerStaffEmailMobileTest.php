<?php
declare(strict_types=1);

namespace FP\Esperienze\Data {
    class MeetingPointManager {
        public static ?object $meetingPoint = null;

        public static function getMeetingPoint(int $id): ?object
        {
            return self::$meetingPoint;
        }
    }
}

namespace {
    use FP\Esperienze\Data\NotificationManager;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', false);
    }

    /** @var array<string, mixed> $test_options */
    $test_options = [
        'fp_esperienze_notifications' => [
            'staff_notifications_enabled' => true,
            'staff_emails' => "team@example.com\n",
        ],
        'fp_esperienze_branding' => [
            'primary_color' => '#123456',
        ],
        'fp_esperienze_gift_email_sender_name' => 'Esperienze',
        'fp_esperienze_gift_email_sender_email' => 'noreply@example.com',
        'admin_email' => 'admin@example.com',
        'date_format' => 'Y-m-d',
        'time_format' => 'H:i',
    ];

    /** @var array<int, array<string, mixed>> $captured_emails */
    $captured_emails = [];

    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void {}

    function get_option(string $name, $default = false)
    {
        global $test_options;

        return $test_options[$name] ?? $default;
    }

    function get_bloginfo($show = '', $filter = 'raw')
    {
        if ($show === 'name') {
            return 'Test Site';
        }

        return 'Test Site';
    }

    function __(string $text, $domain = null): string
    {
        return $text;
    }

    function esc_html__(string $text, $domain = null): string
    {
        return $text;
    }

    function esc_html($text): string
    {
        return (string) $text;
    }

    function esc_attr($text): string
    {
        return (string) $text;
    }

    function esc_url($url): string
    {
        return (string) $url;
    }

    function admin_url(string $path = ''): string
    {
        return 'https://example.com/wp-admin/' . ltrim($path, '/');
    }

    function is_email($email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    function wp_timezone(): \DateTimeZone
    {
        return new \DateTimeZone('UTC');
    }

    function wp_date(string $format, int $timestamp): string
    {
        return gmdate($format, $timestamp);
    }

    function get_userdata(int $user_id)
    {
        return (object) [
            'ID' => $user_id,
            'user_email' => 'user-mobile@example.com',
            'display_name' => 'Jane Mobile',
        ];
    }

    function get_user_meta(int $user_id, string $key, bool $single = false)
    {
        $map = [
            'first_name' => 'Jane',
            'last_name' => 'Mobile',
            'billing_phone' => '+39 000 111 222',
        ];

        return $map[$key] ?? '';
    }

    function wp_mail($to, $subject, $message, $headers = []): bool
    {
        global $captured_emails;

        $captured_emails[] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
        ];

        return true;
    }

    class WC_Product
    {
        public function __construct(private int $product_id) {}

        public function get_id(): int
        {
            return $this->product_id;
        }

        public function get_name(): string
        {
            return 'Sunset Tour';
        }

        public function get_type(): string
        {
            return 'experience';
        }
    }

    class WC_Order {}

    function wc_get_product(int $product_id): WC_Product
    {
        return new WC_Product($product_id);
    }

    function wc_get_order($order_id)
    {
        return null;
    }

    require_once __DIR__ . '/../includes/Data/NotificationManager.php';

    \FP\Esperienze\Data\MeetingPointManager::$meetingPoint = (object) [
        'id' => 7,
        'name' => 'Main Square',
        'address' => '123 Street',
    ];

    $manager = new NotificationManager();

    $booking = (object) [
        'id' => 77,
        'product_id' => 10,
        'order_id' => null,
        'booking_date' => '2024-05-01',
        'booking_time' => '15:30:00',
        'adults' => 2,
        'children' => 1,
        'participants' => 3,
        'meeting_point_id' => 7,
        'customer_id' => 501,
        'customer_notes' => 'Arriving early',
    ];

    $manager->sendStaffNotification($booking);

    if (count($captured_emails) !== 1) {
        echo "Staff email not sent\n";
        exit(1);
    }

    $email = $captured_emails[0];

    if ($email['to'] !== 'team@example.com') {
        echo "Staff email recipient mismatch\n";
        exit(1);
    }

    if (strpos($email['subject'], 'Sunset Tour') === false) {
        echo "Subject missing experience name\n";
        exit(1);
    }

    if (strpos($email['message'], 'Jane Mobile') === false) {
        echo "Customer name missing\n";
        exit(1);
    }

    if (strpos($email['message'], 'user-mobile@example.com') === false) {
        echo "Customer email missing\n";
        exit(1);
    }

    if (strpos($email['message'], '3 total') === false) {
        echo "Participants total missing\n";
        exit(1);
    }

    if (strpos($email['message'], 'Main Square') === false) {
        echo "Meeting point missing\n";
        exit(1);
    }

    if (strpos($email['message'], 'View Order') !== false) {
        echo "Order link should be omitted\n";
        exit(1);
    }

    if (strpos($email['message'], 'Not available') === false) {
        echo "Order ID fallback missing\n";
        exit(1);
    }

    echo "OK\n";
}
