<?php
/**
 * Schedule Management
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Schedule manager class for CRUD operations
 */
class ScheduleManager {
    
    /**
     * Get schedules for a product
     *
     * @param int $product_id Product ID
     * @return array
     */
    public static function getSchedules(int $product_id): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d AND is_active = 1 ORDER BY day_of_week, start_time",
            $product_id
        ));
        
        return $results ?: [];
    }
    
    /**
     * Get schedules for a specific day
     *
     * @param int $product_id Product ID
     * @param int $day_of_week Day of week (0=Sunday, 1=Monday, etc.)
     * @return array
     */
    public static function getSchedulesForDay(int $product_id, int $day_of_week): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %d AND day_of_week = %d AND is_active = 1 ORDER BY start_time",
            $product_id,
            $day_of_week
        ));
        
        return $results ?: [];
    }
    
    /**
     * Create a new schedule
     *
     * @param array $data Schedule data
     * @return int|false Schedule ID on success, false on failure
     */
    public static function createSchedule(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        
        $defaults = [
            'duration_min' => 60,
            'capacity' => 10,
            'lang' => 'en',
            'meeting_point_id' => null,
            'price_adult' => 0.00,
            'price_child' => 0.00,
            'is_active' => 1
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'product_id' => (int) $data['product_id'],
                'day_of_week' => (int) $data['day_of_week'],
                'start_time' => sanitize_text_field($data['start_time']),
                'duration_min' => (int) $data['duration_min'],
                'capacity' => (int) $data['capacity'],
                'lang' => sanitize_text_field($data['lang']),
                'meeting_point_id' => $data['meeting_point_id'] ? (int) $data['meeting_point_id'] : null,
                'price_adult' => (float) $data['price_adult'],
                'price_child' => (float) $data['price_child'],
                'is_active' => (int) $data['is_active']
            ],
            [
                '%d', '%d', '%s', '%d', '%d', '%s', '%d', '%f', '%f', '%d'
            ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update a schedule
     *
     * @param int $id Schedule ID
     * @param array $data Schedule data
     * @return bool
     */
    public static function updateSchedule(int $id, array $data): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        
        $update_data = [];
        $formats = [];
        
        if (isset($data['day_of_week'])) {
            $update_data['day_of_week'] = (int) $data['day_of_week'];
            $formats[] = '%d';
        }
        
        if (isset($data['start_time'])) {
            $update_data['start_time'] = sanitize_text_field($data['start_time']);
            $formats[] = '%s';
        }
        
        if (isset($data['duration_min'])) {
            $update_data['duration_min'] = (int) $data['duration_min'];
            $formats[] = '%d';
        }
        
        if (isset($data['capacity'])) {
            $update_data['capacity'] = (int) $data['capacity'];
            $formats[] = '%d';
        }
        
        if (isset($data['lang'])) {
            $update_data['lang'] = sanitize_text_field($data['lang']);
            $formats[] = '%s';
        }
        
        if (isset($data['meeting_point_id'])) {
            $update_data['meeting_point_id'] = $data['meeting_point_id'] ? (int) $data['meeting_point_id'] : null;
            $formats[] = $data['meeting_point_id'] ? '%d' : '%s';
        }
        
        if (isset($data['price_adult'])) {
            $update_data['price_adult'] = (float) $data['price_adult'];
            $formats[] = '%f';
        }
        
        if (isset($data['price_child'])) {
            $update_data['price_child'] = (float) $data['price_child'];
            $formats[] = '%f';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = (int) $data['is_active'];
            $formats[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Delete a schedule
     *
     * @param int $id Schedule ID
     * @return bool
     */
    public static function deleteSchedule(int $id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
        
        return $result !== false;
    }
    
    /**
     * Get a single schedule by ID
     *
     * @param int $id Schedule ID
     * @return object|null
     */
    public static function getSchedule(int $id): ?object {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
        
        return $result ?: null;
    }
}