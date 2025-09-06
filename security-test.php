<?php
/**
 * Security Test Script for FP Esperienze
 *
 * Run this script to verify security implementation
 * Usage: wp eval-file security-test.php
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

echo "=== FP Esperienze Security Test ===\n\n";

// Test 1: Check if capability exists
echo "1. Testing Capability System:\n";
$admin_role = get_role('administrator');
$shop_manager_role = get_role('shop_manager');
$editor_role = get_role('editor');

if ($admin_role && $admin_role->has_cap('manage_fp_esperienze')) {
    echo "  ✓ Administrator has manage_fp_esperienze capability\n";
} else {
    echo "  ✗ Administrator missing manage_fp_esperienze capability\n";
}

if ($shop_manager_role && $shop_manager_role->has_cap('manage_fp_esperienze')) {
    echo "  ✓ Shop Manager has manage_fp_esperienze capability\n";
} else {
    echo "  ✗ Shop Manager missing manage_fp_esperienze capability\n";
}

if ($editor_role && !$editor_role->has_cap('manage_fp_esperienze')) {
    echo "  ✓ Editor correctly does NOT have manage_fp_esperienze capability\n";
} else {
    echo "  ✗ Editor incorrectly has manage_fp_esperienze capability\n";
}

// Test 2: Test Rate Limiter functionality
echo "\n2. Testing Rate Limiter:\n";
$rate_limiter_exists = class_exists('FP\\Esperienze\\Core\\RateLimiter');
if ($rate_limiter_exists) {
    echo "  ✓ RateLimiter class exists\n";
    
    // Test basic rate limiting
    $limit_check1 = FP\Esperienze\Core\RateLimiter::checkRateLimit('test_endpoint', 5, 60);
    $limit_check2 = FP\Esperienze\Core\RateLimiter::checkRateLimit('test_endpoint', 5, 60);
    
    if ($limit_check1 && $limit_check2) {
        echo "  ✓ Rate limiting allows normal requests\n";
    } else {
        echo "  ✗ Rate limiting blocking normal requests\n";
    }
    
    // Test headers
    $headers = FP\Esperienze\Core\RateLimiter::getRateLimitHeaders('test_endpoint', 5, 60);
    if (isset($headers['X-RateLimit-Limit']) && $headers['X-RateLimit-Limit'] == 5) {
        echo "  ✓ Rate limit headers working correctly\n";
    } else {
        echo "  ✗ Rate limit headers not working\n";
    }
} else {
    echo "  ✗ RateLimiter class not found\n";
}

// Test 3: Test CapabilityManager
echo "\n3. Testing CapabilityManager:\n";
$capability_manager_exists = class_exists('FP\\Esperienze\\Core\\CapabilityManager');
if ($capability_manager_exists) {
    echo "  ✓ CapabilityManager class exists\n";
    
    // Create a test user to check capability functions
    $test_user = wp_create_user('fp_test_user_' . time(), 'test_password', 'test@example.com');
    if (!is_wp_error($test_user)) {
        $user = new WP_User($test_user);
        $user->set_role('administrator');
        
        // Switch to test user context
        wp_set_current_user($test_user);
        
        if (FP\Esperienze\Core\CapabilityManager::canManageFPEsperienze()) {
            echo "  ✓ Administrator can manage FP Esperienze\n";
        } else {
            echo "  ✗ Administrator cannot manage FP Esperienze\n";
        }
        
        // Cleanup test user
        wp_delete_user($test_user);
        wp_set_current_user(0); // Reset current user
    }
} else {
    echo "  ✗ CapabilityManager class not found\n";
}

// Test 4: Check sanitization functions exist
echo "\n4. Testing Sanitization:\n";
$test_input = '<script>alert("xss")</script>Test content';
$sanitized = sanitize_textarea_field($test_input);
if (strpos($sanitized, '<script>') === false) {
    echo "  ✓ HTML tags properly stripped from input\n";
} else {
    echo "  ✗ HTML tags not properly stripped\n";
}

// Test wp_strip_all_tags
$stripped = wp_strip_all_tags($test_input);
if (strpos($stripped, '<script>') === false && strpos($stripped, 'Test content') !== false) {
    echo "  ✓ wp_strip_all_tags working correctly\n";
} else {
    echo "  ✗ wp_strip_all_tags not working properly\n";
}

// Test 5: Check nonce system
echo "\n5. Testing Nonce System:\n";
$nonce = wp_create_nonce('test_action');
if (!empty($nonce)) {
    echo "  ✓ Nonce creation working\n";
    
    if (wp_verify_nonce($nonce, 'test_action')) {
        echo "  ✓ Nonce verification working\n";
    } else {
        echo "  ✗ Nonce verification failed\n";
    }
} else {
    echo "  ✗ Nonce creation failed\n";
}

// Test 6: Check if REST API classes are available
echo "\n6. Testing REST API Classes:\n";
if (class_exists('FP\\Esperienze\\REST\\AvailabilityAPI')) {
    echo "  ✓ AvailabilityAPI class exists\n";
} else {
    echo "  ✗ AvailabilityAPI class not found\n";
}

if (class_exists('FP\\Esperienze\\REST\\BookingsAPI')) {
    echo "  ✓ BookingsAPI class exists\n";
} else {
    echo "  ✗ BookingsAPI class not found\n";
}

echo "\n=== Security Test Complete ===\n";
echo "Review the results above. All items should show ✓ for proper security implementation.\n";
