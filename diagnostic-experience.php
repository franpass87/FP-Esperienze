<?php
/**
 * Quick diagnostic script to check Experience integration
 * This will help pinpoint whether the issue is with:
 * 1. Product type registration 
 * 2. Field visibility
 * 3. Hook timing
 */

// Basic WordPress simulation
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return htmlspecialchars($text);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $args = 1) {
        global $wp_filter;
        if (!isset($wp_filter[$hook])) {
            $wp_filter[$hook] = [];
        }
        $wp_filter[$hook][] = $callback;
        echo "✅ Registered action: $hook\n";
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        global $wp_filter;
        if (!isset($wp_filter[$hook])) {
            $wp_filter[$hook] = [];
        }
        $wp_filter[$hook][] = $callback;
        echo "✅ Registered filter: $hook\n";
        return true;
    }
}

if (!function_exists('has_filter')) {
    function has_filter($hook) {
        global $wp_filter;
        return isset($wp_filter[$hook]) && !empty($wp_filter[$hook]);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        global $wp_filter;
        if (!isset($wp_filter[$hook])) {
            return $value;
        }
        
        foreach ($wp_filter[$hook] as $callback) {
            if (is_callable($callback)) {
                $value = call_user_func($callback, $value, ...$args);
            }
        }
        return $value;
    }
}

// Define required constants
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }
if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) { 
    define('FP_ESPERIENZE_PLUGIN_DIR', __DIR__ . '/'); 
}

// Load autoloader
require_once __DIR__ . '/vendor/autoload.php';

echo "=== Experience Integration Diagnostic ===\n\n";

// Test 1: Experience class instantiation
echo "1. Testing Experience class instantiation:\n";
try {
    $experience = new FP\Esperienze\ProductType\Experience();
    echo "✅ Experience class instantiated successfully\n";
} catch (Exception $e) {
    echo "❌ Failed to instantiate Experience class: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if filters were registered
echo "\n2. Checking filter registration:\n";
$critical_filters = [
    'woocommerce_product_type_selector',
    'woocommerce_product_class', 
    'woocommerce_product_data_tabs'
];

foreach ($critical_filters as $filter) {
    if (has_filter($filter)) {
        echo "✅ Filter registered: $filter\n";
    } else {
        echo "❌ Filter missing: $filter\n";
    }
}

// Test 3: Test the addProductType method directly
echo "\n3. Testing addProductType method:\n";
$sample_types = [
    'simple' => 'Simple Product',
    'variable' => 'Variable Product'
];

echo "Before: " . implode(', ', array_keys($sample_types)) . "\n";
$result = apply_filters('woocommerce_product_type_selector', $sample_types);
echo "After filter: " . implode(', ', array_keys($result)) . "\n";

if (isset($result['experience'])) {
    echo "✅ Experience type added: " . $result['experience'] . "\n";
} else {
    echo "❌ Experience type not added\n";
}

// Test 4: Test product class mapping
echo "\n4. Testing product class mapping:\n";
$class = apply_filters('woocommerce_product_class', 'WC_Product', 'experience');
echo "Product class for 'experience': $class\n";

if ($class === 'WC_Product_Experience') {
    echo "✅ Correct product class mapping\n";
} else {
    echo "❌ Incorrect product class mapping\n";
}

// Test 5: Check if WC_Product_Experience class exists
echo "\n5. Checking WC_Product_Experience class:\n";
if (class_exists('WC_Product_Experience')) {
    echo "✅ WC_Product_Experience class available\n";
} else {
    echo "❌ WC_Product_Experience class not found\n";
}

echo "\n=== Diagnostic Complete ===\n";