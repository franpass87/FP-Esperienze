<?php
/**
 * Investigation script to understand WooCommerce product type loading timing
 */

echo "=== WooCommerce Product Type Loading Investigation ===\n\n";

// Let's check the typical WooCommerce hooks and when they fire
$wc_hooks_to_check = [
    'woocommerce_loaded',
    'woocommerce_init',
    'init',
    'plugins_loaded',
    'wp_loaded',
    'admin_init',
    'current_screen'
];

echo "Typical WordPress/WooCommerce hook firing order:\n";
foreach ($wc_hooks_to_check as $hook) {
    echo "  - $hook\n";
}

echo "\nCurrent plugin initialization:\n";
echo "  1. plugins_loaded -> fp_esperienze_init()\n";
echo "  2. init (priority 5) -> Plugin::initExperienceProductType() -> new Experience()\n";
echo "  3. Experience constructor -> add_filter('woocommerce_product_type_selector', ...)\n";

echo "\nPotential issues:\n";
echo "  1. WooCommerce might load product types BEFORE 'init' hook\n";
echo "  2. Product type selector might be built during admin_init or later\n";
echo "  3. Filter might need to be registered even earlier\n";

echo "\nLet's check the Experience class loading path:\n";

// Check if we can load the class and see what methods it has
require_once __DIR__ . '/vendor/autoload.php';

// Define constants to avoid errors
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }
if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) { 
    define('FP_ESPERIENZE_PLUGIN_DIR', __DIR__ . '/'); 
}

// Now we need to check the Experience class without instantiating it
$reflection = new ReflectionClass('FP\\Esperienze\\ProductType\\Experience');
echo "\nExperience class methods:\n";
foreach ($reflection->getMethods() as $method) {
    if ($method->isPublic()) {
        echo "  - " . $method->getName() . "()\n";
    }
}

echo "\nThe addProductType method:\n";
$method = $reflection->getMethod('addProductType');
echo "  Parameters: " . $method->getNumberOfParameters() . "\n";
echo "  Expected to receive product types array and return modified array\n";

echo "\nRecommended fixes to investigate:\n";
echo "  1. Move filter registration to 'plugins_loaded' hook instead of constructor\n";
echo "  2. Use 'woocommerce_loaded' hook if available\n";
echo "  3. Check if WooCommerce is available before registering filters\n";
echo "  4. Add debug logging to see when filters are actually applied\n";

echo "\n=== Investigation Complete ===\n";