<?php
/**
 * Data Management
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Data manager class
 */
class DataManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load models
        $this->loadModels();
    }

    /**
     * Load data models
     */
    private function loadModels(): void {
        require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/Schedule.php';
        require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/Override.php';
    }

    /**
     * Get availability for a product on a specific date
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array
     */
    public function getAvailabilityForDay(int $product_id, string $date): array {
        // Validate inputs
        if ($product_id <= 0 || empty($date)) {
            return [];
        }
        
        try {
            // Get the day of week (0=Sunday, 1=Monday, etc.)
            $date_obj = new \DateTime($date, wp_timezone());
            $day_of_week = (int) $date_obj->format('w');
            
            // Check for overrides first
            $override = Override::getByDate($product_id, $date);
            
            // Check for global closure
            $global_closure = Override::getByDate(0, $date);
            
            // If there's a global closure or product-specific closure, return empty
            if (($global_closure && $global_closure->is_closed) || ($override && $override->is_closed)) {
                return [];
            }
            
            // Get regular schedules for this day
            $schedules = Schedule::getByDay($product_id, $day_of_week);
            
            if (empty($schedules)) {
                return [];
            }
            
            $slots = [];
            foreach ($schedules as $schedule) {
                $capacity = $schedule->capacity;
                $adult_price = $schedule->price_adult;
                $child_price = $schedule->price_child;
                
                // Apply overrides if they exist
                if ($override) {
                    if ($override->capacity_override !== null) {
                        $capacity = $override->capacity_override;
                    }
                    
                    $price_overrides = $override->getPriceOverrides();
                    if (!empty($price_overrides)) {
                        $adult_price = $price_overrides['adult'] ?? $adult_price;
                        $child_price = $price_overrides['child'] ?? $child_price;
                    }
                }
                
                // Calculate end time
                $start_datetime = new \DateTime($date . ' ' . $schedule->start_time, wp_timezone());
                $end_datetime = clone $start_datetime;
                $end_datetime->add(new \DateInterval('PT' . $schedule->duration_min . 'M'));
                
                // TODO: Get actual booked spots from bookings table
                $booked_spots = 0; // Placeholder
                $available_spots = max(0, $capacity - $booked_spots);
                
                $slots[] = [
                    'schedule_id'     => $schedule->id,
                    'start_time'      => $schedule->start_time,
                    'end_time'        => $end_datetime->format('H:i'),
                    'duration_min'    => $schedule->duration_min,
                    'capacity'        => $capacity,
                    'booked'          => $booked_spots,
                    'available'       => $available_spots,
                    'is_available'    => $available_spots > 0,
                    'adult_price'     => (float) $adult_price,
                    'child_price'     => (float) $child_price,
                    'lang'            => $schedule->lang ?: '',
                    'meeting_point_id' => $schedule->meeting_point_id,
                ];
            }
            
            return $slots;
            
        } catch (\Exception $e) {
            // Log error and return empty array
            error_log('FP Esperienze - Error getting availability: ' . $e->getMessage());
            return [];
        }
    }
}