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

    /**
     * Reschedule a booking to a new date/time slot
     *
     * @param int $booking_id Booking ID
     * @param string $new_date New date (YYYY-MM-DD)
     * @param string $new_time New time (HH:MM)
     * @return array Result with success status and message
     */
    public static function rescheduleBooking(int $booking_id, string $new_date, string $new_time): array {
        global $wpdb;
        
        // Get current booking
        $booking = self::getBooking($booking_id);
        if (!$booking) {
            return [
                'success' => false,
                'message' => __('Booking not found.', 'fp-esperienze')
            ];
        }

        // Get product
        $product = wc_get_product($booking->product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return [
                'success' => false,
                'message' => __('Invalid experience product.', 'fp-esperienze')
            ];
        }

        // Validate new slot format
        $new_datetime = \DateTime::createFromFormat('Y-m-d H:i', $new_date . ' ' . $new_time);
        if (!$new_datetime) {
            return [
                'success' => false,
                'message' => __('Invalid date/time format.', 'fp-esperienze')
            ];
        }

        // Check cutoff time
        $cutoff_minutes = $product->get_cutoff_minutes();
        $cutoff_time = new \DateTime();
        $cutoff_time->add(new \DateInterval('PT' . $cutoff_minutes . 'M'));

        if ($new_datetime <= $cutoff_time) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('This time slot is too close to departure. Please select at least %d minutes in advance.', 'fp-esperienze'),
                    $cutoff_minutes
                )
            ];
        }

        // Check slot availability
        $availability = \FP\Esperienze\Data\Availability::getAvailableSlots($booking->product_id, $new_date);
        $slot_available = false;
        $required_spots = $booking->adults + $booking->children;

        foreach ($availability as $slot) {
            if ($slot['start_time'] === $new_time && $slot['available'] >= $required_spots) {
                $slot_available = true;
                break;
            }
        }

        if (!$slot_available) {
            return [
                'success' => false,
                'message' => __('Selected time slot is not available or does not have enough capacity.', 'fp-esperienze')
            ];
        }

        // Update booking
        $table_name = $wpdb->prefix . 'fp_bookings';
        $result = $wpdb->update(
            $table_name,
            [
                'booking_date' => $new_date,
                'booking_time' => $new_time,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $booking_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to update booking.', 'fp-esperienze')
            ];
        }

        // Trigger cache invalidation for both old and new dates
        do_action('fp_esperienze_booking_rescheduled', $booking->product_id, $booking->booking_date, $new_date);

        // Send confirmation email
        self::sendRescheduleConfirmationEmail($booking_id, $booking->booking_date, $new_date, $new_time);

        return [
            'success' => true,
            'message' => __('Booking rescheduled successfully.', 'fp-esperienze')
        ];
    }

    /**
     * Cancel a booking with refund calculation based on cancellation policy
     *
     * @param int $booking_id Booking ID
     * @param string $reason Cancellation reason
     * @return array Result with success status, message, and refund info
     */
    public static function cancelBooking(int $booking_id, string $reason = ''): array {
        global $wpdb;
        
        // Get current booking
        $booking = self::getBooking($booking_id);
        if (!$booking) {
            return [
                'success' => false,
                'message' => __('Booking not found.', 'fp-esperienze')
            ];
        }

        if ($booking->status === 'cancelled') {
            return [
                'success' => false,
                'message' => __('Booking is already cancelled.', 'fp-esperienze')
            ];
        }

        // Get product and cancellation policy
        $product = wc_get_product($booking->product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return [
                'success' => false,
                'message' => __('Invalid experience product.', 'fp-esperienze')
            ];
        }

        // Calculate refund based on cancellation policy
        $refund_info = self::calculateRefund($booking, $product);

        // Update booking status
        $table_name = $wpdb->prefix . 'fp_bookings';
        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'cancelled',
                'admin_notes' => $reason,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $booking_id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to cancel booking.', 'fp-esperienze')
            ];
        }

        // Trigger cache invalidation
        do_action('fp_esperienze_booking_cancelled', $booking->product_id, $booking->booking_date);

        return [
            'success' => true,
            'message' => __('Booking cancelled successfully.', 'fp-esperienze'),
            'refund_info' => $refund_info
        ];
    }

    /**
     * Calculate refund amount based on cancellation policy
     *
     * @param object $booking Booking object
     * @param WC_Product_Experience $product Product object
     * @return array Refund calculation details
     */
    public static function calculateRefund($booking, $product): array {
        // Get order to calculate refund amount
        $order = wc_get_order($booking->order_id);
        if (!$order) {
            return [
                'refund_amount' => 0,
                'refund_percentage' => 0,
                'reason' => __('Order not found', 'fp-esperienze')
            ];
        }

        // Get order item
        $order_item = $order->get_item($booking->order_item_id);
        if (!$order_item) {
            return [
                'refund_amount' => 0,
                'refund_percentage' => 0,
                'reason' => __('Order item not found', 'fp-esperienze')
            ];
        }

        $item_total = $order_item->get_total();
        
        // Calculate time until experience
        $booking_datetime = new \DateTime($booking->booking_date . ' ' . $booking->booking_time);
        $now = new \DateTime();
        $minutes_until_experience = ($booking_datetime->getTimestamp() - $now->getTimestamp()) / 60;

        // Check if experience has already passed (no-show)
        if ($minutes_until_experience < 0) {
            $no_show_policy = $product->get_no_show_policy();
            switch ($no_show_policy) {
                case 'full_refund':
                    return [
                        'refund_amount' => $item_total,
                        'refund_percentage' => 100,
                        'reason' => __('Full refund - No-show policy', 'fp-esperienze')
                    ];
                case 'partial_refund':
                    $refund_amount = $item_total * 0.5; // 50% refund for no-show
                    return [
                        'refund_amount' => $refund_amount,
                        'refund_percentage' => 50,
                        'reason' => __('Partial refund - No-show policy', 'fp-esperienze')
                    ];
                default: // no_refund
                    return [
                        'refund_amount' => 0,
                        'refund_percentage' => 0,
                        'reason' => __('No refund - No-show policy', 'fp-esperienze')
                    ];
            }
        }

        // Check free cancellation period
        $free_cancel_minutes = $product->get_free_cancel_until_minutes();
        if ($minutes_until_experience >= $free_cancel_minutes) {
            return [
                'refund_amount' => $item_total,
                'refund_percentage' => 100,
                'reason' => __('Full refund - Free cancellation period', 'fp-esperienze')
            ];
        }

        // Apply cancellation fee
        $fee_percentage = $product->get_cancellation_fee_percentage();
        $refund_percentage = max(0, 100 - $fee_percentage);
        $refund_amount = $item_total * ($refund_percentage / 100);

        return [
            'refund_amount' => $refund_amount,
            'refund_percentage' => $refund_percentage,
            'reason' => sprintf(
                __('Refund with %s%% cancellation fee', 'fp-esperienze'),
                $fee_percentage
            )
        ];
    }

    /**
     * Send reschedule confirmation email
     *
     * @param int $booking_id Booking ID
     * @param string $old_date Old date
     * @param string $new_date New date  
     * @param string $new_time New time
     */
    private static function sendRescheduleConfirmationEmail(int $booking_id, string $old_date, string $new_date, string $new_time): void {
        $booking = self::getBooking($booking_id);
        if (!$booking) {
            return;
        }

        $order = wc_get_order($booking->order_id);
        if (!$order) {
            return;
        }

        $product = wc_get_product($booking->product_id);
        if (!$product) {
            return;
        }

        // Prepare email data
        $to = $order->get_billing_email();
        $subject = sprintf(
            __('Your %s booking has been rescheduled', 'fp-esperienze'),
            $product->get_name()
        );

        $message = sprintf(
            __('Your booking has been successfully rescheduled from %s to %s at %s.', 'fp-esperienze'),
            date_i18n(get_option('date_format'), strtotime($old_date)),
            date_i18n(get_option('date_format'), strtotime($new_date)),
            date_i18n(get_option('time_format'), strtotime($new_time))
        );

        // Send email
        wp_mail($to, $subject, $message);
        
        // Trigger action for custom email handling
        do_action('fp_esperienze_reschedule_email_sent', $booking_id, $to, $subject, $message);
    }
}