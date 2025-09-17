<?php
/**
 * Cart Hooks for Experience Products
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

use FP\Esperienze\Data\ExtraManager;
use FP\Esperienze\Data\VoucherManager;
use FP\Esperienze\Data\DynamicPricingManager;
use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Data\Availability;
use FP\Esperienze\Data\ScheduleManager;
use FP\Esperienze\Core\RateLimiter;

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
        add_action('woocommerce_checkout_create_order', [$this, 'saveSessionIdToOrder'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculateExperiencePrice'], 10, 1);
        
        // Voucher redemption hooks
        add_action('wp_ajax_apply_voucher', [$this, 'applyVoucher']);
        add_action('wp_ajax_nopriv_apply_voucher', [$this, 'applyVoucher']);
        add_action('wp_ajax_remove_voucher', [$this, 'removeVoucher']);
        add_action('wp_ajax_nopriv_remove_voucher', [$this, 'removeVoucher']);
        add_action('woocommerce_order_status_completed', [$this, 'processVoucherRedemption'], 20, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'rollbackVoucherRedemption'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'rollbackVoucherRedemption'], 10, 1);
        
        // WooCommerce coupon compatibility hooks
        add_action('woocommerce_applied_coupon', [$this, 'checkCouponVoucherConflict'], 10, 1);
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
        $slot_start = sanitize_text_field(wp_unslash($_POST['fp_slot_start'] ?? ''));
        $meeting_point_id = absint(wp_unslash($_POST['fp_meeting_point_id'] ?? 0));
        $lang = sanitize_text_field(wp_unslash($_POST['fp_lang'] ?? ''));
        $qty_adult = absint(wp_unslash($_POST['fp_qty_adult'] ?? 0));
        $qty_child = absint(wp_unslash($_POST['fp_qty_child'] ?? 0));
        $extras_data = [];
        $extras_json = wp_unslash($_POST['fp_extras'] ?? '');
        if (!empty($extras_json)) {
            $extras_data = json_decode($extras_json, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($extras_data)) {
                error_log('Invalid extras payload: ' . $extras_json);
                $extras_data = [];
            }
        }

        // Get gift data from POST
        $is_gift = !empty($_POST['fp_is_gift']);
        $gift_sender_name = sanitize_text_field(wp_unslash($_POST['fp_gift_sender_name'] ?? ''));
        $gift_recipient_name = sanitize_text_field(wp_unslash($_POST['fp_gift_recipient_name'] ?? ''));
        $gift_recipient_email = sanitize_email(wp_unslash($_POST['fp_gift_recipient_email'] ?? ''));
        $gift_message = sanitize_textarea_field(wp_unslash($_POST['fp_gift_message'] ?? ''));
        $gift_send_date = sanitize_text_field(wp_unslash($_POST['fp_gift_send_date'] ?? ''));

        if ($slot_start) {
            $extras = [];
            if (!empty($extras_data)) {
                foreach ($extras_data as $extra_id => $quantity) {
                    $extras[absint($extra_id)] = absint($quantity);
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
            
            // Add gift data if this is a gift purchase
            if ($is_gift) {
                $cart_item_data['fp_gift'] = [
                    'is_gift' => true,
                    'sender_name' => $gift_sender_name,
                    'recipient_name' => $gift_recipient_name,
                    'recipient_email' => $gift_recipient_email,
                    'message' => $gift_message,
                    'send_date' => $gift_send_date ?: 'immediate',
                ];
            }
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
        $slot_start = sanitize_text_field(wp_unslash($_POST['fp_slot_start'] ?? ''));
        $qty_adult = absint(wp_unslash($_POST['fp_qty_adult'] ?? 0));
        $qty_child = absint(wp_unslash($_POST['fp_qty_child'] ?? 0));
        
        // Get gift data
        $is_gift = !empty($_POST['fp_is_gift']);
        $gift_recipient_name = sanitize_text_field(wp_unslash($_POST['fp_gift_recipient_name'] ?? ''));
        $gift_recipient_email = sanitize_email(wp_unslash($_POST['fp_gift_recipient_email'] ?? ''));

        // Validate required fields
        if (empty($slot_start)) {
            wc_add_notice(__('Please select a time slot.', 'fp-esperienze'), 'error');
            return false;
        }

        if ($qty_adult <= 0 && $qty_child <= 0) {
            wc_add_notice(__('Please select at least one participant.', 'fp-esperienze'), 'error');
            return false;
        }
        
        // Validate gift fields if this is a gift purchase
        if ($is_gift) {
            if (empty($gift_recipient_name)) {
                wc_add_notice(__('Please enter the recipient name for gift purchase.', 'fp-esperienze'), 'error');
                return false;
            }
            
            if (empty($gift_recipient_email) || !is_email($gift_recipient_email)) {
                wc_add_notice(__('Please enter a valid recipient email for gift purchase.', 'fp-esperienze'), 'error');
                return false;
            }
        }

        // Validate slot format (YYYY-MM-DD HH:MM)
        $wp_timezone = wp_timezone();
        $slot_datetime = \DateTime::createFromFormat('Y-m-d H:i', $slot_start, $wp_timezone);

        if ($slot_datetime instanceof \DateTime) {
            $slot_datetime->setTimezone($wp_timezone);
        }

        if (!$slot_datetime || $slot_datetime->format('Y-m-d H:i') !== $slot_start) {
            wc_add_notice(__('Invalid time slot format.', 'fp-esperienze'), 'error');
            return false;
        }

        // Check cutoff time
        $cutoff_minutes = get_post_meta($product_id, '_fp_exp_cutoff_minutes', true) ?: 120;
        $cutoff_time = new \DateTime('now', $wp_timezone);
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

        // Check capacity with real availability calculation
        $total_participants = $qty_adult + $qty_child;
        $date = $slot_datetime->format('Y-m-d');
        $time = $slot_datetime->format('H:i');
        
        // Get real availability for the slot
        $slots = Availability::forDay($product_id, $date);
        $available_spots = 0;
        
        foreach ($slots as $slot) {
            if ($slot['start_time'] === $time) {
                $available_spots = $slot['available'];
                break;
            }
        }
        
        if ($available_spots < $total_participants) {
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
        
        // Create hold if holds system is enabled
        if (HoldManager::isEnabled()) {
            $session_id = WC()->session->get_customer_id();
            $hold_result = HoldManager::createHold($product_id, $slot_start, $total_participants, $session_id);
            
            if (!$hold_result['success']) {
                wc_add_notice($hold_result['message'], 'error');
                return false;
            }
            
            // Show hold confirmation message
            wc_add_notice($hold_result['message'], 'success');
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

        // Display gift information
        if (isset($cart_item['fp_gift']) && $cart_item['fp_gift']['is_gift']) {
            $gift_data = $cart_item['fp_gift'];
            
            $item_data[] = [
                'key'   => __('Gift Purchase', 'fp-esperienze'),
                'value' => __('Yes', 'fp-esperienze'),
            ];
            
            $item_data[] = [
                'key'   => __('Recipient', 'fp-esperienze'),
                'value' => esc_html($gift_data['recipient_name']),
            ];
            
            if (!empty($gift_data['sender_name'])) {
                $item_data[] = [
                    'key'   => __('From', 'fp-esperienze'),
                    'value' => esc_html($gift_data['sender_name']),
                ];
            }
            
            if ($gift_data['send_date'] !== 'immediate') {
                $item_data[] = [
                    'key'   => __('Send Date', 'fp-esperienze'),
                    'value' => esc_html(date_i18n(get_option('date_format'), strtotime($gift_data['send_date']))),
                ];
            }
        }
        
        // Display voucher information
        $cart_item_key = null;
        foreach (WC()->cart->get_cart() as $key => $item) {
            if ($item === $cart_item) {
                $cart_item_key = $key;
                break;
            }
        }
        
        if ($cart_item_key) {
            $applied_voucher = $this->getAppliedVoucher($cart_item_key);
            if ($applied_voucher) {
                $item_data[] = [
                    'key'   => __('Voucher Applied', 'fp-esperienze'),
                    'value' => sprintf(
                        '<span class="fp-voucher-applied">%s <small>(%s)</small></span>',
                        esc_html($applied_voucher['code']),
                        $applied_voucher['amount_type'] === 'full' 
                            ? __('Full discount', 'fp-esperienze')
                            : sprintf(__('Up to %s', 'fp-esperienze'), wc_price($applied_voucher['amount']))
                    ),
                ];
            }
            
            // Display dynamic pricing breakdown
            $this->addDynamicPricingBreakdown($item_data, $cart_item);
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

        $meta_fields = [
            'slot_start' => [
                'machine_key' => '_fp_slot_start',
                'label' => __('Time Slot', 'fp-esperienze'),
            ],
            'meeting_point_id' => [
                'machine_key' => '_fp_meeting_point_id',
                'label' => __('Meeting Point ID', 'fp-esperienze'),
            ],
            'lang' => [
                'machine_key' => '_fp_lang',
                'label' => __('Language', 'fp-esperienze'),
            ],
            'qty_adult' => [
                'machine_key' => '_fp_qty_adult',
                'label' => __('Adults', 'fp-esperienze'),
            ],
            'qty_child' => [
                'machine_key' => '_fp_qty_child',
                'label' => __('Children', 'fp-esperienze'),
            ],
        ];

        foreach ($meta_fields as $field => $meta) {
            if (!array_key_exists($field, $experience_data)) {
                continue;
            }

            $value = $experience_data[$field];
            $has_machine_value = $value !== null && ($value !== '' || is_numeric($value));

            if ($has_machine_value) {
                $item->add_meta_data($meta['machine_key'], $value);
            }

            if (!empty($value)) {
                $item->add_meta_data($meta['label'], $value);
            }
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
        
        // Save gift meta data
        if (isset($values['fp_gift']) && $values['fp_gift']['is_gift']) {
            $gift_data = $values['fp_gift'];
            
            $item->add_meta_data(__('Gift Purchase', 'fp-esperienze'), __('Yes', 'fp-esperienze'));
            $item->add_meta_data(__('Recipient Name', 'fp-esperienze'), $gift_data['recipient_name']);
            $item->add_meta_data(__('Recipient Email', 'fp-esperienze'), $gift_data['recipient_email']);
            
            if (!empty($gift_data['sender_name'])) {
                $item->add_meta_data(__('Sender Name', 'fp-esperienze'), $gift_data['sender_name']);
            }
            
            if (!empty($gift_data['message'])) {
                $item->add_meta_data(__('Gift Message', 'fp-esperienze'), $gift_data['message']);
            }
            
            $item->add_meta_data(__('Send Date', 'fp-esperienze'), $gift_data['send_date']);
        }
        
        // Save voucher meta data if applied
        $applied_voucher = $this->getAppliedVoucher($cart_item_key);
        if ($applied_voucher) {
            $item->add_meta_data('_fp_voucher_code', $applied_voucher['code']);
            $item->add_meta_data('_fp_voucher_id', $applied_voucher['voucher_id']);
            $item->add_meta_data(__('Voucher Applied', 'fp-esperienze'), $applied_voucher['code']);
        }
    }

    /**
     * Save session ID to order meta for hold conversion
     *
     * @param \WC_Order $order Order object
     * @param array $data Posted data
     */
    public function saveSessionIdToOrder($order, $data) {
        $session_id = WC()->session ? WC()->session->get_customer_id() : '';
        if (!empty($session_id)) {
            $order->add_meta_data('_fp_session_id', $session_id);
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

            // Calculate base price (adults + children) with proper tax handling
            $slot_pricing = $this->getExperienceSlotPricing($product, $experience_data);
            $adult_price = $this->getExperiencePriceWithTax($product, 'adult', $qty_adult, $experience_data, $slot_pricing);
            $child_price = $this->getExperiencePriceWithTax($product, 'child', $qty_child, $experience_data, $slot_pricing);
            
            $base_total = $adult_price + $child_price;

            // Calculate extras price with tax handling
            $extras_total = 0;
            if (!empty($experience_data['extras']) && is_array($experience_data['extras'])) {
                foreach ($experience_data['extras'] as $extra_id => $quantity) {
                    if ($quantity > 0) {
                        $extra = ExtraManager::getExtra($extra_id);
                        if ($extra && $extra->is_active) {
                            $extra_price_with_tax = $this->getExtraPriceWithTax($extra, $quantity, $total_participants);
                            $extras_total += $extra_price_with_tax;
                        }
                    }
                }
            }

            // Set the new price
            $total_price = $base_total + $extras_total;
            
            // Check if voucher is applied to this cart item
            $applied_voucher = $this->getAppliedVoucher($cart_item_key);
            if ($applied_voucher) {
                // Check for WooCommerce coupon conflicts
                $has_wc_coupons = !empty(WC()->cart->get_applied_coupons());
                
                $validation = VoucherManager::validateVoucherForRedemption(
                    $applied_voucher['code'], 
                    $product->get_id()
                );
                
                if ($validation['success']) {
                    $voucher = $validation['voucher'];
                    
                    // For full vouchers, check if WooCommerce coupons are applied
                    if ($voucher['amount_type'] === 'full' && $has_wc_coupons) {
                        // Full vouchers are not stackable with WooCommerce coupons
                        // Remove the voucher and add a notice
                        $this->removeAppliedVoucher($cart_item_key);
                        wc_add_notice(
                            __('Full discount vouchers cannot be combined with other coupons. The voucher has been removed.', 'fp-esperienze'),
                            'notice'
                        );
                    } else {
                        if ($voucher['amount_type'] === 'full') {
                            // Full voucher: make base product free, keep extras
                            $voucher_discount = $base_total;
                        } else {
                            // Value voucher: apply discount up to voucher amount on base price only
                            $voucher_discount = min($base_total, floatval($voucher['amount']));
                        }
                        
                        // Allow filtering of voucher discount amount
                        $voucher_discount = apply_filters('fp_esperienze_voucher_discount_amount', $voucher_discount, $voucher, $cart_item);
                        
                        $base_total = max(0, $base_total - $voucher_discount);
                    }
                }
            }
            
            $total_price = $base_total + $extras_total;
            
            // Allow filtering of final cart item price
            $total_price = apply_filters('fp_esperienze_cart_item_price', $total_price, $cart_item, $base_total, $extras_total);
            
            // Set the calculated price on the product
            $product->set_price($total_price);
        }
    }
    
    /**
     * Apply voucher to cart
     */
    public function applyVoucher() {
        check_ajax_referer('fp_voucher_nonce', 'nonce');
        
        // Apply rate limiting for voucher redemption attempts (5 attempts per minute per IP)
        if (!RateLimiter::checkRateLimit('voucher_redemption', 5, 60)) {
            wp_send_json_error([
                'message' => __('Too many voucher redemption attempts. Please try again in a minute.', 'fp-esperienze')
            ]);
        }
        
        $voucher_code = sanitize_text_field(wp_unslash($_POST['voucher_code'] ?? ''));
        $product_id = absint(wp_unslash($_POST['product_id'] ?? 0));
        $cart_item_key = sanitize_text_field(wp_unslash($_POST['cart_item_key'] ?? ''));
        
        if (empty($voucher_code)) {
            wp_send_json_error(['message' => __('Please enter a voucher code.', 'fp-esperienze')]);
        }
        
        // Validate voucher
        $validation = VoucherManager::validateVoucherForRedemption($voucher_code, $product_id);
        
        // Allow filtering of validation results
        $validation = apply_filters('fp_esperienze_voucher_validation', $validation, $voucher_code, $product_id);
        
        if (!$validation['success']) {
            wp_send_json_error(['message' => $validation['message']]);
        }
        
        // Check for WooCommerce coupon conflicts with full vouchers
        $has_wc_coupons = !empty(WC()->cart->get_applied_coupons());
        if ($validation['voucher']['amount_type'] === 'full' && $has_wc_coupons) {
            wp_send_json_error([
                'message' => __('Full discount vouchers cannot be combined with other coupons. Please remove existing coupons first.', 'fp-esperienze')
            ]);
        }
        
        // Store voucher in session
        $voucher_data = [
            'code' => $voucher_code,
            'voucher_id' => $validation['voucher']['id'],
            'product_id' => $product_id,
            'amount_type' => $validation['voucher']['amount_type'],
            'amount' => $validation['voucher']['amount']
        ];
        
        $this->storeAppliedVoucher($cart_item_key, $voucher_data);
        
        // Fire action hook
        do_action('fp_esperienze_voucher_applied', $voucher_code, $product_id, $cart_item_key, $validation['voucher']);
        
        // Calculate new totals
        WC()->cart->calculate_totals();
        
        wp_send_json_success([
            'message' => __('Voucher applied successfully!', 'fp-esperienze'),
            'voucher_code' => $voucher_code,
            'discount_info' => $this->getVoucherDiscountInfo($validation['voucher'])
        ]);
    }
    
    /**
     * Remove voucher from cart
     */
    public function removeVoucher() {
        check_ajax_referer('fp_voucher_nonce', 'nonce');
        
        $cart_item_key = sanitize_text_field(wp_unslash($_POST['cart_item_key'] ?? ''));
        
        if (empty($cart_item_key)) {
            wp_send_json_error(['message' => __('Invalid cart item.', 'fp-esperienze')]);
        }
        
        // Remove voucher from session
        $this->removeAppliedVoucher($cart_item_key);
        
        // Fire action hook
        do_action('fp_esperienze_voucher_removed', $cart_item_key);
        
        // Recalculate totals
        WC()->cart->calculate_totals();
        
        wp_send_json_success([
            'message' => __('Voucher removed successfully.', 'fp-esperienze')
        ]);
    }
    
    /**
     * Process voucher redemption on order completion
     *
     * @param int $order_id Order ID
     */
    public function processVoucherRedemption($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            $voucher_code = $item->get_meta('_fp_voucher_code');
            if ($voucher_code) {
                VoucherManager::redeemVoucher($voucher_code);
                
                // Fire action hook
                do_action('fp_esperienze_voucher_redeemed', $voucher_code, $order_id, $item_id);
                
                // Add order note
                $order->add_order_note(sprintf(
                    __('Voucher %s redeemed for this order.', 'fp-esperienze'),
                    $voucher_code
                ));
            }
        }
    }
    
    /**
     * Rollback voucher redemption on order cancellation/refund
     *
     * @param int $order_id Order ID
     */
    public function rollbackVoucherRedemption($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        foreach ($order->get_items() as $item_id => $item) {
            $voucher_code = $item->get_meta('_fp_voucher_code');
            if ($voucher_code) {
                if (VoucherManager::rollbackVoucherRedemption($voucher_code)) {
                    // Fire action hook
                    do_action('fp_esperienze_voucher_rollback', $voucher_code, $order_id, $item_id);
                    
                    // Add order note
                    $order->add_order_note(sprintf(
                        __('Voucher %s restored to active status due to order cancellation/refund.', 'fp-esperienze'),
                        $voucher_code
                    ));
                }
            }
        }
    }
    
    /**
     * Store applied voucher in session
     *
     * @param string $cart_item_key Cart item key
     * @param array $voucher_data Voucher data
     */
    private function storeAppliedVoucher($cart_item_key, $voucher_data) {
        $session_key = 'fp_applied_vouchers';
        $applied_vouchers = WC()->session->get($session_key, []);
        $applied_vouchers[$cart_item_key] = $voucher_data;
        WC()->session->set($session_key, $applied_vouchers);
    }
    
    /**
     * Get applied voucher for cart item
     *
     * @param string $cart_item_key Cart item key
     * @return array|null Voucher data or null
     */
    private function getAppliedVoucher($cart_item_key) {
        $session_key = 'fp_applied_vouchers';
        $applied_vouchers = WC()->session->get($session_key, []);
        return $applied_vouchers[$cart_item_key] ?? null;
    }
    
    /**
     * Remove applied voucher from session
     *
     * @param string $cart_item_key Cart item key
     */
    private function removeAppliedVoucher($cart_item_key) {
        $session_key = 'fp_applied_vouchers';
        $applied_vouchers = WC()->session->get($session_key, []);
        unset($applied_vouchers[$cart_item_key]);
        WC()->session->set($session_key, $applied_vouchers);
    }
    
    /**
     * Get voucher discount information
     *
     * @param array $voucher Voucher data
     * @return array Discount information
     */
    private function getVoucherDiscountInfo($voucher) {
        if ($voucher['amount_type'] === 'full') {
            return [
                'type' => 'full',
                'description' => __('Full experience discount', 'fp-esperienze')
            ];
        } else {
            return [
                'type' => 'value',
                'amount' => wc_price($voucher['amount']),
                'description' => sprintf(
                    __('Discount up to %s', 'fp-esperienze'),
                    wc_price($voucher['amount'])
                )
            ];
        }
    }
    
    /**
     * Render voucher form template
     *
     * @param string $cart_item_key Cart item key
     * @param int $product_id Product ID
     * @param array|null $applied_voucher Applied voucher data
     * @return string HTML output
     */
    public function renderVoucherForm(string $cart_item_key, int $product_id, ?array $applied_voucher = null) {
        ob_start();
        
        // Load template file
        $template_path = FP_ESPERIENZE_PLUGIN_DIR . 'templates/voucher-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
        
        return ob_get_clean();
    }
    
    /**
     * Check for conflicts when WooCommerce coupons are applied
     *
     * @param string $coupon_code Applied coupon code
     */
    public function checkCouponVoucherConflict($coupon_code) {
        $session_key = 'fp_applied_vouchers';
        $applied_vouchers = WC()->session->get($session_key, []);
        
        if (empty($applied_vouchers)) {
            return;
        }
        
        $conflicts_found = false;
        foreach ($applied_vouchers as $cart_item_key => $voucher_data) {
            // Only full vouchers conflict with WooCommerce coupons
            if ($voucher_data['amount_type'] === 'full') {
                unset($applied_vouchers[$cart_item_key]);
                $conflicts_found = true;
            }
        }
        
        if ($conflicts_found) {
            WC()->session->set($session_key, $applied_vouchers);
            wc_add_notice(
                sprintf(
                    __('Full discount vouchers cannot be combined with coupon "%s". Conflicting vouchers have been removed.', 'fp-esperienze'),
                    $coupon_code
                ),
                'notice'
            );
        }
    }
    
    /**
     * Get experience price with tax for adult or child
     *
     * @param \WC_Product $product Experience product
     * @param string $type 'adult' or 'child'
     * @param int $quantity Quantity
     * @param array $experience_data Experience booking data
     * @param array $slot_pricing Pre-calculated slot pricing data
     * @return float Price including tax
     */
    private function getExperiencePriceWithTax($product, $type, $quantity, array $experience_data = [], array $slot_pricing = []) {
        if ($quantity <= 0) {
            return 0.0;
        }

        if (empty($slot_pricing)) {
            $slot_pricing = $this->getExperienceSlotPricing($product, $experience_data);
        }

        $base_price = $this->resolveExperienceBasePrice($product, $type, $slot_pricing);

        // Allow filtering of base price before tax calculation
        $base_price = apply_filters("fp_esperienze_{$type}_price", $base_price, $product->get_id());

        $tax_class = $this->resolveExperienceTaxClass($product, $type);

        // Create a temporary simple product for tax calculation using the resolved tax class
        $temp_product = new \WC_Product_Simple();
        $temp_product->set_tax_status($product->get_tax_status());
        $temp_product->set_tax_class($tax_class);
        $temp_product->set_price($base_price);

        // Get price with tax handling (respects tax settings and customer location)
        $price_with_tax = wc_get_price_to_display($temp_product, [
            'price' => $base_price,
            'qty'   => 1,
        ]);

        $total_price = $price_with_tax * $quantity;

        // Allow filtering of final calculated price
        return apply_filters(
            "fp_esperienze_{$type}_price_with_tax",
            $total_price,
            $base_price,
            $tax_class,
            $quantity,
            $product->get_id()
        );
    }

    /**
     * Resolve per-slot pricing for adults and children based on the selected slot.
     *
     * @param \WC_Product $product Experience product
     * @param array $experience_data Experience booking data
     * @return array{
     *     adult_price: ?float,
     *     child_price: ?float
     * }
     */
    private function getExperienceSlotPricing($product, array $experience_data): array {
        $pricing = [
            'adult_price' => null,
            'child_price' => null,
        ];

        $slot_start = $experience_data['slot_start'] ?? '';
        if (empty($slot_start)) {
            return $pricing;
        }

        $parts = explode(' ', $slot_start, 2);
        if (count($parts) !== 2) {
            return $pricing;
        }

        $date = $parts[0];
        $time = substr($parts[1], 0, 5);

        if (empty($date) || empty($time)) {
            return $pricing;
        }

        $slots = Availability::forDay($product->get_id(), $date);

        foreach ($slots as $slot) {
            if (($slot['start_time'] ?? '') === $time) {
                if (array_key_exists('adult_price', $slot) && $slot['adult_price'] !== '' && $slot['adult_price'] !== null) {
                    $pricing['adult_price'] = (float) $slot['adult_price'];
                }
                if (array_key_exists('child_price', $slot) && $slot['child_price'] !== '' && $slot['child_price'] !== null) {
                    $pricing['child_price'] = (float) $slot['child_price'];
                }
                break;
            }
        }

        if ($pricing['adult_price'] === null || $pricing['child_price'] === null) {
            try {
                $date_obj = new \DateTime($date);
                $day_of_week = (int) $date_obj->format('w');
            } catch (\Exception $e) {
                $day_of_week = null;
            }

            if ($day_of_week !== null) {
                $schedules = ScheduleManager::getSchedulesForDay($product->get_id(), $day_of_week, $date);
                foreach ($schedules as $schedule) {
                    $schedule_time = isset($schedule->start_time) ? substr($schedule->start_time, 0, 5) : '';
                    if ($schedule_time === $time) {
                        if ($pricing['adult_price'] === null && isset($schedule->price_adult)) {
                            $pricing['adult_price'] = (float) $schedule->price_adult;
                        }
                        if ($pricing['child_price'] === null && isset($schedule->price_child)) {
                            $pricing['child_price'] = (float) $schedule->price_child;
                        }
                        break;
                    }
                }
            }
        }

        return $pricing;
    }

    /**
     * Determine the base price for a participant type using resolved slot pricing or product getters.
     *
     * @param \WC_Product $product Experience product
     * @param string $type Participant type (adult or child)
     * @param array $slot_pricing Slot pricing data
     * @return float
     */
    private function resolveExperienceBasePrice($product, string $type, array $slot_pricing): float {
        $key = $type === 'child' ? 'child_price' : 'adult_price';

        $base_price = null;
        if (array_key_exists($key, $slot_pricing) && $slot_pricing[$key] !== null) {
            $base_price = (float) $slot_pricing[$key];
        }

        if ($base_price === null) {
            $getter = "get_{$type}_price";
            if (is_callable([$product, $getter])) {
                $base_price = (float) $product->{$getter}();
            }
        }

        return $base_price ?? 0.0;
    }

    /**
     * Resolve the tax class for a participant type.
     *
     * @param \WC_Product $product Experience product
     * @param string $type Participant type (adult or child)
     * @return string
     */
    private function resolveExperienceTaxClass($product, string $type): string {
        $getter = "get_{$type}_tax_class";
        if (is_callable([$product, $getter])) {
            $tax_class = (string) $product->{$getter}();
            if ($tax_class !== '') {
                return $tax_class;
            }
        }

        $meta_key = "_experience_{$type}_tax_class";
        $meta_value = get_post_meta($product->get_id(), $meta_key, true);
        if ($meta_value !== '') {
            return (string) $meta_value;
        }

        $product_tax_class = $product->get_tax_class();
        return is_string($product_tax_class) ? $product_tax_class : '';
    }
    
    /**
     * Get extra price with tax
     *
     * @param object $extra Extra object
     * @param int $quantity Quantity
     * @param int $total_participants Total participants for per-person extras
     * @return float Price including tax
     */
    private function getExtraPriceWithTax($extra, $quantity, $total_participants) {
        $base_price = floatval($extra->price);
        $tax_class = $extra->tax_class ?? '';
        
        // Allow filtering of extra base price
        $base_price = apply_filters('fp_esperienze_extra_price', $base_price, $extra->id);
        
        // Create a temporary simple product for tax calculation
        $temp_product = new \WC_Product_Simple();
        $temp_product->set_price($base_price);
        $temp_product->set_tax_class($tax_class);
        
        // Get price with tax handling
        $price_with_tax = wc_get_price_to_display($temp_product, ['price' => $base_price]);
        
        // Apply billing type logic
        if ($extra->billing_type === 'per_person') {
            $total_price = $price_with_tax * $quantity * $total_participants;
        } else {
            $total_price = $price_with_tax * $quantity;
        }
        
        // Allow filtering of final extra price
        return apply_filters('fp_esperienze_extra_price_with_tax', $total_price, $base_price, $tax_class, $quantity, $total_participants, $extra);
    }
    
    /**
     * Add dynamic pricing breakdown to cart item data
     *
     * @param array &$item_data Cart item data array (passed by reference)
     * @param array $cart_item Cart item
     */
    private function addDynamicPricingBreakdown(array &$item_data, array $cart_item): void {
        if (!isset($cart_item['fp_experience'])) {
            return;
        }
        
        $product = $cart_item['data'];
        $product_id = $product->get_id();
        
        // Get applied pricing rules breakdown
        $adult_rules = DynamicPricingManager::getAppliedRulesBreakdown($product_id, 'adult');
        $child_rules = DynamicPricingManager::getAppliedRulesBreakdown($product_id, 'child');
        
        if (empty($adult_rules) && empty($child_rules)) {
            return; // No dynamic pricing applied
        }
        
        $breakdown_html = '<div class="fp-pricing-breakdown">';
        
        if (!empty($adult_rules)) {
            $breakdown_html .= '<div><strong>' . __('Adult Price Adjustments:', 'fp-esperienze') . '</strong></div>';
            foreach ($adult_rules as $rule) {
                $adjustment_text = $rule['adjustment_type'] === 'percentage' 
                    ? sprintf('%+.1f%%', $rule['adjustment']) 
                    : sprintf('%+s', wc_price($rule['adjustment']));
                    
                $breakdown_html .= sprintf(
                    '<div style="font-size: 0.9em; color: #666; margin-left: 10px;">• %s: %s</div>',
                    esc_html($rule['rule_name']),
                    $adjustment_text
                );
            }
        }
        
        if (!empty($child_rules)) {
            $breakdown_html .= '<div><strong>' . __('Child Price Adjustments:', 'fp-esperienze') . '</strong></div>';
            foreach ($child_rules as $rule) {
                $adjustment_text = $rule['adjustment_type'] === 'percentage' 
                    ? sprintf('%+.1f%%', $rule['adjustment']) 
                    : sprintf('%+s', wc_price($rule['adjustment']));
                    
                $breakdown_html .= sprintf(
                    '<div style="font-size: 0.9em; color: #666; margin-left: 10px;">• %s: %s</div>',
                    esc_html($rule['rule_name']),
                    $adjustment_text
                );
            }
        }
        
        $breakdown_html .= '</div>';
        
        $item_data[] = [
            'key'   => __('Dynamic Pricing', 'fp-esperienze'),
            'value' => $breakdown_html,
        ];
    }
}