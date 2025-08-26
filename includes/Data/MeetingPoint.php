<?php
/**
 * Meeting Point Data Model
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Meeting Point CRUD operations
 */
class MeetingPoint {

    /**
     * Table name
     *
     * @var string
     */
    private static $table_name = 'fp_meeting_points';

    /**
     * Get meeting point by ID
     *
     * @param int $id Meeting point ID
     * @return object|null Meeting point data or null if not found
     */
    public static function get(int $id): ?object {
        global $wpdb;
        
        $table = $wpdb->prefix . self::$table_name;
        $sql = $wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id);
        
        return $wpdb->get_row($sql);
    }

    /**
     * Get all meeting points
     *
     * @param array $args Query arguments
     * @return array Meeting points data
     */
    public static function getAll(array $args = []): array {
        global $wpdb;
        
        $defaults = [
            'orderby' => 'name',
            'order' => 'ASC',
            'limit' => 20,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . self::$table_name;
        $sql = "SELECT * FROM $table ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        return $wpdb->get_results($sql);
    }

    /**
     * Create new meeting point
     *
     * @param array $data Meeting point data
     * @return int|false Meeting point ID on success, false on failure
     */
    public static function create(array $data) {
        global $wpdb;
        
        $required_fields = ['name', 'address'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        $table = $wpdb->prefix . self::$table_name;
        
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'address' => sanitize_textarea_field($data['address']),
            'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
            'place_id' => !empty($data['place_id']) ? sanitize_text_field($data['place_id']) : null,
            'note' => !empty($data['note']) ? sanitize_textarea_field($data['note']) : null,
        ];
        
        $format = ['%s', '%s', '%f', '%f', '%s', '%s'];
        
        $result = $wpdb->insert($table, $insert_data, $format);
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update meeting point
     *
     * @param int $id Meeting point ID
     * @param array $data Meeting point data
     * @return bool True on success, false on failure
     */
    public static function update(int $id, array $data): bool {
        global $wpdb;
        
        if ($id <= 0) {
            return false;
        }
        
        $table = $wpdb->prefix . self::$table_name;
        
        $update_data = [];
        $format = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['address'])) {
            $update_data['address'] = sanitize_textarea_field($data['address']);
            $format[] = '%s';
        }
        
        if (isset($data['latitude'])) {
            $update_data['latitude'] = !empty($data['latitude']) ? (float) $data['latitude'] : null;
            $format[] = '%f';
        }
        
        if (isset($data['longitude'])) {
            $update_data['longitude'] = !empty($data['longitude']) ? (float) $data['longitude'] : null;
            $format[] = '%f';
        }
        
        if (isset($data['place_id'])) {
            $update_data['place_id'] = !empty($data['place_id']) ? sanitize_text_field($data['place_id']) : null;
            $format[] = '%s';
        }
        
        if (isset($data['note'])) {
            $update_data['note'] = !empty($data['note']) ? sanitize_textarea_field($data['note']) : null;
            $format[] = '%s';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );
        
        return $result !== false;
    }

    /**
     * Delete meeting point
     *
     * @param int $id Meeting point ID
     * @return bool True on success, false on failure
     */
    public static function delete(int $id): bool {
        global $wpdb;
        
        if ($id <= 0) {
            return false;
        }
        
        // Check if meeting point is in use
        if (self::isInUse($id)) {
            return false;
        }
        
        $table = $wpdb->prefix . self::$table_name;
        $result = $wpdb->delete($table, ['id' => $id], ['%d']);
        
        return $result !== false;
    }

    /**
     * Check if meeting point is in use
     *
     * @param int $id Meeting point ID
     * @return bool True if in use, false otherwise
     */
    public static function isInUse(int $id): bool {
        global $wpdb;
        
        // Check in product meta
        $products_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_fp_exp_meeting_point_id' AND meta_value = %d",
            $id
        ));
        
        if ($products_count > 0) {
            return true;
        }
        
        // Check in schedules
        $schedules_table = $wpdb->prefix . 'fp_schedules';
        $schedules_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $schedules_table WHERE meeting_point_id = %d",
            $id
        ));
        
        if ($schedules_count > 0) {
            return true;
        }
        
        return false;
    }

    /**
     * Get total count
     *
     * @return int Total number of meeting points
     */
    public static function getCount(): int {
        global $wpdb;
        
        $table = $wpdb->prefix . self::$table_name;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    }

    /**
     * Get meeting points for select dropdown
     *
     * @return array Options array for select
     */
    public static function getOptions(): array {
        $options = ['' => __('Select a meeting point', 'fp-esperienze')];
        
        $meeting_points = self::getAll(['limit' => 0]);
        
        foreach ($meeting_points as $point) {
            $options[$point->id] = $point->name;
        }
        
        return $options;
    }
}