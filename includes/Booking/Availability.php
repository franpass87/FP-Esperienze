<?php
/**
 * Availability Management - Reference Implementation from Schedules/Overrides PR
 *
 * @package FP\Esperienze\Booking
 */

namespace FP\Esperienze\Booking;

use FP\Esperienze\Data\DataManager;

defined('ABSPATH') || exit;

/**
 * Availability management class - reference implementation from PR #5
 * This class provides the canonical methods for availability checking
 */
class Availability {
    
    /**
     * Data manager instance
     */
    private DataManager $data_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->data_manager = new DataManager();
    }
    
    /**
     * Get availability for a specific day - PUBLIC API METHOD
     * This method signature must be maintained across all PRs
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array Array of available time slots
     */
    public function for_day(int $product_id, string $date): array {
        return $this->data_manager->getAvailabilityForDay($product_id, $date);
    }
    
    /**
     * Check if a specific slot is available - PUBLIC API METHOD
     * This method signature must be maintained across all PRs
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @param string $time Time in H:i format
     * @param int $participants Number of participants
     * @return bool True if available
     */
    public function check_slot(int $product_id, string $date, string $time, int $participants = 1): bool {
        $slots = $this->for_day($product_id, $date);
        
        foreach ($slots as $slot) {
            if ($slot['start_time'] === $time) {
                return $slot['available_spots'] >= $participants;
            }
        }
        
        return false;
    }
    
    /**
     * Get capacity for a specific slot - PUBLIC API METHOD
     * This method signature must be maintained across all PRs
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format  
     * @param string $time Time in H:i format
     * @return int Available capacity, 0 if slot doesn't exist
     */
    public function get_capacity(int $product_id, string $date, string $time): int {
        $slots = $this->for_day($product_id, $date);
        
        foreach ($slots as $slot) {
            if ($slot['start_time'] === $time) {
                return $slot['available_spots'];
            }
        }
        
        return 0;
    }
}