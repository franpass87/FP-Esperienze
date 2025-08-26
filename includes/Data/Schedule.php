<?php
/**
 * Schedule Model - Reference Implementation
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Schedule model class - reference implementation from PR #5
 */
class Schedule {
    
    public $id;
    public $product_id;
    public $day_of_week;
    public $start_time;
    public $duration_min;
    public $capacity;
    public $lang;
    public $meeting_point_id;
    public $price_adult;
    public $price_child;
    public $is_active;
    public $created_at;
    public $updated_at;
    
    /**
     * Get schedules by product and day of week
     *
     * @param int $product_id Product ID
     * @param int $day_of_week Day of week (0=Sunday)
     * @return array Array of Schedule objects
     */
    public static function getByProductAndDay(int $product_id, int $day_of_week): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND day_of_week = %d AND is_active = 1",
            $product_id,
            $day_of_week
        ));
        
        $schedules = [];
        foreach ($results as $row) {
            $schedule = new self();
            foreach (get_object_vars($row) as $key => $value) {
                $schedule->$key = $value;
            }
            $schedules[] = $schedule;
        }
        
        return $schedules;
    }
}