<?php
declare(strict_types=1);

use FP\Esperienze\Core\AutoTranslator;

// Stub WordPress constants and functions for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}
if (!defined('WEEK_IN_SECONDS')) {
    define('WEEK_IN_SECONDS', 604800);
}

require_once __DIR__ . '/../includes/Core/AutoTranslator.php';

$transients = [];
function get_transient(string $key) {
    global $transients;
    return $transients[$key] ?? false;
}
function set_transient(string $key, $value, int $ttl) {
    global $transients;
    $transients[$key] = $value;
    return true;
}
function get_option(string $name, $default = false) {
    return $default;
}
function apply_filters(string $tag, $value) {
    return $value;
}
function wp_http_validate_url(string $url) {
    return $url;
}
$call_count = 0;
function wp_remote_post(string $url, array $args = []) {
    global $call_count;
    $call_count++;
    $body = json_decode($args['body'], true);
    return [
        'response' => ['code' => 200],
        'body'     => json_encode(['translatedText' => $body['q'] . '[' . $body['source'] . '->' . $body['target'] . ']']),
    ];
}
function is_wp_error($thing) {
    return false;
}
function wp_remote_retrieve_response_code(array $response) {
    return $response['response']['code'];
}
function wp_remote_retrieve_body(array $response) {
    return $response['body'];
}
function wp_json_encode($data) {
    return json_encode($data);
}

// Execute translations
$text = 'Hello';
AutoTranslator::translate($text, 'es', 'en'); // First call triggers API
AutoTranslator::translate($text, 'es', 'en'); // Should hit cache
AutoTranslator::translate($text, 'es', 'fr'); // Different source, triggers API again

if ($call_count !== 2) {
    echo "Cache test failed: expected 2 API calls, got {$call_count}\n";
    exit(1);
}

echo "Cache test passed\n";
