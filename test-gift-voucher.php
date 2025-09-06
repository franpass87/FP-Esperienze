<?php
/**
 * Simple test script to verify gift voucher functionality
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

echo '<h1>FP Esperienze - Gift Voucher Test</h1>';

// Test 1: Check if classes exist
echo '<h2>Test 1: Class Loading</h2>';
try {
    $voucher_manager = new \FP\Esperienze\Data\VoucherManager();
    echo '✅ VoucherManager class loaded successfully<br>';
} catch (Exception $e) {
    echo '❌ VoucherManager class failed: ' . $e->getMessage() . '<br>';
}

try {
    $pdf_class = new \FP\Esperienze\PDF\Voucher_Pdf();
    echo '✅ Voucher_Pdf class loaded successfully<br>';
} catch (Exception $e) {
    echo '❌ Voucher_Pdf class failed: ' . $e->getMessage() . '<br>';
}

try {
    $qr_class = new \FP\Esperienze\PDF\Qr();
    echo '✅ Qr class loaded successfully<br>';
} catch (Exception $e) {
    echo '❌ Qr class failed: ' . $e->getMessage() . '<br>';
}

// Test 2: Check database tables
echo '<h2>Test 2: Database Tables</h2>';
global $wpdb;

$table_name = $wpdb->prefix . 'fp_exp_vouchers';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

if ($table_exists) {
    echo '✅ Gift vouchers table exists<br>';
    
    // Check table structure
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    $required_columns = ['code', 'product_id', 'amount_type', 'amount', 'recipient_name', 'recipient_email', 'status', 'expires_on'];
    
    $existing_columns = array_column($columns, 'Field');
    $missing_columns = array_diff($required_columns, $existing_columns);
    
    if (empty($missing_columns)) {
        echo '✅ All required columns exist<br>';
    } else {
        echo '❌ Missing columns: ' . implode(', ', $missing_columns) . '<br>';
    }
} else {
    echo '❌ Gift vouchers table does not exist<br>';
}

// Test 3: Check dependencies
echo '<h2>Test 3: Dependencies</h2>';

if (class_exists('Dompdf\Dompdf')) {
    echo '✅ DOMPDF library loaded<br>';
} else {
    echo '❌ DOMPDF library not found<br>';
}

if (class_exists('chillerlan\QRCode\QRCode')) {
    echo '✅ QR Code library loaded<br>';
} else {
    echo '❌ QR Code library not found<br>';
}

// Test 4: Check settings
echo '<h2>Test 4: Gift Settings</h2>';

$settings = [
    'fp_esperienze_gift_default_exp_months' => get_option('fp_esperienze_gift_default_exp_months'),
    'fp_esperienze_gift_pdf_brand_color' => get_option('fp_esperienze_gift_pdf_brand_color'),
    'fp_esperienze_gift_email_sender_name' => get_option('fp_esperienze_gift_email_sender_name'),
    'fp_esperienze_gift_email_sender_email' => get_option('fp_esperienze_gift_email_sender_email'),
    'fp_esperienze_gift_secret_hmac' => get_option('fp_esperienze_gift_secret_hmac'),
];

foreach ($settings as $key => $value) {
    if (!empty($value)) {
        echo '✅ ' . $key . ': ' . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) . '<br>';
    } else {
        echo '❌ ' . $key . ': Not set<br>';
    }
}

// Test 5: Check upload directory
echo '<h2>Test 5: Upload Directory</h2>';

$upload_dir = wp_upload_dir();
$voucher_dir = $upload_dir['basedir'] . '/fp-vouchers/';

if (wp_mkdir_p($voucher_dir)) {
    echo '✅ Voucher directory created/exists: ' . $voucher_dir . '<br>';
    
    if (is_writable($voucher_dir)) {
        echo '✅ Voucher directory is writable<br>';
    } else {
        echo '❌ Voucher directory is not writable<br>';
    }
} else {
    echo '❌ Failed to create voucher directory<br>';
}

// Test 6: Generate test QR code
echo '<h2>Test 6: QR Code Generation</h2>';

try {
    $test_voucher_data = [
        'code' => 'TEST123456',
        'product_id' => 1,
        'amount_type' => 'full',
        'amount' => 100.00,
        'expires_on' => date('Y-m-d', strtotime('+1 year')),
    ];
    
    $qr_path = \FP\Esperienze\PDF\Qr::generate($test_voucher_data);
    
    if (file_exists($qr_path)) {
        echo '✅ QR code generated successfully<br>';
        echo '<img src="' . str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $qr_path) . '" style="max-width: 150px;"><br>';
    } else {
        echo '❌ QR code file not found<br>';
    }
} catch (Exception $e) {
    echo '❌ QR code generation failed: ' . $e->getMessage() . '<br>';
}

// Test 7: Test QR payload verification
echo '<h2>Test 7: QR Payload Verification</h2>';

try {
    $test_payload = 'FPX|VC:TEST123456|PID:1|TYPE:full|AMT:100|EXP:2025-12-31|SIG:' . hash_hmac('sha256', 'FPX|VC:TEST123456|PID:1|TYPE:full|AMT:100|EXP:2025-12-31', get_option('fp_esperienze_gift_secret_hmac', ''));
    
    $verified_data = \FP\Esperienze\PDF\Qr::verifyPayload($test_payload);
    
    if ($verified_data !== false) {
        echo '✅ QR payload verification successful<br>';
        echo 'Verified data: <pre>' . print_r($verified_data, true) . '</pre>';
    } else {
        echo '❌ QR payload verification failed<br>';
    }
} catch (Exception $e) {
    echo '❌ QR payload verification error: ' . $e->getMessage() . '<br>';
}

// Test 8: Check experience products
echo '<h2>Test 8: Experience Products</h2>';

$experience_products = get_posts([
    'post_type' => 'product',
    'meta_query' => [
        [
            'key' => '_fp_experience_enabled',
            'value' => 'yes',
            'compare' => '='
        ]
    ],
    'posts_per_page' => 5,
    'post_status' => 'publish'
]);

if (!empty($experience_products)) {
    echo '✅ Found ' . count($experience_products) . ' experience products<br>';
    foreach ($experience_products as $product) {
        echo '- ' . $product->post_title . ' (ID: ' . $product->ID . ')<br>';
    }
} else {
    echo '❌ No experience products found. Create at least one experience product to test the gift feature.<br>';
}

echo '<h2>Test Summary</h2>';
echo '<p>If all tests pass, the gift voucher feature should be ready for manual testing.</p>';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ul>';
echo '<li>Create an experience product if none exist</li>';
echo '<li>Test the gift form on the frontend</li>';
echo '<li>Complete a test order with gift option</li>';
echo '<li>Verify voucher generation and email delivery</li>';
echo '</ul>';

echo '<p><em>Remember to remove this test file from production!</em></p>';
?>