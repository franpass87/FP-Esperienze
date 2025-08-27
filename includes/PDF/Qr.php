<?php
/**
 * QR Code Generator for Vouchers
 *
 * @package FP\Esperienze\PDF
 */

namespace FP\Esperienze\PDF;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

defined('ABSPATH') || exit;

/**
 * QR code generation class for vouchers
 */
class Qr {
    
    /**
     * Generate QR code for voucher
     *
     * @param array $voucher_data Voucher data
     * @return string QR code file path
     */
    public static function generate($voucher_data): string {
        $payload = self::buildPayload($voucher_data);
        
        $options = new QROptions([
            'version'    => 5,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel'   => QRCode::ECC_M,
            'scale'      => 5,
            'imageBase64' => false,
        ]);
        
        $qrcode = new QRCode($options);
        $qr_image = $qrcode->render($payload);
        
        // Save QR code image
        $upload_dir = wp_upload_dir();
        $qr_dir = $upload_dir['basedir'] . '/fp-esperienze/voucher/qr/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }
        
        $filename = 'qr-' . $voucher_data['code'] . '-' . time() . '.png';
        $file_path = $qr_dir . $filename;
        
        file_put_contents($file_path, $qr_image);
        
        return $file_path;
    }
    
    /**
     * Build QR code payload with HMAC signature and key rotation
     *
     * @param array $voucher_data Voucher data
     * @return string Signed payload
     */
    private static function buildPayload($voucher_data): string {
        // Get current key info for rotation
        $key_info = self::getCurrentHMACKey();
        
        $payload_data = [
            'VC' => $voucher_data['code'],
            'PID' => $voucher_data['product_id'],
            'TYPE' => $voucher_data['amount_type'],
            'AMT' => $voucher_data['amount'],
            'EXP' => $voucher_data['expires_on'],
            'KID' => $key_info['kid'], // Key ID for rotation
        ];
        
        $payload_parts = [];
        foreach ($payload_data as $key => $value) {
            $payload_parts[] = $key . ':' . $value;
        }
        
        $payload_string = 'FPX|' . implode('|', $payload_parts);
        
        // Generate HMAC signature with current key
        $signature = hash_hmac('sha256', $payload_string, $key_info['key']);
        
        return $payload_string . '|SIG:' . $signature;
    }
    
    /**
     * Verify QR code payload signature with key rotation support
     *
     * @param string $payload QR code payload
     * @return array|false Parsed data or false if invalid
     */
    public static function verifyPayload($payload) {
        if (!str_starts_with($payload, 'FPX|')) {
            return false;
        }
        
        $parts = explode('|', $payload);
        if (count($parts) < 7) { // Increased minimum parts for KID
            return false;
        }
        
        // Extract signature
        $sig_part = end($parts);
        if (!str_starts_with($sig_part, 'SIG:')) {
            return false;
        }
        
        $provided_signature = substr($sig_part, 4);
        $payload_without_sig = implode('|', array_slice($parts, 0, -1));
        
        // Parse payload data first to get KID
        $data = [];
        for ($i = 1; $i < count($parts) - 1; $i++) {
            $part = $parts[$i];
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $data[$key] = $value;
            }
        }
        
        // Get key for verification (support key rotation)
        $key_id = $data['KID'] ?? 'default';
        $verification_key = self::getHMACKeyById($key_id);
        
        if (!$verification_key) {
            return false; // Unknown key ID
        }
        
        // Verify signature
        $expected_signature = hash_hmac('sha256', $payload_without_sig, $verification_key);
        
        if (!hash_equals($expected_signature, $provided_signature)) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Get current HMAC key info for signing
     *
     * @return array Key info with 'kid' and 'key'
     */
    private static function getCurrentHMACKey(): array {
        $keys = get_option('fp_esperienze_hmac_keys', []);
        $current_kid = get_option('fp_esperienze_current_key_id', 'default');
        
        // Initialize default key if none exists
        if (empty($keys)) {
            $keys = self::initializeDefaultKey();
            update_option('fp_esperienze_hmac_keys', $keys);
        }
        
        // Ensure current key exists
        if (!isset($keys[$current_kid])) {
            $current_kid = 'default';
            update_option('fp_esperienze_current_key_id', $current_kid);
        }
        
        return [
            'kid' => $current_kid,
            'key' => $keys[$current_kid]['key']
        ];
    }
    
    /**
     * Get HMAC key by ID for verification
     *
     * @param string $key_id Key ID
     * @return string|false Key or false if not found
     */
    private static function getHMACKeyById(string $key_id) {
        $keys = get_option('fp_esperienze_hmac_keys', []);
        return $keys[$key_id]['key'] ?? false;
    }
    
    /**
     * Initialize default HMAC key
     *
     * @return array Keys array
     */
    private static function initializeDefaultKey(): array {
        $default_key = get_option('fp_esperienze_gift_secret_hmac', '');
        if (empty($default_key)) {
            $default_key = bin2hex(random_bytes(32)); // 256-bit key
            update_option('fp_esperienze_gift_secret_hmac', $default_key);
        }
        
        return [
            'default' => [
                'key' => $default_key,
                'created' => time(),
                'status' => 'active'
            ]
        ];
    }
    
    /**
     * Rotate HMAC key (for admin use)
     *
     * @return string New key ID
     */
    public static function rotateHMACKey(): string {
        $keys = get_option('fp_esperienze_hmac_keys', []);
        $new_kid = 'key_' . time();
        $new_key = bin2hex(random_bytes(32));
        
        // Mark old keys as deprecated (keep for verification)
        foreach ($keys as &$key_info) {
            if ($key_info['status'] === 'active') {
                $key_info['status'] = 'deprecated';
                $key_info['deprecated_at'] = time();
            }
        }
        
        // Add new key
        $keys[$new_kid] = [
            'key' => $new_key,
            'created' => time(),
            'status' => 'active'
        ];
        
        update_option('fp_esperienze_hmac_keys', $keys);
        update_option('fp_esperienze_current_key_id', $new_kid);
        
        return $new_kid;
    }
    
    /**
     * @param string $voucher_code Voucher code
     * @return string Redemption URL
     */
    public static function getRedemptionUrl($voucher_code): string {
        return add_query_arg([
            'voucher_code' => $voucher_code,
            'action' => 'redeem_voucher'
        ], home_url('/voucher-redeem/'));
    }
}