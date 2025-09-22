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
     * Option name used to persist historical pricing adjustments.
     */
    private const HISTORY_OPTION = 'fp_dynamic_pricing_history';

    /**
     * Number of days to retain pricing adjustment history.
     */
    private const HISTORY_RETENTION_DAYS = 90;

    /**
     * Maximum number of history entries to store.
     */
    private const HISTORY_MAX_ENTRIES = 500;

    /**
     * Seconds contained in a day (fallback when DAY_IN_SECONDS is unavailable).
     */
    private const SECONDS_PER_DAY = 86400;
    
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

        $columns = [
            'id',
            'product_id',
            'rule_type',
            'rule_name',
            'is_active',
            'priority',
            'date_start',
            'date_end',
            'applies_to',
            'days_before',
            'min_participants',
            'adjustment_type',
            'adult_adjustment',
            'child_adjustment',
            'created_at',
            'updated_at',
        ];

        $results = $wpdb->get_results($wpdb->prepare(
            'SELECT ' . implode(', ', $columns) . " FROM $table_name $where_clause ORDER BY priority ASC, rule_type ASC",
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

        $price_changed = abs($current_price - $base_price) > 0.0001;
        $should_log = $context['log_history'] ?? true;

        if ($price_changed && !empty($applied_rules)) {
            $GLOBALS['fp_applied_pricing_rules'][$product_id][$type] = $applied_rules;

            if ($should_log) {
                self::recordAdjustmentHistory(
                    $product_id,
                    $type,
                    $base_price,
                    $current_price,
                    $applied_rules,
                    $context
                );
            }
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
        $schedules = ScheduleManager::getSchedules($product_id);
        $base_adult_price = isset($schedules[0]) ? (float) $schedules[0]->price_adult : 0.0;
        $base_child_price = isset($schedules[0]) ? (float) $schedules[0]->price_child : 0.0;
        
        $context = [
            'booking_date' => $test_data['booking_date'] ?? date('Y-m-d'),
            'purchase_date' => $test_data['purchase_date'] ?? date('Y-m-d'),
            'total_participants' => absint(($test_data['qty_adult'] ?? 0) + ($test_data['qty_child'] ?? 0)),
            'log_history' => false,
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

    /**
     * Retrieve pricing adjustment history for analytics.
     *
     * @param int      $days            Number of days to include.
     * @param int|null $reference_time  Optional timestamp used for filtering.
     * @return array<int, array<string, mixed>>
     */
    public static function getAdjustmentHistory(int $days = 30, ?int $reference_time = null): array {
        $history = get_option(self::HISTORY_OPTION, []);

        if (!is_array($history)) {
            return [];
        }

        $days = max(0, $days);

        if (0 === $days) {
            return array_values($history);
        }

        $reference_time = $reference_time ?? time();
        $cutoff = $reference_time - ($days * self::SECONDS_PER_DAY);

        $filtered = array_filter(
            $history,
            static function ($entry) use ($cutoff) {
                if (!is_array($entry) || !isset($entry['timestamp'])) {
                    return false;
                }

                return (int) $entry['timestamp'] >= $cutoff;
            }
        );

        return array_values($filtered);
    }

    /**
     * Persist pricing adjustment metadata for later insights.
     *
     * @param int   $product_id    Product identifier.
     * @param string $type         Price type (adult/child).
     * @param float $base_price    Original price prior to adjustments.
     * @param float $final_price   Final price after adjustments.
     * @param array $applied_rules Applied rules metadata.
     * @param array $context       Pricing context values.
     * @return void
     */
    private static function recordAdjustmentHistory(
        int $product_id,
        string $type,
        float $base_price,
        float $final_price,
        array $applied_rules,
        array $context
    ): void {
        $history = get_option(self::HISTORY_OPTION, []);

        if (!is_array($history)) {
            $history = [];
        }

        $timestamp = isset($context['history_timestamp'])
            ? (int) $context['history_timestamp']
            : time();

        $booking_date = isset($context['booking_date']) && is_string($context['booking_date'])
            ? date('Y-m-d', strtotime($context['booking_date']))
            : date('Y-m-d', $timestamp);

        $participants = $context['total_participants'] ?? 0;
        if (function_exists('absint')) {
            $participants = absint($participants);
        } else {
            $participants = (int) max(0, (int) $participants);
        }

        $difference = $final_price - $base_price;
        $percent = $base_price > 0.0 ? ($difference / $base_price) * 100 : 0.0;

        $rules = array_map(
            static function ($rule) {
                return [
                    'rule_name' => is_array($rule) && isset($rule['rule_name']) ? (string) $rule['rule_name'] : ($rule->rule_name ?? ''),
                    'rule_type' => is_array($rule) && isset($rule['rule_type']) ? (string) $rule['rule_type'] : ($rule->rule_type ?? ''),
                    'adjustment' => (float) (is_array($rule) && isset($rule['adjustment']) ? $rule['adjustment'] : ($rule->adjustment ?? 0)),
                    'adjustment_type' => is_array($rule) && isset($rule['adjustment_type']) ? (string) $rule['adjustment_type'] : ($rule->adjustment_type ?? ''),
                ];
            },
            $applied_rules
        );

        $source = 'calculation';
        if (isset($context['history_source']) && is_string($context['history_source'])) {
            if (function_exists('sanitize_text_field')) {
                $source = sanitize_text_field($context['history_source']);
            } else {
                $source = preg_replace('/[^a-z0-9_\- ]/i', '', $context['history_source']);
            }
        }

        $history[] = [
            'timestamp' => $timestamp,
            'product_id' => $product_id,
            'price_type' => $type,
            'base_price' => round($base_price, 2),
            'final_price' => round($final_price, 2),
            'adjustment_amount' => round($difference, 2),
            'adjustment_percent' => round($percent, 2),
            'rules' => $rules,
            'total_participants' => $participants,
            'booking_date' => $booking_date,
            'source' => $source,
        ];

        $retention_cutoff = $timestamp - (self::HISTORY_RETENTION_DAYS * self::SECONDS_PER_DAY);

        $history = array_filter(
            $history,
            static function ($entry) use ($retention_cutoff) {
                if (!is_array($entry) || !isset($entry['timestamp'])) {
                    return false;
                }

                return (int) $entry['timestamp'] >= $retention_cutoff;
            }
        );

        if (count($history) > self::HISTORY_MAX_ENTRIES) {
            $history = array_slice(array_values($history), -self::HISTORY_MAX_ENTRIES);
        } else {
            $history = array_values($history);
        }

        update_option(self::HISTORY_OPTION, $history, false);
    }
}