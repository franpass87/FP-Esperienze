<?php
/**
 * WooCommerce Integration for Extras
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

use FP\Esperienze\Data\ExtraManager;

defined('ABSPATH') || exit;

/**
 * Extras integration with WooCommerce cart and orders
 */
class ExtrasIntegration {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', [$this, 'addCartItemData'], 10, 2);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'getCartItemFromSession'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'getItemData'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'addOrderItemMeta'], 10, 4);
        add_filter('woocommerce_cart_item_price', [$this, 'modifyCartItemPrice'], 10, 3);
        add_filter('woocommerce_cart_item_subtotal', [$this, 'modifyCartItemSubtotal'], 10, 3);
    }

    /**
     * Add extras data to cart item
     *
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @return array Modified cart item data
     */
    public function addCartItemData(array $cart_item_data, int $product_id): array {
        // Only process experience products
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return $cart_item_data;
        }

        // Check if extras data was submitted
        if (!isset($_POST['experience_extras']) || !is_array($_POST['experience_extras'])) {
            return $cart_item_data;
        }

        $extras_data = [];
        $total_extras_price = 0;

        foreach ($_POST['experience_extras'] as $extra_id) {
            $extra_id = intval($extra_id);
            $extra = ExtraManager::getExtra($extra_id);
            
            if (!$extra || !$extra['is_active']) {
                continue;
            }

            $quantity = 1;
            if (isset($_POST["extra_qty_{$extra_id}"])) {
                $quantity = max(1, min(intval($_POST["extra_qty_{$extra_id}"]), $extra['max_quantity']));
            }

            $extras_data[] = [
                'id' => $extra_id,
                'name' => $extra['name'],
                'price' => $extra['price'],
                'pricing_type' => $extra['pricing_type'],
                'quantity' => $quantity,
                'subtotal' => $extra['price'] * $quantity
            ];

            $total_extras_price += $extra['price'] * $quantity;
        }

        if (!empty($extras_data)) {
            $cart_item_data['fp_extras'] = $extras_data;
            $cart_item_data['fp_extras_total'] = $total_extras_price;
        }

        return $cart_item_data;
    }

    /**
     * Get cart item from session
     *
     * @param array $cart_item Cart item
     * @param array $values Session values
     * @return array Cart item
     */
    public function getCartItemFromSession(array $cart_item, array $values): array {
        if (isset($values['fp_extras'])) {
            $cart_item['fp_extras'] = $values['fp_extras'];
            $cart_item['fp_extras_total'] = $values['fp_extras_total'] ?? 0;
        }

        return $cart_item;
    }

    /**
     * Display extras in cart
     *
     * @param array $item_data Item data
     * @param array $cart_item Cart item
     * @return array Modified item data
     */
    public function getItemData(array $item_data, array $cart_item): array {
        if (!isset($cart_item['fp_extras']) || empty($cart_item['fp_extras'])) {
            return $item_data;
        }

        $item_data[] = [
            'name' => __('Extras', 'fp-esperienze'),
            'value' => $this->formatExtrasForDisplay($cart_item['fp_extras'])
        ];

        return $item_data;
    }

    /**
     * Add extras meta to order items
     *
     * @param \WC_Order_Item_Product $item Order item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart values
     * @param \WC_Order $order Order
     */
    public function addOrderItemMeta($item, string $cart_item_key, array $values, $order): void {
        if (!isset($values['fp_extras']) || empty($values['fp_extras'])) {
            return;
        }

        $item->add_meta_data('_fp_extras', $values['fp_extras']);
        $item->add_meta_data('_fp_extras_total', $values['fp_extras_total'] ?? 0);
        $item->add_meta_data(__('Extras', 'fp-esperienze'), $this->formatExtrasForDisplay($values['fp_extras']));
    }

    /**
     * Modify cart item price to include extras
     *
     * @param string $price Price HTML
     * @param array $cart_item Cart item
     * @param string $cart_item_key Cart item key
     * @return string Modified price HTML
     */
    public function modifyCartItemPrice(string $price, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item['fp_extras_total']) || $cart_item['fp_extras_total'] <= 0) {
            return $price;
        }

        $product_price = $cart_item['data']->get_price();
        $total_price = $product_price + $cart_item['fp_extras_total'];
        
        return wc_price($total_price);
    }

    /**
     * Modify cart item subtotal to include extras
     *
     * @param string $subtotal Subtotal HTML
     * @param array $cart_item Cart item
     * @param string $cart_item_key Cart item key
     * @return string Modified subtotal HTML
     */
    public function modifyCartItemSubtotal(string $subtotal, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item['fp_extras_total']) || $cart_item['fp_extras_total'] <= 0) {
            return $subtotal;
        }

        $product_subtotal = $cart_item['data']->get_price() * $cart_item['quantity'];
        $extras_subtotal = $cart_item['fp_extras_total'] * $cart_item['quantity'];
        $total_subtotal = $product_subtotal + $extras_subtotal;
        
        return wc_price($total_subtotal);
    }

    /**
     * Format extras for display
     *
     * @param array $extras Extras data
     * @return string Formatted extras string
     */
    private function formatExtrasForDisplay(array $extras): string {
        $formatted = [];
        
        foreach ($extras as $extra) {
            $line = $extra['name'];
            if ($extra['quantity'] > 1) {
                $line .= ' × ' . $extra['quantity'];
            }
            $line .= ' (' . wc_price($extra['subtotal']) . ')';
            $formatted[] = $line;
        }
        
        return implode(', ', $formatted);
    }
}