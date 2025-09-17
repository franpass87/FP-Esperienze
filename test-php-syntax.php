<?php
/**
 * PHP Syntax and Structure Test for Experience Product Type
 * 
 * This script tests the PHP syntax and basic structure of the Experience product type
 * without requiring WordPress to be loaded.
 */

echo "=== FP Esperienze - PHP Syntax and Structure Test ===\n\n";

$base_dir = dirname(__FILE__);

// Files to test
$files_to_test = [
    'fp-esperienze.php' => 'Main plugin file',
    'includes/ProductType/Experience.php' => 'Experience product type class',
    'includes/ProductType/WC_Product_Experience.php' => 'WC_Product_Experience class',
    'includes/Core/Plugin.php' => 'Main plugin class',
];

echo "Testing PHP syntax for key files...\n\n";

$all_passed = true;

foreach ($files_to_test as $file => $description) {
    $full_path = $base_dir . '/' . $file;
    
    if (!file_exists($full_path)) {
        echo "❌ File not found: $file\n";
        $all_passed = false;
        continue;
    }
    
    // Test PHP syntax
    $output = [];
    $return_code = 0;
    exec("php -l " . escapeshellarg($full_path) . " 2>&1", $output, $return_code);
    
    if ($return_code === 0) {
        echo "✅ $description - Syntax OK\n";
    } else {
        echo "❌ $description - Syntax Error:\n";
        echo "   " . implode("\n   ", $output) . "\n";
        $all_passed = false;
    }
}

echo "\n" . str_repeat("=", 60) . "\n";

if ($all_passed) {
    echo "🎉 ALL SYNTAX TESTS PASSED!\n\n";
    echo "Next steps:\n";
    echo "1. Upload/activate the plugin in WordPress\n";
    echo "2. Run test-experience-functionality.php for full testing\n";
    echo "3. Manually test creating an Experience product\n";
} else {
    echo "❌ SYNTAX ERRORS FOUND!\n\n";
    echo "Please fix the syntax errors above before proceeding.\n";
}

echo "\nFile structure check:\n";

// Check important directories
$directories = [
    'includes/ProductType',
    'includes/Core',
    'templates',
    'assets',
];

foreach ($directories as $dir) {
    $full_path = $base_dir . '/' . $dir;
    if (is_dir($full_path)) {
        echo "✅ Directory exists: $dir\n";
    } else {
        echo "❌ Directory missing: $dir\n";
    }
}

echo "\nComposer dependencies:\n";
if (file_exists($base_dir . '/composer.json')) {
    echo "✅ composer.json found\n";
    if (file_exists($base_dir . '/vendor/autoload.php')) {
        echo "✅ Composer autoloader found\n";
    } else {
        echo "⚠️  Composer dependencies not installed (run: composer install --no-dev)\n";
    }
} else {
    echo "❌ composer.json not found\n";
}

?>