<?php
/**
 * Booking Management
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

defined('ABSPATH') || exit;

/**
 * Booking manager class for handling experience bookings
 */
class BookingManager {
    
    /**
     * Constructor - Initialize WooCommerce hooks
     */
    public function __construct() {
        // Order status change hooks
        add_action('woocommerce_order_status_processing', [$this, 'createBookingsFromOrder'], 10, 1);
        add_action('woocommerce_order_status_completed', [$this, 'createBookingsFromOrder'], 10, 1);
        
        // Refund hooks
        add_action('woocommerce_order_refunded', [$this, 'handleOrderRefund'], 10, 2);
        add_action('woocommerce_order_status_cancelled', [$this, 'cancelBookingsFromOrder'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'cancelBookingsFromOrder'], 10, 1);
    }
    
    /**
     * Create booking records when order reaches processing/completed status
     *
     * @param int $order_id Order ID
     */
    public function createBookingsFromOrder(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();
            
            if (!$product || $product->get_type() !== 'experience') {
                continue;
            }
            
            // Check if booking already exists for this order item
            if ($this->bookingExistsForOrderItem($order_id, $item_id)) {
                continue;
            }
            
            // Extract experience data from order item meta
            $booking_data = $this->extractBookingDataFromOrderItem($item, $product->get_id());
            
            if ($booking_data) {
                $this->createBooking($order_id, $item_id, $booking_data);
            }
        }
    }
    
    /**
     * Handle order refund
     *
     * @param int $order_id Order ID
     * @param int $refund_id Refund ID
     */
    public function handleOrderRefund(int $order_id, int $refund_id): void {
        $refund = wc_get_order($refund_id);
        if (!$refund) {
            return;
        }
        
        // Get refunded items
        foreach ($refund->get_items() as $item) {
            $original_item_id = $item->get_meta('_refunded_item_id');
            if ($original_item_id) {
                $this->updateBookingStatus($order_id, $original_item_id, 'refunded');
            }
        }
    }
    
    /**
     * Cancel all bookings when order is cancelled or refunded
     *
     * @param int $order_id Order ID
     */
    public function cancelBookingsFromOrder(int $order_id): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $wpdb->update(
            $table_name,
            ['status' => 'cancelled', 'updated_at' => current_time('mysql')],
            ['order_id' => $order_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Check if booking exists for order item
     *
     * @param int $order_id Order ID
     * @param int $item_id Order item ID
     * @return bool
     */
    private function bookingExistsForOrderItem(int $order_id, int $item_id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE order_id = %d AND order_item_id = %d",
                $order_id,
                $item_id
            )
        );
        
        return $count > 0;
    }
    
    /**
     * Extract booking data from order item meta
     *
     * @param \WC_Order_Item_Product $item Order item
     * @param int $product_id Product ID
     * @return array|null Booking data or null if invalid
     */
    private function extractBookingDataFromOrderItem(\WC_Order_Item_Product $item, int $product_id): ?array {
        $time_slot = $item->get_meta('Time Slot');
        $adults = $item->get_meta('Adults') ?: 0;
        $children = $item->get_meta('Children') ?: 0;
        $meeting_point_id = $item->get_meta('Meeting Point ID') ?: null;
        $language = $item->get_meta('Language') ?: '';
        
        if (empty($time_slot)) {
            return null;
        }
        
        // Parse slot start (format: YYYY-MM-DD HH:MM)
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $time_slot);
        if (!$slot_datetime) {
            return null;
        }
        
        return [
            'product_id' => $product_id,
            'booking_date' => $slot_datetime->format('Y-m-d'),
            'booking_time' => $slot_datetime->format('H:i:s'),
            'adults' => intval($adults),
            'children' => intval($children),
            'meeting_point_id' => $meeting_point_id ? intval($meeting_point_id) : null,
            'status' => 'confirmed',
            'customer_notes' => '',
            'admin_notes' => sprintf(__('Created from order #%d', 'fp-esperienze'), $item->get_order_id()),
        ];
    }
    
    /**
     * Create a booking record
     *
     * @param int $order_id Order ID
     * @param int $item_id Order item ID
     * @param array $booking_data Booking data
     * @return int|false Booking ID or false on failure
     */
    private function createBooking(int $order_id, int $item_id, array $booking_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $data = array_merge($booking_data, [
            'order_id' => $order_id,
            'order_item_id' => $item_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log("Failed to create booking for order {$order_id}, item {$item_id}: " . $wpdb->last_error);
            return false;
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Trigger cache invalidation
        do_action('fp_esperienze_booking_created', $booking_data['product_id'], $booking_data['booking_date']);
        
        // Log success
        error_log("Created booking #{$booking_id} for order #{$order_id}, item #{$item_id}");
        
        return $booking_id;
    }
    
    /**
     * Update booking status
     *
     * @param int $order_id Order ID
     * @param int $item_id Order item ID
     * @param string $status New status
     * @return bool Success
     */
    private function updateBookingStatus(int $order_id, int $item_id, string $status): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        // Get booking data before update for cache invalidation
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT product_id, booking_date FROM $table_name WHERE order_id = %d AND order_item_id = %d",
            $order_id,
            $item_id
        ));
        
        $result = $wpdb->update(
            $table_name,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['order_id' => $order_id, 'order_item_id' => $item_id],
            ['%s', '%s'],
            ['%d', '%d']
        );
        
        if ($result !== false && $booking) {
            // Trigger cache invalidation based on status
            if ($status === 'cancelled') {
                do_action('fp_esperienze_booking_cancelled', $booking->product_id, $booking->booking_date);
            } elseif ($status === 'refunded') {
                do_action('fp_esperienze_booking_refunded', $booking->product_id, $booking->booking_date);
            }
        }
        
        return $result !== false;
    }
    
    /**
     * Get all bookings with optional filters
     *
     * @param array $filters Filters array
     * @return array Bookings
     */
    public static function getBookings(array $filters = []): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $where_clauses = ['1=1'];
        $where_values = [];
        
        // Apply filters
        if (!empty($filters['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $filters['status'];
        }
        
        if (!empty($filters['product_id'])) {
            $where_clauses[] = 'product_id = %d';
            $where_values[] = $filters['product_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = 'booking_date >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = 'booking_date <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        // Build query
        $where_sql = implode(' AND ', $where_clauses);
        $order_by = 'ORDER BY booking_date DESC, booking_time DESC';
        $limit = '';
        
        if (!empty($filters['limit'])) {
            $limit = $wpdb->prepare('LIMIT %d', $filters['limit']);
        }
        
        $sql = "SELECT * FROM {$table_name} WHERE {$where_sql} {$order_by} {$limit}";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get booking by ID
     *
     * @param int $booking_id Booking ID
     * @return object|null Booking or null
     */
    public static function getBooking(int $booking_id): ?object {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $booking_id)
        );
    }
}