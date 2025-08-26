<?php
/**
 * Meeting Point Model - Reference Implementation  
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Meeting Point model class - reference implementation from PR #6
 */
class MeetingPoint {
    
    public $id;
    public $name;
    public $address;
    public $latitude;
    public $longitude;
    public $place_id;
    public $note;
    public $created_at;
    public $updated_at;
    
    /**
     * Get meeting point by ID
     *
     * @param int $id Meeting point ID
     * @return MeetingPoint|null
     */
    public static function get(int $id): ?MeetingPoint {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_meeting_points';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $id
        ));
        
        if (!$row) {
            return null;
        }
        
        $meeting_point = new self();
        foreach (get_object_vars($row) as $key => $value) {
            $meeting_point->$key = $value;
        }
        
        return $meeting_point;
    }
}