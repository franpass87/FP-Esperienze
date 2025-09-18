<?php
/**
 * Hold Manager for Optimistic Locking
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use FP\Esperienze\Core\CacheManager;
use FP\Esperienze\Data\Availability;

defined('ABSPATH') || exit;

/**
 * Hold manager class for handling capacity holds and optimistic locking
 */
class HoldManager {
    
    /**
     * Hold duration in minutes
     */
    private static function getHoldDuration(): int {
        return (int) get_option('fp_esperienze_hold_duration_minutes', 15);
    }
    
    /**
     * Check if holds system is enabled
     */
    public static function isEnabled(): bool {
        return (bool) get_option('fp_esperienze_enable_holds', 1);
    }
    
    /**
     * Create a hold for capacity
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param int $qty Number of spots to hold
     * @param string|null $session_id Session ID
     * @return array Result with success status and hold data
     */
    public static function createHold(int $product_id, string $slot_start, int $qty, ?string $session_id = null): array {
        if (!self::isEnabled()) {
            return ['success' => false, 'message' => __('Holds system disabled', 'fp-esperienze')];
        }
        
        global $wpdb;
        
        // Use current session ID if not provided
        if (!$session_id) {
            $session_id = WC()->session->get_customer_id();
        }
        
        if (empty($session_id)) {
            return ['success' => false, 'message' => __('Invalid session', 'fp-esperienze')];
        }
        
        // Convert slot_start to datetime format for database
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return ['success' => false, 'message' => __('Invalid slot format', 'fp-esperienze')];
        }
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        
        // Remove any existing holds for this session/product/slot
        self::releaseHold($product_id, $slot_start, $session_id);
        
        // Calculate expiry time
        $expires_at = new \DateTime();
        $expires_at->add(new \DateInterval('PT' . self::getHoldDuration() . 'M'));
        
        // Insert new hold
        $result = $wpdb->insert(
            $table_name,
            [
                'session_id' => $session_id,
                'product_id' => $product_id,
                'slot_start' => $slot_datetime->format('Y-m-d H:i:s'),
                'qty' => $qty,
                'expires_at' => $expires_at->format('Y-m-d H:i:s'),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to create hold', 'fp-esperienze')
            ];
        }

        CacheManager::invalidateAvailabilityCache($product_id, $slot_datetime->format('Y-m-d'));

        return [
            'success' => true,
            'hold_id' => $wpdb->insert_id,
            'expires_at' => $expires_at,
            'message' => sprintf(
                __('Spots reserved for %d minutes', 'fp-esperienze'),
                self::getHoldDuration()
            )
        ];
    }
    
    /**
     * Get holds for a specific slot
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param bool $exclude_expired Whether to exclude expired holds
     * @return array Holds data
     */
    public static function getHoldsForSlot(int $product_id, string $slot_start, bool $exclude_expired = true): array {
        global $wpdb;
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return [];
        }
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        $where_clause = "WHERE product_id = %d AND slot_start = %s";
        $params = [$product_id, $slot_datetime->format('Y-m-d H:i:s')];
        
        if ($exclude_expired) {
            $where_clause .= " AND expires_at > %s";
            $params[] = current_time('mysql');
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name $where_clause ORDER BY created_at ASC",
            ...$params
        ));
        
        return $results ?: [];
    }
    
    /**
     * Get total held quantity for a slot
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param string|null $exclude_session_id Session ID to exclude from count
     * @return int Total held quantity
     */
    public static function getHeldQuantity(int $product_id, string $slot_start, ?string $exclude_session_id = null): int {
        global $wpdb;
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return 0;
        }
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        $where_clause = "WHERE product_id = %d AND slot_start = %s AND expires_at > %s";
        $params = [$product_id, $slot_datetime->format('Y-m-d H:i:s'), current_time('mysql')];
        
        if ($exclude_session_id) {
            $where_clause .= " AND session_id != %s";
            $params[] = $exclude_session_id;
        }
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(qty) FROM $table_name $where_clause",
            ...$params
        ));
        
        return (int) ($result ?: 0);
    }
    
    /**
     * Release a hold
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param string $session_id Session ID
     * @return bool Success
     */
    public static function releaseHold(int $product_id, string $slot_start, string $session_id): bool {
        global $wpdb;
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return false;
        }
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        
        $result = $wpdb->delete(
            $table_name,
            [
                'product_id' => $product_id,
                'slot_start' => $slot_datetime->format('Y-m-d H:i:s'),
                'session_id' => $session_id
            ],
            ['%d', '%s', '%s']
        );

        if ($result !== false) {
            CacheManager::invalidateAvailabilityCache($product_id, $slot_datetime->format('Y-m-d'));
        }

        return $result !== false;
    }
    
    /**
     * Release all holds for a session
     *
     * @param string $session_id Session ID
     * @return bool Success
     */
    public static function releaseSessionHolds(string $session_id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        
        $result = $wpdb->delete(
            $table_name,
            ['session_id' => $session_id],
            ['%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Convert hold to booking atomically
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param string $session_id Session ID
     * @param array $booking_data Booking data
     * @return array Result with success status and booking ID
     */
    public static function convertHoldToBooking(int $product_id, string $slot_start, string $session_id, array $booking_data): array {
        global $wpdb;
        
        if (!self::isEnabled()) {
            // Fallback to atomic capacity check when holds disabled
            return self::atomicCapacityCheck($product_id, $slot_start, $booking_data);
        }
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return ['success' => false, 'message' => __('Invalid slot format', 'fp-esperienze')];
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            $holds_table = $wpdb->prefix . 'fp_exp_holds';
            $bookings_table = $wpdb->prefix . 'fp_bookings';
            
            // Check if hold exists and is valid
            $hold = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $holds_table 
                 WHERE product_id = %d AND slot_start = %s AND session_id = %s AND expires_at > %s",
                $product_id,
                $slot_datetime->format('Y-m-d H:i:s'),
                $session_id,
                current_time('mysql')
            ));
            
            if (!$hold) {
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('Hold expired or not found. Please try again.', 'fp-esperienze')
                ];
            }
            
            // Check total capacity including other bookings
            $required_qty = $booking_data['adults'] + $booking_data['children'];
            if ($hold->qty < $required_qty) {
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('Hold quantity insufficient for booking.', 'fp-esperienze')
                ];
            }
            
            // Create booking
            $booking_result = $wpdb->insert(
                $bookings_table,
                $booking_data,
                ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );
            
            if ($booking_result === false) {
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('Failed to create booking.', 'fp-esperienze')
                ];
            }
            
            $booking_id = $wpdb->insert_id;
            
            // Remove the hold
            $hold_delete_result = $wpdb->delete(
                $holds_table,
                ['id' => $hold->id],
                ['%d']
            );
            
            if ($hold_delete_result === false) {
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('Failed to remove hold after booking creation.', 'fp-esperienze')
                ];
            }
            
            // Clean up other expired holds for this slot (this is not critical, so we don't rollback on failure)
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $holds_table 
                 WHERE product_id = %d AND slot_start = %s AND expires_at <= %s",
                $product_id,
                $slot_datetime->format('Y-m-d H:i:s'),
                current_time('mysql')
            ));
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'success' => true,
                'booking_id' => $booking_id,
                'message' => __('Booking created successfully.', 'fp-esperienze')
            ];
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return [
                'success' => false,
                'message' => __('Transaction failed. Please try again.', 'fp-esperienze')
            ];
        }
    }
    
    /**
     * Atomic capacity check fallback when holds are disabled
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param array $booking_data Booking data
     * @return array Result with success status
     */
    private static function atomicCapacityCheck(int $product_id, string $slot_start, array $booking_data): array {
        global $wpdb;
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return ['success' => false, 'message' => __('Invalid slot format', 'fp-esperienze')];
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get current availability
            $date = $slot_datetime->format('Y-m-d');
            $time = $slot_datetime->format('H:i');
            
            $slots = Availability::forDay($product_id, $date);
            $available_capacity = 0;
            
            foreach ($slots as $slot) {
                if ($slot['start_time'] === $time) {
                    $available_capacity = $slot['available'];
                    break;
                }
            }
            
            $required_qty = $booking_data['adults'] + $booking_data['children'];
            
            if ($available_capacity < $required_qty) {
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => sprintf(
                        __('Not enough capacity. Only %d spots available.', 'fp-esperienze'),
                        $available_capacity
                    )
                ];
            }
            
            // Create booking
            $bookings_table = $wpdb->prefix . 'fp_bookings';
            $booking_result = $wpdb->insert(
                $bookings_table,
                $booking_data,
                ['%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );
            
            if ($booking_result === false) {
                $wpdb->query('ROLLBACK');
                return [
                    'success' => false,
                    'message' => __('Failed to create booking.', 'fp-esperienze')
                ];
            }
            
            $booking_id = $wpdb->insert_id;
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'success' => true,
                'booking_id' => $booking_id,
                'message' => __('Booking created successfully.', 'fp-esperienze')
            ];
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            return [
                'success' => false,
                'message' => __('Transaction failed. Please try again.', 'fp-esperienze')
            ];
        }
    }
    
    /**
     * Clean up expired holds
     *
     * @return int Number of holds cleaned up
     */
    public static function cleanupExpiredHolds(): int {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE expires_at <= %s",
            current_time('mysql')
        ));
        
        return (int) ($result ?: 0);
    }
    
    /**
     * Get hold information for a session and slot
     *
     * @param int $product_id Product ID
     * @param string $slot_start Slot start datetime (Y-m-d H:i format)
     * @param string $session_id Session ID
     * @return object|null Hold data or null if not found
     */
    public static function getSessionHold(int $product_id, string $slot_start, string $session_id): ?object {
        global $wpdb;
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime) {
            return null;
        }
        
        $table_name = $wpdb->prefix . 'fp_exp_holds';
        
        $hold = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE product_id = %d AND slot_start = %s AND session_id = %s AND expires_at > %s",
            $product_id,
            $slot_datetime->format('Y-m-d H:i:s'),
            $session_id,
            current_time('mysql')
        ));
        
        return $hold ?: null;
    }
}