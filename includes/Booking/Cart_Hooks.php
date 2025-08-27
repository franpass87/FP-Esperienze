<?php
/**
 * Cart Hooks for Experience Products
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

use FP\Esperienze\Data\ExtraManager;
use FP\Esperienze\Data\VoucherManager;

defined('ABSPATH') || exit;

/**
 * Cart hooks class for handling experience bookings
 */
class Cart_Hooks {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', [$this, 'addExperienceCartData'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateExperienceBooking'], 10, 6);
        add_filter('woocommerce_get_item_data', [$this, 'displayExperienceCartData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addExperienceOrderItemMeta'], 10, 4);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculateExperiencePrice'], 10, 1);
        
        // Voucher hooks
        add_action('wp_ajax_fp_apply_voucher', [$this, 'applyVoucherAjax']);
        add_action('wp_ajax_nopriv_fp_apply_voucher', [$this, 'applyVoucherAjax']);
        add_action('wp_ajax_fp_remove_voucher', [$this, 'removeVoucherAjax']);
        add_action('wp_ajax_nopriv_fp_remove_voucher', [$this, 'removeVoucherAjax']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'addVoucherToCartData'], 20, 2);
        add_filter('woocommerce_get_item_data', [$this, 'displayVoucherInCart'], 20, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addVoucherOrderItemMeta'], 20, 4);
    }

    /**
     * Add experience cart data
     *
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @param int $variation_id Variation ID
     * @return array Modified cart data
     */
    public function addExperienceCartData($cart_item_data, $product_id, $variation_id) {
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_type() !== 'experience') {
            return $cart_item_data;
        }

        // Get experience booking data from POST
        $slot_start = sanitize_text_field($_POST['fp_slot_start'] ?? '');
        $meeting_point_id = absint($_POST['fp_meeting_point_id'] ?? 0);
        $lang = sanitize_text_field($_POST['fp_lang'] ?? '');
        $qty_adult = absint($_POST['fp_qty_adult'] ?? 0);
        $qty_child = absint($_POST['fp_qty_child'] ?? 0);
        $extras_json = sanitize_text_field($_POST['fp_extras'] ?? '');

        if ($slot_start) {
            $extras = [];
            if (!empty($extras_json)) {
                $extras_data = json_decode($extras_json, true);
                if (is_array($extras_data)) {
                    foreach ($extras_data as $extra_id => $quantity) {
                        $extras[absint($extra_id)] = absint($quantity);
                    }
                }
            }

            $cart_item_data['fp_experience'] = [
                'slot_start' => $slot_start,
                'meeting_point_id' => $meeting_point_id,
                'lang' => $lang,
                'qty_adult' => $qty_adult,
                'qty_child' => $qty_child,
                'extras' => $extras,
            ];
        }

        return $cart_item_data;
    }

    /**
     * Validate experience booking before adding to cart
     *
     * @param bool $passed Validation status
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $variation_id Variation ID
     * @param array $variations Variations
     * @param array $cart_item_data Cart item data
     * @return bool Validation result
     */
    public function validateExperienceBooking($passed, $product_id, $quantity, $variation_id = 0, $variations = [], $cart_item_data = []) {
        $product = wc_get_product($product_id);
        
        if (!$product || $product->get_type() !== 'experience') {
            return $passed;
        }

        // Get booking data
        $slot_start = sanitize_text_field($_POST['fp_slot_start'] ?? '');
        $qty_adult = absint($_POST['fp_qty_adult'] ?? 0);
        $qty_child = absint($_POST['fp_qty_child'] ?? 0);

        // Validate required fields
        if (empty($slot_start)) {
            wc_add_notice(__('Please select a time slot.', 'fp-esperienze'), 'error');
            return false;
        }

        if ($qty_adult <= 0 && $qty_child <= 0) {
            wc_add_notice(__('Please select at least one participant.', 'fp-esperienze'), 'error');
            return false;
        }

        // Validate slot format (YYYY-MM-DD HH:MM)
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start);
        if (!$slot_datetime || $slot_datetime->format('Y-m-d H:i') !== $slot_start) {
            wc_add_notice(__('Invalid time slot format.', 'fp-esperienze'), 'error');
            return false;
        }

        // Check cutoff time
        $cutoff_minutes = get_post_meta($product_id, '_fp_exp_cutoff_minutes', true) ?: 120;
        $cutoff_time = new \DateTime();
        $cutoff_time->add(new \DateInterval('PT' . $cutoff_minutes . 'M'));

        if ($slot_datetime <= $cutoff_time) {
            wc_add_notice(
                sprintf(
                    __('This time slot is too close to departure. Please book at least %d minutes in advance.', 'fp-esperienze'),
                    $cutoff_minutes
                ),
                'error'
            );
            return false;
        }

        // Check capacity (simplified validation - in real implementation would check database)
        $total_participants = $qty_adult + $qty_child;
        $capacity = $product->get_capacity() ?: 10;
        
        // For demo purposes, assume 30% of capacity is already booked
        $available_spots = ceil($capacity * 0.7);
        
        if ($total_participants > $available_spots) {
            wc_add_notice(
                sprintf(
                    __('Only %d spots available for this time slot. You selected %d participants.', 'fp-esperienze'),
                    $available_spots,
                    $total_participants
                ),
                'error'
            );
            return false;
        }

        return $passed;
    }

    /**
     * Display experience data in cart
     *
     * @param array $item_data Item data
     * @param array $cart_item Cart item
     * @return array Modified item data
     */
    public function displayExperienceCartData($item_data, $cart_item) {
        if (!isset($cart_item['fp_experience'])) {
            return $item_data;
        }

        $experience_data = $cart_item['fp_experience'];

        if (!empty($experience_data['slot_start'])) {
            $item_data[] = [
                'key'   => __('Time Slot', 'fp-esperienze'),
                'value' => esc_html(date('F j, Y - g:i A', strtotime($experience_data['slot_start']))),
            ];
        }

        if (!empty($experience_data['lang'])) {
            $item_data[] = [
                'key'   => __('Language', 'fp-esperienze'),
                'value' => esc_html($experience_data['lang']),
            ];
        }

        if (!empty($experience_data['qty_adult'])) {
            $item_data[] = [
                'key'   => __('Adults', 'fp-esperienze'),
                'value' => esc_html($experience_data['qty_adult']),
            ];
        }

        if (!empty($experience_data['qty_child'])) {
            $item_data[] = [
                'key'   => __('Children', 'fp-esperienze'),
                'value' => esc_html($experience_data['qty_child']),
            ];
        }

        // Display extras
        if (!empty($experience_data['extras']) && is_array($experience_data['extras'])) {
            foreach ($experience_data['extras'] as $extra_id => $quantity) {
                if ($quantity > 0) {
                    $extra = ExtraManager::getExtra($extra_id);
                    if ($extra) {
                        $item_data[] = [
                            'key'   => esc_html($extra->name),
                            'value' => sprintf(__('Qty: %d', 'fp-esperienze'), $quantity),
                        ];
                    }
                }
            }
        }

        return $item_data;
    }

    /**
     * Add experience meta to order items
     *
     * @param \WC_Order_Item_Product $item Order item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param \WC_Order $order Order object
     */
    public function addExperienceOrderItemMeta($item, $cart_item_key, $values, $order) {
        if (!isset($values['fp_experience'])) {
            return;
        }

        $experience_data = $values['fp_experience'];

        if (!empty($experience_data['slot_start'])) {
            $item->add_meta_data(__('Time Slot', 'fp-esperienze'), $experience_data['slot_start']);
        }

        if (!empty($experience_data['meeting_point_id'])) {
            $item->add_meta_data(__('Meeting Point ID', 'fp-esperienze'), $experience_data['meeting_point_id']);
        }

        if (!empty($experience_data['lang'])) {
            $item->add_meta_data(__('Language', 'fp-esperienze'), $experience_data['lang']);
        }

        if (!empty($experience_data['qty_adult'])) {
            $item->add_meta_data(__('Adults', 'fp-esperienze'), $experience_data['qty_adult']);
        }

        if (!empty($experience_data['qty_child'])) {
            $item->add_meta_data(__('Children', 'fp-esperienze'), $experience_data['qty_child']);
        }

        // Save extras to order item meta
        if (!empty($experience_data['extras']) && is_array($experience_data['extras'])) {
            foreach ($experience_data['extras'] as $extra_id => $quantity) {
                if ($quantity > 0) {
                    $extra = ExtraManager::getExtra($extra_id);
                    if ($extra) {
                        $item->add_meta_data($extra->name, sprintf(__('Qty: %d', 'fp-esperienze'), $quantity));
                    }
                }
            }
        }
    }

    /**
     * Calculate experience price including extras
     *
     * @param \WC_Cart $cart Cart object
     */
    public function calculateExperiencePrice($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (!isset($cart_item['fp_experience'])) {
                continue;
            }

            $product = $cart_item['data'];
            if ($product->get_type() !== 'experience') {
                continue;
            }

            $experience_data = $cart_item['fp_experience'];
            $qty_adult = $experience_data['qty_adult'] ?? 0;
            $qty_child = $experience_data['qty_child'] ?? 0;
            $total_participants = $qty_adult + $qty_child;

            // Calculate base price (adults + children)
            $adult_price = floatval(get_post_meta($product->get_id(), '_experience_adult_price', true) ?: 0);
            $child_price = floatval(get_post_meta($product->get_id(), '_experience_child_price', true) ?: 0);
            
            $base_total = ($qty_adult * $adult_price) + ($qty_child * $child_price);

            // Calculate extras price
            $extras_total = 0;
            if (!empty($experience_data['extras']) && is_array($experience_data['extras'])) {
                foreach ($experience_data['extras'] as $extra_id => $quantity) {
                    if ($quantity > 0) {
                        $extra = ExtraManager::getExtra($extra_id);
                        if ($extra && $extra->is_active) {
                            if ($extra->billing_type === 'per_person') {
                                $extras_total += $extra->price * $quantity * $total_participants;
                            } else {
                                $extras_total += $extra->price * $quantity;
                            }
                        }
                    }
                }
            }

            // Check for applied voucher and calculate discount
            $applied_voucher = VoucherManager::getAppliedVoucherFromSession();
            if ($applied_voucher && $applied_voucher['product_id'] == $product->get_id()) {
                $discount = VoucherManager::calculateDiscount($applied_voucher, $base_total);
                $base_total = max(0, $base_total - $discount);
            }

            // Set the new price
            $total_price = $base_total + $extras_total;
            $product->set_price($total_price);
        }
    }
    
    /**
     * Handle voucher application via AJAX
     */
    public function applyVoucherAjax(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fp_voucher_nonce')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        $voucher_code = sanitize_text_field($_POST['voucher_code'] ?? '');
        $product_id = absint($_POST['product_id'] ?? 0);
        
        if (empty($voucher_code) || !$product_id) {
            wp_send_json_error([
                'message' => __('Please enter a valid voucher code.', 'fp-esperienze')
            ]);
        }
        
        // Validate voucher
        $voucher = VoucherManager::validateVoucher($voucher_code, $product_id);
        
        if (!$voucher) {
            wp_send_json_error([
                'message' => __('Invalid voucher code or voucher not applicable to this product.', 'fp-esperienze')
            ]);
        }
        
        // Store voucher in session
        VoucherManager::setAppliedVoucherInSession([
            'code' => $voucher_code,
            'type' => $voucher['voucher_type'],
            'amount' => $voucher['amount'],
            'voucher_id' => $voucher['id'],
            'product_id' => $product_id
        ]);
        
        wp_send_json_success([
            'message' => __('Voucher applied successfully!', 'fp-esperienze'),
            'voucher_type' => $voucher['voucher_type'],
            'amount' => $voucher['amount']
        ]);
    }
    
    /**
     * Handle voucher removal via AJAX
     */
    public function removeVoucherAjax(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'fp_voucher_nonce')) {
            wp_die(__('Security check failed.', 'fp-esperienze'));
        }
        
        VoucherManager::removeVoucherFromSession();
        
        wp_send_json_success([
            'message' => __('Voucher removed.', 'fp-esperienze')
        ]);
    }
    
    /**
     * Add voucher data to cart item
     */
    public function addVoucherToCartData(array $cart_item_data, int $product_id): array {
        $applied_voucher = VoucherManager::getAppliedVoucherFromSession();
        
        if ($applied_voucher && $applied_voucher['product_id'] == $product_id) {
            $cart_item_data['fp_voucher'] = $applied_voucher;
            // Clear from session after adding to cart
            VoucherManager::removeVoucherFromSession();
        }
        
        return $cart_item_data;
    }
    
    /**
     * Display voucher in cart
     */
    public function displayVoucherInCart(array $item_data, array $cart_item): array {
        if (!empty($cart_item['fp_voucher'])) {
            $voucher = $cart_item['fp_voucher'];
            $item_data[] = [
                'key' => __('Voucher Applied', 'fp-esperienze'),
                'value' => sprintf(
                    __('Code: %s (%s)', 'fp-esperienze'),
                    $voucher['code'],
                    $voucher['type'] === 'full' ? __('Free Experience', 'fp-esperienze') : wc_price($voucher['amount'])
                ),
                'display' => ''
            ];
        }
        
        return $item_data;
    }
    
    /**
     * Add voucher meta to order item
     */
    public function addVoucherOrderItemMeta($item, $cart_item_key, $values, $order): void {
        if (!empty($values['fp_voucher'])) {
            $voucher = $values['fp_voucher'];
            $item->add_meta_data(__('Voucher Code', 'fp-esperienze'), $voucher['code']);
            $item->add_meta_data('_fp_voucher_id', $voucher['voucher_id']);
            $item->add_meta_data('_fp_voucher_type', $voucher['type']);
            $item->add_meta_data('_fp_voucher_amount', $voucher['amount']);
        }
    }
}