<?php
/**
 * Simple test script to verify Brevo integration functionality
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

echo '<h1>FP Esperienze - Brevo Integration Test</h1>';

// Test 1: Check if BrevoManager class exists
echo '<h2>Test 1: Class Loading</h2>';
try {
    $brevo_manager = new \FP\Esperienze\Integrations\BrevoManager();
    echo '✅ BrevoManager class loaded successfully<br>';
    
    // Test if the manager is enabled
    $reflection = new ReflectionClass($brevo_manager);
    $method = $reflection->getMethod('isEnabled');
    $method->setAccessible(true);
    $is_enabled = $method->invoke($brevo_manager);
    
    echo $is_enabled ? '✅ Brevo integration is enabled<br>' : '⚠️ Brevo integration is disabled (check settings)<br>';
    
} catch (Exception $e) {
    echo '❌ BrevoManager class failed to load: ' . $e->getMessage() . '<br>';
}

// Test 2: Check settings
echo '<h2>Test 2: Settings Configuration</h2>';
$settings = get_option('fp_esperienze_integrations', []);

$api_key = $settings['brevo_api_key'] ?? '';
$list_id_it = $settings['brevo_list_id_it'] ?? 0;
$list_id_en = $settings['brevo_list_id_en'] ?? 0;

echo $api_key ? '✅ Brevo API key is configured<br>' : '⚠️ Brevo API key is missing<br>';
echo $list_id_it ? '✅ Italian list ID is configured: ' . $list_id_it . '<br>' : '⚠️ Italian list ID is missing<br>';
echo $list_id_en ? '✅ English list ID is configured: ' . $list_id_en . '<br>' : '⚠️ English list ID is missing<br>';

// Test 3: Test language detection
echo '<h2>Test 3: Language Detection</h2>';
try {
    $brevo_manager = new \FP\Esperienze\Integrations\BrevoManager();
    $reflection = new ReflectionClass($brevo_manager);
    
    // Create a mock order for testing
    if (class_exists('WC_Order')) {
        $order = new WC_Order();
        $order->set_billing_email('test@example.com');
        $order->set_billing_first_name('Test');
        $order->set_billing_last_name('User');
        
        $method = $reflection->getMethod('determineOrderLanguage');
        $method->setAccessible(true);
        $language = $method->invoke($brevo_manager, $order);
        
        echo '✅ Language detection works: ' . $language . '<br>';
        
        // Test customer data extraction
        $method = $reflection->getMethod('extractCustomerData');
        $method->setAccessible(true);
        $customer_data = $method->invoke($brevo_manager, $order);
        
        if ($customer_data) {
            echo '✅ Customer data extraction works<br>';
            echo '&nbsp;&nbsp;&nbsp;&nbsp;Email: ' . $customer_data['email'] . '<br>';
            echo '&nbsp;&nbsp;&nbsp;&nbsp;Name: ' . $customer_data['first_name'] . ' ' . $customer_data['last_name'] . '<br>';
            echo '&nbsp;&nbsp;&nbsp;&nbsp;Language: ' . $customer_data['language'] . '<br>';
        } else {
            echo '❌ Customer data extraction failed<br>';
        }
    } else {
        echo '⚠️ WooCommerce not available for testing<br>';
    }
    
} catch (Exception $e) {
    echo '❌ Language detection test failed: ' . $e->getMessage() . '<br>';
}

// Test 4: Check hooks are registered
echo '<h2>Test 4: Hook Registration</h2>';
$hooks_registered = 0;
if (has_action('woocommerce_order_status_processing')) {
    echo '✅ Processing order hook is registered<br>';
    $hooks_registered++;
}
if (has_action('woocommerce_order_status_completed')) {
    echo '✅ Completed order hook is registered<br>';
    $hooks_registered++;
}

echo $hooks_registered > 0 ? '✅ Order status hooks are active<br>' : '❌ No order status hooks found<br>';

echo '<h2>Test Summary</h2>';
echo '<p>All critical components appear to be loaded. To fully test the integration:</p>';
echo '<ol>';
echo '<li>Configure Brevo API key and list IDs in <strong>FP Esperienze → Settings → Integrations</strong></li>';
echo '<li>Create a test order with experience products</li>';
echo '<li>Change order status to "processing" or "completed"</li>';
echo '<li>Check WordPress error logs for Brevo API calls</li>';
echo '<li>Verify contacts appear in your Brevo lists</li>';
echo '</ol>';

echo '<p><strong>⚠️ Remember to remove this test file after testing!</strong></p>';
?>