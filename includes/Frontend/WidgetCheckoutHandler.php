<?php
/**
 * Widget Checkout Handler
 * Handles bookings from iframe widgets
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

defined('ABSPATH') || exit;

/**
 * Widget Checkout Handler class
 */
class WidgetCheckoutHandler {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'handleWidgetBooking']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueCheckoutScripts']);
    }

    /**
     * Handle widget booking on checkout page
     */
    public function handleWidgetBooking(): void {
        if (!is_admin() && isset($_GET['fp_widget_booking'])) {
            add_action('template_redirect', [$this, 'processWidgetBooking']);
        }
    }

    /**
     * Process widget booking and add to cart
     */
    public function processWidgetBooking(): void {
        // Verify we're on checkout page or cart page
        if (!is_checkout() && !is_cart()) {
            return;
        }

        // Sanitize and validate parameters
        $product_id = absint($_GET['product_id'] ?? 0);
        $adult_qty = absint($_GET['adult_qty'] ?? 0);
        $child_qty = absint($_GET['child_qty'] ?? 0);
        $return_url = isset($_GET['return_url']) ? esc_url_raw($_GET['return_url']) : '';

        if (!$product_id || ($adult_qty + $child_qty) === 0) {
            wc_add_notice(__('Invalid booking data received from widget.', 'fp-esperienze'), 'error');
            return;
        }

        // Verify product exists and is an experience
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            wc_add_notice(__('Experience not found.', 'fp-esperienze'), 'error');
            return;
        }

        // Clear cart to ensure clean booking
        WC()->cart->empty_cart();

        // Prepare cart item data
        $cart_item_data = [
            'fp_adult_qty' => $adult_qty,
            'fp_child_qty' => $child_qty,
            'fp_widget_booking' => true,
        ];

        if ($return_url) {
            $cart_item_data['fp_return_url'] = $return_url;
            // Store return URL in session for after-checkout redirect
            WC()->session->set('fp_widget_return_url', $return_url);
        }

        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart(
            $product_id,
            1, // quantity is always 1 for experiences
            0, // variation_id
            [], // variation
            $cart_item_data
        );

        if ($cart_item_key) {
            wc_add_notice(
                sprintf(
                    __('"%s" has been added to your cart.', 'fp-esperienze'),
                    $product->get_name()
                ),
                'success'
            );

            // Redirect to checkout if on cart page
            if (is_cart()) {
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        } else {
            wc_add_notice(__('Failed to add experience to cart.', 'fp-esperienze'), 'error');
        }
    }

    /**
     * Enqueue checkout scripts for widget integration
     */
    public function enqueueCheckoutScripts(): void {
        if (is_checkout() && WC()->session->get('fp_widget_return_url')) {
            wp_add_inline_script('wc-checkout', '
                jQuery(document).ready(function($) {
                    // Handle successful checkout
                    $("body").on("checkout_place_order_success", function(event, response) {
                        var returnUrl = "' . esc_js(WC()->session->get('fp_widget_return_url')) . '";
                        if (returnUrl && window.opener) {
                            // Notify parent window of successful booking
                            window.opener.postMessage({
                                type: "fp_widget_booking_success",
                                order_id: response.order_id || null,
                                return_url: returnUrl
                            }, "*");
                            
                            // Close popup or redirect
                            setTimeout(function() {
                                if (window.opener) {
                                    window.close();
                                } else {
                                    window.location.href = returnUrl;
                                }
                            }, 3000);
                        }
                    });
                });
            ');
        }
    }
}