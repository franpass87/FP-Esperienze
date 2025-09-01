<?php
/**
 * Schedule Helper
 *
 * @package FP\Esperienze\Helpers
 */

namespace FP\Esperienze\Helpers;

defined('ABSPATH') || exit;

/**
 * Helper class for schedule-related operations
 */
class ScheduleHelper {
    
    /**
     * Hydrate schedule with effective values from product meta when override is NULL/empty
     *
     * @param object $schedule Schedule object from database
     * @param int $product_id Product ID
     * @return object Schedule object with effective properties added
     */
    public static function hydrateEffectiveValues($schedule, int $product_id) {
        if (!$schedule) {
            return $schedule;
        }
        
        // Clone the schedule to avoid modifying the original
        $hydrated = clone $schedule;
        
        // Add effective properties
        $hydrated->effective = new \stdClass();
        
        // Duration: use schedule value if not null/empty, otherwise product meta
        $hydrated->effective->duration_min = self::getEffectiveValue(
            $schedule->duration_min ?? null,
            get_post_meta($product_id, '_fp_exp_duration', true),
            60 // fallback default
        );
        
        // Capacity: use schedule value if not null/empty, otherwise product meta
        $hydrated->effective->capacity = self::getEffectiveValue(
            $schedule->capacity ?? null,
            get_post_meta($product_id, '_fp_exp_capacity', true),
            10 // fallback default
        );
        
        // Language: use schedule value if not null/empty, otherwise product meta
        $hydrated->effective->lang = self::getEffectiveValue(
            $schedule->lang ?? null,
            get_post_meta($product_id, '_fp_exp_language', true),
            'en' // fallback default
        );
        
        // Meeting Point: use schedule value if not null/empty, otherwise product meta
        $hydrated->effective->meeting_point_id = self::getEffectiveValue(
            $schedule->meeting_point_id ?? null,
            get_post_meta($product_id, '_fp_exp_meeting_point_id', true),
            null // no fallback for meeting point
        );
        
        // Adult Price: use schedule value if not null/empty, otherwise WooCommerce regular price
        $hydrated->effective->price_adult = self::getEffectiveValue(
            $schedule->price_adult ?? null,
            get_post_meta($product_id, '_regular_price', true),
            0.00 // fallback default
        );
        
        // Child Price: use schedule value if not null/empty, otherwise product meta
        $hydrated->effective->price_child = self::getEffectiveValue(
            $schedule->price_child ?? null,
            get_post_meta($product_id, '_fp_exp_price_child', true),
            0.00 // fallback default
        );
        
        return $hydrated;
    }
    
    /**
     * Get effective value: override if not empty, otherwise default, otherwise fallback
     *
     * @param mixed $override_value Override value from schedule
     * @param mixed $default_value Default value from product meta
     * @param mixed $fallback_value Fallback value if both are empty
     * @return mixed Effective value
     */
    private static function getEffectiveValue($override_value, $default_value, $fallback_value) {
        // Check if override value is meaningful (not null, not empty string)
        // For numeric values, 0 is considered valid
        if ($override_value !== null && $override_value !== '') {
            return $override_value;
        }
        
        // Check if default value is meaningful
        // For numeric values, 0 is considered valid
        if ($default_value !== null && $default_value !== '') {
            return $default_value;
        }
        
        // Return fallback
        return $fallback_value;
    }
    
    /**
     * Aggregate existing schedules into builder-friendly format
     * Groups schedules with same attributes by days
     *
     * @param array $schedules Array of schedule objects
     * @param int $product_id Product ID for meta context
     * @return array Array with 'time_slots' and 'raw_schedules' keys
     */
    public static function aggregateSchedulesForBuilder(array $schedules, int $product_id): array {
        $time_slots = [];
        $raw_schedules = [];
        
        // Group schedules by their effective properties (except day_of_week)
        $groups = [];
        
        foreach ($schedules as $schedule) {
            $hydrated = self::hydrateEffectiveValues($schedule, $product_id);
            
            // Create grouping key based on schedule attributes (excluding day and ID)
            $key = sprintf(
                '%s_%d_%d_%s_%s_%.2f_%.2f',
                $schedule->start_time,
                $hydrated->effective->duration_min,
                $hydrated->effective->capacity,
                $hydrated->effective->lang,
                $hydrated->effective->meeting_point_id ?: 'null',
                $hydrated->effective->price_adult,
                $hydrated->effective->price_child
            );
            
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'start_time' => $schedule->start_time,
                    'duration_min' => $hydrated->effective->duration_min,
                    'capacity' => $hydrated->effective->capacity,
                    'lang' => $hydrated->effective->lang,
                    'meeting_point_id' => $hydrated->effective->meeting_point_id,
                    'price_adult' => $hydrated->effective->price_adult,
                    'price_child' => $hydrated->effective->price_child,
                    'days' => [],
                    'schedule_ids' => [],
                    'can_aggregate' => true
                ];
            }
            
            $groups[$key]['days'][] = (int) $schedule->day_of_week;
            $groups[$key]['schedule_ids'][] = $schedule->id;
        }
        
        // Convert groups to time slots and identify non-aggregatable schedules
        foreach ($groups as $group) {
            // Always try to create a time slot if it has valid data
            // Single day slots with overrides can still be represented in the builder
            $time_slots[] = [
                'start_time' => $group['start_time'],
                'days' => $group['days'],
                'overrides' => self::extractOverrides($group, $product_id),
                'schedule_ids' => $group['schedule_ids']
            ];
        }
        
        return [
            'time_slots' => $time_slots,
            'raw_schedules' => $raw_schedules
        ];
    }
    
    /**
     * Check if a schedule slot can be represented using inheritance
     *
     * @param array $group Schedule group data
     * @param int $product_id Product ID
     * @return bool True if slot uses inheritance, false if has specific overrides
     */
    private static function isInheritableSlot(array $group, int $product_id): bool {
        // Get product defaults
        $default_duration = get_post_meta($product_id, '_fp_exp_duration', true) ?: 60;
        $default_capacity = get_post_meta($product_id, '_fp_exp_capacity', true) ?: 10;
        $default_lang = get_post_meta($product_id, '_fp_exp_language', true) ?: 'en';
        $default_meeting_point = get_post_meta($product_id, '_fp_exp_meeting_point_id', true);
        $default_price_adult = get_post_meta($product_id, '_regular_price', true) ?: 0.00;
        $default_price_child = get_post_meta($product_id, '_fp_exp_price_child', true) ?: 0.00;
        
        // Check if all values match defaults (can inherit) - handle null values as "use default"
        return (
            ($group['duration_min'] === null || (int)$group['duration_min'] === (int)$default_duration) &&
            ($group['capacity'] === null || (int)$group['capacity'] === (int)$default_capacity) &&
            ($group['lang'] === null || trim($group['lang']) === trim($default_lang)) &&
            ($group['meeting_point_id'] === null || (int)$group['meeting_point_id'] === (int)$default_meeting_point) &&
            ($group['price_adult'] === null || abs((float)$group['price_adult'] - (float)$default_price_adult) < 0.01) &&
            ($group['price_child'] === null || abs((float)$group['price_child'] - (float)$default_price_child) < 0.01)
        );
    }
    
    /**
     * Extract overrides that differ from product defaults
     *
     * @param array $group Schedule group data
     * @param int $product_id Product ID
     * @return array Array of override values that differ from defaults
     */
    private static function extractOverrides(array $group, int $product_id): array {
        $overrides = [];
        
        // Get product defaults
        $default_duration = get_post_meta($product_id, '_fp_exp_duration', true) ?: 60;
        $default_capacity = get_post_meta($product_id, '_fp_exp_capacity', true) ?: 10;
        $default_lang = get_post_meta($product_id, '_fp_exp_language', true) ?: 'en';
        $default_meeting_point = get_post_meta($product_id, '_fp_exp_meeting_point_id', true);
        $default_price_adult = get_post_meta($product_id, '_regular_price', true) ?: 0.00;
        $default_price_child = get_post_meta($product_id, '_fp_exp_price_child', true) ?: 0.00;
        
        // Only include overrides that differ from defaults (handle null values properly)
        if (isset($group['duration_min']) && $group['duration_min'] !== null && 
            (int)$group['duration_min'] !== (int)$default_duration) {
            $overrides['duration_min'] = $group['duration_min'];
        }
        
        if (isset($group['capacity']) && $group['capacity'] !== null && 
            (int)$group['capacity'] !== (int)$default_capacity) {
            $overrides['capacity'] = $group['capacity'];
        }
        
        if (isset($group['lang']) && $group['lang'] !== null && 
            trim($group['lang']) !== trim($default_lang)) {
            $overrides['lang'] = $group['lang'];
        }
        
        if (isset($group['meeting_point_id']) && $group['meeting_point_id'] !== null && 
            (int)$group['meeting_point_id'] !== (int)$default_meeting_point) {
            $overrides['meeting_point_id'] = $group['meeting_point_id'];
        }
        
        if (isset($group['price_adult']) && $group['price_adult'] !== null && 
            abs((float)$group['price_adult'] - (float)$default_price_adult) >= 0.01) {
            $overrides['price_adult'] = $group['price_adult'];
        }
        
        if (isset($group['price_child']) && $group['price_child'] !== null && 
            abs((float)$group['price_child'] - (float)$default_price_child) >= 0.01) {
            $overrides['price_child'] = $group['price_child'];
        }
        
        return $overrides;
    }
}