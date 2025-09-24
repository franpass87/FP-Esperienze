<?php
declare(strict_types=1);

namespace FP\Esperienze\Core {
    class RateLimiter
    {
        public static function checkRateLimit($key, $limit, $window): bool
        {
            return true;
        }
    }
}

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

        public function get_error_message(): string
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }

        public function add_data(array $data): void
        {
            $this->data = array_merge($this->data, $data);
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
                if (strtolower($header) === $target) {
                    return $value;
                }
            }

            return null;
        }
    }

    if (!function_exists('wp_unslash')) {
        function wp_unslash($value)
        {
            return $value;
        }
    }

    if (!function_exists('sanitize_text_field')) {
        function sanitize_text_field($value)
        {
            if (is_string($value)) {
                $value = strip_tags($value);
                $value = preg_replace('/[\r\n\t\0\x0B]+/', '', $value);
                return trim($value);
            }

            return $value;
        }
    }

    if (!function_exists('esc_url_raw')) {
        function esc_url_raw(string $url): string
        {
            return $url;
        }
    }

    if (!function_exists('absint')) {
        function absint($value): int
        {
            return abs((int) $value);
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data)
        {
            return json_encode($data);
        }
    }

    if (!function_exists('wp_remote_retrieve_response_code')) {
        function wp_remote_retrieve_response_code($response)
        {
            if ($response instanceof WP_Error) {
                return 0;
            }

            return $response['response']['code'] ?? 0;
        }
    }

    if (!function_exists('wp_remote_retrieve_body')) {
        function wp_remote_retrieve_body($response)
        {
            if ($response instanceof WP_Error) {
                return '';
            }

            return $response['body'] ?? '';
        }
    }

    if (!function_exists('is_wp_error')) {
        function is_wp_error($thing): bool
        {
            return $thing instanceof WP_Error;
        }
    }

    $current_time_value = strtotime('2024-06-01 12:00:00');

    if (!function_exists('current_time')) {
        function current_time(string $type, bool $gmt = false)
        {
            global $current_time_value;

            if ($type === 'timestamp') {
                return $current_time_value;
            }

            if ($type === 'mysql') {
                return gmdate('Y-m-d H:i:s', $current_time_value);
            }

            if ($type === 'timestamp' && $gmt) {
                return $current_time_value;
            }

            return '';
        }
    }

    if (!function_exists('wp_salt')) {
        function wp_salt(string $scheme = 'auth'): string
        {
            return 'salt-' . $scheme;
        }
    }

    if (!function_exists('wp_hash')) {
        function wp_hash(string $data, string $scheme = 'auth'): string
        {
            return hash_hmac('sha256', $data, wp_salt($scheme));
        }
    }

    if (!function_exists('__')) {
        function __(string $text, string $domain = ''): string
        {
            return $text;
        }
    }

    $options = [
        'fp_esperienze_mobile_notifications' => [
            'provider' => 'fcm',
            'server_key' => 'test-key',
            'project_id' => 'project-123',
        ],
    ];

    function get_option(string $key, $default = false)
    {
        global $options;

        return $options[$key] ?? $default;
    }

    $user_meta = [];

    function get_user_meta(int $user_id, string $key, bool $single = false)
    {
        global $user_meta;

        if (!isset($user_meta[$user_id][$key])) {
            return $single ? '' : [];
        }

        $value = $user_meta[$user_id][$key];

        if ($single) {
            return $value;
        }

        return is_array($value) ? $value : [$value];
    }

    function update_user_meta(int $user_id, string $key, $value)
    {
        global $user_meta;

        if (!isset($user_meta[$user_id])) {
            $user_meta[$user_id] = [];
        }

        $user_meta[$user_id][$key] = $value;

        return true;
    }

    function delete_user_meta(int $user_id, string $key)
    {
        global $user_meta;

        if (isset($user_meta[$user_id][$key])) {
            unset($user_meta[$user_id][$key]);
        }

        return true;
    }

    $wp_remote_responses = [];

    function wp_remote_post($url, array $args)
    {
        global $wp_remote_responses;

        $body = $args['body'] ?? '';
        $decoded = json_decode((string) $body, true);
        $token = '';

        if (is_array($decoded)) {
            if (isset($decoded['to'])) {
                $token = (string) $decoded['to'];
            } elseif (isset($decoded['include_player_ids']) && is_array($decoded['include_player_ids']) && isset($decoded['include_player_ids'][0])) {
                $token = (string) $decoded['include_player_ids'][0];
            }
        }

        if (isset($wp_remote_responses[$url][$token])) {
            return $wp_remote_responses[$url][$token];
        }

        if (isset($wp_remote_responses[$token])) {
            return $wp_remote_responses[$token];
        }

        return new WP_Error('unexpected_token', 'No stubbed response for token', ['token' => $token, 'url' => $url]);
    }

    class PushTokenWPDBStub
    {
        public string $prefix = 'wp_';

        /** @var array<string, array<string, mixed>> */
        public array $tokens = [];

        /**
         * @return array{0: string, 1: array}
         */
        public function prepare(string $query, ...$args): array
        {
            return [$query, $args];
        }

        public function get_row($prepared)
        {
            [$query, $args] = $prepared;

            if (!str_contains($query, 'WHERE token =')) {
                return null;
            }

            $token = (string) ($args[0] ?? '');

            if (!isset($this->tokens[$token])) {
                return null;
            }

            return (object) $this->tokens[$token];
        }

        /**
         * @param array{0: string, 1: array} $prepared
         * @return array<int, object>
         */
        public function get_results($prepared): array
        {
            [$query, $args] = $prepared;

            if (!str_contains($query, 'WHERE user_id')) {
                return [];
            }

            $user_id = (int) ($args[0] ?? 0);
            $results = [];

            foreach ($this->tokens as $row) {
                if ((int) ($row['user_id'] ?? 0) !== $user_id) {
                    continue;
                }

                $results[] = (object) [
                    'token' => $row['token'],
                    'platform' => $row['platform'] ?? null,
                    'expires_at' => $row['expires_at'] ?? null,
                ];
            }

            return $results;
        }

        public function insert(string $table, array $data)
        {
            if ($table !== $this->prefix . 'fp_push_tokens') {
                return false;
            }

            $token = $data['token'];
            $this->tokens[$token] = $data;

            return 1;
        }

        public function update(string $table, array $data, array $where, $format = null, $where_format = null)
        {
            if ($table !== $this->prefix . 'fp_push_tokens') {
                return false;
            }

            $token = $where['token'] ?? null;

            if ($token === null || !isset($this->tokens[$token])) {
                return 0;
            }

            $existing = $this->tokens[$token];
            $this->tokens[$token] = array_merge($existing, $data);

            return 1;
        }

        public function delete(string $table, array $where, array $formats = [])
        {
            if ($table !== $this->prefix . 'fp_push_tokens') {
                return 0;
            }

            $token = $where['token'] ?? '';

            if ($token === '' || !isset($this->tokens[$token])) {
                return 0;
            }

            unset($this->tokens[$token]);

            return 1;
        }
    }

    $wpdb = new PushTokenWPDBStub();
    $GLOBALS['wpdb'] = $wpdb;

    require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

    $reflection = new \ReflectionClass(MobileAPIManager::class);
    /** @var MobileAPIManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $generate_token = $reflection->getMethod('generateMobileToken');
    $generate_token->setAccessible(true);
    $auth_token = $generate_token->invoke($manager, 42);

    $request = new WP_REST_Request([
        'token' => ' Device_Token! ',
        'platform' => 'iOS',
    ], [
        'Authorization' => 'Bearer ' . $auth_token,
    ]);

    $response = $manager->registerPushToken($request);

    $data = $response->get_data();
    if (($data['success'] ?? false) !== true) {
        echo "Register push token should return success\n";
        exit(1);
    }

    if (!isset($wpdb->tokens['Device_Token'])) {
        echo "Sanitized token was not stored in the database\n";
        exit(1);
    }

    $token_row = $wpdb->tokens['Device_Token'];
    $initial_created_at = $token_row['created_at'];

    if ($token_row['platform'] !== 'iOS') {
        echo "Platform should be stored with the token\n";
        exit(1);
    }

    if ($token_row['user_id'] !== 42) {
        echo "Token should be associated with the current user\n";
        exit(1);
    }

    if (empty($token_row['expires_at'])) {
        echo "Token expiry should be stored\n";
        exit(1);
    }

    // Re-register with a new platform to ensure updates work.
    $request = new WP_REST_Request([
        'token' => 'Device_Token',
        'platform' => 'android',
    ], [
        'Authorization' => 'Bearer ' . $auth_token,
    ]);

    $manager->registerPushToken($request);

    $token_row = $wpdb->tokens['Device_Token'];

    if ($token_row['platform'] !== 'android') {
        echo "Token platform should be updated on re-registration\n";
        exit(1);
    }

    if ($token_row['created_at'] !== $initial_created_at) {
        echo "Token creation timestamp should not change on update\n";
        exit(1);
    }

    // Prepare tokens for sending.
    $wpdb->tokens['Device_Token']['expires_at'] = '2024-07-01 12:00:00';
    $wpdb->tokens['ExpiredToken'] = [
        'token' => 'ExpiredToken',
        'user_id' => 42,
        'platform' => 'android',
        'expires_at' => '2024-05-01 00:00:00',
        'created_at' => '2024-04-01 00:00:00',
        'last_seen' => null,
    ];
    $wpdb->tokens['ErrorToken'] = [
        'token' => 'ErrorToken',
        'user_id' => 42,
        'platform' => 'ios',
        'expires_at' => '2024-07-01 12:00:00',
        'created_at' => '2024-06-01 12:00:00',
        'last_seen' => null,
    ];

    $wp_remote_responses = [
        'https://fcm.googleapis.com/fcm/send' => [
            'Device_Token' => [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'success' => 1,
                    'results' => [
                        ['message_id' => '123'],
                    ],
                ]),
            ],
            'ErrorToken' => [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'success' => 0,
                    'results' => [
                        ['error' => 'NotRegistered'],
                    ],
                ]),
            ],
        ],
    ];

    $current_time_value = strtotime('2024-06-05 09:00:00');

    $send_method = $reflection->getMethod('sendPushToUser');
    $send_method->setAccessible(true);
    $result = $send_method->invoke($manager, 42, 'Test', 'Body', ['url' => 'https://example.com'], 'high');

    if ($result !== true) {
        echo "Expected push to be sent successfully\n";
        exit(1);
    }

    if (!isset($wpdb->tokens['Device_Token'])) {
        echo "Valid token should remain after sending\n";
        exit(1);
    }

    if (isset($wpdb->tokens['ErrorToken']) || isset($wpdb->tokens['ExpiredToken'])) {
        echo "Expired or invalid tokens should be removed after sending\n";
        exit(1);
    }

    if ($wpdb->tokens['Device_Token']['last_seen'] !== gmdate('Y-m-d H:i:s', $current_time_value)) {
        echo "Token last_seen should be updated after a successful send\n";
        exit(1);
    }

    // Configure OneSignal credentials and stub responses.
    $options['fp_esperienze_mobile_notifications'] = [
        'provider' => 'onesignal',
        'app_id' => 'onesignal-app',
        'rest_api_key' => 'rest-key',
    ];

    $wp_remote_responses = [
        'https://onesignal.com/api/v1/notifications' => [
            'OS_Success' => [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'id' => 'notif-100',
                    'recipients' => 1,
                ]),
            ],
            'OS_Invalid' => [
                'response' => ['code' => 200],
                'body' => json_encode([
                    'errors' => [
                        ['code' => 'invalid_player_id', 'message' => 'Player ID invalid'],
                    ],
                    'invalid_player_ids' => ['OS_Invalid'],
                ]),
            ],
            'OS_RateLimited' => [
                'response' => ['code' => 429],
                'body' => json_encode([
                    'errors' => [
                        ['code' => 'rate_limit_exceeded', 'message' => 'Too many requests'],
                    ],
                ]),
            ],
        ],
    ];

    $wpdb->tokens['OS_Success'] = [
        'token' => 'OS_Success',
        'user_id' => 99,
        'platform' => 'android',
        'expires_at' => '2024-07-01 12:00:00',
        'created_at' => '2024-06-01 00:00:00',
        'last_seen' => null,
    ];
    $wpdb->tokens['OS_Invalid'] = [
        'token' => 'OS_Invalid',
        'user_id' => 99,
        'platform' => 'ios',
        'expires_at' => '2024-07-01 12:00:00',
        'created_at' => '2024-06-01 00:00:00',
        'last_seen' => null,
    ];
    $wpdb->tokens['OS_RateLimited'] = [
        'token' => 'OS_RateLimited',
        'user_id' => 99,
        'platform' => 'ios',
        'expires_at' => '2024-07-01 12:00:00',
        'created_at' => '2024-06-01 00:00:00',
        'last_seen' => null,
    ];

    $current_time_value = strtotime('2024-06-06 10:00:00');

    $result = $send_method->invoke($manager, 99, 'OS Title', 'OS Body', ['foo' => 'bar'], 'high');

    if ($result !== true) {
        echo "Expected OneSignal push to succeed when at least one token is valid\n";
        exit(1);
    }

    if (($wpdb->tokens['OS_Success']['last_seen'] ?? '') !== gmdate('Y-m-d H:i:s', $current_time_value)) {
        echo "OneSignal success token should update last_seen\n";
        exit(1);
    }

    if (isset($wpdb->tokens['OS_Invalid'])) {
        echo "OneSignal invalid player tokens should be removed\n";
        exit(1);
    }

    if (!isset($wpdb->tokens['OS_RateLimited'])) {
        echo "Tokens affected by rate limits should not be pruned\n";
        exit(1);
    }

    // Ensure OneSignal failures return informative WP_Error data.
    $wpdb->tokens['OS_Failure'] = [
        'token' => 'OS_Failure',
        'user_id' => 123,
        'platform' => 'android',
        'expires_at' => '2024-07-01 12:00:00',
        'created_at' => '2024-06-01 00:00:00',
        'last_seen' => null,
    ];

    $wp_remote_responses['https://onesignal.com/api/v1/notifications']['OS_Failure'] = [
        'response' => ['code' => 200],
        'body' => json_encode([
            'errors' => [
                ['code' => 'invalid_player_id', 'message' => 'Invalid player'],
            ],
            'invalid_player_ids' => ['OS_Failure'],
        ]),
    ];

    $failure_result = $send_method->invoke($manager, 123, 'Fail', 'Fail', [], 'high');

    if (!$failure_result instanceof WP_Error) {
        echo "Expected OneSignal failure to return a WP_Error\n";
        exit(1);
    }

    if ($failure_result->code !== 'push_delivery_failed') {
        echo "Failure WP_Error should use push_delivery_failed code\n";
        exit(1);
    }

    $failure_data = $failure_result->get_error_data();
    if (!in_array('OS_Failure', $failure_data['invalid_tokens'] ?? [], true)) {
        echo "Failure data should list invalid OneSignal tokens\n";
        exit(1);
    }

    if (empty($failure_data['errors'])) {
        echo "Failure data should include error messages\n";
        exit(1);
    }

    if (isset($wpdb->tokens['OS_Failure'])) {
        echo "Invalid OneSignal tokens should be removed after failure\n";
        exit(1);
    }

    // Directly invoke sendPushPayload for OneSignal to inspect provider-specific metadata.
    $send_payload_method = $reflection->getMethod('sendPushPayload');
    $send_payload_method->setAccessible(true);

    $wp_remote_responses['https://onesignal.com/api/v1/notifications']['OS_Direct_Invalid'] = [
        'response' => ['code' => 200],
        'body' => json_encode([
            'errors' => [
                ['code' => 'invalid_player_id', 'message' => 'Invalid direct token'],
            ],
            'invalid_player_ids' => ['OS_Direct_Invalid'],
        ]),
    ];

    $direct_result = $send_payload_method->invoke($manager, 'OS_Direct_Invalid', [
        'title' => 'Direct',
        'body' => 'Message',
        'data' => ['example' => '1'],
        'priority' => 'high',
    ], [
        'user_id' => 456,
        'platform' => 'ios',
    ]);

    if (!$direct_result instanceof WP_Error) {
        echo "Direct OneSignal invocation should return WP_Error for invalid tokens\n";
        exit(1);
    }

    $direct_data = $direct_result->get_error_data();
    if (($direct_data['error'] ?? '') !== 'invalid_player_id') {
        echo "Direct OneSignal error should expose provider error code\n";
        exit(1);
    }

    if (($direct_data['remove_token'] ?? false) !== true) {
        echo "Direct OneSignal error should request token removal\n";
        exit(1);
    }

    if (($direct_data['status'] ?? 0) !== 410) {
        echo "Direct OneSignal error should mark status 410 for invalid tokens\n";
        exit(1);
    }

    $wp_remote_responses['https://onesignal.com/api/v1/notifications']['OS_RateLimit_Direct'] = [
        'response' => ['code' => 429],
        'body' => json_encode([
            'errors' => [
                ['code' => 'rate_limit_exceeded', 'message' => 'Rate limit hit'],
            ],
        ]),
    ];

    $rate_result = $send_payload_method->invoke($manager, 'OS_RateLimit_Direct', [
        'title' => 'Direct',
        'body' => 'Message',
        'data' => [],
    ], []);

    if (!$rate_result instanceof WP_Error) {
        echo "Rate limited OneSignal request should return WP_Error\n";
        exit(1);
    }

    $rate_data = $rate_result->get_error_data();
    if (($rate_data['status'] ?? 0) !== 429) {
        echo "Rate limited OneSignal errors should surface HTTP 429 status\n";
        exit(1);
    }

    if (($rate_data['remove_token'] ?? false) !== false) {
        echo "Rate limit errors should not mark tokens for deletion\n";
        exit(1);
    }

    if (($rate_data['error'] ?? '') !== 'rate_limit_exceeded') {
        echo "Rate limit errors should expose provider error codes\n";
        exit(1);
    }

    echo "Mobile API push token tests passed\n";
}
