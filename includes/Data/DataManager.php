<?php
/**
 * Data Management
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Data manager class - central coordinator for data operations
 */
class DataManager {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Initialize data management components
        $this->init();
    }
    
    /**
     * Initialize data management
     */
    private function init(): void {
        // Register any data-related hooks here if needed
    }
    
    /**
     * Get schedule manager instance
     *
     * @return ScheduleManager
     */
    public static function schedules(): string {
        return ScheduleManager::class;
    }
    
    /**
     * Get override manager instance
     *
     * @return OverrideManager
     */
    public static function overrides(): string {
        return OverrideManager::class;
    }
    
    /**
     * Get availability instance
     *
     * @return Availability
     */
    public static function availability(): string {
        return Availability::class;
    }
    
    /**
     * Get extra manager instance
     *
     * @return ExtraManager
     */
    public static function extras(): string {
        return ExtraManager::class;
    }
}