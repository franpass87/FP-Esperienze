<?php
/**
 * Dynamic Pricing Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Dynamic pricing manager class for CRUD operations and price calculations
 */
class DynamicPricingManager {

    /**
     * Cache group for dynamic pricing rules.
     */
    private const CACHE_GROUP = 'fp_dynamic_pricing_rules';
    
    /**
     * Get all pricing rules for a product
     *
     * @param int $product_id Product ID
     * @param bool $active_only Whether to return only active rules
     * @return array
     */
    public static function getProductRules(int $product_id, bool $active_only = true): array {
        global $wpdb;

        $cache_key = self::getCacheKey($product_id, $active_only);
        $cached    = wp_cache_get($cache_key, self::CACHE_GROUP);
        if (false !== $cached) {
            return $cached;
        }

        $table_name  = $wpdb->prefix . 'fp_dynamic_pricing_rules';
        $where_clause = 'WHERE product_id = %d';
        $params       = [$product_id];

        if ($active_only) {
            $where_clause .= ' AND is_active = 1';
        }

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT id, product_id, rule_type, priority, conditions, action_type, action_value, start_date, end_date, is_active FROM $table_name $where_clause ORDER BY priority ASC, rule_type ASC",
            ...$params
        ));

        $results = $results ?: [];
        wp_cache_set($cache_key, $results, self::CACHE_GROUP);

        return $results;
    }
    
    /**
     * Save pricing rule
     *
     * @param array $data Rule data
     * @return int|false Rule ID on success, false on failure
     */
    public static function saveRule(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_dynamic_pricing_rules';
        
        // Sanitize and validate data
        $rule_data = [
            'product_id' => absint($data['product_id'] ?? 0),
            'rule_type' => sanitize_text_field($data['rule_type'] ?? ''),
            'rule_name' => sanitize_text_field($data['rule_name'] ?? ''),
            'is_active' => isset($data['is_active']) ? 1 : 0,
            'priority' => absint($data['priority'] ?? 0),
            'date_start' => !empty($data['date_start']) ? sanitize_text_field($data['date_start']) : null,
            'date_end' => !empty($data['date_end']) ? sanitize_text_field($data['date_end']) : null,
            'applies_to' => !empty($data['applies_to']) ? sanitize_text_field($data['applies_to']) : null,
            'days_before' => !empty($data['days_before']) ? absint($data['days_before']) : null,
            'min_participants' => !empty($data['min_participants']) ? absint($data['min_participants']) : null,
            'adjustment_type' => sanitize_text_field($data['adjustment_type'] ?? 'percentage'),
            'adult_adjustment' => floatval($data['adult_adjustment'] ?? 0),
            'child_adjustment' => floatval($data['child_adjustment'] ?? 0)
        ];
        
        // Validate required fields
        if (!$rule_data['product_id'] || !$rule_data['rule_type'] || !$rule_data['rule_name']) {
            return false;
        }
        
        $rule_id = absint($data['id'] ?? 0);
        
        if ($rule_id > 0) {
            // Update existing rule
            $result = $wpdb->update(
                $table_name,
                $rule_data,
                ['id' => $rule_id],
                ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%f'],
                ['%d']
            );

            if (false !== $result) {
                self::clearCache($rule_data['product_id']);
                return $rule_id;
            }

            return false;
        } else {
            // Create new rule
            $result = $wpdb->insert(
                $table_name,
                $rule_data,
                ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%f', '%f']
            );

            if ($result) {
                self::clearCache($rule_data['product_id']);
                return $wpdb->insert_id;
            }

            return false;
        }
    }
    
    /**
     * Delete a pricing rule
     *
     * @param int $rule_id Rule ID
     * @return bool
     */
    public static function deleteRule(int $rule_id): bool {
        global $wpdb;

        $table_name  = $wpdb->prefix . 'fp_dynamic_pricing_rules';
        $product_id  = (int) $wpdb->get_var($wpdb->prepare("SELECT product_id FROM $table_name WHERE id = %d", $rule_id));
        $result      = $wpdb->delete(
            $table_name,
            ['id' => $rule_id],
            ['%d']
        );

        if (false !== $result && $product_id) {
            self::clearCache($product_id);
        }

        return $result !== false;
    }

    /**
     * Generate cache key for product rules.
     *
     * @param int  $product_id  Product ID.
     * @param bool $active_only Whether to return only active rules.
     * @return string
     */
    private static function getCacheKey(int $product_id, bool $active_only): string {
        return 'product_' . $product_id . ($active_only ? '_active' : '_all');
    }

    /**
     * Clear cached rules for a product.
     *
     * @param int $product_id Product ID.
     * @return void
     */
    private static function clearCache(int $product_id): void {
        wp_cache_delete(self::getCacheKey($product_id, true), self::CACHE_GROUP);
        wp_cache_delete(self::getCacheKey($product_id, false), self::CACHE_GROUP);
    }
    
    /**
     * Calculate dynamic price for adult or child
     *
     * @param float $base_price Base price
     * @param int $product_id Product ID
     * @param string $type 'adult' or 'child'
     * @param array $context Context data (date, participants, etc.)
     * @return float Modified price
     */
    public static function calculateDynamicPrice(float $base_price, int $product_id, string $type, array $context = []): float {
        $rules = self::getProductRules($product_id, true);
        
        if (empty($rules)) {
            return $base_price;
        }
        
        $current_price = $base_price;
        $applied_rules = [];
        
        // Apply rules in priority order: seasonal → weekend/weekday → early-bird → group
        $rule_order = ['seasonal', 'weekend_weekday', 'early_bird', 'group'];
        
        foreach ($rule_order as $rule_type) {
            foreach ($rules as $rule) {
                if ($rule->rule_type !== $rule_type) {
                    continue;
                }
                
                if (self::shouldApplyRule($rule, $context)) {
                    $adjustment_field = $type === 'adult' ? 'adult_adjustment' : 'child_adjustment';
                    $adjustment = floatval($rule->$adjustment_field);
                    
                    if ($adjustment != 0) {
                        if ($rule->adjustment_type === 'percentage') {
                            $current_price = $current_price * (1 + $adjustment / 100);
                        } else {
                            $current_price = $current_price + $adjustment;
                        }
                        
                        $applied_rules[] = [
                            'rule_name' => $rule->rule_name,
                            'rule_type' => $rule->rule_type,
                            'adjustment' => $adjustment,
                            'adjustment_type' => $rule->adjustment_type
                        ];
                    }
                }
            }
        }
        
        // Ensure price doesn't go below 0
        $current_price = max(0, $current_price);
        
        // Store applied rules in context for display purposes
        if (!empty($applied_rules)) {
            $GLOBALS['fp_applied_pricing_rules'][$product_id][$type] = $applied_rules;
        }
        
        return $current_price;
    }
    
    /**
     * Check if a rule should be applied based on context
     *
     * @param object $rule Rule object
     * @param array $context Context data
     * @return bool
     */
    private static function shouldApplyRule(object $rule, array $context): bool {
        switch ($rule->rule_type) {
            case 'seasonal':
                return self::checkSeasonalRule($rule, $context);
            case 'weekend_weekday':
                return self::checkWeekendWeekdayRule($rule, $context);
            case 'early_bird':
                return self::checkEarlyBirdRule($rule, $context);
            case 'group':
                return self::checkGroupRule($rule, $context);
            default:
                return false;
        }
    }
    
    /**
     * Check seasonal rule
     *
     * @param object $rule Rule object
     * @param array $context Context data
     * @return bool
     */
    private static function checkSeasonalRule(object $rule, array $context): bool {
        $booking_date = $context['booking_date'] ?? date('Y-m-d');
        
        if (!$rule->date_start || !$rule->date_end) {
            return false;
        }
        
        return $booking_date >= $rule->date_start && $booking_date <= $rule->date_end;
    }
    
    /**
     * Check weekend/weekday rule
     *
     * @param object $rule Rule object
     * @param array $context Context data
     * @return bool
     */
    private static function checkWeekendWeekdayRule(object $rule, array $context): bool {
        $booking_date = $context['booking_date'] ?? date('Y-m-d');
        $day_of_week = date('w', strtotime($booking_date)); // 0 = Sunday, 6 = Saturday
        
        $is_weekend = ($day_of_week == 0 || $day_of_week == 6);
        
        if ($rule->applies_to === 'weekend') {
            return $is_weekend;
        } elseif ($rule->applies_to === 'weekday') {
            return !$is_weekend;
        }
        
        return false;
    }
    
    /**
     * Check early bird rule
     *
     * @param object $rule Rule object
     * @param array $context Context data
     * @return bool
     */
    private static function checkEarlyBirdRule(object $rule, array $context): bool {
        $booking_date = $context['booking_date'] ?? date('Y-m-d');
        $purchase_date = $context['purchase_date'] ?? date('Y-m-d');
        
        if (!$rule->days_before) {
            return false;
        }
        
        $days_diff = (strtotime($booking_date) - strtotime($purchase_date)) / (60 * 60 * 24);
        
        return $days_diff >= $rule->days_before;
    }
    
    /**
     * Check group rule
     *
     * @param object $rule Rule object
     * @param array $context Context data
     * @return bool
     */
    private static function checkGroupRule(object $rule, array $context): bool {
        $total_participants = absint($context['total_participants'] ?? 0);
        
        if (!$rule->min_participants) {
            return false;
        }
        
        return $total_participants >= $rule->min_participants;
    }
    
    /**
     * Get applied pricing rules breakdown for display
     *
     * @param int $product_id Product ID
     * @param string $type 'adult' or 'child'
     * @return array
     */
    public static function getAppliedRulesBreakdown(int $product_id, string $type): array {
        return $GLOBALS['fp_applied_pricing_rules'][$product_id][$type] ?? [];
    }
    
    /**
     * Preview price calculation for testing
     *
     * @param int $product_id Product ID
     * @param array $test_data Test scenario data
     * @return array Price breakdown
     */
    public static function previewPricing(int $product_id, array $test_data): array {
        $base_adult_price = floatval(get_post_meta($product_id, '_experience_adult_price', true) ?: 0);
        $base_child_price = floatval(get_post_meta($product_id, '_experience_child_price', true) ?: 0);
        
        $context = [
            'booking_date' => $test_data['booking_date'] ?? date('Y-m-d'),
            'purchase_date' => $test_data['purchase_date'] ?? date('Y-m-d'),
            'total_participants' => absint(($test_data['qty_adult'] ?? 0) + ($test_data['qty_child'] ?? 0))
        ];
        
        // Clear previous applied rules
        unset($GLOBALS['fp_applied_pricing_rules'][$product_id]);
        
        $final_adult_price = self::calculateDynamicPrice($base_adult_price, $product_id, 'adult', $context);
        $final_child_price = self::calculateDynamicPrice($base_child_price, $product_id, 'child', $context);
        
        $adult_rules = self::getAppliedRulesBreakdown($product_id, 'adult');
        $child_rules = self::getAppliedRulesBreakdown($product_id, 'child');
        
        return [
            'base_prices' => [
                'adult' => $base_adult_price,
                'child' => $base_child_price
            ],
            'final_prices' => [
                'adult' => $final_adult_price,
                'child' => $final_child_price
            ],
            'applied_rules' => [
                'adult' => $adult_rules,
                'child' => $child_rules
            ],
            'total' => [
                'base' => ($base_adult_price * ($test_data['qty_adult'] ?? 0)) + ($base_child_price * ($test_data['qty_child'] ?? 0)),
                'final' => ($final_adult_price * ($test_data['qty_adult'] ?? 0)) + ($final_child_price * ($test_data['qty_child'] ?? 0))
            ]
        ];
    }
}