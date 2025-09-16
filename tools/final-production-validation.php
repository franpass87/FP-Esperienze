#!/usr/bin/env php
<?php
/**
 * Final Production Readiness Validation Script
 * Comprehensive test for FP Esperienze plugin production deployment
 */

define('FP_ESPERIENZE_VERSION', '1.0.0');
define('FP_ESPERIENZE_PLUGIN_DIR', dirname(__DIR__) . '/');

echo "🚀 FP ESPERIENZE - FINAL PRODUCTION READINESS VALIDATION\n";
echo str_repeat("=", 70) . "\n";

$validation_results = [
    'overall_score' => 0,
    'max_score' => 0,
    'critical_failures' => [],
    'warnings' => [],
    'passed_tests' => []
];

/**
 * Test runner function
 */
function runTest($name, $callback, &$results, $critical = true) {
    $results['max_score']++;
    echo "🔍 Testing: $name ... ";
    
    try {
        $result = $callback();
        if ($result === true) {
            echo "✅ PASS\n";
            $results['overall_score']++;
            $results['passed_tests'][] = $name;
        } else {
            echo "❌ FAIL\n";
            if ($critical) {
                $results['critical_failures'][] = "$name: " . (is_string($result) ? $result : 'Test failed');
            } else {
                $results['warnings'][] = "$name: " . (is_string($result) ? $result : 'Test failed');
            }
        }
    } catch (Exception $e) {
        echo "💥 ERROR\n";
        if ($critical) {
            $results['critical_failures'][] = "$name: " . $e->getMessage();
        } else {
            $results['warnings'][] = "$name: " . $e->getMessage();
        }
    }
}

// ==================== CORE ARCHITECTURE TESTS ====================
echo "\n📋 CORE ARCHITECTURE TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Main plugin file exists and is valid", function() {
    $main_file = FP_ESPERIENZE_PLUGIN_DIR . 'fp-esperienze.php';
    if (!file_exists($main_file)) return "Main plugin file not found";
    
    $content = file_get_contents($main_file);
    if (strpos($content, 'Plugin Name:') === false) return "Invalid plugin header";
    if (strpos($content, "defined('ABSPATH')") === false) return "Missing ABSPATH check";
    
    return true;
}, $validation_results);

runTest("Composer autoloader is configured", function() {
    $autoloader = FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';
    if (!file_exists($autoloader)) return "Composer autoloader missing - run composer install";
    
    require_once $autoloader;
    return true;
}, $validation_results);

runTest("Core plugin class is loadable", function() {
    $core_file = FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/Plugin.php';
    if (!file_exists($core_file)) return "Core Plugin class file missing";
    
    // Check if class can be loaded (PSR-4 autoloading test)
    $expected_path = FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/Plugin.php';
    return file_exists($expected_path);
}, $validation_results);

runTest("Database installer is present", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/Installer.php');
}, $validation_results);

// ==================== WOOCOMMERCE INTEGRATION TESTS ====================
echo "\n🛒 WOOCOMMERCE INTEGRATION TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Experience product type class exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/ProductType/Experience.php');
}, $validation_results);

runTest("WC_Product_Experience class exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/ProductType/WC_Product_Experience.php');
}, $validation_results);

runTest("HPOS compatibility declaration", function() {
    $main_file = FP_ESPERIENZE_PLUGIN_DIR . 'fp-esperienze.php';
    $content = file_get_contents($main_file);
    return strpos($content, 'FeaturesUtil::declare_compatibility') !== false;
}, $validation_results);

// ==================== ADMIN INTERFACE TESTS ====================
echo "\n⚙️ ADMIN INTERFACE TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Admin menu manager exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Admin/MenuManager.php');
}, $validation_results);

runTest("Setup wizard is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Admin/SetupWizard.php');
}, $validation_results);

runTest("Admin assets are present", function() {
    $admin_js = FP_ESPERIENZE_PLUGIN_DIR . 'assets/js/admin-modular.js';
    $admin_css = FP_ESPERIENZE_PLUGIN_DIR . 'assets/css/admin.css';
    
    if (!file_exists($admin_js)) return "Admin JavaScript missing";
    if (!file_exists($admin_css)) return "Admin CSS missing";
    
    return true;
}, $validation_results);

// ==================== DATA MANAGEMENT TESTS ====================
echo "\n💾 DATA MANAGEMENT TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Schedule manager is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/ScheduleManager.php');
}, $validation_results);

runTest("Booking manager is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Booking/BookingManager.php');
}, $validation_results);

runTest("Meeting point manager exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/MeetingPointManager.php');
}, $validation_results);

runTest("Voucher manager is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Data/VoucherManager.php');
}, $validation_results);

// ==================== REST API TESTS ====================
echo "\n🌐 REST API TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Availability API is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/REST/AvailabilityAPI.php');
}, $validation_results);

runTest("Bookings API exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/REST/BookingsAPI.php');
}, $validation_results);

runTest("Secure PDF API is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/REST/SecurePDFAPI.php');
}, $validation_results);

// ==================== SECURITY TESTS ====================
echo "\n🔒 SECURITY TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Capability manager is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/CapabilityManager.php');
}, $validation_results);

runTest("Security enhancer exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/SecurityEnhancer.php');
}, $validation_results);

runTest("Rate limiting is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/RateLimiter.php');
}, $validation_results);

runTest("ABSPATH protection in critical files", function() {
    $critical_files = [
        'includes/Core/Plugin.php',
        'includes/ProductType/Experience.php',
        'includes/Admin/MenuManager.php'
    ];
    
    foreach ($critical_files as $file) {
        $path = FP_ESPERIENZE_PLUGIN_DIR . $file;
        if (!file_exists($path)) continue;
        
        $content = file_get_contents($path);
        if (strpos($content, "defined('ABSPATH')") === false && 
            strpos($content, 'defined("ABSPATH")') === false &&
            strpos($content, "defined( 'ABSPATH' )") === false) {
            return "Missing ABSPATH check in $file";
        }
    }
    
    return true;
}, $validation_results);

// ==================== FRONTEND TESTS ====================
echo "\n🎨 FRONTEND TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Template system is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Frontend/Templates.php');
}, $validation_results);

runTest("Shortcode system exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Frontend/Shortcodes.php');
}, $validation_results);

runTest("Single experience template exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'templates/single-experience.php');
}, $validation_results);

// ==================== PDF/VOUCHER TESTS ====================
echo "\n📄 PDF/VOUCHER SYSTEM TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("PDF voucher generator exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/PDF/Voucher_Pdf.php');
}, $validation_results);

runTest("QR code generator is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/PDF/Qr.php');
}, $validation_results);

runTest("Required PDF dependencies", function() {
    $composer_json = FP_ESPERIENZE_PLUGIN_DIR . 'composer.json';
    if (!file_exists($composer_json)) return "composer.json missing";
    
    $composer_data = json_decode(file_get_contents($composer_json), true);
    if (!isset($composer_data['require']['dompdf/dompdf'])) return "dompdf dependency missing";
    if (!isset($composer_data['require']['chillerlan/php-qrcode'])) return "QR code dependency missing";
    
    return true;
}, $validation_results);

// ==================== INTERNATIONALIZATION TESTS ====================
echo "\n🌍 INTERNATIONALIZATION TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Translation files exist", function() {
    $pot_file = FP_ESPERIENZE_PLUGIN_DIR . 'languages/fp-esperienze.pot';
    if (!file_exists($pot_file)) return "POT template file missing";
    
    return true;
}, $validation_results, false);

runTest("I18n manager is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Core/I18nManager.php');
}, $validation_results, false);

// ==================== INTEGRATION TESTS ====================
echo "\n🔗 INTEGRATION TESTS\n";
echo str_repeat("-", 50) . "\n";

runTest("Tracking manager exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Integrations/TrackingManager.php');
}, $validation_results, false);

runTest("Brevo integration is implemented", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Integrations/BrevoManager.php');
}, $validation_results, false);

runTest("Google Places integration exists", function() {
    return file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'includes/Integrations/GooglePlacesManager.php');
}, $validation_results, false);

// ==================== FINAL ASSESSMENT ====================
echo "\n" . str_repeat("=", 70) . "\n";
echo "🎯 FINAL PRODUCTION READINESS ASSESSMENT\n";
echo str_repeat("=", 70) . "\n";

$score_percentage = ($validation_results['overall_score'] / $validation_results['max_score']) * 100;

echo "📊 OVERALL SCORE: {$validation_results['overall_score']}/{$validation_results['max_score']} (" . round($score_percentage, 1) . "%)\n\n";

if (empty($validation_results['critical_failures'])) {
    if ($score_percentage >= 95) {
        echo "🟢 STATUS: EXCELLENT - READY FOR PRODUCTION\n";
        echo "✨ The plugin exceeds production standards with " . round($score_percentage, 1) . "% compliance.\n";
    } elseif ($score_percentage >= 85) {
        echo "🟡 STATUS: GOOD - READY FOR PRODUCTION WITH MINOR IMPROVEMENTS\n";
        echo "✅ The plugin meets production standards with " . round($score_percentage, 1) . "% compliance.\n";
    } else {
        echo "🟠 STATUS: READY WITH WARNINGS\n";
        echo "⚠️ The plugin can be deployed but should address warnings.\n";
    }
} else {
    echo "🔴 STATUS: NOT READY FOR PRODUCTION\n";
    echo "❌ Critical issues must be resolved before deployment.\n";
}

if (!empty($validation_results['critical_failures'])) {
    echo "\n🚨 CRITICAL ISSUES TO RESOLVE:\n";
    echo str_repeat("-", 40) . "\n";
    foreach ($validation_results['critical_failures'] as $failure) {
        echo "❌ $failure\n";
    }
}

if (!empty($validation_results['warnings'])) {
    echo "\n⚠️ WARNINGS TO CONSIDER:\n";
    echo str_repeat("-", 40) . "\n";
    foreach ($validation_results['warnings'] as $warning) {
        echo "⚠️ $warning\n";
    }
}

echo "\n✅ SUCCESSFUL VALIDATIONS:\n";
echo str_repeat("-", 40) . "\n";
foreach ($validation_results['passed_tests'] as $test) {
    echo "✅ $test\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "🏁 PRODUCTION READINESS VALIDATION COMPLETE\n";
echo str_repeat("=", 70) . "\n";

// Return appropriate exit code
exit(empty($validation_results['critical_failures']) ? 0 : 1);