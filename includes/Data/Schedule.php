<?php
/**
 * Schedule Model
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Schedule model class
 */
class Schedule {
    
    /**
     * Schedule properties
     */
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
     * Constructor
     *
     * @param object|array $data Schedule data
     */
    public function __construct($data = null) {
        if ($data) {
            $this->fill($data);
        }
    }

    /**
     * Fill model with data
     *
     * @param object|array $data
     */
    public function fill($data): void {
        $data = (object) $data;
        
        $this->id = $data->id ?? null;
        $this->product_id = $data->product_id ?? null;
        $this->day_of_week = $data->day_of_week ?? null;
        $this->start_time = $data->start_time ?? null;
        $this->duration_min = $data->duration_min ?? 60;
        $this->capacity = $data->capacity ?? 1;
        $this->lang = $data->lang ?? '';
        $this->meeting_point_id = $data->meeting_point_id ?? null;
        $this->price_adult = $data->price_adult ?? 0.00;
        $this->price_child = $data->price_child ?? 0.00;
        $this->is_active = $data->is_active ?? 1;
        $this->created_at = $data->created_at ?? null;
        $this->updated_at = $data->updated_at ?? null;
    }

    /**
     * Get all schedules for a product
     *
     * @param int $product_id Product ID
     * @return array Array of Schedule objects
     */
    public static function getByProduct(int $product_id): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d ORDER BY day_of_week, start_time",
            $product_id
        ));
        
        $schedules = [];
        foreach ($results as $row) {
            $schedules[] = new self($row);
        }
        
        return $schedules;
    }

    /**
     * Get schedules for a specific day of week
     *
     * @param int $product_id Product ID
     * @param int $day_of_week Day of week (0=Sunday, 1=Monday, etc.)
     * @return array Array of Schedule objects
     */
    public static function getByDay(int $product_id, int $day_of_week): array {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_schedules';
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d AND day_of_week = %d AND is_active = 1 ORDER BY start_time",
            $product_id,
            $day_of_week
        ));
        
        $schedules = [];
        foreach ($results as $row) {
            $schedules[] = new self($row);
        }
        
        return $schedules;
    }

    /**
     * Save schedule
     *
     * @return bool|int False on failure, ID on success
     */
    public function save() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'fp_schedules';
        $data = [
            'product_id' => $this->product_id,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'duration_min' => $this->duration_min,
            'capacity' => $this->capacity,
            'lang' => $this->lang,
            'meeting_point_id' => $this->meeting_point_id,
            'price_adult' => $this->price_adult,
            'price_child' => $this->price_child,
            'is_active' => $this->is_active,
        ];
        
        if ($this->id) {
            // Update existing
            $result = $wpdb->update($table, $data, ['id' => $this->id]);
            return $result !== false ? $this->id : false;
        } else {
            // Insert new
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert($table, $data);
            if ($result) {
                $this->id = $wpdb->insert_id;
                return $this->id;
            }
            return false;
        }
    }

    /**
     * Delete schedule
     *
     * @return bool
     */
    public function delete(): bool {
        if (!$this->id) {
            return false;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'fp_schedules';
        
        return $wpdb->delete($table, ['id' => $this->id]) !== false;
    }

    /**
     * Get day name
     *
     * @return string
     */
    public function getDayName(): string {
        $days = [
            0 => __('Sunday', 'fp-esperienze'),
            1 => __('Monday', 'fp-esperienze'),
            2 => __('Tuesday', 'fp-esperienze'),
            3 => __('Wednesday', 'fp-esperienze'),
            4 => __('Thursday', 'fp-esperienze'),
            5 => __('Friday', 'fp-esperienze'),
            6 => __('Saturday', 'fp-esperienze'),
        ];
        
        return $days[$this->day_of_week] ?? '';
    }

    /**
     * Get end time based on start time and duration
     *
     * @return string
     */
    public function getEndTime(): string {
        if (!$this->start_time || !$this->duration_min) {
            return '';
        }
        
        $start = new \DateTime($this->start_time);
        $start->add(new \DateInterval('PT' . $this->duration_min . 'M'));
        
        return $start->format('H:i');
    }
}