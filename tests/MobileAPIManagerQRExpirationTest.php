<?php
declare(strict_types=1);

use FP\Esperienze\REST\MobileAPIManager;

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__);
}

class WP_Error
{
    public function __construct(
        public string $code = '',
        public string $message = '',
        public array $data = []
    ) {
    }

    public function get_error_code(): string
    {
        return $this->code;
    }

    public function get_error_message(): string
    {
        return $this->message;
    }

    public function get_error_data(): array
    {
        return $this->data;
    }
}

class WP_REST_Request
{
    public function __construct(private array $params = [])
    {
    }

    public function get_param(string $key)
    {
        return $this->params[$key] ?? null;
    }
}

function __(string $text, string $domain = ''): string
{
    return $text;
}

function sanitize_text_field($value)
{
    return is_string($value) ? trim($value) : '';
}

function wp_salt(string $scheme = 'auth'): string
{
    return 'unit-test-salt';
}

function wp_json_encode($data): string
{
    return json_encode($data);
}

function is_wp_error($value): bool
{
    return $value instanceof WP_Error;
}

require_once __DIR__ . '/../includes/REST/MobileAPIManager.php';

$reflection = new ReflectionClass(MobileAPIManager::class);
/** @var MobileAPIManager $manager */
$manager = $reflection->newInstanceWithoutConstructor();

$generateMethod = $reflection->getMethod('generateBookingQRData');
$generateMethod->setAccessible(true);

$decodeMethod = $reflection->getMethod('decodeQRData');
$decodeMethod->setAccessible(true);

$freshPayload = $generateMethod->invoke($manager, 99);
$freshResult = $decodeMethod->invoke($manager, $freshPayload);

if (!is_array($freshResult)) {
    echo "Decoded payload should be an array for fresh QR data\n";
    exit(1);
}

if (($freshResult['booking_id'] ?? null) !== 99) {
    echo "Decoded booking identifier mismatch for valid QR\n";
    exit(1);
}

$ttlReflection = $reflection->getReflectionConstant('QR_CODE_TTL');
$ttl = $ttlReflection ? (int) $ttlReflection->getValue() : 86400;

$expiredTimestamp = time() - ($ttl + 5);
$expiredHash = hash_hmac('sha256', 'booking_' . 77 . '_' . $expiredTimestamp, wp_salt('auth'));
$expiredPayload = base64_encode(wp_json_encode([
    'booking_id' => 77,
    'timestamp' => $expiredTimestamp,
    'hash' => $expiredHash,
]));

$expiredResult = $decodeMethod->invoke($manager, $expiredPayload);

if (!$expiredResult instanceof WP_Error) {
    echo "Expired QR payload should return a WP_Error\n";
    exit(1);
}

if ($expiredResult->code !== 'qr_expired') {
    echo "Expired QR should use the qr_expired error code\n";
    exit(1);
}

$expiredData = $expiredResult->get_error_data();

if (($expiredData['status'] ?? null) !== 410) {
    echo "Expired QR error should report HTTP status 410\n";
    exit(1);
}

if (($expiredData['expires_after'] ?? null) !== $ttl) {
    echo "Expiration policy should include the QR lifetime in seconds\n";
    exit(1);
}

if (($expiredData['expired_at'] ?? null) !== ($expiredTimestamp + $ttl)) {
    echo "Expired QR error should expose the expiration timestamp\n";
    exit(1);
}

$scanResult = $manager->scanQRCode(new WP_REST_Request(['qr_data' => $expiredPayload]));

if (!$scanResult instanceof WP_Error) {
    echo "Scanning an expired QR should return a WP_Error response\n";
    exit(1);
}

if ($scanResult->code !== 'qr_expired') {
    echo "Scan response should propagate the qr_expired error code\n";
    exit(1);
}

if (($scanResult->data['status'] ?? null) !== 410) {
    echo "Scan response should preserve the HTTP status policy\n";
    exit(1);
}

$policyText = $scanResult->data['expiration_policy'] ?? '';
if (!is_string($policyText) || stripos($policyText, 'expire') === false) {
    echo "Expiration policy description should be included in the error payload\n";
    exit(1);
}

echo "QR expiration validation test passed\n";
