#!/usr/bin/env php
<?php
/**
 * Standalone Production Readiness Test
 * Run this script to validate FP Esperienze plugin production readiness
 */

// Set up basic constants
define('FP_ESPERIENZE_VERSION', '1.0.0');
define('FP_ESPERIENZE_PLUGIN_DIR', dirname(__DIR__) . '/');

echo "\n" . str_repeat("=", 60) . "\n";
echo "FP ESPERIENZE PRODUCTION READINESS CHECK\n";
echo str_repeat("=", 60) . "\n";

$results = [
    'overall_status' => 'pass',
    'critical_issues' => [],
    'warnings' => [],
    'checks' => []
];

// Basic file structure checks
echo "\n🔍 Checking file structure...\n";

$required_files = [
    'fp-esperienze.php' => 'Main plugin file',
    'includes/Core/Plugin.php' => 'Core plugin class',
    'includes/Core/Installer.php' => 'Database installer',
    'includes/ProductType/Experience.php' => 'Experience product type',
    'includes/ProductType/WC_Product_Experience.php' => 'WooCommerce experience product',
    'includes/Admin/MenuManager.php' => 'Admin menu manager',
    'includes/REST/AvailabilityAPI.php' => 'Availability REST API',
    'includes/Data/ScheduleManager.php' => 'Schedule manager',
    'vendor/autoload.php' => 'Composer autoloader',
    'languages/fp-esperienze.pot' => 'Translation template',
    'templates/single-experience.php' => 'Single experience template',
    'assets/js/admin-modular.js' => 'Admin JavaScript',
    'assets/css/admin.css' => 'Admin CSS'
];

foreach ($required_files as $file => $description) {
    $path = FP_ESPERIENZE_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        $results['checks'][] = "✅ $description ($file)";
        echo "✅ $description\n";
    } else {
        $issue = "❌ Missing: $description ($file)";
        if (in_array($file, ['fp-esperienze.php', 'includes/Core/Plugin.php', 'includes/Core/Installer.php'])) {
            $results['critical_issues'][] = $issue;
        } else {
            $results['warnings'][] = $issue;
        }
        echo "❌ Missing: $description\n";
    }
}

// Syntax checks
echo "\n🔍 Checking PHP syntax...\n";

$php_files = [
    'fp-esperienze.php',
    'includes/Core/Plugin.php',
    'includes/Core/Installer.php',
    'includes/ProductType/Experience.php',
    'includes/ProductType/WC_Product_Experience.php',
    'includes/Admin/MenuManager.php',
    'includes/REST/AvailabilityAPI.php'
];

foreach ($php_files as $file) {
    $path = FP_ESPERIENZE_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        $output = shell_exec("php -l \"$path\" 2>&1");
        if (strpos($output, 'No syntax errors') !== false) {
            $results['checks'][] = "✅ Syntax valid: $file";
            echo "✅ Syntax valid: $file\n";
        } else {
            $results['critical_issues'][] = "❌ Syntax error in $file: " . trim($output);
            echo "❌ Syntax error in $file\n";
        }
    }
}

// Check composer dependencies
echo "\n🔍 Checking composer dependencies...\n";

if (file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php')) {
    $results['checks'][] = "✅ Composer autoloader exists";
    echo "✅ Composer autoloader exists\n";
    
    // Check for required packages
    $composer_json = FP_ESPERIENZE_PLUGIN_DIR . 'composer.json';
    if (file_exists($composer_json)) {
        $composer_data = json_decode(file_get_contents($composer_json), true);
        $required_packages = ['dompdf/dompdf', 'chillerlan/php-qrcode'];
        
        foreach ($required_packages as $package) {
            if (isset($composer_data['require'][$package])) {
                $results['checks'][] = "✅ Required package: $package";
                echo "✅ Required package: $package\n";
            } else {
                $results['warnings'][] = "⚠️ Missing package: $package";
                echo "⚠️ Missing package: $package\n";
            }
        }
    }
} else {
    $results['critical_issues'][] = "❌ Composer autoloader missing - run: composer install --no-dev";
    echo "❌ Composer autoloader missing\n";
}

// Check directory structure
echo "\n🔍 Checking directory structure...\n";

$required_dirs = [
    'includes/Core',
    'includes/ProductType', 
    'includes/Admin',
    'includes/REST',
    'includes/Data',
    'includes/Frontend',
    'includes/Booking',
    'includes/PDF',
    'includes/Integrations',
    'templates',
    'assets/js',
    'assets/css',
    'languages'
];

foreach ($required_dirs as $dir) {
    $path = FP_ESPERIENZE_PLUGIN_DIR . $dir;
    if (is_dir($path)) {
        $results['checks'][] = "✅ Directory exists: $dir";
        echo "✅ Directory exists: $dir\n";
    } else {
        $results['warnings'][] = "⚠️ Directory missing: $dir";
        echo "⚠️ Directory missing: $dir\n";
    }
}

// Check for autoloading issues
echo "\n🔍 Checking autoloader setup...\n";

if (file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';
    
    // Test PSR-4 autoloading
    $autoloader_test_classes = [
        'FP\\Esperienze\\Core\\Plugin',
        'FP\\Esperienze\\ProductType\\Experience',
        'FP\\Esperienze\\Admin\\MenuManager'
    ];
    
    foreach ($autoloader_test_classes as $class) {
        $file_path = str_replace(['FP\\Esperienze\\', '\\'], ['includes/', '/'], $class) . '.php';
        $full_path = FP_ESPERIENZE_PLUGIN_DIR . $file_path;
        
        if (file_exists($full_path)) {
            $results['checks'][] = "✅ Autoloader mapping correct: $class";
            echo "✅ Autoloader mapping correct: " . basename($class) . "\n";
        } else {
            $results['critical_issues'][] = "❌ Autoloader mapping issue: $class -> $file_path";
            echo "❌ Autoloader mapping issue: " . basename($class) . "\n";
        }
    }
}

// Check security implementations
echo "\n🔍 Checking security implementations...\n";

$security_files = [
    'includes/Core/CapabilityManager.php',
    'includes/Core/SecurityEnhancer.php',
    'includes/Core/RateLimiter.php'
];

foreach ($security_files as $file) {
    $path = FP_ESPERIENZE_PLUGIN_DIR . $file;
    if (file_exists($path)) {
        $results['checks'][] = "✅ Security component: " . basename($file);
        echo "✅ Security component: " . basename($file) . "\n";
    } else {
        $results['warnings'][] = "⚠️ Security component missing: " . basename($file);
        echo "⚠️ Security component missing: " . basename($file) . "\n";
    }
}

// Check for common security patterns in code
$main_file = FP_ESPERIENZE_PLUGIN_DIR . 'fp-esperienze.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    
    // Check for ABSPATH protection
    if (strpos($content, "defined('ABSPATH')") !== false || strpos($content, 'defined("ABSPATH")') !== false) {
        $results['checks'][] = "✅ ABSPATH protection in main file";
        echo "✅ ABSPATH protection in main file\n";
    } else {
        $results['warnings'][] = "⚠️ ABSPATH protection missing in main file";
        echo "⚠️ ABSPATH protection missing in main file\n";
    }
    
    // Check for version checks
    if (strpos($content, 'version_compare') !== false) {
        $results['checks'][] = "✅ Version compatibility checks";
        echo "✅ Version compatibility checks\n";
    } else {
        $results['warnings'][] = "⚠️ Version compatibility checks missing";
        echo "⚠️ Version compatibility checks missing\n";
    }
}

// Final assessment
echo "\n" . str_repeat("=", 60) . "\n";
echo "FINAL ASSESSMENT\n";
echo str_repeat("=", 60) . "\n";

if (!empty($results['critical_issues'])) {
    $results['overall_status'] = 'fail';
    echo "❌ NOT READY FOR PRODUCTION\n";
    echo "\n🚨 CRITICAL ISSUES TO FIX:\n";
    foreach ($results['critical_issues'] as $issue) {
        echo "$issue\n";
    }
} elseif (!empty($results['warnings'])) {
    $results['overall_status'] = 'warning';
    echo "⚠️ READY WITH WARNINGS\n";
    echo "\n⚠️ WARNINGS TO CONSIDER:\n";
    foreach ($results['warnings'] as $warning) {
        echo "$warning\n";
    }
} else {
    echo "✅ READY FOR PRODUCTION\n";
}

echo "\n📊 SUMMARY:\n";
echo "✅ Successful checks: " . count($results['checks']) . "\n";
echo "⚠️ Warnings: " . count($results['warnings']) . "\n";
echo "❌ Critical issues: " . count($results['critical_issues']) . "\n";

echo "\n" . str_repeat("=", 60) . "\n";

// Return proper exit code
exit(!empty($results['critical_issues']) ? 1 : 0);