<?php
declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content');
}

if (!function_exists('trailingslashit')) {
    function trailingslashit(string $path): string
    {
        return rtrim($path, "\\/") . '/';
    }
}

if (!function_exists('is_multisite')) {
    function is_multisite(): bool
    {
        return false;
    }
}

if (!function_exists('get_current_blog_id')) {
    function get_current_blog_id(): int
    {
        return 1;
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return rtrim(dirname($file), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return 'https://example.test/wp-content/plugins/fp-esperienze/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        return basename($file);
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, int $timestamp, ?DateTimeZone $timezone = null): string
    {
        $zone = $timezone ?: new DateTimeZone('UTC');
        $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone($zone);
        return $date->format($format);
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, int $timestamp, bool $gmt = false): string
    {
        if ($gmt) {
            return gmdate($format, $timestamp);
        }
        return date($format, $timestamp);
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void
    {
        global $fp_esperienze_test_actions;
        if (!is_array($fp_esperienze_test_actions)) {
            $fp_esperienze_test_actions = [];
        }
        $fp_esperienze_test_actions[] = [
            'hook' => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];
    }
}

if (!function_exists('current_time')) {
    function current_time(string $type)
    {
        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s');
        }
        if ($type === 'timestamp') {
            return time();
        }
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, bool $create_dir = true)
    {
        $base = sys_get_temp_dir() . '/fp-esperienze-tests/uploads';
        if ($create_dir && !is_dir($base)) {
            mkdir($base, 0775, true);
        }
        return [
            'path' => $base,
            'url' => 'https://example.test/wp-content/uploads',
            'subdir' => '',
            'basedir' => $base,
            'baseurl' => 'https://example.test/wp-content/uploads',
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $dir): bool
    {
        return is_dir($dir) ? true : mkdir($dir, 0775, true);
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool
    {
        return true;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        return false;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $flags = 0)
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | $flags);
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = ''): string
    {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_is_writable')) {
    function wp_is_writable(string $path): bool
    {
        return is_writable($path);
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = '', string $filter = 'raw')
    {
        if ($show === 'version') {
            return '6.5';
        }

        return 'FP Esperienze Test';
    }
}

if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void
    {
        // No-op for tests.
    }
}

if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void
    {
        // No-op for tests.
    }
}

if (!function_exists('register_uninstall_hook')) {
    function register_uninstall_hook(string $file, callable $callback): void
    {
        // No-op for tests.
    }
}

if (!class_exists('WP_CLI_Command')) {
    abstract class WP_CLI_Command
    {
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product
    {
    }
}

require_once __DIR__ . '/../../fp-esperienze.php';
