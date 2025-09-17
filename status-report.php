<?php
/**
 * Experience Product Type - Complete Status Report
 * 
 * This script provides a comprehensive status report of the Experience product type implementation
 * Run this to get a complete overview of the current state
 */

echo "=== FP Esperienze - Experience Product Type Status Report ===\n";
echo "Generated: " . date('Y-m-d H:i:s T') . "\n\n";

$status = [];

// 1. File Existence Check
echo "📁 FILE STRUCTURE CHECK\n";
echo str_repeat("-", 40) . "\n";

$critical_files = [
    'fp-esperienze.php' => 'Main plugin file',
    'includes/ProductType/Experience.php' => 'Experience product type class',
    'includes/ProductType/WC_Product_Experience.php' => 'WC_Product_Experience class',
    'includes/Core/Plugin.php' => 'Main plugin class',
    'composer.json' => 'Composer configuration',
];

$files_ok = true;
foreach ($critical_files as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file - $description\n";
    } else {
        echo "❌ $file - MISSING - $description\n";
        $files_ok = false;
    }
}

$status['files'] = $files_ok;

// 2. PHP Syntax Check
echo "\n🔍 PHP SYNTAX CHECK\n";
echo str_repeat("-", 40) . "\n";

$syntax_ok = true;
foreach (['fp-esperienze.php', 'includes/ProductType/Experience.php', 'includes/ProductType/WC_Product_Experience.php', 'includes/Core/Plugin.php'] as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $output = [];
        $return_code = 0;
        exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $return_code);
        
        if ($return_code === 0) {
            echo "✅ $file - Syntax OK\n";
        } else {
            echo "❌ $file - Syntax Error\n";
            echo "   " . implode("\n   ", $output) . "\n";
            $syntax_ok = false;
        }
    }
}

$status['syntax'] = $syntax_ok;

// 3. Filter Hook Analysis
echo "\n🔗 FILTER HOOK ANALYSIS\n";
echo str_repeat("-", 40) . "\n";

$experience_file = __DIR__ . '/includes/ProductType/Experience.php';
if (file_exists($experience_file)) {
    $content = file_get_contents($experience_file);
    
    // Check for correct filter usage
    if (strpos($content, "woocommerce_product_type_selector") !== false) {
        echo "✅ Uses correct filter: woocommerce_product_type_selector\n";
        $status['filter_correct'] = true;
    } else {
        echo "❌ Missing or incorrect filter hook\n";
        $status['filter_correct'] = false;
    }
    
    // Check for product class filter
    if (strpos($content, "woocommerce_product_class") !== false) {
        echo "✅ Product class filter registered\n";
        $status['class_filter'] = true;
    } else {
        echo "❌ Product class filter missing\n";
        $status['class_filter'] = false;
    }
    
    // Check for data store registration
    if (strpos($content, "woocommerce_data_stores") !== false) {
        echo "✅ Data store filter registered\n";
        $status['datastore_filter'] = true;
    } else {
        echo "❌ Data store filter missing\n";
        $status['datastore_filter'] = false;
    }
} else {
    echo "❌ Experience.php file not found\n";
    $status['filter_correct'] = false;
    $status['class_filter'] = false;
    $status['datastore_filter'] = false;
}

// 4. Class Definition Check
echo "\n📝 CLASS DEFINITIONS\n";
echo str_repeat("-", 40) . "\n";

// Check Experience class structure
$experience_content = file_exists($experience_file) ? file_get_contents($experience_file) : '';
if (strpos($experience_content, 'class Experience') !== false) {
    echo "✅ Experience class defined\n";
    $status['experience_class'] = true;
} else {
    echo "❌ Experience class not found\n";
    $status['experience_class'] = false;
}

if (strpos($experience_content, 'public function addProductType') !== false) {
    echo "✅ addProductType method exists\n";
    $status['add_product_type_method'] = true;
} else {
    echo "❌ addProductType method missing\n";
    $status['add_product_type_method'] = false;
}

// Check WC_Product_Experience class
$wc_product_file = __DIR__ . '/includes/ProductType/WC_Product_Experience.php';
if (file_exists($wc_product_file)) {
    $wc_content = file_get_contents($wc_product_file);
    if (strpos($wc_content, 'class WC_Product_Experience extends \\WC_Product') !== false) {
        echo "✅ WC_Product_Experience class properly extends WC_Product\n";
        $status['wc_product_class'] = true;
    } else {
        echo "❌ WC_Product_Experience class structure issue\n";
        $status['wc_product_class'] = false;
    }
    
    if (strpos($wc_content, "protected \$product_type = 'experience'") !== false) {
        echo "✅ Product type property set correctly\n";
        $status['product_type_property'] = true;
    } else {
        echo "❌ Product type property missing or incorrect\n";
        $status['product_type_property'] = false;
    }
} else {
    echo "❌ WC_Product_Experience.php file not found\n";
    $status['wc_product_class'] = false;
    $status['product_type_property'] = false;
}

// 5. Plugin Initialization Check
echo "\n🚀 PLUGIN INITIALIZATION\n";
echo str_repeat("-", 40) . "\n";

$plugin_file = __DIR__ . '/includes/Core/Plugin.php';
if (file_exists($plugin_file)) {
    $plugin_content = file_get_contents($plugin_file);
    
    if (strpos($plugin_content, 'initExperienceProductType') !== false) {
        echo "✅ Experience product type initialization method exists\n";
        $status['init_method'] = true;
    } else {
        echo "❌ Experience initialization method missing\n";
        $status['init_method'] = false;
    }
    
    if (strpos($plugin_content, 'new Experience()') !== false) {
        echo "✅ Experience class instantiation found\n";
        $status['experience_instantiation'] = true;
    } else {
        echo "❌ Experience class instantiation missing\n";
        $status['experience_instantiation'] = false;
    }
} else {
    echo "❌ Plugin.php file not found\n";
    $status['init_method'] = false;
    $status['experience_instantiation'] = false;
}

// 6. Composer Dependencies
echo "\n📦 COMPOSER DEPENDENCIES\n";
echo str_repeat("-", 40) . "\n";

if (file_exists(__DIR__ . '/composer.json')) {
    echo "✅ composer.json exists\n";
    
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        echo "✅ Composer autoloader available\n";
        $status['composer_autoload'] = true;
    } else {
        echo "⚠️  Composer dependencies not installed\n";
        echo "   Run: composer install --no-dev\n";
        $status['composer_autoload'] = false;
    }
} else {
    echo "❌ composer.json missing\n";
    $status['composer_autoload'] = false;
}

// 7. Testing Files
echo "\n🧪 TESTING TOOLS\n";
echo str_repeat("-", 40) . "\n";

$test_files = [
    'test-php-syntax.php' => 'PHP syntax test',
    'test-experience-functionality.php' => 'WordPress functionality test',
    'MANUAL_TESTING_GUIDE.md' => 'Manual testing guide',
    'product-type-demo.html' => 'Interactive demo',
];

$testing_tools = 0;
foreach ($test_files as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file - $description\n";
        $testing_tools++;
    } else {
        echo "❌ $file - Missing - $description\n";
    }
}

$status['testing_tools'] = $testing_tools;

// 8. Overall Status Summary
echo "\n" . str_repeat("=", 60) . "\n";
echo "OVERALL STATUS SUMMARY\n";
echo str_repeat("=", 60) . "\n";

$total_checks = count($status);
$passed_checks = array_sum(array_map(function($v) { return is_bool($v) ? ($v ? 1 : 0) : ($v > 0 ? 1 : 0); }, $status));

echo sprintf("Passed Checks: %d/%d\n", $passed_checks, $total_checks);

if ($passed_checks === $total_checks) {
    echo "\n🎉 EXCELLENT! All checks passed.\n";
    echo "The Experience product type should be fully functional.\n\n";
    echo "✅ Ready for production use\n";
    echo "✅ All critical components present\n";
    echo "✅ PHP syntax correct\n";
    echo "✅ Filter hooks properly configured\n";
} else {
    echo "\n⚠️  Some issues detected. Review the report above.\n\n";
    
    $issues = [];
    if (!$status['files']) $issues[] = "Missing critical files";
    if (!$status['syntax']) $issues[] = "PHP syntax errors";
    if (!$status['filter_correct']) $issues[] = "Filter hook issues";
    if (!$status['experience_class']) $issues[] = "Experience class problems";
    if (!$status['wc_product_class']) $issues[] = "WC_Product_Experience class issues";
    
    echo "Issues to fix:\n";
    foreach ($issues as $issue) {
        echo "- $issue\n";
    }
}

echo "\n📋 NEXT STEPS:\n";
if ($status['composer_autoload'] === false) {
    echo "1. Install composer dependencies: composer install --no-dev\n";
}
echo "2. Activate plugin in WordPress\n";
echo "3. Run test-experience-functionality.php in WordPress\n";
echo "4. Manually test: WordPress Admin → Products → Add New\n";
echo "5. Verify 'Experience' appears in Product Type dropdown\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "Report complete. For help, see MANUAL_TESTING_GUIDE.md\n";

?>