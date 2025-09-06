<?php
/**
 * Dynamic Pricing Hooks
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use FP\Esperienze\Core\CapabilityManager;

defined('ABSPATH') || exit;

/**
 * Dynamic pricing hooks class for integrating with existing pricing system
 */
class DynamicPricingHooks {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into existing pricing filters
        add_filter('fp_esperienze_adult_price', [$this, 'applyDynamicPricing'], 10, 2);
        add_filter('fp_esperienze_child_price', [$this, 'applyDynamicPricing'], 10, 2);
        
        // AJAX endpoints for admin
        add_action('wp_ajax_fp_save_pricing_rule', [$this, 'savePricingRule']);
        add_action('wp_ajax_fp_delete_pricing_rule', [$this, 'deletePricingRule']);
        add_action('wp_ajax_fp_preview_pricing', [$this, 'previewPricing']);
    }
    
    /**
     * Apply dynamic pricing to base prices
     *
     * @param float $base_price Base price
     * @param int $product_id Product ID
     * @return float Modified price
     */
    public function applyDynamicPricing(float $base_price, int $product_id): float {
        // Determine if this is adult or child price based on current filter
        $current_filter = current_filter();
        $type = strpos($current_filter, 'adult') !== false ? 'adult' : 'child';
        
        // Get booking context from cart or current state
        $context = $this->getPricingContext($product_id);
        
        return DynamicPricingManager::calculateDynamicPrice($base_price, $product_id, $type, $context);
    }
    
    /**
     * Get pricing context for calculations
     *
     * @param int $product_id Product ID
     * @return array Context data
     */
    private function getPricingContext(int $product_id): array {
        $context = [
            'booking_date' => date('Y-m-d'),
            'purchase_date' => date('Y-m-d'),
            'total_participants' => 0
        ];
        
        // Try to get context from cart if available
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                if (isset($cart_item['fp_experience']) && 
                    $cart_item['data']->get_id() == $product_id) {
                    
                    $experience_data = $cart_item['fp_experience'];
                    
                    if (!empty($experience_data['slot_start'])) {
                        $context['booking_date'] = date('Y-m-d', strtotime($experience_data['slot_start']));
                    }
                    
                    $context['total_participants'] = 
                        absint($experience_data['qty_adult'] ?? 0) + 
                        absint($experience_data['qty_child'] ?? 0);
                    
                    break;
                }
            }
        }
        
        // Try to get context from $_POST if in admin or AJAX
        if (!empty($_POST['fp_experience'])) {
            $exp_data = $_POST['fp_experience'];
            
            if (!empty($exp_data['slot_start'])) {
                $context['booking_date'] = date('Y-m-d', strtotime(sanitize_text_field($exp_data['slot_start'])));
            }
            
            $context['total_participants'] = 
                absint($exp_data['qty_adult'] ?? 0) + 
                absint($exp_data['qty_child'] ?? 0);
        }
        
        return $context;
    }
    
    /**
     * AJAX handler for saving pricing rules
     */
    public function savePricingRule() {
        check_ajax_referer('fp_pricing_nonce', 'nonce');
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Permission denied.', 'fp-esperienze')]);
        }
        
        $rule_data = [
            'id' => absint($_POST['id'] ?? 0),
            'product_id' => absint($_POST['product_id'] ?? 0),
            'rule_type' => sanitize_text_field($_POST['rule_type'] ?? ''),
            'rule_name' => sanitize_text_field($_POST['rule_name'] ?? ''),
            'is_active' => isset($_POST['is_active']),
            'priority' => absint($_POST['priority'] ?? 0),
            'date_start' => sanitize_text_field($_POST['date_start'] ?? ''),
            'date_end' => sanitize_text_field($_POST['date_end'] ?? ''),
            'applies_to' => sanitize_text_field($_POST['applies_to'] ?? ''),
            'days_before' => absint($_POST['days_before'] ?? 0),
            'min_participants' => absint($_POST['min_participants'] ?? 0),
            'adjustment_type' => sanitize_text_field($_POST['adjustment_type'] ?? 'percentage'),
            'adult_adjustment' => floatval($_POST['adult_adjustment'] ?? 0),
            'child_adjustment' => floatval($_POST['child_adjustment'] ?? 0)
        ];
        
        $rule_id = DynamicPricingManager::saveRule($rule_data);
        
        if ($rule_id) {
            wp_send_json_success([
                'message' => __('Pricing rule saved successfully.', 'fp-esperienze'),
                'rule_id' => $rule_id
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save pricing rule.', 'fp-esperienze')]);
        }
    }
    
    /**
     * AJAX handler for deleting pricing rules
     */
    public function deletePricingRule() {
        check_ajax_referer('fp_pricing_nonce', 'nonce');
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Permission denied.', 'fp-esperienze')]);
        }
        
        $rule_id = absint($_POST['rule_id'] ?? 0);
        
        if (DynamicPricingManager::deleteRule($rule_id)) {
            wp_send_json_success(['message' => __('Pricing rule deleted successfully.', 'fp-esperienze')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete pricing rule.', 'fp-esperienze')]);
        }
    }
    
    /**
     * AJAX handler for pricing preview
     */
    public function previewPricing() {
        check_ajax_referer('fp_pricing_nonce', 'nonce');
        
        if (!CapabilityManager::canManageFPEsperienze()) {
            wp_send_json_error(['message' => __('Permission denied.', 'fp-esperienze')]);
        }
        
        $product_id = absint($_POST['product_id'] ?? 0);
        $test_data = [
            'booking_date' => sanitize_text_field($_POST['booking_date'] ?? date('Y-m-d')),
            'purchase_date' => sanitize_text_field($_POST['purchase_date'] ?? date('Y-m-d')),
            'qty_adult' => absint($_POST['qty_adult'] ?? 1),
            'qty_child' => absint($_POST['qty_child'] ?? 0)
        ];
        
        $preview = DynamicPricingManager::previewPricing($product_id, $test_data);
        
        wp_send_json_success($preview);
    }
}
