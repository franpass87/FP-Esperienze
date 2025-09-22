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

    $now = \time();

    /** @var array<int, array<string, mixed>> */
    $user_meta = [
        1 => [
            '_push_notification_tokens' => ['valid-token', 'expired-token', 'no-expiry-token'],
            '_push_token_expires_at'    => [
                'valid-token'  => $now + 3600,
                'expired-token' => $now - 3600,
            ],
        ],
        2 => [
            '_push_notification_tokens' => ['expired-only'],
            '_push_token_expires_at'    => [
                'expired-only' => $now - 60,
            ],
        ],
        3 => [
            '_push_notification_tokens' => ['missing-expiry'],
            '_push_token_expires_at'    => [],
        ],
    ];

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

    function get_user_meta($user_id, $key, $single = false)
    {
        global $user_meta;

        if (!isset($user_meta[$user_id][$key])) {
            return $single ? false : [];
        }

        return $user_meta[$user_id][$key];
    }

    function update_user_meta($user_id, $key, $value): void
    {
        global $user_meta;

        if (!isset($user_meta[$user_id])) {
            $user_meta[$user_id] = [];
        }

        $user_meta[$user_id][$key] = $value;
    }

    function delete_user_meta($user_id, $key): void
    {
        global $user_meta;

        if (isset($user_meta[$user_id][$key])) {
            unset($user_meta[$user_id][$key]);

            if (empty($user_meta[$user_id])) {
                unset($user_meta[$user_id]);
            }
        }
    }

    class WP_User_Query
    {
        /** @var array<int, int> */
        private $results;

        public function __construct(array $args)
        {
            global $user_meta;

            $all_user_ids = \array_keys($user_meta);
            $number = isset($args['number']) ? \max(1, (int) $args['number']) : \count($all_user_ids);
            $paged = isset($args['paged']) ? \max(1, (int) $args['paged']) : 1;

            $offset = ($paged - 1) * $number;
            $this->results = \array_slice($all_user_ids, $offset, $number);
        }

        /**
         * @return array<int, int>
         */
        public function get_results(): array
        {
            return $this->results;
        }
    }
}

namespace FP\Esperienze\Core {
    class CapabilityManager { public function __construct() {} }
    class I18nManager { public function __construct() {} }
    class CacheManager { public function __construct() {} }
    class AssetOptimizer {
        public static function init(): void {}
        public static function getMinifiedAssetUrl($type, $handle) { return false; }
    }
    class WebhookManager { public function __construct() {} }
}

namespace FP\Esperienze\Booking {
    class Cart_Hooks { public function __construct() {} }
    class BookingManager { public function __construct() {} }
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

    global $actions, $scheduled_events, $user_meta, $now;

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

    $user1_tokens = get_user_meta(1, '_push_notification_tokens', true);
    if ($user1_tokens !== ['valid-token']) {
        echo "Expected only the valid token to remain for user 1\n";
        exit(1);
    }

    $user1_expiries = get_user_meta(1, '_push_token_expires_at', true);
    if ($user1_expiries !== ['valid-token' => $now + 3600]) {
        echo "Expected user 1 expiries to match remaining tokens\n";
        exit(1);
    }

    if (get_user_meta(2, '_push_notification_tokens', true) !== false) {
        echo "Expired tokens for user 2 should be removed\n";
        exit(1);
    }

    if (get_user_meta(2, '_push_token_expires_at', true) !== false) {
        echo "Expired token expiries for user 2 should be removed\n";
        exit(1);
    }

    if (get_user_meta(3, '_push_notification_tokens', true) !== false) {
        echo "Tokens without expiries for user 3 should be removed\n";
        exit(1);
    }

    if (get_user_meta(3, '_push_token_expires_at', true) !== false) {
        echo "Expiry map for user 3 should be removed\n";
        exit(1);
    }

    echo "Push token cron registration and cleanup test passed\n";
}
