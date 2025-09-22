<?php
/**
 * Analytics event tracker for funnel metrics.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use WC_Product;

defined('ABSPATH') || exit;

/**
 * Persist experience analytics events for the advanced analytics dashboard.
 */
class AnalyticsTracker {

    /**
     * Analytics events table name.
     */
    private string $tableName;

    /**
     * Whether table availability has been checked.
     */
    private bool $tableChecked = false;

    /**
     * Cached table availability flag.
     */
    private bool $tableAvailable = false;

    /**
     * Prevent duplicate checkout logs per request.
     */
    private bool $checkoutLogged = false;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;

        if (!isset($wpdb)) {
            return;
        }

        $this->tableName = $wpdb->prefix . 'fp_analytics_events';

        add_action('template_redirect', [$this, 'maybeLogProductView'], 20);
        add_action('woocommerce_add_to_cart', [$this, 'handleAddToCart'], 10, 6);
        add_action('woocommerce_before_checkout_form', [$this, 'handleCheckoutStart'], 1);
    }

    /**
     * Log product view and website visit events for experience products.
     */
    public function maybeLogProductView(): void {
        if (is_admin() || !function_exists('is_singular') || !is_singular('product')) {
            return;
        }

        $product_id = get_queried_object_id();
        if (!$product_id || !function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product || $product->get_type() !== 'experience') {
            return;
        }

        $session_id = $this->getSessionIdentifier();
        if ($session_id === null) {
            return;
        }

        $this->recordEvent('visit', $session_id, $product_id, null, true);
        $this->recordEvent('product_view', $session_id, $product_id, null, true);
    }

    /**
     * Log add to cart events for experience products.
     */
    public function handleAddToCart($cart_item_key, int $product_id, int $quantity, int $variation_id = 0, array $variation = [], array $cart_item_data = []): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
        if (!function_exists('wc_get_product')) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$product instanceof WC_Product || $product->get_type() !== 'experience') {
            return;
        }

        $session_id = $this->getSessionIdentifier();
        if ($session_id === null) {
            return;
        }

        $this->recordEvent('add_to_cart', $session_id, $product_id, $quantity);
    }

    /**
     * Log checkout start when the checkout form is displayed with an experience product in the cart.
     */
    public function handleCheckoutStart(): void {
        if ($this->checkoutLogged) {
            return;
        }

        $session_id = $this->getSessionIdentifier();
        if ($session_id === null || !function_exists('WC')) {
            return;
        }

        $wc = WC();
        if (!$wc || !isset($wc->cart) || !$wc->cart) {
            return;
        }

        $cart = $wc->cart;
        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            if (!$product instanceof WC_Product && isset($item['product_id'])) {
                $product = wc_get_product((int) $item['product_id']);
            }

            if ($product instanceof WC_Product && $product->get_type() === 'experience') {
                $this->checkoutLogged = true;
                $this->recordEvent('checkout_start', $session_id, 0, null, true);
                break;
            }
        }
    }

    /**
     * Record an analytics event.
     */
    private function recordEvent(string $event_type, string $session_id, int $product_id = 0, ?int $quantity = null, bool $dedupe = false): void {
        if (!$this->isTableAvailable()) {
            return;
        }

        if ($dedupe) {
            $date_key = gmdate('Y-m-d');
            $transient_key = 'fp_analytics_event_' . md5($event_type . '|' . $session_id . '|' . $product_id . '|' . $date_key);
            if (get_transient($transient_key)) {
                return;
            }

            $ttl = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
            set_transient($transient_key, 1, $ttl);
        }

        global $wpdb;

        $data = [
            'event_type' => $event_type,
            'product_id' => $product_id > 0 ? $product_id : null,
            'session_id' => $session_id,
            'customer_id' => get_current_user_id() ?: null,
            'quantity' => $quantity !== null ? max(0, $quantity) : null,
            'created_at' => current_time('mysql', true),
        ];

        $formats = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                unset($data[$key]);
                continue;
            }

            $formats[] = in_array($key, ['product_id', 'customer_id', 'quantity'], true) ? '%d' : '%s';
        }

        $wpdb->insert($this->tableName, $data, $formats);
    }

    /**
     * Get the WooCommerce session identifier.
     */
    private function getSessionIdentifier(): ?string {
        if (!function_exists('WC')) {
            return null;
        }

        $wc = WC();
        if (!$wc) {
            return null;
        }

        $session = $wc->session;
        if (!$session) {
            return null;
        }

        $customer_id = $session->get_customer_id();
        if (!empty($customer_id)) {
            return (string) $customer_id;
        }

        if (method_exists($session, 'get_session_id')) {
            $session_id = $session->get_session_id();
            if (!empty($session_id)) {
                return (string) $session_id;
            }
        }

        return null;
    }

    /**
     * Check whether the analytics events table is available.
     */
    private function isTableAvailable(): bool {
        if ($this->tableChecked) {
            return $this->tableAvailable;
        }

        $this->tableChecked = true;

        global $wpdb;
        if (!isset($wpdb)) {
            return false;
        }

        $result = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $this->tableName));
        $this->tableAvailable = !empty($result);

        return $this->tableAvailable;
    }
}
