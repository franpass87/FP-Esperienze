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
     * @param string $schedule_type Optional filter by schedule type ('recurring', 'fixed', or null for all)
     * @return array
     */
    public static function getSchedules(int $product_id, ?string $schedule_type = null): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        
        $where_clause = "WHERE product_id = %d AND is_active = 1";
        $params = [$product_id];
        
        if ($schedule_type !== null) {
            $where_clause .= " AND schedule_type = %s";
            $params[] = $schedule_type;
        }
        
        // Order by appropriate field based on type
        $order_clause = "ORDER BY 
            CASE WHEN schedule_type = 'fixed' THEN event_date ELSE NULL END,
            CASE WHEN schedule_type = 'recurring' THEN day_of_week ELSE NULL END,
            start_time";
        
        $sql = "SELECT * FROM $table_name $where_clause $order_clause";
        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        
        return $results ?: [];
    }
    
    /**
     * Get schedules for a specific day (supports both recurring and event schedules)
     *
     * @param int $product_id Product ID
     * @param int $day_of_week Day of week (0=Sunday, 1=Monday, etc.)
     * @return array
     */
    public static function getSchedulesForDay(int $product_id, int $day_of_week): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE product_id = %d 
             AND ((schedule_type = 'recurring' AND day_of_week = %d) OR schedule_type = 'fixed')
             AND is_active = 1 
             ORDER BY start_time",
            $product_id,
            $day_of_week
        ));
        
        return $results ?: [];
    }
    
    /**
     * Get event schedules for a specific date
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array
     */
    public static function getEventSchedulesForDate(int $product_id, string $date): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE product_id = %d 
             AND schedule_type = 'fixed' 
             AND event_date = %s 
             AND is_active = 1 
             ORDER BY start_time",
            $product_id,
            $date
        ));
        
        return $results ?: [];
    }
    
    /**
     * Get all recurring schedules for a product
     *
     * @param int $product_id Product ID
     * @return array
     */
    public static function getRecurringSchedules(int $product_id): array {
        return self::getSchedules($product_id, 'recurring');
    }
    
    /**
     * Get all event schedules for a product
     *
     * @param int $product_id Product ID
     * @return array
     */
    public static function getEventSchedules(int $product_id): array {
        return self::getSchedules($product_id, 'fixed');
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
        
        // Only set required defaults - allow override fields to be NULL for inheritance
        $required_defaults = [
            'schedule_type' => 'recurring',
            'is_active' => 1
        ];
        
        $data = wp_parse_args($data, $required_defaults);
        
        // Validate schedule type and required fields
        if (!in_array($data['schedule_type'], ['recurring', 'fixed'])) {
            return false;
        }
        
        // Prepare base insert data
        $insert_data = [
            'product_id' => (int) $data['product_id'],
            'schedule_type' => sanitize_text_field($data['schedule_type']),
            'start_time' => sanitize_text_field($data['start_time']),
            'is_active' => (int) $data['is_active']
        ];
        
        $formats = ['%d', '%s', '%s', '%d'];
        
        // Handle schedule type specific fields
        if ($data['schedule_type'] === 'recurring') {
            if (empty($data['day_of_week']) && $data['day_of_week'] !== 0 && $data['day_of_week'] !== '0') {
                return false; // day_of_week is required for recurring schedules
            }
            $insert_data['day_of_week'] = (int) $data['day_of_week'];
            $formats[] = '%d';
        } elseif ($data['schedule_type'] === 'fixed') {
            if (empty($data['event_date'])) {
                return false; // event_date is required for fixed schedules
            }
            $insert_data['event_date'] = sanitize_text_field($data['event_date']);
            $formats[] = '%s';
        }
        
        // Handle override fields - allow NULL for inheritance
        if (isset($data['duration_min']) && $data['duration_min'] !== '' && $data['duration_min'] !== null) {
            $insert_data['duration_min'] = (int) $data['duration_min'];
            $formats[] = '%d';
        }
        
        if (isset($data['capacity']) && $data['capacity'] !== '' && $data['capacity'] !== null) {
            $insert_data['capacity'] = (int) $data['capacity'];
            $formats[] = '%d';
        }
        
        if (isset($data['lang']) && $data['lang'] !== '' && $data['lang'] !== null) {
            $insert_data['lang'] = sanitize_text_field($data['lang']);
            $formats[] = '%s';
        }
        
        if (isset($data['meeting_point_id']) && $data['meeting_point_id'] !== '' && $data['meeting_point_id'] !== null) {
            $insert_data['meeting_point_id'] = (int) $data['meeting_point_id'];
            $formats[] = '%d';
        }
        
        if (isset($data['price_adult']) && $data['price_adult'] !== '' && $data['price_adult'] !== null) {
            $insert_data['price_adult'] = (float) $data['price_adult'];
            $formats[] = '%f';
        }
        
        if (isset($data['price_child']) && $data['price_child'] !== '' && $data['price_child'] !== null) {
            $insert_data['price_child'] = (float) $data['price_child'];
            $formats[] = '%f';
        }
        
        $result = $wpdb->insert($table_name, $insert_data, $formats);
        
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