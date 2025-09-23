<?php
declare(strict_types=1);

namespace FP\Esperienze\Admin\Settings {
    class AutoTranslateSettings {
        public const OPTION_TARGET_LANGUAGES = 'fp_es_target_languages';
    }
}

namespace FP\Esperienze\Core {
    class I18nManager {
        public static array $calls = [];

        public static function translateString(string $text, string $key, bool $register = false): string {
            self::$calls[] = [
                'text'     => $text,
                'key'      => $key,
                'register' => $register,
            ];

            return $text;
        }

        public static function getAvailableLanguages(): array {
            return ['en'];
        }
    }
}

namespace {

use FP\Esperienze\Core\TranslationQueue;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

spl_autoload_register(
    static function (string $class): void {
        $prefix   = 'FP\\Esperienze\\';
        $base_dir = __DIR__ . '/../includes/';

        if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
            return;
        }

        $relative_class = substr($class, strlen($prefix));
        $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
);

$captured_logs = [];
$GLOBALS['fp_es_translation_handler'] = static function (string $message, array $context) use (&$captured_logs): void {
    $captured_logs[] = [
        'message' => $message,
        'context' => $context,
    ];
};

function apply_filters(string $tag, $value, ...$args) {
    if ('fp_es_translation_logger_handler' === $tag && isset($GLOBALS['fp_es_translation_handler'])) {
        return $GLOBALS['fp_es_translation_handler'];
    }

    return $value;
}

function wp_json_encode($data) {
    return json_encode($data);
}

$job_id = 42;
$GLOBALS['fp_es_jobs'] = [
    $job_id => [
        '_fp_es_job_type'    => 'string',
        '_fp_es_string_key'  => 'greeting',
        '_fp_es_string_text' => 'Hello',
        '_fp_es_lang'        => 'es',
    ],
];
$GLOBALS['fp_es_deleted'] = [];

function get_posts(array $args = []) {
    return array_keys($GLOBALS['fp_es_jobs']);
}

function get_post_meta($post_id, $key, $single = false) {
    return $GLOBALS['fp_es_jobs'][$post_id][$key] ?? '';
}

function wp_delete_post($post_id, $force_delete = false): void {
    $GLOBALS['fp_es_deleted'][] = $post_id;
    unset($GLOBALS['fp_es_jobs'][$post_id]);
}

TranslationQueue::processQueue();

if (count($captured_logs) !== 1) {
    echo "Translation queue logging test failed: log handler was not invoked once.\n";
    exit(1);
}

$log_entry = $captured_logs[0];

if ($log_entry['message'] !== 'TranslationQueue string job untranslated') {
    echo "Translation queue logging test failed: unexpected log message.\n";
    exit(1);
}

if (($log_entry['context']['key'] ?? null) !== 'greeting') {
    echo "Translation queue logging test failed: context missing string key.\n";
    exit(1);
}

if (!in_array($job_id, $GLOBALS['fp_es_deleted'], true)) {
    echo "Translation queue logging test failed: queued job was not cleaned up.\n";
    exit(1);
}

echo "Translation queue logging test passed\n";
}
