<?php
/**
 * Comprehensive Experience Product Type Test
 * 
 * This script creates a test experience product and verifies all functionality
 * Run this in WordPress admin or via WP-CLI to test the Experience product type
 * 
 * Usage: wp eval-file test-experience-functionality.php
 * Or place in WordPress root and access via browser (admin required)
 */

// Prevent direct access unless in WordPress or WP-CLI
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    // Load WordPress if accessed directly
    $wp_load_paths = [
        dirname(__FILE__) . '/wp-config.php',
        dirname(__FILE__) . '/../wp-config.php',
        dirname(__FILE__) . '/../../wp-config.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress not found. Please run this script from WordPress root or use WP-CLI.');
    }
}

// Security check
if (!defined('WP_CLI') && !current_user_can('manage_options')) {
    wp_die('Access denied. Administrator privileges required.');
}

echo "=== FP Esperienze - Experience Product Type Comprehensive Test ===\n\n";

/**
 * Test Experience Product Creation
 */
function test_experience_product_creation() {
    echo "🧪 Testing Experience Product Creation...\n";
    
    // Create a test experience product
    $product_data = [
        'post_title' => 'Test Experience - Rome Walking Tour',
        'post_content' => 'A comprehensive walking tour of Rome\'s historic center.',
        'post_status' => 'publish',
        'post_type' => 'product',
        'meta_input' => [
            '_product_type' => 'experience',
            '_virtual' => 'yes',
            '_downloadable' => 'no',
            '_regular_price' => '45.00',
            '_price' => '45.00',
            '_fp_experience_duration' => '180', // 3 hours
            '_fp_experience_capacity' => '15',
            '_fp_experience_adult_price' => '45.00',
            '_fp_experience_child_price' => '25.00',
            '_fp_exp_cutoff_minutes' => '120',
        ]
    ];
    
    $product_id = wp_insert_post($product_data);
    
    if (is_wp_error($product_id)) {
        echo "❌ Failed to create product: " . $product_id->get_error_message() . "\n";
        return false;
    }
    
    echo "✅ Created test product with ID: $product_id\n";
    
    // Verify the product was created correctly
    $product = wc_get_product($product_id);
    
    if (!$product) {
        echo "❌ Failed to retrieve created product\n";
        return false;
    }
    
    if ($product->get_type() !== 'experience') {
        echo "❌ Product type is '{$product->get_type()}', expected 'experience'\n";
        return false;
    }
    
    echo "✅ Product type correctly set to 'experience'\n";
    echo "✅ Product class: " . get_class($product) . "\n";
    
    return $product_id;
}

/**
 * Test Product Type Registration
 */
function test_product_type_registration() {
    echo "\n🧪 Testing Product Type Registration...\n";
    
    // Check if product type is registered
    $product_types = wc_get_product_types();
    
    if (!isset($product_types['experience'])) {
        echo "❌ Experience product type not registered in WooCommerce\n";
        echo "Available types: " . implode(', ', array_keys($product_types)) . "\n";
        return false;
    }
    
    echo "✅ Experience product type registered: " . $product_types['experience'] . "\n";
    
    // Test filter hooks
    $test_types = ['simple' => 'Simple Product'];
    $filtered_types = apply_filters('woocommerce_product_type_selector', $test_types);
    
    if (!isset($filtered_types['experience'])) {
        echo "❌ woocommerce_product_type_selector filter not working\n";
        return false;
    }
    
    echo "✅ woocommerce_product_type_selector filter working correctly\n";
    
    // Test product class filter
    $product_class = apply_filters('woocommerce_product_class', 'WC_Product', 'experience');
    echo "✅ Product class for experience: $product_class\n";
    
    return true;
}

/**
 * Test Experience-Specific Features
 */
function test_experience_features($product_id) {
    echo "\n🧪 Testing Experience-Specific Features...\n";
    
    $product = wc_get_product($product_id);
    
    // Test virtual product
    if (!$product->is_virtual()) {
        echo "❌ Experience product should be virtual\n";
        return false;
    }
    echo "✅ Product correctly set as virtual\n";
    
    // Test meta data
    $duration = get_post_meta($product_id, '_fp_experience_duration', true);
    $capacity = get_post_meta($product_id, '_fp_experience_capacity', true);
    $adult_price = get_post_meta($product_id, '_fp_experience_adult_price', true);
    
    if ($duration !== '180') {
        echo "❌ Duration not saved correctly. Expected: 180, Got: $duration\n";
        return false;
    }
    echo "✅ Duration saved correctly: $duration minutes\n";
    
    if ($capacity !== '15') {
        echo "❌ Capacity not saved correctly. Expected: 15, Got: $capacity\n";
        return false;
    }
    echo "✅ Capacity saved correctly: $capacity people\n";
    
    if ($adult_price !== '45.00') {
        echo "❌ Adult price not saved correctly. Expected: 45.00, Got: $adult_price\n";
        return false;
    }
    echo "✅ Adult price saved correctly: €$adult_price\n";
    
    return true;
}

/**
 * Test Admin Interface Elements
 */
function test_admin_interface() {
    echo "\n🧪 Testing Admin Interface Elements...\n";
    
    // Check if Experience class exists and is instantiated
    if (!class_exists('FP\\Esperienze\\ProductType\\Experience')) {
        echo "❌ Experience class not found\n";
        return false;
    }
    echo "✅ Experience class exists\n";
    
    if (!class_exists('FP\\Esperienze\\ProductType\\WC_Product_Experience')) {
        echo "❌ WC_Product_Experience class not found\n";
        return false;
    }
    echo "✅ WC_Product_Experience class exists\n";
    
    // Test product data tabs filter
    $tabs = apply_filters('woocommerce_product_data_tabs', []);
    
    if (!isset($tabs['experience_product_data'])) {
        echo "⚠️  Experience tab not found in product data tabs (may be conditional)\n";
    } else {
        echo "✅ Experience tab registered in product data tabs\n";
    }
    
    return true;
}

/**
 * Run all tests
 */
function run_comprehensive_test() {
    $results = [];
    
    // Test 1: Product Type Registration
    $results['registration'] = test_product_type_registration();
    
    // Test 2: Admin Interface
    $results['admin'] = test_admin_interface();
    
    // Test 3: Product Creation
    $product_id = test_experience_product_creation();
    $results['creation'] = $product_id !== false;
    
    // Test 4: Experience Features (only if product was created)
    if ($product_id) {
        $results['features'] = test_experience_features($product_id);
        
        // Clean up - delete test product
        wp_delete_post($product_id, true);
        echo "\n🧹 Cleaned up test product (ID: $product_id)\n";
    } else {
        $results['features'] = false;
    }
    
    // Summary
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "TEST SUMMARY\n";
    echo str_repeat("=", 60) . "\n";
    
    $passed = 0;
    $total = count($results);
    
    foreach ($results as $test => $result) {
        $status = $result ? "✅ PASS" : "❌ FAIL";
        echo sprintf("%-20s: %s\n", ucfirst($test), $status);
        if ($result) $passed++;
    }
    
    echo str_repeat("-", 60) . "\n";
    echo sprintf("OVERALL: %d/%d tests passed\n", $passed, $total);
    
    if ($passed === $total) {
        echo "\n🎉 ALL TESTS PASSED! Experience product type is fully functional.\n";
        echo "\nThe Experience product type should now be available in:\n";
        echo "WordPress Admin → Products → Add New → Product Type dropdown\n";
        return true;
    } else {
        echo "\n⚠️  SOME TESTS FAILED. Check the output above for details.\n";
        return false;
    }
}

// Execute the comprehensive test
$success = run_comprehensive_test();

if (defined('WP_CLI')) {
    WP_CLI::success('Test completed. ' . ($success ? 'All functionality working!' : 'Some issues found.'));
} else {
    echo "\n\n";
    if ($success) {
        echo "<div style='background: #d4edda; color: #155724; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 20px 0;'>";
        echo "<h3>✅ Success!</h3>";
        echo "<p>The Experience product type is fully functional and ready to use.</p>";
        echo "<p><strong>Next step:</strong> Go to <a href='" . admin_url('post-new.php?post_type=product') . "'>Products → Add New</a> and select 'Experience' from the Product Type dropdown.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px 0;'>";
        echo "<h3>⚠️ Issues Found</h3>";
        echo "<p>Some tests failed. Please check the output above and ensure:</p>";
        echo "<ul>";
        echo "<li>FP Esperienze plugin is activated</li>";
        echo "<li>WooCommerce is installed and activated</li>";
        echo "<li>No PHP errors in the error logs</li>";
        echo "</ul>";
        echo "</div>";
    }
}

// Additional debug information
echo "\n" . str_repeat("=", 60) . "\n";
echo "DEBUG INFORMATION\n";
echo str_repeat("=", 60) . "\n";
echo "WordPress Version: " . get_bloginfo('version') . "\n";
echo "WooCommerce Version: " . (defined('WC_VERSION') ? WC_VERSION : 'Not Available') . "\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "FP Esperienze Active: " . (is_plugin_active('fp-esperienze/fp-esperienze.php') ? 'Yes' : 'No') . "\n";
echo "Memory Limit: " . ini_get('memory_limit') . "\n";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s\n";

?>