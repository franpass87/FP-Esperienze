<?php
/**
 * Data Management - Reference Implementation
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Data manager class - reference implementation from PR #5
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
        $date_obj = new \DateTime($date);
        $day_of_week = (int) $date_obj->format('w'); // 0 = Sunday
        
        // Check for global closures first
        $global_override = Override::getGlobalClosure($date);
        if ($global_override) {
            return [];
        }
        
        // Check for product-specific override
        $override = Override::getByProductAndDate($product_id, $date);
        if ($override && $override->is_closed) {
            return [];
        }
        
        // Get regular schedules for this day of week
        $schedules = Schedule::getByProductAndDay($product_id, $day_of_week);
        
        $slots = [];
        foreach ($schedules as $schedule) {
            $slot = [
                'start_time' => $schedule->start_time,
                'duration' => $schedule->duration_min,
                'capacity' => $schedule->capacity,
                'price_adult' => $schedule->price_adult,
                'price_child' => $schedule->price_child,
                'available_spots' => $schedule->capacity,
                'language' => $schedule->lang ?: 'English'
            ];
            
            // Apply override modifications if they exist
            if ($override) {
                if ($override->capacity_override !== null) {
                    $slot['capacity'] = $override->capacity_override;
                    $slot['available_spots'] = $override->capacity_override;
                }
                
                if ($override->price_override_json) {
                    $price_overrides = json_decode($override->price_override_json, true);
                    if (isset($price_overrides['adult'])) {
                        $slot['price_adult'] = $price_overrides['adult'];
                    }
                    if (isset($price_overrides['child'])) {
                        $slot['price_child'] = $price_overrides['child'];
                    }
                }
            }
            
            // TODO: Subtract existing bookings from available_spots
            
            $slots[] = $slot;
        }
        
        return $slots;
    }
}