<?php
/**
 * Test script to verify the autoloading fix
 * 
 * This script tests that the FP\Esperienze\Core\Plugin class can be loaded
 * and instantiated, which should fix the original error:
 * 'Class "FP\Esperienze\Core\Plugin" not found'
 * 
 * Run this script from the plugin directory to verify the fix.
 */

echo "FP Esperienze - Autoloading Test\n";
echo "=================================\n\n";

// Check we're in the right directory
if (!file_exists('fp-esperienze.php')) {
    echo "❌ Error: This script must be run from the FP Esperienze plugin directory.\n";
    exit(1);
}

// Check composer autoloader exists
$autoloader_path = 'vendor/autoload.php';
if (!file_exists($autoloader_path)) {
    echo "❌ Error: Composer dependencies not installed.\n";
    echo "   Please run: composer install --no-dev --optimize-autoloader\n";
    echo "   Or use the setup.sh script.\n";
    exit(1);
}

echo "✅ Found composer autoloader\n";

// Define minimal WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

// Load autoloader
require_once $autoloader_path;
echo "✅ Composer autoloader loaded\n";

// Test Plugin class loading
echo "Testing FP\\Esperienze\\Core\\Plugin class loading... ";
try {
    if (class_exists('FP\\Esperienze\\Core\\Plugin')) {
        echo "✅ OK\n";
    } else {
        echo "❌ Class not found\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test Experience product type class
echo "Testing FP\\Esperienze\\ProductType\\Experience class loading... ";
try {
    if (class_exists('FP\\Esperienze\\ProductType\\Experience')) {
        echo "✅ OK\n";
    } else {
        echo "❌ Class not found\n";
        exit(1);
    }
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ All tests passed!\n";
echo "\nThe autoloading issue should now be fixed.\n";
echo "You can now activate the plugin in WordPress.\n";