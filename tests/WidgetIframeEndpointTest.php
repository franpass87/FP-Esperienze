<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    $registered_routes = [];

    if (!function_exists('add_filter')) {
        function add_filter($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
            return true;
        }
    }

    if (!function_exists('register_rest_route')) {
        function register_rest_route($namespace, $route, $args = [], $override = false) {
            global $registered_routes;

            $registered_routes[] = [$namespace, $route];

            return true;
        }
    }

    if (!class_exists('WP_Error')) {
        class WP_Error {
            public function __construct(
                public string $code,
                public string $message = '',
                public array $data = []
            ) {
            }
        }
    }

    if (!class_exists('WP_REST_Server')) {
        class WP_REST_Server {
            public const READABLE = 'GET';
        }
    }

    if (!class_exists('WP_REST_Request')) {
        class WP_REST_Request {
            public function __construct(private array $params = [], private array $headers = []) {}

            public function get_param(string $key) {
                return $this->params[$key] ?? null;
            }

            public function set_param(string $key, $value): void {
                $this->params[$key] = $value;
            }

            public function get_header(string $key): ?string {
                $lookup = strtolower($key);

                foreach ($this->headers as $header => $value) {
                    if (strtolower($header) === $lookup) {
                        return $value;
                    }
                }

                return null;
            }
        }
    }

    if (!class_exists('WP_REST_Response')) {
        class WP_REST_Response {
            /** @var array<string, string> */
            private array $headers = [];

            public function __construct(private $data = null) {}

            public function header(string $name, string $value): void {
                $this->headers[$name] = $value;
            }

            /**
             * @return array<string, string>
             */
            public function get_headers(): array {
                return $this->headers;
            }

            public function get_data() {
                return $this->data;
            }

            public function set_data($data): void {
                $this->data = $data;
            }
        }
    }

    if (!function_exists('__')) {
        function __($text, $domain = null) {
            return $text;
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing) {
            return $thing instanceof WP_Error;
        }
    }

    if (!function_exists('wc_get_product')) {
        function wc_get_product($product_id) {
            global $widget_products;

            return $widget_products[$product_id] ?? null;
        }
    }

    if (!function_exists('wp_get_attachment_image_url')) {
        function wp_get_attachment_image_url($attachment_id, $size = 'large') {
            return "https://example.test/media/{$attachment_id}-{$size}.jpg";
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($post_id, $key = '', $single = false) {
            return 'Alt ' . $post_id;
        }
    }

    if (!function_exists('get_permalink')) {
        function get_permalink($post_id) {
            return 'https://example.test/experience/' . $post_id;
        }
    }

    if (!function_exists('get_woocommerce_currency')) {
        function get_woocommerce_currency() {
            return 'EUR';
        }
    }

    if (!function_exists('get_woocommerce_currency_symbol')) {
        function get_woocommerce_currency_symbol() {
            return '€';
        }
    }

    if (!function_exists('home_url')) {
        function home_url($path = '') {
            return 'https://example.test' . $path;
        }
    }

    if (!function_exists('get_rest_url')) {
        function get_rest_url($blog_id = null, $path = '', $scheme = 'rest') {
            return 'https://example.test/wp-json/' . ltrim($path, '/');
        }
    }

    if (!function_exists('get_locale')) {
        function get_locale() {
            return 'en_US';
        }
    }

    if (!function_exists('esc_attr')) {
        function esc_attr($text) {
            return is_scalar($text) ? (string) $text : '';
        }
    }

    if (!function_exists('esc_html')) {
        function esc_html($text) {
            return is_scalar($text) ? (string) $text : '';
        }
    }

    if (!function_exists('esc_url')) {
        function esc_url($url) {
            return is_scalar($url) ? (string) $url : '';
        }
    }

    if (!function_exists('esc_js')) {
        function esc_js($text) {
            return is_scalar($text) ? (string) $text : '';
        }
    }

    if (!function_exists('wp_kses_post')) {
        function wp_kses_post($text) {
            return is_string($text) ? $text : '';
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    if (!function_exists('apply_filters')) {
        function apply_filters($tag, $value, ...$args) {
            return $value;
        }
    }

    class DummyWidgetProduct {
        public function __construct(private int $id) {}

        public function get_type() {
            return 'experience';
        }

        public function get_name() {
            return 'Scenic Boat Tour';
        }

        public function get_description() {
            return 'Enjoy a scenic boat tour along the river.';
        }

        public function get_short_description() {
            return 'Scenic river cruise';
        }

        public function get_image_id() {
            return 5011;
        }

        public function get_gallery_image_ids() {
            return [5012, 5013];
        }
    }

    class WidgetWPDBStub {
        public string $prefix = 'wp_';
        public string $last_error = '';

        /** @var array<int, array<int, object>> */
        public array $schedules = [];

        /** @var array<int, object> */
        public array $meetingPoints = [];

        /**
         * @return array{0: string, 1: array}
         */
        public function prepare(string $query, ...$args): array {
            return [$query, $args];
        }

        /**
         * @param array{0: string, 1: array}|string $prepared
         * @return array<int, object>
         */
        public function get_results($prepared): array {
            if (is_array($prepared)) {
                [$query, $args] = $prepared;
            } else {
                $query = $prepared;
                $args = [];
            }

            if (str_contains($query, $this->prefix . 'fp_schedules')) {
                $product_id = (int) ($args[0] ?? 0);

                return $this->schedules[$product_id] ?? [];
            }

            if (str_contains($query, $this->prefix . 'fp_meeting_points')) {
                return array_values($this->meetingPoints);
            }

            return [];
        }

        /**
         * @param array{0: string, 1: array}|string $prepared
         * @return array<int, int>
         */
        public function get_col($prepared): array {
            if (!is_array($prepared)) {
                return [];
            }

            [$query, $args] = $prepared;

            if (str_contains($query, $this->prefix . 'fp_schedules')) {
                $product_id = (int) ($args[0] ?? 0);
                $ids = [];

                foreach ($this->schedules[$product_id] ?? [] as $schedule) {
                    if (isset($schedule->meeting_point_id) && $schedule->meeting_point_id !== null) {
                        $ids[] = (int) $schedule->meeting_point_id;
                    }
                }

                return array_values(array_unique($ids));
            }

            return [];
        }

        /**
         * @param array{0: string, 1: array}|string $prepared
         */
        public function get_row($prepared): ?object {
            if (!is_array($prepared)) {
                return null;
            }

            [$query, $args] = $prepared;

            if (str_contains($query, $this->prefix . 'fp_meeting_points')) {
                $id = (int) ($args[0] ?? 0);

                return $this->meetingPoints[$id] ?? null;
            }

            return null;
        }
    }
}

namespace FP\Esperienze\Core {
    class I18nManager {
        public static bool $active = false;

        public static function isMultilingualActive(): bool {
            return self::$active;
        }

        public static function getTranslatedMeetingPoint(object $meeting_point): object {
            return $meeting_point;
        }
    }
}

namespace FP\Esperienze\Data {
    class ExtraManager {
        /** @var array<int, array<int, object>> */
        public static array $extras = [];

        public static function getExtras(int $product_id): array {
            return self::$extras[$product_id] ?? [];
        }
    }
}

namespace {
    require_once __DIR__ . '/../includes/Data/MeetingPointManager.php';
    require_once __DIR__ . '/../includes/Data/ScheduleManager.php';
    require_once __DIR__ . '/../includes/REST/WidgetAPI.php';

    $GLOBALS['wpdb'] = new WidgetWPDBStub();
    $wpdb = $GLOBALS['wpdb'];

    $wpdb->schedules[501] = [
        (object) [
            'id' => 7001,
            'product_id' => 501,
            'meeting_point_id' => 21,
            'price_adult' => '65.00',
            'price_child' => '55.00',
            'duration_min' => 120,
            'capacity' => 16,
            'lang' => 'en',
        ],
        (object) [
            'id' => 7002,
            'product_id' => 501,
            'meeting_point_id' => 22,
            'price_adult' => '70.00',
            'price_child' => '60.00',
            'duration_min' => 120,
            'capacity' => 16,
            'lang' => 'it',
        ],
        (object) [
            'id' => 7003,
            'product_id' => 501,
            'meeting_point_id' => 22,
            'price_adult' => '75.00',
            'price_child' => '65.00',
            'duration_min' => 120,
            'capacity' => 16,
            'lang' => 'es',
        ],
        (object) [
            'id' => 7004,
            'product_id' => 501,
            'meeting_point_id' => null,
            'price_adult' => '80.00',
            'price_child' => '70.00',
            'duration_min' => 120,
            'capacity' => 16,
            'lang' => 'fr',
        ],
    ];

    $wpdb->schedules[999] = [];

    $wpdb->meetingPoints = [
        21 => (object) [
            'id' => 21,
            'name' => 'River Dock',
            'address' => 'Dock Street 1',
            'lat' => 45.061,
            'lng' => 7.678,
        ],
        22 => (object) [
            'id' => 22,
            'name' => 'Hill Entrance',
            'address' => 'Hill Road 5',
            'lat' => 45.102,
            'lng' => 7.701,
        ],
        99 => (object) [
            'id' => 99,
            'name' => 'Warehouse Lot',
            'address' => 'Industrial Zone',
            'lat' => 44.9,
            'lng' => 7.5,
        ],
    ];

    $widget_products = [
        501 => new DummyWidgetProduct(501),
    ];

    $cacheProperty = new \ReflectionProperty(\FP\Esperienze\Data\MeetingPointManager::class, 'cache');
    $cacheProperty->setAccessible(true);
    $cacheProperty->setValue(null, []);

    $allMeetingPoints = \FP\Esperienze\Data\MeetingPointManager::getAllMeetingPoints();
    if (count($allMeetingPoints) !== 3) {
        echo "Unexpected total meeting points count\n";
        exit(1);
    }

    $fallbackMeetingPoints = \FP\Esperienze\Data\MeetingPointManager::getMeetingPointsForProduct(999);
    if (count($fallbackMeetingPoints) !== 3) {
        echo "Fallback meeting points should include all locations\n";
        exit(1);
    }

    $api = new \FP\Esperienze\REST\WidgetAPI();
    $request = new \WP_REST_Request([
        'product_id' => 501,
        'width' => '600px',
        'height' => '720px',
        'theme' => 'light',
    ]);

    $response = $api->getIframeWidget($request);
    if (!($response instanceof \WP_REST_Response)) {
        echo "Iframe endpoint did not return a REST response\n";
        exit(1);
    }

    $html = $response->get_data();
    if (!is_string($html) || stripos($html, '<html') === false) {
        echo "Iframe response does not contain HTML output\n";
        exit(1);
    }

    if (!preg_match('/const widgetData = (\{.*?\});/s', $html, $matches)) {
        echo "Unable to locate widget data JSON in HTML output\n";
        exit(1);
    }

    $widgetData = json_decode($matches[1], true);
    if (!is_array($widgetData)) {
        echo "Widget data JSON is invalid\n";
        exit(1);
    }

    if (!isset($widgetData['meeting_points']) || !is_array($widgetData['meeting_points'])) {
        echo "Widget data is missing meeting points section\n";
        exit(1);
    }

    $meetingPointIds = array_column($widgetData['meeting_points'], 'id');
    sort($meetingPointIds);

    if ($meetingPointIds !== [21, 22]) {
        echo "Meeting points in widget data do not match product schedules\n";
        exit(1);
    }

    if (count($meetingPointIds) !== count(array_unique($meetingPointIds))) {
        echo "Meeting points include duplicate entries\n";
        exit(1);
    }

    echo "Widget iframe endpoint test passed\n";
}
