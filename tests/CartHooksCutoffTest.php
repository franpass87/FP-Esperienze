<?php
declare(strict_types=1);

namespace FP\Esperienze\Data {
    /**
     * Stub availability class for testing.
     */
    class Availability {
        public static function forDay(int $product_id, string $date): array {
            global $availability_data;

            return $availability_data[$product_id][$date] ?? [];
        }
    }

    /**
     * Stub hold manager for testing.
     */
    class HoldManager {
        public static function isEnabled(): bool {
            return false;
        }
    }
}

namespace {
    use FP\Esperienze\Booking\Cart_Hooks;

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    // Globals used by stubs.
    $availability_data = [];
    $cutoff_meta_map = [];
    $notices = [];
    $products = [];

    // WordPress/WooCommerce stubs.
    function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }

    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        return true;
    }

    function wc_get_product($product_id) {
        global $products;

        return $products[$product_id] ?? null;
    }

    function wc_add_notice($message, $type = 'notice') {
        global $notices;

        $notices[] = [
            'type'    => $type,
            'message' => $message,
        ];

        return true;
    }

    function sanitize_text_field($value) {
        return is_string($value) ? trim($value) : '';
    }

    function sanitize_textarea_field($value) {
        return is_string($value) ? trim($value) : '';
    }

    function sanitize_email($value) {
        return is_string($value) ? trim($value) : '';
    }

    function wp_unslash($value) {
        return $value;
    }

    function absint($value) {
        return abs((int) $value);
    }

    function is_email($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    function wp_timezone() {
        return new \DateTimeZone('UTC');
    }

    function __(string $text, $domain = null) {
        return $text;
    }

    function get_post_meta($post_id, $key = '', $single = false) {
        global $cutoff_meta_map;

        if ('_fp_exp_cutoff_minutes' === $key && array_key_exists($post_id, $cutoff_meta_map)) {
            return $cutoff_meta_map[$post_id];
        }

        return '';
    }

    function WC() {
        return new class() {
            public $session;
            public $cart;

            public function __construct() {
                $this->session = new class() {
                    public function get_customer_id() {
                        return 'dummy-session';
                    }
                };
                $this->cart = new class() {
                    public function get_cart() {
                        return [];
                    }
                };
            }
        };
    }

    function esc_html($text) {
        return $text;
    }

    function wc_price($price) {
        return (string) $price;
    }

    function get_option($name, $default = false) {
        return $default;
    }

    if (!function_exists('fp_esperienze_wp_date')) {
        function fp_esperienze_wp_date($format, $datetime = null, $gmt = false) {
            if ($datetime instanceof \DateTimeInterface) {
                $timestamp = $datetime->getTimestamp();
            } elseif (is_numeric($datetime)) {
                $timestamp = (int) $datetime;
            } elseif (is_string($datetime) && $datetime !== '') {
                $timestamp = strtotime($datetime);
            } else {
                $timestamp = time();
            }

            return $gmt ? gmdate($format, $timestamp) : date($format, $timestamp);
        }
    }

    function apply_filters($tag, $value) {
        return $value;
    }

    function do_action($tag, ...$args) {
        return null;
    }

    class DummyProduct {
        private string $type;

        public function __construct(string $type) {
            $this->type = $type;
        }

        public function get_type() {
            return $this->type;
        }
    }

    require_once __DIR__ . '/../includes/Booking/Cart_Hooks.php';

    // Prepare products and metadata for testing.
    $products[101] = new DummyProduct('experience');
    $products[102] = new DummyProduct('experience');

    $cutoff_meta_map = [
        101 => '120',
        102 => '0',
    ];

    $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

    $positive_slot = $now->modify('+60 minutes');
    $positive_date = $positive_slot->format('Y-m-d');
    $positive_time = $positive_slot->format('H:i');

    $availability_data[101][$positive_date] = [
        [
            'start_time' => $positive_time,
            'available'  => 5,
        ],
    ];

    $zero_slot = $now->modify('+10 minutes');
    $zero_date = $zero_slot->format('Y-m-d');
    $zero_time = $zero_slot->format('H:i');

    $availability_data[102][$zero_date] = [
        [
            'start_time' => $zero_time,
            'available'  => 5,
        ],
    ];

    $cart_hooks = new Cart_Hooks();

    // Positive cutoff should block bookings within the cutoff window.
    $notices = [];
    $_POST = [
        'fp_slot_start' => $positive_slot->format('Y-m-d H:i'),
        'fp_qty_adult'  => '1',
        'fp_qty_child'  => '0',
    ];

    $result_positive = $cart_hooks->validateExperienceBooking(true, 101, 1);

    if (false !== $result_positive) {
        echo "Cutoff positive test failed: expected rejection for insufficient lead time\n";
        exit(1);
    }

    if (empty($notices)) {
        echo "Cutoff positive test failed: expected notice for insufficient lead time\n";
        exit(1);
    }

    $notice = end($notices);
    if (false === strpos($notice['message'], '120')) {
        echo "Cutoff positive test failed: notice missing cutoff minutes\n";
        exit(1);
    }

    // Zero cutoff should allow near-term bookings.
    $notices = [];
    $_POST = [
        'fp_slot_start' => $zero_slot->format('Y-m-d H:i'),
        'fp_qty_adult'  => '1',
        'fp_qty_child'  => '0',
    ];

    $result_zero = $cart_hooks->validateExperienceBooking(true, 102, 1);

    if (true !== $result_zero) {
        echo "Cutoff zero test failed: expected booking to pass with zero cutoff\n";
        exit(1);
    }

    if (!empty($notices)) {
        echo "Cutoff zero test failed: unexpected notices\n";
        exit(1);
    }

    echo "Cart Hooks cutoff tests passed\n";
}
