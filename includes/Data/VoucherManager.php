<?php
/**
 * Voucher Management for Gift Voucher Redemption
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Voucher manager class for handling gift voucher redemption
 */
class VoucherManager {
    
    /**
     * Validate voucher code for redemption
     *
     * @param string $voucher_code Voucher code to validate
     * @param int|null $product_id Optional product ID for compatibility check
     * @return array|false Voucher data if valid, false if invalid
     */
    public static function validateVoucher(string $voucher_code, ?int $product_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        // Get voucher from database
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE voucher_code = %s",
            $voucher_code
        ));
        
        if (!$voucher) {
            return false;
        }
        
        // Check if voucher is active
        if ($voucher->status !== 'active') {
            return false;
        }
        
        // Check expiration
        if ($voucher->expires_on && strtotime($voucher->expires_on) < time()) {
            return false;
        }
        
        // Verify HMAC signature
        if (!self::verifyVoucherSignature($voucher)) {
            return false;
        }
        
        // Check product compatibility if product_id is provided
        if ($product_id && $voucher->product_id && $voucher->product_id != $product_id) {
            return false;
        }
        
        return (array) $voucher;
    }
    
    /**
     * Verify voucher HMAC signature
     *
     * @param object $voucher Voucher object
     * @return bool True if signature is valid
     */
    private static function verifyVoucherSignature($voucher): bool {
        $secret_key = get_option('fp_esperienze_voucher_secret_key', 'default-secret-key');
        
        // Reconstruct the data that was signed
        $data_to_sign = sprintf(
            '%s|%s|%s|%s|%s',
            $voucher->voucher_code,
            $voucher->voucher_type,
            $voucher->amount,
            $voucher->product_id ?: '',
            $voucher->expires_on ?: ''
        );
        
        $expected_signature = hash_hmac('sha256', $data_to_sign, $secret_key);
        
        return hash_equals($expected_signature, $voucher->signature);
    }
    
    /**
     * Apply voucher to cart item
     *
     * @param string $voucher_code Voucher code
     * @param array $cart_item_data Cart item data
     * @return array|false Modified cart item data or false if failed
     */
    public static function applyVoucherToCart(string $voucher_code, array $cart_item_data) {
        $product_id = $cart_item_data['product_id'] ?? null;
        
        if (!$product_id) {
            return false;
        }
        
        $voucher = self::validateVoucher($voucher_code, $product_id);
        
        if (!$voucher) {
            return false;
        }
        
        // Add voucher data to cart item
        $cart_item_data['fp_voucher'] = [
            'code' => $voucher_code,
            'type' => $voucher['voucher_type'],
            'amount' => $voucher['amount'],
            'voucher_id' => $voucher['id']
        ];
        
        return $cart_item_data;
    }
    
    /**
     * Calculate discount amount for voucher
     *
     * @param array $voucher_data Voucher data
     * @param float $product_total Product total (base price, not including extras)
     * @return float Discount amount
     */
    public static function calculateDiscount(array $voucher_data, float $product_total): float {
        if ($voucher_data['type'] === 'full') {
            // Full voucher makes the product free
            return $product_total;
        }
        
        if ($voucher_data['type'] === 'value') {
            // Value voucher applies discount up to voucher amount
            return min($voucher_data['amount'], $product_total);
        }
        
        return 0;
    }
    
    /**
     * Mark voucher as redeemed
     *
     * @param string $voucher_code Voucher code
     * @param int $order_id Order ID
     * @return bool Success
     */
    public static function redeemVoucher(string $voucher_code, int $order_id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'redeemed',
                'redeemed_at' => current_time('mysql'),
                'order_id' => $order_id,
                'updated_at' => current_time('mysql')
            ],
            ['voucher_code' => $voucher_code],
            ['%s', '%s', '%d', '%s'],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Restore voucher to active status (for order cancellation/refund)
     *
     * @param string $voucher_code Voucher code
     * @return bool Success
     */
    public static function restoreVoucher(string $voucher_code): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        // Only restore if not used elsewhere
        $voucher = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE voucher_code = %s",
            $voucher_code
        ));
        
        if (!$voucher || $voucher->status !== 'redeemed') {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'active',
                'redeemed_at' => null,
                'order_id' => null,
                'updated_at' => current_time('mysql')
            ],
            ['voucher_code' => $voucher_code],
            ['%s', null, null, '%s'],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Get voucher by code
     *
     * @param string $voucher_code Voucher code
     * @return object|null Voucher object or null
     */
    public static function getVoucherByCode(string $voucher_code) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_vouchers';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE voucher_code = %s",
            $voucher_code
        ));
    }
    
    /**
     * Remove voucher from cart session
     */
    public static function removeVoucherFromSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['fp_applied_voucher']);
    }
    
    /**
     * Get applied voucher from session
     *
     * @return array|null Voucher data or null
     */
    public static function getAppliedVoucherFromSession(): ?array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return $_SESSION['fp_applied_voucher'] ?? null;
    }
    
    /**
     * Set applied voucher in session
     *
     * @param array $voucher_data Voucher data
     */
    public static function setAppliedVoucherInSession(array $voucher_data): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['fp_applied_voucher'] = $voucher_data;
    }
}