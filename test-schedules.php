<?php
/**
 * Simple test script to verify schedules and overrides functionality
 * 
 * This script can be run by placing it in the WordPress root directory
 * and accessing it via browser (only for testing purposes).
 * 
 * REMOVE THIS FILE AFTER TESTING!
 */

require_once 'wp-config.php';
require_once 'wp-load.php';

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Only administrators can run this test.' );
}

if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
    wp_die( 'This test file can only be run in debug mode.' );
}

echo '<h1>FP Esperienze - Schedules and Overrides Test</h1>';

// Test 1: Check if classes exist
echo '<h2>Test 1: Class Loading</h2>';
try {
    $schedule_manager = new \FP\Esperienze\Data\ScheduleManager();
    echo '✅ ScheduleManager class loaded successfully<br>';
} catch (Exception $e) {
    echo '❌ ScheduleManager class failed: ' . $e->getMessage() . '<br>';
}

try {
    $override_manager = new \FP\Esperienze\Data\OverrideManager();
    echo '✅ OverrideManager class loaded successfully<br>';
} catch (Exception $e) {
    echo '❌ OverrideManager class failed: ' . $e->getMessage() . '<br>';
}

try {
    $availability = new \FP\Esperienze\Data\Availability();
    echo '✅ Availability class loaded successfully<br>';
} catch (Exception $e) {
    echo '❌ Availability class failed: ' . $e->getMessage() . '<br>';
}

// Test 2: Check database tables
echo '<h2>Test 2: Database Tables</h2>';
global $wpdb;

$tables = [
    'fp_schedules' => $wpdb->prefix . 'fp_schedules',
    'fp_overrides' => $wpdb->prefix . 'fp_overrides',
];

foreach ($tables as $name => $full_name) {
    $result = $wpdb->get_var("SHOW TABLES LIKE '$full_name'");
    if ($result) {
        echo "✅ Table $name exists<br>";
        
        // Check table structure
        $columns = $wpdb->get_results("DESCRIBE $full_name");
        echo "&nbsp;&nbsp;&nbsp;Columns: ";
        foreach ($columns as $column) {
            echo $column->Field . ', ';
        }
        echo '<br>';
    } else {
        echo "❌ Table $name missing<br>";
    }
}

// Test 3: Find an experience product for testing
echo '<h2>Test 3: Experience Product Detection</h2>';
$experience_products = get_posts([
    'post_type' => 'product',
    'meta_query' => [
        [
            'key' => '_product_type',
            'value' => 'experience'
        ]
    ],
    'posts_per_page' => 1,
    'fields' => 'ids'
]);

if (!empty($experience_products)) {
    $product_id = $experience_products[0];
    echo "✅ Found experience product ID: $product_id<br>";
    
    // Test 4: Schedule operations
    echo '<h2>Test 4: Schedule Operations</h2>';
    try {
        // Create a test schedule
        $schedule_data = [
            'product_id' => $product_id,
            'day_of_week' => 1, // Monday
            'start_time' => '09:00:00',
            'duration_min' => 60,
            'capacity' => 10,
            'lang' => 'en',
            'price_adult' => 50.00,
            'price_child' => 25.00
        ];
        
        $schedule_id = \FP\Esperienze\Data\ScheduleManager::createSchedule($schedule_data);
        if ($schedule_id) {
            echo "✅ Created test schedule ID: $schedule_id<br>";
            
            // Test retrieval
            $schedules = \FP\Esperienze\Data\ScheduleManager::getSchedules($product_id);
            echo "✅ Retrieved " . count($schedules) . " schedules<br>";
            
            // Clean up
            \FP\Esperienze\Data\ScheduleManager::deleteSchedule($schedule_id);
            echo "✅ Cleaned up test schedule<br>";
        } else {
            echo "❌ Failed to create test schedule<br>";
        }
    } catch (Exception $e) {
        echo "❌ Schedule test failed: " . $e->getMessage() . "<br>";
    }
    
    // Test 5: Override operations
    echo '<h2>Test 5: Override Operations</h2>';
    try {
        $override_data = [
            'product_id' => $product_id,
            'date' => '2024-12-25',
            'is_closed' => 1,
            'reason' => 'Test closure'
        ];
        
        $override_id = \FP\Esperienze\Data\OverrideManager::saveOverride($override_data);
        if ($override_id) {
            echo "✅ Created test override ID: $override_id<br>";
            
            // Test retrieval
            $override = \FP\Esperienze\Data\OverrideManager::getOverride($product_id, '2024-12-25');
            if ($override) {
                echo "✅ Retrieved test override<br>";
            }
            
            // Clean up
            \FP\Esperienze\Data\OverrideManager::deleteOverride($product_id, '2024-12-25');
            echo "✅ Cleaned up test override<br>";
        } else {
            echo "❌ Failed to create test override<br>";
        }
    } catch (Exception $e) {
        echo "❌ Override test failed: " . $e->getMessage() . "<br>";
    }
    
    // Test 6: Availability calculation
    echo '<h2>Test 6: Availability Calculation</h2>';
    try {
        // Create a temporary schedule for testing
        $schedule_data = [
            'product_id' => $product_id,
            'day_of_week' => (int) date('w'), // Today's day
            'start_time' => '10:00:00',
            'duration_min' => 60,
            'capacity' => 8,
            'lang' => 'en',
            'price_adult' => 40.00,
            'price_child' => 20.00
        ];
        
        $schedule_id = \FP\Esperienze\Data\ScheduleManager::createSchedule($schedule_data);
        if ($schedule_id) {
            $today = date('Y-m-d');
            $slots = \FP\Esperienze\Data\Availability::forDay($product_id, $today);
            
            echo "✅ Generated " . count($slots) . " availability slots for today<br>";
            if (!empty($slots)) {
                $slot = $slots[0];
                echo "&nbsp;&nbsp;&nbsp;Sample slot: {$slot['start_time']}-{$slot['end_time']}, ";
                echo "Capacity: {$slot['capacity']}, Adult: €{$slot['adult_price']}<br>";
            }
            
            // Clean up
            \FP\Esperienze\Data\ScheduleManager::deleteSchedule($schedule_id);
            echo "✅ Cleaned up test schedule<br>";
        }
    } catch (Exception $e) {
        echo "❌ Availability test failed: " . $e->getMessage() . "<br>";
    }
    
} else {
    echo "❌ No experience products found. Create an experience product first.<br>";
}

// Test 7: REST API endpoint
echo '<h2>Test 7: REST API Endpoint</h2>';
$rest_url = rest_url('fp-exp/v1/availability');
echo "REST endpoint should be available at: <a href='$rest_url' target='_blank'>$rest_url</a><br>";

echo '<h2>Test Complete</h2>';
echo '<p><strong>Remember to remove this test file after testing!</strong></p>';
?>