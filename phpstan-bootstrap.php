<?php
/**
 * PHPStan bootstrap file for WordPress
 */

// Define WordPress constants if not already defined
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// Define plugin constants
if (!defined('FP_ESPERIENZE_VERSION')) {
    define('FP_ESPERIENZE_VERSION', '1.0.0');
}

if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
    define('FP_ESPERIENZE_PLUGIN_DIR', __DIR__ . '/');
}

if (!defined('FP_ESPERIENZE_PLUGIN_URL')) {
    define('FP_ESPERIENZE_PLUGIN_URL', 'http://example.com/wp-content/plugins/fp-esperienze/');
}

// Mock WordPress functions for static analysis
if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = array()) {}
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') { return $text; }
}

if (!function_exists('esc_html')) {
    function esc_html($text) { return $text; }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) { return $text; }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = "_wpnonce", $referer = true, $echo = true) {}
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return $str; }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) { return false; }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) { return true; }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) { return true; }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct($code = '', $message = '', $data = '') {}
        public function get_error_code() { return ''; }
        public function get_error_message($code = '') { return ''; }
        public function get_error_data($code = '') { return null; }
        public function add($code, $message, $data = '') {}
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID = 0;
        public $post_title = '';
        public $post_content = '';
        public $post_status = '';
        public $post_type = '';
    }
}

if (!class_exists('WP_User')) {
    class WP_User {
        public $ID = 0;
        public $user_login = '';
        public $user_email = '';
        public function has_cap($capability) { return true; }
    }
}