<?php
/**
 * Meeting Point Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use FP\Esperienze\Core\I18nManager;

defined('ABSPATH') || exit;

/**
 * Meeting Point Manager class for CRUD operations
 */
class MeetingPointManager {

    /**
     * Simple in-memory cache for meeting points.
     *
     * @var array<int, object>
     */
    private static array $cache = [];

    /**
     * Get all meeting points
     *
     * @param bool $translate Whether to return translated versions
     * @return array
     */
    public static function getAllMeetingPoints(bool $translate = true): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $results = $wpdb->get_results(
            // Query contains no external variables, so prepare() is unnecessary.
            "SELECT * FROM {$table_name} ORDER BY name ASC"
        );
        
        if (!$results) {
            return [];
        }
        
        // Apply translations if requested and multilingual plugin is active
        if ($translate && I18nManager::isMultilingualActive()) {
            $results = array_map(function($meeting_point) {
                return I18nManager::getTranslatedMeetingPoint($meeting_point);
            }, $results);
        }
        
        return $results;
    }

    /**
     * Get meeting point by ID
     *
     * @param int $id Meeting point ID
     * @param bool $translate Whether to return translated version
     * @return object|null
     */
    public static function getMeetingPoint(int $id, bool $translate = true): ?object {
        if (isset(self::$cache[$id])) {
            $result = self::$cache[$id];

            if ($translate && I18nManager::isMultilingualActive()) {
                return I18nManager::getTranslatedMeetingPoint($result);
            }

            return $result;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $result     = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));

        if (!$result) {
            return null;
        }

        self::$cache[$id] = $result;

        // Apply translation if requested and multilingual plugin is active
        if ($translate && I18nManager::isMultilingualActive()) {
            $result = I18nManager::getTranslatedMeetingPoint($result);
        }

        return $result;
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
        
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'address' => sanitize_textarea_field($data['address'])
        ];
        $insert_formats = ['%s', '%s'];

        if (array_key_exists('lat', $data) && is_numeric($data['lat'])) {
            $insert_data['lat'] = (float) $data['lat'];
            $insert_formats[] = '%f';
        }

        if (array_key_exists('lng', $data) && is_numeric($data['lng'])) {
            $insert_data['lng'] = (float) $data['lng'];
            $insert_formats[] = '%f';
        }

        $insert_data['place_id'] = ($data['place_id'] !== null && $data['place_id'] !== '')
            ? sanitize_text_field($data['place_id'])
            : null;
        $insert_formats[] = '%s';

        $insert_data['note'] = sanitize_textarea_field($data['note']);
        $insert_formats[] = '%s';

        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $insert_formats
        );
        
        if ($result) {
            $meeting_point_id = $wpdb->insert_id;
            
            // Fire hook for translation registration
            do_action('fp_meeting_point_created', $meeting_point_id);
            
            return $meeting_point_id;
        }
        
        return false;
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
        
        $clear_lat = false;
        $clear_lng = false;

        if (array_key_exists('lat', $data)) {
            if ($data['lat'] === '' || $data['lat'] === null) {
                $clear_lat = true;
            } elseif (is_numeric($data['lat'])) {
                $update_data['lat'] = (float) $data['lat'];
                $formats[] = '%f';
            }
        }

        if (array_key_exists('lng', $data)) {
            if ($data['lng'] === '' || $data['lng'] === null) {
                $clear_lng = true;
            } elseif (is_numeric($data['lng'])) {
                $update_data['lng'] = (float) $data['lng'];
                $formats[] = '%f';
            }
        }
        
        if (isset($data['place_id'])) {
            $update_data['place_id'] = $data['place_id'] ? sanitize_text_field($data['place_id']) : null;
            $formats[] = '%s';
        }
        
        if (isset($data['note'])) {
            $update_data['note'] = sanitize_textarea_field($data['note']);
            $formats[] = '%s';
        }
        
        if (empty($update_data) && !$clear_lat && !$clear_lng) {
            return false;
        }

        $updated = false;

        if (!empty($update_data)) {
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($result === false) {
                return false;
            }

            $updated = true;
        }

        if ($clear_lat || $clear_lng) {
            $set_null_clauses = [];

            if ($clear_lat) {
                $set_null_clauses[] = 'lat = NULL';
            }

            if ($clear_lng) {
                $set_null_clauses[] = 'lng = NULL';
            }

            $null_query = sprintf(
                'UPDATE %s SET %s WHERE id = %%d',
                $table_name,
                implode(', ', $set_null_clauses)
            );

            $null_result = $wpdb->query($wpdb->prepare($null_query, $id));

            if ($null_result === false) {
                return false;
            }

            $updated = true;
        }

        if ($updated) {
            // Fire hook for translation registration
            do_action('fp_meeting_point_updated', $id);
        }

        return $updated;
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
        $options = [];
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