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
        $qr_dir = $upload_dir['basedir'] . '/fp-vouchers/qr/';
        
        if (!file_exists($qr_dir)) {
            wp_mkdir_p($qr_dir);
        }
        
        $filename = 'qr-' . $voucher_data['code'] . '-' . time() . '.png';
        $file_path = $qr_dir . $filename;
        
        file_put_contents($file_path, $qr_image);
        
        return $file_path;
    }
    
    /**
     * Build QR code payload with HMAC signature
     *
     * @param array $voucher_data Voucher data
     * @return string Signed payload
     */
    private static function buildPayload($voucher_data): string {
        $payload_data = [
            'VC' => $voucher_data['code'],
            'PID' => $voucher_data['product_id'],
            'TYPE' => $voucher_data['amount_type'],
            'AMT' => $voucher_data['amount'],
            'EXP' => $voucher_data['expires_on'],
        ];
        
        $payload_parts = [];
        foreach ($payload_data as $key => $value) {
            $payload_parts[] = $key . ':' . $value;
        }
        
        $payload_string = 'FPX|' . implode('|', $payload_parts);
        
        // Generate HMAC signature
        $secret = get_option('fp_esperienze_gift_secret_hmac', '');
        $signature = hash_hmac('sha256', $payload_string, $secret);
        
        return $payload_string . '|SIG:' . $signature;
    }
    
    /**
     * Verify QR code payload signature
     *
     * @param string $payload QR code payload
     * @return array|false Parsed data or false if invalid
     */
    public static function verifyPayload($payload) {
        if (!str_starts_with($payload, 'FPX|')) {
            return false;
        }
        
        $parts = explode('|', $payload);
        if (count($parts) < 6) {
            return false;
        }
        
        // Extract signature
        $sig_part = end($parts);
        if (!str_starts_with($sig_part, 'SIG:')) {
            return false;
        }
        
        $provided_signature = substr($sig_part, 4);
        $payload_without_sig = implode('|', array_slice($parts, 0, -1));
        
        // Verify signature
        $secret = get_option('fp_esperienze_gift_secret_hmac', '');
        $expected_signature = hash_hmac('sha256', $payload_without_sig, $secret);
        
        if (!hash_equals($expected_signature, $provided_signature)) {
            return false;
        }
        
        // Parse payload data
        $data = [];
        for ($i = 1; $i < count($parts) - 1; $i++) {
            $part = $parts[$i];
            if (strpos($part, ':') !== false) {
                list($key, $value) = explode(':', $part, 2);
                $data[$key] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * Generate QR code URL for voucher redemption
     *
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