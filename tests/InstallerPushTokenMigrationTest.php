<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_message(): string { return $this->message; }
    }
}

$current_time_value = strtotime('2024-06-01 12:00:00');

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

$options = [];

function get_option(string $key, $default = false)
{
    global $options;

    return $options[$key] ?? $default;
}

function update_option(string $key, $value)
{
    global $options;

    $options[$key] = $value;

    return true;
}

$deleted_meta = [];

function delete_user_meta(int $user_id, string $key): void
{
    global $deleted_meta, $wpdb;

    $deleted_meta[] = [$user_id, $key];

    foreach ($wpdb->usermetaRows as $index => $row) {
        if ((int) $row['user_id'] === $user_id && $row['meta_key'] === $key) {
            unset($wpdb->usermetaRows[$index]);
        }
    }
}

if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return $trimmed;
        }

        $result = @unserialize($trimmed);
        if ($result === false && $trimmed !== 'b:0;') {
            return $value;
        }

        return $result;
    }
}

class MigrationWPDBStub
{
    public string $prefix = 'wp_';
    public string $usermeta = 'wp_usermeta';

    /** @var array<string, array<string, mixed>> */
    public array $pushTokens = [];

    /** @var array<int, array<string, mixed>> */
    public array $usermetaRows = [];

    public string $last_error = '';

    public function prepare(string $query, ...$args): array
    {
        return [$query, $args];
    }

    public function get_var($prepared)
    {
        [$query, $args] = $prepared;

        if (str_contains($query, 'SHOW TABLES LIKE')) {
            return $args[0] ?? $this->prefix . 'fp_push_tokens';
        }

        return null;
    }

    /**
     * @param array{0: string, 1: array} $prepared
     */
    public function get_results($prepared): array
    {
        if (is_array($prepared)) {
            [$query, $args] = $prepared;
        } else {
            $query = (string) $prepared;
            $args = [];
        }

        if (str_contains($query, $this->usermeta)) {
            return array_map(static fn(array $row): object => (object) $row, $this->usermetaRows);
        }

        return [];
    }

    public function get_row($prepared)
    {
        [$query, $args] = $prepared;

        if (!str_contains($query, 'WHERE token =')) {
            return null;
        }

        $token = (string) ($args[0] ?? '');

        if ($token === '' || !isset($this->pushTokens[$token])) {
            return null;
        }

        return (object) $this->pushTokens[$token];
    }

    public function insert(string $table, array $data)
    {
        if ($table !== $this->prefix . 'fp_push_tokens') {
            return false;
        }

        $this->pushTokens[$data['token']] = $data;

        return 1;
    }

    public function update(string $table, array $data, array $where, $format = null, $where_format = null)
    {
        if ($table !== $this->prefix . 'fp_push_tokens') {
            return false;
        }

        $token = $where['token'] ?? '';

        if ($token === '' || !isset($this->pushTokens[$token])) {
            return 0;
        }

        $this->pushTokens[$token] = array_merge($this->pushTokens[$token], $data);

        return 1;
    }

    public function delete(string $table, array $where, array $formats = [])
    {
        if ($table !== $this->usermeta) {
            return 0;
        }

        $user_id = (int) ($where['user_id'] ?? 0);
        $meta_key = $where['meta_key'] ?? '';

        foreach ($this->usermetaRows as $index => $row) {
            if ((int) $row['user_id'] === $user_id && $row['meta_key'] === $meta_key) {
                unset($this->usermetaRows[$index]);
            }
        }

        return 1;
    }
}

$wpdb = new MigrationWPDBStub();
$GLOBALS['wpdb'] = $wpdb;

$wpdb->usermetaRows = [
    [
        'user_id' => 7,
        'meta_key' => '_push_notification_tokens',
        'meta_value' => serialize(['token-one', 'token-two']),
    ],
    [
        'user_id' => 7,
        'meta_key' => '_push_token_expires_at',
        'meta_value' => serialize([
            'token-one' => $current_time_value + 3600,
            'token-two' => $current_time_value - 120,
        ]),
    ],
    [
        'user_id' => 7,
        'meta_key' => '_push_platform',
        'meta_value' => 'android',
    ],
    [
        'user_id' => 7,
        'meta_key' => '_push_registered_at',
        'meta_value' => '2024-05-30 09:00:00',
    ],
    [
        'user_id' => 8,
        'meta_key' => '_push_notification_tokens',
        'meta_value' => serialize([]),
    ],
];

require_once __DIR__ . '/../includes/Core/Installer.php';

$result = \FP\Esperienze\Core\Installer::migratePushTokens();

if ($result instanceof WP_Error) {
    echo "Migration returned WP_Error: " . $result->get_error_message() . "\n";
    exit(1);
}

if (!isset($options['fp_esperienze_push_tokens_migrated'])) {
    echo "Migration completion option was not updated\n";
    exit(1);
}

if (!isset($wpdb->pushTokens['token-one']) || !isset($wpdb->pushTokens['token-two'])) {
    echo "Tokens were not migrated into the push tokens table\n";
    exit(1);
}

$token_one = $wpdb->pushTokens['token-one'];
$token_two = $wpdb->pushTokens['token-two'];

if ($token_one['user_id'] !== 7 || $token_one['platform'] !== 'android') {
    echo "Token metadata was not preserved during migration\n";
    exit(1);
}

if ($token_one['created_at'] !== '2024-05-30 09:00:00' || $token_one['last_seen'] !== '2024-05-30 09:00:00') {
    echo "Token timestamps were not derived from registered_at\n";
    exit(1);
}

if ($token_two['expires_at'] === null) {
    echo "Token expiry timestamp should be migrated when present\n";
    exit(1);
}

$deleted_pairs = array_map(static fn(array $pair): string => $pair[0] . ':' . $pair[1], $deleted_meta);

$expected_deleted = [
    '7:_push_notification_tokens',
    '7:_push_token_expires_at',
    '7:_push_platform',
    '7:_push_registered_at',
    '8:_push_notification_tokens',
];

foreach ($expected_deleted as $key) {
    if (!in_array($key, $deleted_pairs, true)) {
        echo "Legacy meta {$key} was not removed\n";
        exit(1);
    }
}

// Ensure running the migration again is a no-op.
$options['fp_esperienze_push_tokens_migrated'] = 'done';
$result = \FP\Esperienze\Core\Installer::migratePushTokens();

if ($result instanceof WP_Error) {
    echo "Second migration run should not return an error\n";
    exit(1);
}

if (count($wpdb->pushTokens) !== 2) {
    echo "Repeated migration should not duplicate tokens\n";
    exit(1);
}

echo "Installer push token migration test passed\n";
