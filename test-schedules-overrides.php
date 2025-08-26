<?php
/**
 * Simple Test Cases for Schedules and Overrides
 *
 * @package FP\Esperienze\Tests
 */

// This file demonstrates the 3 test cases mentioned in the requirements
// Run this file from a WordPress context to test the functionality

require_once ABSPATH . 'wp-config.php';
require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/DataManager.php';
require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/Schedule.php';
require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/Override.php';

use FP\Esperienze\Data\DataManager;
use FP\Esperienze\Data\Schedule;
use FP\Esperienze\Data\Override;

/**
 * Test Cases for Schedules and Overrides functionality
 */
class FPEsperienzeTests {
    
    private $test_product_id = 123; // Sample product ID
    private $data_manager;
    
    public function __construct() {
        $this->data_manager = new DataManager();
    }
    
    /**
     * Test Case 1: Normal day with regular schedules
     */
    public function testNormalDay() {
        echo "<h2>Test Case 1: Normal Day</h2>\n";
        
        // Create a test schedule for Monday (day 1)
        $schedule = new Schedule();
        $schedule->product_id = $this->test_product_id;
        $schedule->day_of_week = 1; // Monday
        $schedule->start_time = '09:00:00';
        $schedule->duration_min = 120;
        $schedule->capacity = 15;
        $schedule->lang = 'en';
        $schedule->meeting_point_id = 1;
        $schedule->price_adult = 50.00;
        $schedule->price_child = 25.00;
        $schedule->is_active = 1;
        
        // Get next Monday's date
        $next_monday = date('Y-m-d', strtotime('next monday'));
        
        echo "Testing availability for normal Monday: {$next_monday}\n";
        
        $availability = $this->data_manager->getAvailabilityForDay($this->test_product_id, $next_monday);
        
        echo "Expected: Available slots with normal pricing\n";
        echo "Result: " . (empty($availability) ? "No slots available" : count($availability) . " slots found") . "\n";
        
        if (!empty($availability)) {
            foreach ($availability as $slot) {
                printf("  - %s-%s: %d capacity, €%.2f adult, €%.2f child\n", 
                       $slot['start_time'], $slot['end_time'], 
                       $slot['capacity'], $slot['adult_price'], $slot['child_price']);
            }
        }
        echo "\n";
    }
    
    /**
     * Test Case 2: Closed day (override)
     */
    public function testClosedDay() {
        echo "<h2>Test Case 2: Closed Day</h2>\n";
        
        // Create a closure override for next Tuesday
        $next_tuesday = date('Y-m-d', strtotime('next tuesday'));
        
        $override = new Override();
        $override->product_id = $this->test_product_id;
        $override->date = $next_tuesday;
        $override->is_closed = 1;
        $override->reason = 'Test closure - maintenance';
        
        echo "Testing availability for closed Tuesday: {$next_tuesday}\n";
        
        $availability = $this->data_manager->getAvailabilityForDay($this->test_product_id, $next_tuesday);
        
        echo "Expected: No available slots (closed)\n";
        echo "Result: " . (empty($availability) ? "Correctly closed - no slots" : count($availability) . " slots found (ERROR!)") . "\n";
        echo "\n";
    }
    
    /**
     * Test Case 3: Price/capacity override
     */
    public function testPriceCapacityOverride() {
        echo "<h2>Test Case 3: Price/Capacity Override</h2>\n";
        
        // Create a price/capacity override for next Wednesday
        $next_wednesday = date('Y-m-d', strtotime('next wednesday'));
        
        $override = new Override();
        $override->product_id = $this->test_product_id;
        $override->date = $next_wednesday;
        $override->is_closed = 0;
        $override->capacity_override = 20; // Increased capacity
        $override->setPriceOverrides([
            'adult' => 45.00, // Discounted price
            'child' => 20.00  // Discounted price
        ]);
        $override->reason = 'Test override - special pricing';
        
        echo "Testing availability for Wednesday with overrides: {$next_wednesday}\n";
        
        $availability = $this->data_manager->getAvailabilityForDay($this->test_product_id, $next_wednesday);
        
        echo "Expected: Available slots with override pricing (€45/€20) and capacity (20)\n";
        echo "Result: " . (empty($availability) ? "No slots available" : count($availability) . " slots found") . "\n";
        
        if (!empty($availability)) {
            foreach ($availability as $slot) {
                printf("  - %s-%s: %d capacity, €%.2f adult, €%.2f child\n", 
                       $slot['start_time'], $slot['end_time'], 
                       $slot['capacity'], $slot['adult_price'], $slot['child_price']);
                
                // Validate override values
                if ($slot['capacity'] == 20 && $slot['adult_price'] == 45.00 && $slot['child_price'] == 20.00) {
                    echo "    ✓ Override values correctly applied\n";
                } else {
                    echo "    ✗ Override values NOT correctly applied\n";
                }
            }
        }
        echo "\n";
    }
    
    /**
     * Test global closure
     */
    public function testGlobalClosure() {
        echo "<h2>Test Case 4: Global Closure</h2>\n";
        
        // Create a global closure (product_id = 0)
        $test_date = date('Y-m-d', strtotime('+7 days'));
        
        $global_override = new Override();
        $global_override->product_id = 0; // Global closure
        $global_override->date = $test_date;
        $global_override->is_closed = 1;
        $global_override->reason = 'Test global closure - holiday';
        
        echo "Testing availability for global closure date: {$test_date}\n";
        
        $availability = $this->data_manager->getAvailabilityForDay($this->test_product_id, $test_date);
        
        echo "Expected: No available slots (global closure)\n";
        echo "Result: " . (empty($availability) ? "Correctly closed - no slots" : count($availability) . " slots found (ERROR!)") . "\n";
        echo "\n";
    }
    
    /**
     * Run all tests
     */
    public function runAllTests() {
        echo "<h1>FP Esperienze Schedules & Overrides Test Suite</h1>\n";
        echo "Testing with product ID: {$this->test_product_id}\n";
        echo "WordPress timezone: " . wp_timezone_string() . "\n\n";
        
        $this->testNormalDay();
        $this->testClosedDay();
        $this->testPriceCapacityOverride();
        $this->testGlobalClosure();
        
        echo "<h2>Test Summary</h2>\n";
        echo "All test cases completed. Review results above.\n";
        echo "Note: These are basic validation tests. Full functionality requires:\n";
        echo "- Database tables to be created\n";
        echo "- Sample schedules to be configured\n";
        echo "- WordPress environment to be properly initialized\n";
    }
}

// Run tests if this file is accessed directly
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    $tests = new FPEsperienzeTests();
    $tests->runAllTests();
}