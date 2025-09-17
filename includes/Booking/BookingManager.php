<?php
/**
 * Booking Management
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Data\Availability;

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

        global $wpdb;
        $table_name       = $wpdb->prefix . 'fp_bookings';
        $existing_item_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT order_item_id FROM {$table_name} WHERE order_id = %d",
                $order_id
            )
        );

        foreach ($order->get_items() as $item_id => $item) {
            /** @var \WC_Order_Item_Product $item */
            $product = $item->get_product();

            if (!$product || $product->get_type() !== 'experience') {
                continue;
            }
            
            // Check if booking already exists for this order item
            if (in_array($item_id, $existing_item_ids, true)) {
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
     * Extract booking data from order item meta
     *
     * @param \WC_Order_Item_Product $item Order item
     * @param int $product_id Product ID
     * @return array|null Booking data or null if invalid
     */
    private function extractBookingDataFromOrderItem(\WC_Order_Item_Product $item, int $product_id): ?array {
        $time_slot = $this->getOrderItemBookingMeta(
            $item,
            '_fp_slot_start',
            $this->getLegacyMetaLabels('Time Slot')
        );

        if (empty($time_slot)) {
            return null;
        }

        // Parse slot start (format: YYYY-MM-DD HH:MM)
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', (string) $time_slot);
        if (!$slot_datetime) {
            return null;
        }

        $adults_meta = $this->getOrderItemBookingMeta(
            $item,
            '_fp_qty_adult',
            $this->getLegacyMetaLabels('Adults')
        );
        $children_meta = $this->getOrderItemBookingMeta(
            $item,
            '_fp_qty_child',
            $this->getLegacyMetaLabels('Children')
        );
        $meeting_point_meta = $this->getOrderItemBookingMeta(
            $item,
            '_fp_meeting_point_id',
            $this->getLegacyMetaLabels('Meeting Point ID')
        );
        $language_meta = $this->getOrderItemBookingMeta(
            $item,
            '_fp_lang',
            $this->getLegacyMetaLabels('Language')
        );

        $adults = is_numeric($adults_meta) ? max(0, (int) $adults_meta) : 0;
        $children = is_numeric($children_meta) ? max(0, (int) $children_meta) : 0;
        $meeting_point_id = is_numeric($meeting_point_meta) ? (int) $meeting_point_meta : null;
        if ($meeting_point_id !== null && $meeting_point_id <= 0) {
            $meeting_point_id = null;
        }

        $language = $language_meta !== null ? trim((string) $language_meta) : '';

        return [
            'product_id' => $product_id,
            'booking_date' => $slot_datetime->format('Y-m-d'),
            'booking_time' => $slot_datetime->format('H:i:s'),
            'adults' => $adults,
            'children' => $children,
            'meeting_point_id' => $meeting_point_id,
            'status' => 'confirmed',
            'customer_notes' => '',
            'admin_notes' => sprintf(__('Created from order #%d', 'fp-esperienze'), $item->get_order_id()),
        ];
    }

    /**
     * Get booking-related meta value from an order item with backward compatibility.
     *
     * @param \WC_Order_Item_Product $item Order item.
     * @param string $machine_key Machine-readable meta key.
     * @param array $legacy_labels Legacy translated labels to try.
     * @return mixed|null Meta value or null if not found.
     */
    private function getOrderItemBookingMeta(\WC_Order_Item_Product $item, string $machine_key, array $legacy_labels) {
        $value = $item->get_meta($machine_key, true);
        if ($value !== '' && $value !== null) {
            return $value;
        }

        foreach ($legacy_labels as $label) {
            if ($label === '') {
                continue;
            }

            $legacy_value = $item->get_meta($label, true);
            if ($legacy_value !== '' && $legacy_value !== null) {
                return $legacy_value;
            }
        }

        return null;
    }

    /**
     * Build a list of legacy meta labels for backward compatibility lookups.
     *
     * @param string $label Base label string.
     * @return array Legacy labels to check.
     */
    private function getLegacyMetaLabels(string $label): array {
        $translated = __($label, 'fp-esperienze');
        $labels = [$translated];

        if ($translated !== $label) {
            $labels[] = $label;
        }

        return array_values(array_unique(array_filter($labels, static function ($value) {
            return $value !== '';
        })));
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
        // Get order to find session ID for hold conversion
        $order = wc_get_order($order_id);
        $session_id = '';
        
        if ($order) {
            // Try to get session ID from order meta or customer ID
            $session_id = $order->get_meta('_fp_session_id') ?: $order->get_customer_id();
        }
        
        // Prepare complete booking data for database
        $complete_booking_data = array_merge($booking_data, [
            'order_id' => $order_id,
            'order_item_id' => $item_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);
        
        // Create slot_start in the format expected by HoldManager
        $slot_start = $booking_data['booking_date'] . ' ' . substr($booking_data['booking_time'], 0, 5);
        
        // Try to convert hold to booking if holds are enabled
        if (HoldManager::isEnabled() && !empty($session_id)) {
            $conversion_result = HoldManager::convertHoldToBooking(
                $booking_data['product_id'],
                $slot_start,
                $session_id,
                $complete_booking_data
            );
            
            if ($conversion_result['success']) {
                // Trigger cache invalidation
                do_action('fp_esperienze_booking_created', $booking_data['product_id'], $booking_data['booking_date']);
                
                // Log success
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Created booking #{$conversion_result['booking_id']} from hold for order #{$order_id}, item #{$item_id}");
                }
                
                return $conversion_result['booking_id'];
            } else {
                // Log hold conversion failure and fall through to direct creation
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Hold conversion failed for order {$order_id}, item {$item_id}: " . $conversion_result['message']);
                }
            }
        }
        
        // Fallback: Direct booking creation (when holds disabled or conversion failed)
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $result = $wpdb->insert($table_name, $complete_booking_data);
        
        if ($result === false) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Failed to create booking for order {$order_id}, item {$item_id}: " . $wpdb->last_error);
            }
            return false;
        }
        
        $booking_id = $wpdb->insert_id;
        
        // Trigger cache invalidation
        do_action('fp_esperienze_booking_created', $booking_data['product_id'], $booking_data['booking_date']);
        
        // Log success
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Created booking #{$booking_id} for order #{$order_id}, item #{$item_id}");
        }
        
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
    public function updateBookingStatus(int $order_id, int $item_id, string $status): bool {
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
    public static function getBookings(array $filters = [], int $limit = 0, int $offset = 0): array {
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

        $sql = "SELECT * FROM {$table_name} WHERE {$where_sql} {$order_by}";

        if ($limit > 0) {
            $sql .= ' LIMIT %d OFFSET %d';
            $where_values[] = $limit;
            $where_values[] = $offset;
        }

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return $wpdb->get_results($sql);
    }

    /**
     * Count bookings with optional filters
     *
     * @param array $filters Filters array
     * @return int Total number of bookings
     */
    public static function countBookings(array $filters = []): int {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_bookings';

        $where_clauses = ['1=1'];
        $where_values = [];

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

        $where_sql = implode(' AND ', $where_clauses);
        $sql = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";

        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Get bookings by date range for calendar view
     *
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date End date (Y-m-d format)
     * @return array Bookings within date range
     */
    public static function getBookingsByDateRange(string $start_date, string $end_date): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE booking_date >= %s AND booking_date <= %s 
             AND status != 'cancelled'
             ORDER BY booking_date ASC, booking_time ASC",
            $start_date,
            $end_date
        );
        
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
     * @param string $new_date New date (Y-m-d format)
     * @param string $new_time New time (H:i:s format)
     * @param string $admin_notes Optional admin notes
     * @return array Result with success/error info
     */
    public static function rescheduleBooking(int $booking_id, string $new_date, string $new_time, string $admin_notes = ''): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_bookings';
        
        // Get current booking
        $booking = self::getBooking($booking_id);
        if (!$booking) {
            return [
                'success' => false,
                'message' => __('Booking not found.', 'fp-esperienze')
            ];
        }
        
        // Validate the booking can be rescheduled
        if ($booking->status !== 'confirmed') {
            return [
                'success' => false,
                'message' => __('Only confirmed bookings can be rescheduled.', 'fp-esperienze')
            ];
        }
        
        // Check cutoff time for new slot
        $cutoff_check = self::validateCutoffTime($booking->product_id, $new_date, $new_time);
        if (!$cutoff_check['valid']) {
            return [
                'success' => false,
                'message' => $cutoff_check['message']
            ];
        }
        
        // Check capacity for new slot
        $capacity_check = self::validateCapacity($booking->product_id, $new_date, $new_time, $booking->adults + $booking->children);
        if (!$capacity_check['valid']) {
            return [
                'success' => false,
                'message' => $capacity_check['message']
            ];
        }
        
        // Store old date/time for cache invalidation
        $old_date = $booking->booking_date;
        $old_time = $booking->booking_time;
        
        // Update booking
        $result = $wpdb->update(
            $table_name,
            [
                'booking_date' => $new_date,
                'booking_time' => $new_time,
                'admin_notes' => $admin_notes,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $booking_id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        if ($result === false) {
            return [
                'success' => false,
                'message' => __('Failed to update booking.', 'fp-esperienze')
            ];
        }
        
        // Trigger cache invalidation for both old and new slots
        do_action('fp_esperienze_booking_rescheduled', $booking->product_id, $old_date, $new_date);
        
        // Send email notification
        self::sendRescheduleNotification($booking_id, $old_date, $old_time, $new_date, $new_time);
        
        return [
            'success' => true,
            'message' => __('Booking rescheduled successfully.', 'fp-esperienze')
        ];
    }

    /**
     * Validate cutoff time for a slot
     *
     * @param int $product_id Product ID
     * @param string $date Date (Y-m-d format)
     * @param string $time Time (H:i:s format)
     * @return array Validation result
     */
    private static function validateCutoffTime(int $product_id, string $date, string $time): array {
        $cutoff_minutes = get_post_meta($product_id, '_fp_exp_cutoff_minutes', true) ?: 120;
        
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $time);
        if (!$slot_datetime) {
            return [
                'valid' => false,
                'message' => __('Invalid date/time format.', 'fp-esperienze')
            ];
        }
        
        $cutoff_time = new \DateTime();
        $cutoff_time->add(new \DateInterval('PT' . $cutoff_minutes . 'M'));
        
        if ($slot_datetime <= $cutoff_time) {
            return [
                'valid' => false,
                'message' => sprintf(
                    __('This time slot is too close to departure. Please book at least %d minutes in advance.', 'fp-esperienze'),
                    $cutoff_minutes
                )
            ];
        }
        
        return ['valid' => true];
    }

    /**
     * Validate capacity for a slot
     *
     * @param int $product_id Product ID
     * @param string $date Date (Y-m-d format)
     * @param string $time Time (H:i:s format)
     * @param int $participants Number of participants needed
     * @return array Validation result
     */
    private static function validateCapacity(int $product_id, string $date, string $time, int $participants): array {
        // Get slots for the date to check capacity
        $slots = Availability::getSlotsForDate($product_id, $date);
        
        // Find the specific slot
        $target_slot = null;
        foreach ($slots as $slot) {
            if ($slot['start_time'] === substr($time, 0, 5)) { // Compare H:i format
                $target_slot = $slot;
                break;
            }
        }
        
        if (!$target_slot) {
            return [
                'valid' => false,
                'message' => __('Time slot not available.', 'fp-esperienze')
            ];
        }
        
        if ($target_slot['available'] < $participants) {
            return [
                'valid' => false,
                'message' => sprintf(
                    __('Not enough capacity. Only %d spots available.', 'fp-esperienze'),
                    $target_slot['available']
                )
            ];
        }
        
        return ['valid' => true];
    }

    /**
     * Send reschedule notification email
     *
     * @param int $booking_id Booking ID
     * @param string $old_date Old date
     * @param string $old_time Old time
     * @param string $new_date New date
     * @param string $new_time New time
     */
    private static function sendRescheduleNotification(int $booking_id, string $old_date, string $old_time, string $new_date, string $new_time): void {
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
        
        $to = $order->get_billing_email();
        $subject = sprintf(__('Booking Rescheduled - %s', 'fp-esperienze'), $product->get_name());
        
        $message = sprintf(
            __('Your booking has been rescheduled:\n\nProduct: %s\nOriginal Date: %s at %s\nNew Date: %s at %s\n\nOrder: #%d\nBooking ID: %d', 'fp-esperienze'),
            $product->get_name(),
            date_i18n(get_option('date_format'), strtotime($old_date)),
            date_i18n(get_option('time_format'), strtotime($old_time)),
            date_i18n(get_option('date_format'), strtotime($new_date)),
            date_i18n(get_option('time_format'), strtotime($new_time)),
            $booking->order_id,
            $booking_id
        );
        
        $mail_sent = wp_mail($to, $subject, $message);
        
        if (!$mail_sent && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Failed to send booking reschedule notification to: {$to}");
        }
    }

    /**
     * Check if booking can be cancelled based on cancellation rules
     *
     * @param int $booking_id Booking ID
     * @return array Result with cancellation info
     */
    public static function checkCancellationRules(int $booking_id): array {
        $booking = self::getBooking($booking_id);
        if (!$booking) {
            return [
                'can_cancel' => false,
                'message' => __('Booking not found.', 'fp-esperienze')
            ];
        }
        
        if ($booking->status !== 'confirmed') {
            return [
                'can_cancel' => false,
                'message' => __('Only confirmed bookings can be cancelled.', 'fp-esperienze')
            ];
        }
        
        // Get cancellation rules
        $free_cancel_until = get_post_meta($booking->product_id, '_fp_exp_free_cancel_until_minutes', true) ?: 1440;
        $cancel_fee_percent = get_post_meta($booking->product_id, '_fp_exp_cancel_fee_percent', true) ?: 0;
        
        // Calculate booking datetime
        $booking_datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $booking->booking_date . ' ' . $booking->booking_time);
        if (!$booking_datetime) {
            return [
                'can_cancel' => false,
                'message' => __('Invalid booking date/time.', 'fp-esperienze')
            ];
        }
        
        // Calculate free cancellation deadline
        $free_cancel_deadline = clone $booking_datetime;
        $free_cancel_deadline->sub(new \DateInterval('PT' . $free_cancel_until . 'M'));
        
        $now = new \DateTime();
        $is_free_cancellation = $now <= $free_cancel_deadline;
        
        return [
            'can_cancel' => true,
            'is_free' => $is_free_cancellation,
            'fee_percent' => $cancel_fee_percent,
            'deadline' => $free_cancel_deadline,
            'message' => $is_free_cancellation 
                ? __('Free cancellation available.', 'fp-esperienze')
                : sprintf(__('Cancellation fee: %s%%', 'fp-esperienze'), $cancel_fee_percent)
        ];
    }
}