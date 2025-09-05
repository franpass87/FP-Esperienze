<?php
/**
 * Debug script for time slots saving issue
 * 
 * This script helps identify why time slots are not being saved
 * in the Recurring Time Slots interface.
 */

// Mock WordPress environment for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Simple mock functions for testing
if (!function_exists('current_user_can')) {
    function current_user_can($capability) { return true; }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) { return 'test_nonce_' . md5($action); }
}
if (!function_exists('htmlspecialchars')) {
    // htmlspecialchars is a native PHP function, no need to mock
}

// Load the plugin classes for testing
require_once __DIR__ . '/includes/Data/ScheduleManager.php';

// Ensure user has admin capabilities
if (!current_user_can('manage_options')) {
    die('Access denied. Admin capabilities required.');
}

echo "<h1>FP Esperienze - Time Slots Debug</h1>\n";

// Check if plugin is active
if (!class_exists('FP\Esperienze\ProductType\Experience')) {
    echo "<p style='color: red;'>Plugin not loaded. Attempting to load...</p>\n";
    
    // Try to load the plugin manually
    if (file_exists(__DIR__ . '/fp-esperienze.php')) {
        include_once __DIR__ . '/fp-esperienze.php';
        echo "<p style='color: green;'>Plugin loaded manually.</p>\n";
    } else {
        die('Plugin file not found.');
    }
}

// Test creating a simple experience product
echo "<h2>1. Testing Experience Product Creation</h2>\n";

$product_id = wp_insert_post([
    'post_title' => 'Test Experience - Debug Time Slots',
    'post_type' => 'product',
    'post_status' => 'draft',
    'meta_input' => [
        '_product_type' => 'experience'
    ]
]);

if (is_wp_error($product_id)) {
    echo "<p style='color: red;'>Failed to create test product: " . $product_id->get_error_message() . "</p>\n";
    exit;
}

echo "<p>Created test product with ID: $product_id</p>\n";

// Simulate form submission data for time slots
echo "<h2>2. Simulating Time Slots Form Submission</h2>\n";

// Mock $_POST data as it would come from the form
$_POST = [
    'woocommerce_meta_nonce' => wp_create_nonce('woocommerce_save_data'),
    'product-type' => 'experience',
    'builder_slots' => [
        0 => [
            'start_time' => '09:00',
            'days' => ['1', '3', '5'], // Monday, Wednesday, Friday
            'advanced_enabled' => '0'
        ],
        1 => [
            'start_time' => '14:30',
            'days' => ['2', '4'], // Tuesday, Thursday
            'advanced_enabled' => '1',
            'duration_min' => '120',
            'capacity' => '8',
            'lang' => 'English',
            'price_adult' => '45.00',
            'price_child' => '25.00'
        ]
    ]
];

echo "<p>Simulated POST data:</p>\n";
echo "<pre>" . htmlspecialchars(print_r($_POST['builder_slots'], true)) . "</pre>\n";

// Test the save functionality
echo "<h2>3. Testing Save Functionality</h2>\n";

try {
    // Instantiate the Experience class
    $experience = new FP\Esperienze\ProductType\Experience();
    
    // Call the save method directly
    echo "<p>Calling saveProductData for product ID: $product_id</p>\n";
    $experience->saveProductData($product_id);
    
    echo "<p style='color: green;'>Save method executed without errors.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error during save: " . $e->getMessage() . "</p>\n";
}

// Check what was actually saved
echo "<h2>4. Checking Saved Data</h2>\n";

global $wpdb;

// Check schedules table
$schedules = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}fp_schedules WHERE product_id = %d",
    $product_id
));

echo "<p>Schedules found: " . count($schedules) . "</p>\n";

if ($schedules) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Day</th><th>Start Time</th><th>Duration</th><th>Capacity</th><th>Language</th><th>Price Adult</th><th>Price Child</th></tr>\n";
    
    foreach ($schedules as $schedule) {
        echo "<tr>\n";
        echo "<td>{$schedule->id}</td>\n";
        echo "<td>{$schedule->day_of_week}</td>\n";
        echo "<td>{$schedule->start_time}</td>\n";
        echo "<td>" . ($schedule->duration_min ?: 'default') . "</td>\n";
        echo "<td>" . ($schedule->capacity ?: 'default') . "</td>\n";
        echo "<td>" . ($schedule->lang ?: 'default') . "</td>\n";
        echo "<td>" . ($schedule->price_adult ?: 'default') . "</td>\n";
        echo "<td>" . ($schedule->price_child ?: 'default') . "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
} else {
    echo "<p style='color: red;'>No schedules were saved!</p>\n";
}

// Check product meta
echo "<h2>5. Checking Product Meta</h2>\n";

$product_type = get_post_meta($product_id, '_product_type', true);
echo "<p>Product type: " . ($product_type ?: 'NOT SET') . "</p>\n";

// Clean up
echo "<h2>6. Cleanup</h2>\n";

// Delete the test product
wp_delete_post($product_id, true);

// Delete any schedules that might remain
$wpdb->delete($wpdb->prefix . 'fp_schedules', ['product_id' => $product_id]);

echo "<p>Test product and data cleaned up.</p>\n";

echo "<h2>Debug Complete</h2>\n";
?>