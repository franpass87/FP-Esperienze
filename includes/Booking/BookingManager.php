<?php
/**
 * Booking Management
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

defined('ABSPATH') || exit;

/**
 * Booking manager class
 * 
 * Handles booking creation, status updates, and capacity management
 * 
 * @since 1.0.0
 */
class BookingManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize hooks
     */
    private function init(): void {
        // Hook into order status changes
        add_action('woocommerce_order_status_processing', [$this, 'createBookingsOnOrderProcessing']);
        add_action('woocommerce_order_status_completed', [$this, 'createBookingsOnOrderCompleted']);
        
        // Hook into refunds and cancellations
        add_action('woocommerce_order_status_refunded', [$this, 'handleOrderRefund']);
        add_action('woocommerce_order_status_cancelled', [$this, 'handleOrderCancellation']);
        add_action('woocommerce_order_fully_refunded', [$this, 'handleOrderFullRefund']);
    }
    
    /**
     * Create bookings when order status changes to processing
     * 
     * @param int $order_id Order ID
     */
    public function createBookingsOnOrderProcessing(int $order_id): void {
        $this->createBookingsForOrder($order_id, 'pending');
    }
    
    /**
     * Create bookings when order status changes to completed
     * 
     * @param int $order_id Order ID
     */
    public function createBookingsOnOrderCompleted(int $order_id): void {
        $this->createBookingsForOrder($order_id, 'confirmed');
    }
    
    /**
     * Create booking records for experience items in an order
     * 
     * @param int $order_id Order ID
     * @param string $status Booking status
     */
    private function createBookingsForOrder(int $order_id, string $status = 'confirmed'): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'fp_bookings';
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            
            // Only process experience products
            if (!$product || $product->get_type() !== 'experience') {
                continue;
            }
            
            // Check if booking already exists
            $existing_booking = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_bookings WHERE order_id = %d AND order_item_id = %d",
                $order_id,
                $item_id
            ));
            
            if ($existing_booking) {
                continue; // Booking already exists
            }
            
            // Get booking details from item meta
            $booking_date = $item->get_meta('_booking_date');
            $booking_time = $item->get_meta('_booking_time');
            $adults = (int) $item->get_meta('_booking_adults') ?: 1;
            $children = (int) $item->get_meta('_booking_children') ?: 0;
            $meeting_point_id = $item->get_meta('_booking_meeting_point') ?: $product->get_default_meeting_point();
            $customer_notes = $item->get_meta('_booking_notes');
            
            // Use defaults if booking data is missing
            if (!$booking_date) {
                $booking_date = date('Y-m-d', strtotime('+1 day'));
            }
            if (!$booking_time) {
                $booking_time = '10:00:00';
            }
            
            // Create booking record
            $result = $wpdb->insert(
                $table_bookings,
                [
                    'order_id' => $order_id,
                    'order_item_id' => $item_id,
                    'product_id' => $product->get_id(),
                    'booking_date' => $booking_date,
                    'booking_time' => $booking_time,
                    'adults' => $adults,
                    'children' => $children,
                    'meeting_point_id' => $meeting_point_id,
                    'status' => $status,
                    'customer_notes' => $customer_notes,
                    'created_at' => current_time('mysql'),
                ],
                [
                    '%d', '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s'
                ]
            );
            
            if ($result) {
                // Fire action for booking created
                do_action('fp_esperienze_booking_created', $wpdb->insert_id, $order_id, $item_id);
            }
        }
    }
    
    /**
     * Handle order refund
     * 
     * @param int $order_id Order ID
     */
    public function handleOrderRefund(int $order_id): void {
        $this->updateBookingStatus($order_id, 'refunded');
    }
    
    /**
     * Handle order cancellation
     * 
     * @param int $order_id Order ID
     */
    public function handleOrderCancellation(int $order_id): void {
        $this->updateBookingStatus($order_id, 'cancelled');
    }
    
    /**
     * Handle full order refund
     * 
     * @param int $order_id Order ID
     */
    public function handleOrderFullRefund(int $order_id): void {
        $this->updateBookingStatus($order_id, 'refunded');
    }
    
    /**
     * Update booking status and restore capacity
     * 
     * @param int $order_id Order ID
     * @param string $new_status New booking status
     */
    private function updateBookingStatus(int $order_id, string $new_status): void {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'fp_bookings';
        
        // Get all bookings for this order
        $bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_bookings WHERE order_id = %d AND status NOT IN ('cancelled', 'refunded')",
            $order_id
        ));
        
        foreach ($bookings as $booking) {
            // Update booking status
            $wpdb->update(
                $table_bookings,
                ['status' => $new_status, 'updated_at' => current_time('mysql')],
                ['id' => $booking->id],
                ['%s', '%s'],
                ['%d']
            );
            
            // Fire action for booking status updated
            do_action('fp_esperienze_booking_status_updated', $booking->id, $new_status, $booking->status);
        }
    }
    
    /**
     * Get bookings by criteria
     * 
     * @param array $args Query arguments
     * @return array Booking records
     */
    public function getBookings(array $args = []): array {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'fp_bookings';
        
        $defaults = [
            'status' => '',
            'product_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['product_id'])) {
            $where_clauses[] = 'product_id = %d';
            $where_values[] = $args['product_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'booking_date >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'booking_date <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $limit = absint($args['limit']);
        $offset = absint($args['offset']);
        
        $query = "SELECT * FROM $table_bookings WHERE $where_clause ORDER BY $orderby LIMIT $offset, $limit";
        
        if (!empty($where_values)) {
            return $wpdb->get_results($wpdb->prepare($query, $where_values));
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get booking count by criteria
     * 
     * @param array $args Query arguments
     * @return int Booking count
     */
    public function getBookingCount(array $args = []): int {
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'fp_bookings';
        
        $where_clauses = ['1=1'];
        $where_values = [];
        
        if (!empty($args['status'])) {
            $where_clauses[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['product_id'])) {
            $where_clauses[] = 'product_id = %d';
            $where_values[] = $args['product_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'booking_date >= %s';
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'booking_date <= %s';
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $query = "SELECT COUNT(*) FROM $table_bookings WHERE $where_clause";
        
        if (!empty($where_values)) {
            return (int) $wpdb->get_var($wpdb->prepare($query, $where_values));
        }
        
        return (int) $wpdb->get_var($query);
    }
}