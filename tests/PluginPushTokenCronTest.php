<?php
declare(strict_types=1);

namespace {
    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    /** @var array<string, array<int, callable>> */
    $actions = [];
    /** @var array<string, array<int, callable>> */
    $filters = [];
    /** @var array<string, array{timestamp: int, recurrence: string}> */
    $scheduled_events = [];

    $current_time_value = strtotime('2024-06-01 12:00:00');

    function add_action($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
        global $actions;
        $actions[$hook][] = $callback;
    }

    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1): void
    {
        global $filters;
        $filters[$hook][] = $callback;
    }

    function do_action($hook, ...$args): void
    {
        global $actions;

        if (empty($actions[$hook])) {
            return;
        }

        foreach ($actions[$hook] as $callback) {
            \call_user_func_array($callback, $args);
        }
    }

    function wp_next_scheduled($hook, $args = [], $timestamp = 0)
    {
        global $scheduled_events;
        $scheduled = $scheduled_events[$hook]['timestamp'] ?? false;

        if (!$scheduled) {
            return false;
        }

        if (!empty($timestamp) && (int) $timestamp !== (int) $scheduled) {
            return false;
        }

        return $scheduled;
    }

    function wp_schedule_event($timestamp, $recurrence, $hook): void
    {
        global $scheduled_events;
        $scheduled_events[$hook] = [
            'timestamp'  => $timestamp,
            'recurrence' => $recurrence,
        ];
    }

    function __(string $text, string $domain = ''): string
    {
        return $text;
    }

    function apply_filters($hook, $value)
    {
        return $value;
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

            public function get_error_message(): string
            {
                return $this->message;
            }

            public function get_error_data()
            {
                return $this->data;
            }
        }
    }

    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }

    function current_time(string $type, bool $gmt = false)
    {
        global $current_time_value;

        if ($type === 'timestamp') {
            return $current_time_value;
        }

        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s', $current_time_value);
        }

        return '';
    }

    class PushTokenWPDBStub
    {
        public string $prefix = 'wp_';

        /** @var array<string, array{token: string, user_id: int, expires_at: ?string}> */
        public array $tokens = [];

        /** @var array<int, array{0: string, 1: array}> */
        public array $prepare_calls = [];

        /**
         * @return array{0: string, 1: array}
         */
        public function prepare(string $query, ...$args): array
        {
            $prepared = [$query, $args];
            $this->prepare_calls[] = $prepared;

            return $prepared;
        }

        /**
         * @param array{0: string, 1: array} $prepared
         * @return array<int, object>
         */
        public function get_results($prepared): array
        {
            [$query, $args] = $prepared;

            if (!str_contains($query, 'SELECT token FROM')) {
                return [];
            }

            $cutoff = (string) ($args[0] ?? '');
            $limit  = isset($args[1]) ? (int) $args[1] : 0;
            $cutoff_ts = strtotime($cutoff . ' UTC');

            $results = [];

            foreach ($this->tokens as $token => $row) {
                $expires_at = $row['expires_at'];
                if ($expires_at === null || $expires_at === '') {
                    continue;
                }

                $expiry_ts = strtotime($expires_at . ' UTC');
                if ($expiry_ts === false) {
                    continue;
                }

                if ($cutoff_ts !== false && $expiry_ts <= $cutoff_ts) {
                    $results[] = (object) ['token' => $token];
                    if ($limit > 0 && count($results) >= $limit) {
                        break;
                    }
                }
            }

            return $results;
        }

        public function delete(string $table, array $where, array $formats = []): int
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

    $wpdb->tokens = [
        'valid-token' => [
            'token' => 'valid-token',
            'user_id' => 1,
            'expires_at' => '2024-06-01 13:00:00',
        ],
        'expired-token' => [
            'token' => 'expired-token',
            'user_id' => 1,
            'expires_at' => '2024-05-31 09:00:00',
        ],
        'no-expiry-token' => [
            'token' => 'no-expiry-token',
            'user_id' => 1,
            'expires_at' => null,
        ],
        'expired-only' => [
            'token' => 'expired-only',
            'user_id' => 2,
            'expires_at' => '2024-06-01 11:30:00',
        ],
        'missing-expiry' => [
            'token' => 'missing-expiry',
            'user_id' => 3,
            'expires_at' => null,
        ],
    ];
}

namespace FP\Esperienze\Core {
    class CapabilityManager { public function __construct() {} }
    class I18nManager { public function __construct() {} }
    class CacheManager { public function __construct() {} }
    class AnalyticsTracker { public function __construct() {} }
    class AssetOptimizer {
        public static function init(): void {}
        public static function getMinifiedAssetUrl($type, $handle) { return false; }
    }
    class WebhookManager { public function __construct() {} }
    class Installer {
        public static function ensurePushTokenStorage() { return true; }
        public static function addPerformanceIndexes(): void {}
    }
}

namespace FP\Esperienze\Booking {
    class Cart_Hooks { public function __construct() {} }
    class BookingManager {
        public function __construct() {}

        public static function getInstance(): self
        {
            return new self();
        }
    }
}

namespace FP\Esperienze\Data {
    class DynamicPricingHooks { public function __construct() {} }
    class VoucherManager { public function __construct() {} }
    class NotificationManager { public function __construct() {} }
    class HoldManager { public static function cleanupExpiredHolds(): int { return 0; } }
    class WPMLHooks { public function __construct() {} }
}

namespace FP\Esperienze\Integrations {
    class TrackingManager { public function __construct() {} }
    class MetaCAPIManager { public function __construct() {} }
    class BrevoManager { public function __construct() {} }
    class GooglePlacesManager { public function __construct() {} }
    class EmailMarketingManager { public function __construct() {} }
}

namespace FP\Esperienze\AI {
    class AIFeaturesManager { public function __construct() {} }
}

namespace {
    require_once __DIR__ . '/../includes/Core/Plugin.php';

    $plugin_reflection = new \ReflectionClass(\FP\Esperienze\Core\Plugin::class);
    /** @var \FP\Esperienze\Core\Plugin $plugin */
    $plugin = $plugin_reflection->newInstanceWithoutConstructor();

    $init_components = $plugin_reflection->getMethod('initComponents');
    $init_components->setAccessible(true);

    $init_components->invoke($plugin);

    global $actions, $scheduled_events, $wpdb;

    if (empty($actions['fp_cleanup_push_tokens'])) {
        echo "Push token cleanup hook was not registered\n";
        exit(1);
    }

    if (\count($actions['fp_cleanup_push_tokens']) !== 1) {
        echo "Push token cleanup hook was registered more than once\n";
        exit(1);
    }

    if (!isset($scheduled_events['fp_cleanup_push_tokens'])) {
        echo "Push token cleanup event was not scheduled\n";
        exit(1);
    }

    if (($scheduled_events['fp_cleanup_push_tokens']['recurrence'] ?? '') !== 'daily') {
        echo "Push token cleanup event should use the daily schedule\n";
        exit(1);
    }

    // Invoke components again to ensure hooks are not duplicated.
    $init_components->invoke($plugin);

    if (\count($actions['fp_cleanup_push_tokens']) !== 1) {
        echo "Push token cleanup hook should only be registered once\n";
        exit(1);
    }

    do_action('fp_cleanup_push_tokens');

    if (!isset($wpdb->tokens['valid-token'])) {
        echo "Expected the valid token to remain\n";
        exit(1);
    }

    if (!isset($wpdb->tokens['no-expiry-token'])) {
        echo "Token without expiry should remain\n";
        exit(1);
    }

    if (!isset($wpdb->tokens['missing-expiry'])) {
        echo "Token with missing expiry should remain\n";
        exit(1);
    }

    if (isset($wpdb->tokens['expired-token'])) {
        echo "Expired token for user 1 should be removed\n";
        exit(1);
    }

    if (isset($wpdb->tokens['expired-only'])) {
        echo "Expired token for user 2 should be removed\n";
        exit(1);
    }

    echo "Push token cron registration and cleanup test passed\n";
}
