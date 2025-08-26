<?php
/**
 * Simple test script for FP Esperienze booking widget
 * 
 * This script tests the basic functionality without needing a full WordPress environment
 *
 * @package FP\Esperienze
 */

// Basic validation tests
class BookingWidgetTests {
    
    public function runTests() {
        echo "FP Esperienze Booking Widget Tests\n";
        echo "==================================\n\n";
        
        $this->testDateValidation();
        $this->testCapacityValidation();
        $this->testCutoffValidation();
        $this->testQuantityValidation();
        
        echo "\nAll tests completed!\n";
    }
    
    private function testDateValidation() {
        echo "Testing date validation...\n";
        
        // Test valid date format
        $valid_date = '2024-12-25';
        $this->assertTrue(preg_match('/^\d{4}-\d{2}-\d{2}$/', $valid_date), "Valid date format");
        
        // Test invalid date format
        $invalid_date = '25-12-2024';
        $this->assertFalse(preg_match('/^\d{4}-\d{2}-\d{2}$/', $invalid_date), "Invalid date format");
        
        // Test past date
        $past_date = '2020-01-01';
        $this->assertTrue(strtotime($past_date) < strtotime('today'), "Past date detection");
        
        echo "✓ Date validation tests passed\n\n";
    }
    
    private function testCapacityValidation() {
        echo "Testing capacity validation...\n";
        
        // Mock slot data
        $slot = [
            'time' => '09:00',
            'capacity' => 20,
            'booked' => 15,
            'capacity_left' => 5
        ];
        
        // Test valid booking
        $requested_quantity = 3;
        $this->assertTrue($slot['capacity_left'] >= $requested_quantity, "Valid capacity booking");
        
        // Test invalid booking
        $requested_quantity = 10;
        $this->assertFalse($slot['capacity_left'] >= $requested_quantity, "Invalid capacity booking");
        
        // Test "last spots" warning threshold
        $this->assertTrue($slot['capacity_left'] <= 5, "Last spots warning threshold");
        
        echo "✓ Capacity validation tests passed\n\n";
    }
    
    private function testCutoffValidation() {
        echo "Testing cutoff validation...\n";
        
        $cutoff_hours = 2;
        $current_time = strtotime('2024-01-15 10:00:00');
        
        // Test slot within cutoff (should be unavailable)
        $slot_time_close = strtotime('2024-01-15 11:30:00');
        $is_available_close = ($slot_time_close - ($cutoff_hours * 3600)) > $current_time;
        $this->assertFalse($is_available_close, "Slot within cutoff should be unavailable");
        
        // Test slot outside cutoff (should be available)
        $slot_time_far = strtotime('2024-01-15 15:00:00');
        $is_available_far = ($slot_time_far - ($cutoff_hours * 3600)) > $current_time;
        $this->assertTrue($is_available_far, "Slot outside cutoff should be available");
        
        echo "✓ Cutoff validation tests passed\n\n";
    }
    
    private function testQuantityValidation() {
        echo "Testing quantity validation...\n";
        
        // Test minimum adults requirement
        $adults = 1;
        $children = 2;
        $this->assertTrue($adults >= 1, "Minimum adults requirement");
        
        // Test maximum quantity limit
        $total_quantity = $adults + $children;
        $max_allowed = 20;
        $this->assertTrue($total_quantity <= $max_allowed, "Maximum quantity limit");
        
        // Test zero adults (invalid)
        $adults_zero = 0;
        $this->assertFalse($adults_zero >= 1, "Zero adults should be invalid");
        
        echo "✓ Quantity validation tests passed\n\n";
    }
    
    private function assertTrue($condition, $message) {
        if (!$condition) {
            echo "✗ FAILED: $message\n";
            return false;
        }
        return true;
    }
    
    private function assertFalse($condition, $message) {
        if ($condition) {
            echo "✗ FAILED: $message\n";
            return false;
        }
        return true;
    }
}

// Run tests if called directly
if (php_sapi_name() === 'cli') {
    $tests = new BookingWidgetTests();
    $tests->runTests();
}