<?php
/**
 * Booking Management
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Data\Availability;
use FP\Esperienze\Data\MeetingPointManager;
use WP_Error;

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
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            $order = null;
        }

        $order_currency = '';
        $order_item_total = 0.0;
        if ($order) {
            $order_item_total = $this->calculateOrderItemTotal($order, $item_id);
            $order_currency = (string) $order->get_currency();
        }

        $session_id = '';
        if ($order) {
            $session_id = (string) ($order->get_meta('_fp_session_id') ?: $order->get_customer_id());
        }

        $participants = $this->parseParticipants([
            'adults' => $booking_data['adults'] ?? 0,
            'children' => $booking_data['children'] ?? 0,
        ]);

        $currency_fallback = '';
        if ($order_currency === '' && function_exists('get_woocommerce_currency')) {
            $currency_fallback = (string) get_woocommerce_currency();
        }

        $complete_booking_data = array_merge($booking_data, [
            'order_id' => $order_id,
            'order_item_id' => $item_id,
            'participants' => $participants['total'],
            'customer_id' => $booking_data['customer_id'] ?? 0,
            'booking_number' => $booking_data['booking_number'] ?? self::generateBookingNumber(),
            'total_amount' => isset($booking_data['total_amount']) ? round((float) $booking_data['total_amount'], 2) : $order_item_total,
            'currency' => isset($booking_data['currency']) ? (string) $booking_data['currency'] : ($order_currency ?: $currency_fallback),
            'checked_in_at' => $booking_data['checked_in_at'] ?? null,
            'checked_in_by' => $booking_data['checked_in_by'] ?? null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        if ($order && (int) $complete_booking_data['customer_id'] <= 0) {
            $complete_booking_data['customer_id'] = $order->get_customer_id() ?: $order->get_user_id() ?: 0;
        }

        if ($complete_booking_data['currency'] === '' && function_exists('get_woocommerce_currency')) {
            $complete_booking_data['currency'] = (string) get_woocommerce_currency();
        }

        $complete_booking_data['customer_id'] = max(0, (int) $complete_booking_data['customer_id']);

        $result = $this->persistBooking($complete_booking_data, $order, $session_id, 'order');

        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'Failed to create booking for order %d, item %d: %s',
                    $order_id,
                    $item_id,
                    $result->get_error_message()
                ));
            }

            return false;
        }

        return $result;
    }

    /**
     * Create a booking for a customer without a WooCommerce order.
     *
     * @param int   $customer_id  Customer ID.
     * @param array $booking_data Booking data.
     *
     * @return int|WP_Error
     */
    public function createCustomerBooking(int $customer_id, array $booking_data) {
        $customer_id = absint($customer_id);

        if ($customer_id <= 0) {
            return new WP_Error('invalid_customer', __('A valid customer is required.', 'fp-esperienze'), ['status' => 400]);
        }

        $product_id = isset($booking_data['product_id']) ? (int) $booking_data['product_id'] : 0;
        $product = $product_id > 0 ? wc_get_product($product_id) : null;

        if (!$product || $product->get_type() !== 'experience') {
            return new WP_Error('invalid_product', __('Invalid experience selected.', 'fp-esperienze'), ['status' => 400]);
        }

        $booking_date = isset($booking_data['booking_date']) ? sanitize_text_field((string) $booking_data['booking_date']) : '';
        $date_obj = \DateTime::createFromFormat('Y-m-d', $booking_date);
        if ($booking_date === '' || !$date_obj || $date_obj->format('Y-m-d') !== $booking_date) {
            return new WP_Error('invalid_date', __('Invalid booking date.', 'fp-esperienze'), ['status' => 400]);
        }

        $normalized_time = $this->normalizeBookingTime($booking_data['booking_time'] ?? '');
        if ($normalized_time === null) {
            return new WP_Error('invalid_time', __('Invalid booking time.', 'fp-esperienze'), ['status' => 400]);
        }

        $participants = $this->parseParticipants($booking_data['participants'] ?? null);
        if ($participants['total'] <= 0) {
            return new WP_Error('invalid_participants', __('At least one participant is required.', 'fp-esperienze'), ['status' => 400]);
        }

        $cutoff_check = self::validateCutoffTime($product_id, $booking_date, $normalized_time);
        if (!$cutoff_check['valid']) {
            return new WP_Error('booking_cutoff', $cutoff_check['message'], ['status' => 400]);
        }

        $capacity_check = self::validateCapacity($product_id, $booking_date, $normalized_time, $participants['total']);
        if (!$capacity_check['valid']) {
            return new WP_Error('booking_capacity', $capacity_check['message'], ['status' => 400]);
        }

        $slot = $this->getSlotForBooking($product_id, $booking_date, $normalized_time);
        if (!$slot) {
            return new WP_Error('slot_unavailable', __('Time slot not available.', 'fp-esperienze'), ['status' => 400]);
        }

        $requested_meeting_point = isset($booking_data['meeting_point_id']) ? (int) $booking_data['meeting_point_id'] : 0;
        $meeting_point_id = null;

        if ($requested_meeting_point > 0) {
            if (!MeetingPointManager::getMeetingPoint($requested_meeting_point)) {
                return new WP_Error('invalid_meeting_point', __('Selected meeting point does not exist.', 'fp-esperienze'), ['status' => 400]);
            }

            if (!empty($slot['meeting_point_id']) && (int) $slot['meeting_point_id'] !== $requested_meeting_point) {
                return new WP_Error('invalid_meeting_point', __('Selected meeting point is not available for this slot.', 'fp-esperienze'), ['status' => 400]);
            }

            $meeting_point_id = $requested_meeting_point;
        } elseif (!empty($slot['meeting_point_id'])) {
            $meeting_point_id = (int) $slot['meeting_point_id'];
        }

        $user = get_userdata($customer_id);
        $customer_email = $user ? $user->user_email : '';
        $customer_name = $user ? trim(sprintf('%s %s', $user->first_name, $user->last_name)) : '';
        if ($customer_name === '' && $user) {
            $customer_name = $user->display_name ?: $customer_email;
        }
        $customer_phone = $user ? get_user_meta($customer_id, 'billing_phone', true) : '';

        $timestamp = current_time('mysql');
        $extras = isset($booking_data['extras']) && is_array($booking_data['extras']) ? $booking_data['extras'] : [];
        $total_amount = $this->calculateBookingTotal($slot, $participants, $extras);

        $complete_booking_data = apply_filters(
            'fp_customer_booking_data',
            [
                'order_id' => 0,
                'order_item_id' => 0,
                'product_id' => $product_id,
                'booking_date' => $booking_date,
                'booking_time' => $normalized_time,
                'adults' => $participants['adults'],
                'children' => $participants['children'],
                'participants' => $participants['total'],
                'meeting_point_id' => $meeting_point_id ?: null,
                'status' => 'confirmed',
                'customer_notes' => isset($booking_data['customer_notes']) ? sanitize_textarea_field((string) $booking_data['customer_notes']) : '',
                'admin_notes' => isset($booking_data['admin_notes']) ? sanitize_textarea_field((string) $booking_data['admin_notes']) : __('Created via mobile app', 'fp-esperienze'),
                'customer_id' => $customer_id,
                'booking_number' => self::generateBookingNumber(),
                'total_amount' => round($total_amount, 2),
                'currency' => function_exists('get_woocommerce_currency') ? (string) get_woocommerce_currency() : '',
                'checked_in_at' => null,
                'checked_in_by' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $booking_data,
            $slot,
            $customer_id
        );

        $payload_overrides = [
            'customer_id' => $customer_id,
            'customer_email' => $customer_email,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
        ];

        $result = $this->persistBooking($complete_booking_data, null, '', 'customer', $payload_overrides);

        if (is_wp_error($result)) {
            return $result;
        }

        return $result;
    }

    /**
     * Persist a booking record either converting a hold or creating it directly.
     *
     * @param array        $complete_booking_data Prepared booking data.
     * @param \WC_Order|null $order               Related order instance.
     * @param string       $session_id            Session identifier for hold conversion.
     * @param string       $context               Context label for logging.
     * @param array        $payload_overrides     Additional data for hook payloads.
     *
     * @return int|WP_Error
     */
    private function persistBooking(array $complete_booking_data, ?\WC_Order $order, string $session_id = '', string $context = 'order', array $payload_overrides = []) {
        $product_id = isset($complete_booking_data['product_id']) ? (int) $complete_booking_data['product_id'] : 0;
        $booking_date = $complete_booking_data['booking_date'] ?? '';
        $booking_time = $complete_booking_data['booking_time'] ?? '';
        $slot_start = trim($booking_date . ' ' . substr((string) $booking_time, 0, 5));

        if (!isset($complete_booking_data['customer_id'])) {
            $complete_booking_data['customer_id'] = $order ? ($order->get_customer_id() ?: $order->get_user_id() ?: 0) : 0;
        }

        $complete_booking_data['customer_id'] = max(0, (int) $complete_booking_data['customer_id']);

        if (!isset($complete_booking_data['participants'])) {
            $adult_count = isset($complete_booking_data['adults']) ? max(0, (int) $complete_booking_data['adults']) : 0;
            $child_count = isset($complete_booking_data['children']) ? max(0, (int) $complete_booking_data['children']) : 0;
            $complete_booking_data['participants'] = $adult_count + $child_count;
        }

        if (!isset($complete_booking_data['booking_number']) || $complete_booking_data['booking_number'] === '') {
            $complete_booking_data['booking_number'] = self::generateBookingNumber();
        }

        if (!isset($complete_booking_data['total_amount'])) {
            $order_item_id = isset($complete_booking_data['order_item_id']) ? (int) $complete_booking_data['order_item_id'] : 0;
            $complete_booking_data['total_amount'] = $this->calculateOrderItemTotal($order, $order_item_id);
        } else {
            $complete_booking_data['total_amount'] = round((float) $complete_booking_data['total_amount'], 2);
        }

        if (!isset($complete_booking_data['currency']) || $complete_booking_data['currency'] === '') {
            if ($order) {
                $complete_booking_data['currency'] = (string) $order->get_currency();
            } elseif (function_exists('get_woocommerce_currency')) {
                $complete_booking_data['currency'] = (string) get_woocommerce_currency();
            } else {
                $complete_booking_data['currency'] = '';
            }
        }

        if (!array_key_exists('checked_in_at', $complete_booking_data)) {
            $complete_booking_data['checked_in_at'] = null;
        }

        if (!array_key_exists('checked_in_by', $complete_booking_data)) {
            $complete_booking_data['checked_in_by'] = null;
        }

        if (HoldManager::isEnabled() && $session_id !== '') {
            $conversion_result = HoldManager::convertHoldToBooking(
                $product_id,
                $slot_start,
                $session_id,
                $complete_booking_data
            );

            if (!empty($conversion_result['success'])) {
                $booking_id = (int) $conversion_result['booking_id'];

                do_action('fp_esperienze_booking_created', $product_id, $booking_date);

                $booking_record = self::getBooking($booking_id);
                if ($booking_record) {
                    $booking_payload = array_merge($complete_booking_data, $payload_overrides, (array) $booking_record);
                } else {
                    $booking_payload = array_merge(['id' => $booking_id], $payload_overrides, $complete_booking_data);
                }
                $booking_payload = $this->buildBookingPayload($booking_id, $booking_payload, $order);

                do_action('fp_booking_confirmed', $booking_id, $booking_payload);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf('Created %s booking #%d from hold.', $context, $booking_id));
                }

                return $booking_id;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                $message = isset($conversion_result['message']) ? (string) $conversion_result['message'] : 'unknown error';
                error_log(sprintf('Hold conversion failed for %s booking: %s', $context, $message));
            }
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_bookings';

        $result = $wpdb->insert($table_name, $complete_booking_data);

        if ($result === false) {
            $error_message = $wpdb->last_error ?: __('Failed to create booking.', 'fp-esperienze');

            return new WP_Error('booking_creation_failed', $error_message, ['status' => 500]);
        }

        $booking_id = (int) $wpdb->insert_id;

        do_action('fp_esperienze_booking_created', $product_id, $booking_date);

        $booking_record = self::getBooking($booking_id);
        if ($booking_record) {
            $booking_payload = array_merge($complete_booking_data, $payload_overrides, (array) $booking_record);
        } else {
            $booking_payload = array_merge(['id' => $booking_id], $payload_overrides, $complete_booking_data);
        }
        $booking_payload = $this->buildBookingPayload($booking_id, $booking_payload, $order);

        do_action('fp_booking_confirmed', $booking_id, $booking_payload);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('Created %s booking #%d.', $context, $booking_id));
        }

        return $booking_id;
    }

    /**
     * Normalize participant data.
     *
     * @param mixed $participants Participants information.
     *
     * @return array{adults:int,children:int,total:int}
     */
    private function parseParticipants($participants): array {
        $adults = 0;
        $children = 0;

        if (is_array($participants)) {
            if (array_key_exists('adults', $participants) || array_key_exists('children', $participants)) {
                $adults = isset($participants['adults']) ? max(0, (int) $participants['adults']) : 0;
                $children = isset($participants['children']) ? max(0, (int) $participants['children']) : 0;
            } else {
                $values = array_values($participants);
                $adults = isset($values[0]) ? max(0, (int) $values[0]) : 0;
                $children = isset($values[1]) ? max(0, (int) $values[1]) : 0;
            }
        } elseif ($participants !== null) {
            $adults = max(0, (int) $participants);
        }

        return [
            'adults' => $adults,
            'children' => $children,
            'total' => $adults + $children,
        ];
    }

    /**
     * Normalize booking time to H:i:s format.
     *
     * @param string $time Time string.
     *
     * @return string|null
     */
    private function normalizeBookingTime(string $time): ?string {
        $time = trim($time);
        if ($time === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            $date = \DateTime::createFromFormat($format, $time);
            if ($date && $date->format($format) === $time) {
                return $date->format('H:i:s');
            }
        }

        return null;
    }

    /**
     * Locate the availability slot for a booking.
     *
     * @param int    $product_id Product ID.
     * @param string $date       Booking date.
     * @param string $time       Booking time (H:i:s).
     *
     * @return array|null
     */
    private function getSlotForBooking(int $product_id, string $date, string $time): ?array {
        $slots = Availability::getSlotsForDate($product_id, $date);
        $target_time = substr($time, 0, 5);

        foreach ($slots as $slot) {
            if (!isset($slot['start_time'])) {
                continue;
            }

            if ($slot['start_time'] === $target_time) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Calculate the total amount for a WooCommerce order item including taxes.
     *
     * @param \WC_Order|null $order   Related order instance.
     * @param int             $item_id Order item ID.
     *
     * @return float
     */
    private function calculateOrderItemTotal(?\WC_Order $order, int $item_id): float {
        if (!$order instanceof \WC_Order) {
            return 0.0;
        }

        if ($item_id > 0) {
            $order_item = $order->get_item($item_id);
            if ($order_item instanceof \WC_Order_Item_Product) {
                $total = (float) $order_item->get_total() + (float) $order_item->get_total_tax();

                if ($total <= 0) {
                    $total = (float) $order_item->get_subtotal() + (float) $order_item->get_subtotal_tax();
                }

                return round($total, 2);
            }
        }

        $items = $order->get_items();
        if (count($items) === 1) {
            $single_item = reset($items);
            if ($single_item instanceof \WC_Order_Item_Product) {
                $total = (float) $single_item->get_total() + (float) $single_item->get_total_tax();

                if ($total <= 0) {
                    $total = (float) $single_item->get_subtotal() + (float) $single_item->get_subtotal_tax();
                }

                return round($total, 2);
            }

            return round((float) $order->get_total(), 2);
        }

        return 0.0;
    }

    /**
     * Calculate total amount for a booking.
     *
     * @param array $slot         Availability slot information.
     * @param array $participants Participant breakdown.
     * @param array $extras       Selected extras.
     *
     * @return float
     */
    private function calculateBookingTotal(array $slot, array $participants, array $extras = []): float {
        $adult_price = isset($slot['adult_price']) ? (float) $slot['adult_price'] : 0.0;
        $child_price = isset($slot['child_price']) ? (float) $slot['child_price'] : $adult_price;

        $total = ($participants['adults'] * $adult_price) + ($participants['children'] * $child_price);
        $total += $this->calculateExtrasTotal($extras, $participants);

        $total = (float) apply_filters('fp_customer_booking_total', $total, $slot, $participants, $extras);

        return round($total, 2);
    }

    /**
     * Calculate total amount for extras.
     *
     * @param array $extras       Extras selection.
     * @param array $participants Participant breakdown.
     *
     * @return float
     */
    private function calculateExtrasTotal(array $extras, array $participants): float {
        $total = 0.0;

        foreach ($extras as $extra) {
            if (is_array($extra)) {
                if (isset($extra['total'])) {
                    $total += (float) $extra['total'];
                    continue;
                }

                $price = isset($extra['price']) ? (float) $extra['price'] : 0.0;
                $quantity = isset($extra['quantity']) ? (int) $extra['quantity'] : 1;

                if (isset($extra['billing_type']) && $extra['billing_type'] === 'per_person') {
                    $quantity = max(1, $participants['total']);
                } elseif ($quantity < 1) {
                    $quantity = 1;
                }

                $total += $price * $quantity;
            } elseif (is_numeric($extra)) {
                $total += (float) $extra;
            }
        }

        return $total;
    }

    /**
     * Generate a unique booking number.
     *
     * @return string
     */
    public static function generateBookingNumber(): string {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_bookings';
        $prefix = apply_filters('fp_booking_number_prefix', 'FP');
        $timestamp = current_time('timestamp');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = apply_filters(
                'fp_booking_number_candidate',
                sprintf('%s-%s-%04d', strtoupper($prefix), date_i18n('Ymd', $timestamp), wp_rand(1000, 9999)),
                $prefix,
                $timestamp,
                $attempt
            );

            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE booking_number = %s", $candidate));
            if (!$exists) {
                return $candidate;
            }
        }

        return strtoupper($prefix) . '-' . uniqid('', false);
    }

    /**
     * Build a payload for booking hooks ensuring required information is present.
     *
     * @param int            $booking_id Booking ID.
     * @param array          $payload    Booking payload data.
     * @param \WC_Order|null $order      Related order instance.
     *
     * @return array
     */
    private function buildBookingPayload(int $booking_id, array $payload, ?\WC_Order $order): array {
        $payload['id'] = $payload['id'] ?? $booking_id;
        $payload['booking_id'] = $payload['booking_id'] ?? $booking_id;

        if ($order) {
            $order_id = isset($payload['order_id']) ? (int) $payload['order_id'] : 0;
            if ($order_id <= 0) {
                $payload['order_id'] = (int) $order->get_id();
            }

            if (!isset($payload['customer_id']) || $payload['customer_id'] === null || $payload['customer_id'] === '') {
                $payload['customer_id'] = $order->get_customer_id() ?: $order->get_user_id() ?: 0;
            }

            if (empty($payload['customer_email'])) {
                $payload['customer_email'] = $order->get_billing_email();
            }

            $customer_name = $payload['customer_name'] ?? '';
            if ($customer_name === '') {
                $customer_name = trim(sprintf('%s %s', $order->get_billing_first_name(), $order->get_billing_last_name()));
                if ($customer_name === '') {
                    $customer_name = $order->get_formatted_billing_full_name() ?: $order->get_billing_email();
                }

                $payload['customer_name'] = $customer_name;
            }

            if (empty($payload['customer_phone'])) {
                $payload['customer_phone'] = $order->get_billing_phone();
            }

            $product_id = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
            if ($product_id <= 0 && !empty($payload['order_item_id'])) {
                $order_item = $order->get_item((int) $payload['order_item_id']);
                if ($order_item instanceof \WC_Order_Item_Product) {
                    $product = $order_item->get_product();
                    if ($product) {
                        $payload['product_id'] = $product->get_id();

                        if (empty($payload['experience_name'])) {
                            $payload['experience_name'] = $product->get_name();
                        }

                        if (empty($payload['experience_url']) && method_exists($product, 'get_permalink')) {
                            $payload['experience_url'] = $product->get_permalink();
                        }
                    }
                }
            }
        }

        return $payload;
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
            "SELECT * FROM $table_name WHERE order_id = %d AND order_item_id = %d",
            $order_id,
            $item_id
        ));

        $updated_at = current_time('mysql');

        $result = $wpdb->update(
            $table_name,
            ['status' => $status, 'updated_at' => $updated_at],
            ['order_id' => $order_id, 'order_item_id' => $item_id],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($result !== false && $booking) {
            $booking_id = isset($booking->id) ? (int) $booking->id : 0;

            // Trigger cache invalidation based on status
            if ($status === 'cancelled') {
                do_action('fp_esperienze_booking_cancelled', $booking->product_id, $booking->booking_date);
            } elseif ($status === 'refunded') {
                do_action('fp_esperienze_booking_refunded', $booking->product_id, $booking->booking_date);
            } elseif ($status === 'completed' && $booking_id > 0) {
                $updated_booking = self::getBooking($booking_id);
                $booking_payload = $updated_booking ? (array) $updated_booking : array_merge(
                    (array) $booking,
                    [
                        'status' => $status,
                        'updated_at' => $updated_at,
                    ]
                );

                $order = null;
                if (!empty($booking_payload['order_id'])) {
                    $order_candidate = wc_get_order((int) $booking_payload['order_id']);
                    if ($order_candidate instanceof \WC_Order) {
                        $order = $order_candidate;
                    }
                }

                $booking_payload = $this->buildBookingPayload($booking_id, $booking_payload, $order);

                do_action('fp_booking_completed', $booking_id, $booking_payload);
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