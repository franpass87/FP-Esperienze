<?php
/**
 * Meeting Point Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Meeting Point Manager class for CRUD operations
 */
class MeetingPointManager {

    /**
     * Get all meeting points
     *
     * @return array
     */
    public static function getAllMeetingPoints(): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY name ASC");
        
        return $results ?: [];
    }

    /**
     * Get meeting point by ID
     *
     * @param int $id Meeting point ID
     * @return object|null
     */
    public static function getMeetingPoint(int $id): ?object {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));
        
        return $result ?: null;
    }

    /**
     * Create a new meeting point
     *
     * @param array $data Meeting point data
     * @return int|false Meeting point ID on success, false on failure
     */
    public static function createMeetingPoint(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        
        $defaults = [
            'name' => '',
            'address' => '',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
            'note' => ''
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => sanitize_text_field($data['name']),
                'address' => sanitize_textarea_field($data['address']),
                'lat' => $data['lat'] ? (float) $data['lat'] : null,
                'lng' => $data['lng'] ? (float) $data['lng'] : null,
                'place_id' => $data['place_id'] ? sanitize_text_field($data['place_id']) : null,
                'note' => sanitize_textarea_field($data['note'])
            ],
            [
                '%s', '%s', '%f', '%f', '%s', '%s'
            ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update a meeting point
     *
     * @param int $id Meeting point ID
     * @param array $data Meeting point data
     * @return bool
     */
    public static function updateMeetingPoint(int $id, array $data): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        
        $update_data = [];
        $formats = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['address'])) {
            $update_data['address'] = sanitize_textarea_field($data['address']);
            $formats[] = '%s';
        }
        
        if (isset($data['lat'])) {
            $update_data['lat'] = $data['lat'] ? (float) $data['lat'] : null;
            $formats[] = $data['lat'] ? '%f' : '%s';
        }
        
        if (isset($data['lng'])) {
            $update_data['lng'] = $data['lng'] ? (float) $data['lng'] : null;
            $formats[] = $data['lng'] ? '%f' : '%s';
        }
        
        if (isset($data['place_id'])) {
            $update_data['place_id'] = $data['place_id'] ? sanitize_text_field($data['place_id']) : null;
            $formats[] = '%s';
        }
        
        if (isset($data['note'])) {
            $update_data['note'] = sanitize_textarea_field($data['note']);
            $formats[] = '%s';
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
     * Delete a meeting point
     *
     * @param int $id Meeting point ID
     * @return bool
     */
    public static function deleteMeetingPoint(int $id): bool {
        global $wpdb;
        
        // Check if meeting point is used in schedules
        $table_schedules = $wpdb->prefix . 'fp_schedules';
        $used_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_schedules WHERE meeting_point_id = %d",
            $id
        ));
        
        if ($used_count > 0) {
            return false; // Cannot delete if in use
        }
        
        // Check if meeting point is set as default in products
        $used_in_products = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_fp_exp_meeting_point_id' AND meta_value = %d",
            $id
        ));
        
        if ($used_in_products > 0) {
            return false; // Cannot delete if set as default
        }
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);
        
        return $result !== false;
    }

    /**
     * Get meeting points for select dropdown
     *
     * @return array
     */
    public static function getMeetingPointsForSelect(): array {
        $options = ['' => __('Select a meeting point', 'fp-esperienze')];
        $meeting_points = self::getAllMeetingPoints();
        
        foreach ($meeting_points as $meeting_point) {
            $options[$meeting_point->id] = $meeting_point->name;
        }
        
        return $options;
    }

    /**
     * Check if a meeting point exists
     *
     * @param int $id Meeting point ID
     * @return bool
     */
    public static function meetingPointExists(int $id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d",
            $id
        ));
        
        return $exists > 0;
    }
}