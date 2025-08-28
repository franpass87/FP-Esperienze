<?php
/**
 * Test script to verify the product type fix works correctly
 * 
 * This simulates the product saving process to ensure the product type
 * is correctly preserved when saving experience products.
 * 
 * REMOVE THIS FILE AFTER TESTING!
 */

// Simulate WordPress environment for testing
if (!defined('ABSPATH')) {
    echo "This test can only be run within WordPress environment.\n";
    exit;
}

echo '<h1>FP Esperienze - Product Type Fix Test</h1>';

// Test 1: Simulate saving an experience product
echo '<h2>Test 1: Experience Product Type Saving</h2>';

// Simulate POST data as it would come from the admin form
$_POST['product-type'] = 'experience';
$_POST['woocommerce_meta_nonce'] = wp_create_nonce('woocommerce_save_data');
$_POST['_experience_duration'] = '120';
$_POST['_experience_capacity'] = '10';

// Create a test product
$test_product_id = wp_insert_post([
    'post_title' => 'Test Experience Product ' . time(),
    'post_type' => 'product',
    'post_status' => 'draft'
]);

if ($test_product_id) {
    echo "✅ Test product created with ID: {$test_product_id}<br>";
    
    // Simulate the Experience::saveProductData method
    try {
        $experience = new \FP\Esperienze\ProductType\Experience();
        
        // Use reflection to call the private method (for testing)
        $reflection = new ReflectionClass($experience);
        $method = $reflection->getMethod('saveProductData');
        $method->setAccessible(true);
        $method->invoke($experience, $test_product_id);
        
        // Check if product type was saved correctly
        $saved_product_type = get_post_meta($test_product_id, '_product_type', true);
        
        if ($saved_product_type === 'experience') {
            echo "✅ Product type correctly saved as 'experience'<br>";
        } else {
            echo "❌ Product type save failed. Expected 'experience', got: '{$saved_product_type}'<br>";
        }
        
        // Check if experience data was saved
        $duration = get_post_meta($test_product_id, '_experience_duration', true);
        if ($duration === '120') {
            echo "✅ Experience data correctly saved<br>";
        } else {
            echo "❌ Experience data save failed<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error during save test: " . $e->getMessage() . "<br>";
    }
    
    // Clean up test product
    wp_delete_post($test_product_id, true);
    echo "🧹 Test product cleaned up<br>";
} else {
    echo "❌ Failed to create test product<br>";
}

// Test 2: Verify non-experience products are not affected
echo '<h2>Test 2: Non-Experience Product Type Handling</h2>';

$_POST['product-type'] = 'simple';

$test_product_id_2 = wp_insert_post([
    'post_title' => 'Test Simple Product ' . time(),
    'post_type' => 'product', 
    'post_status' => 'draft'
]);

if ($test_product_id_2) {
    echo "✅ Test simple product created with ID: {$test_product_id_2}<br>";
    
    try {
        $experience = new \FP\Esperienze\ProductType\Experience();
        $reflection = new ReflectionClass($experience);
        $method = $reflection->getMethod('saveProductData');
        $method->setAccessible(true);
        $method->invoke($experience, $test_product_id_2);
        
        $saved_product_type = get_post_meta($test_product_id_2, '_product_type', true);
        
        if ($saved_product_type !== 'experience') {
            echo "✅ Non-experience product type correctly preserved (not forced to 'experience')<br>";
        } else {
            echo "❌ Non-experience product incorrectly set to 'experience'<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Error during non-experience test: " . $e->getMessage() . "<br>";
    }
    
    wp_delete_post($test_product_id_2, true);
    echo "🧹 Test simple product cleaned up<br>";
}

// Clean up POST data
unset($_POST['product-type'], $_POST['woocommerce_meta_nonce'], $_POST['_experience_duration'], $_POST['_experience_capacity']);

echo '<h2>Test Complete</h2>';
echo '<p><strong>Remember to delete this test file after testing!</strong></p>';