<?php
/**
 * PHP Syntax and Structure Test for Experience Product Type
 *
 * This script tests the PHP syntax and basic structure of the Experience product type
 * without requiring WordPress to be loaded.
 */

fp_esperienze_bootstrap_wordpress(__DIR__);
fp_esperienze_assert_admin_access();

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
$syntax_checks_available = false;

foreach ($files_to_test as $file => $description) {
    $full_path = $base_dir . '/' . $file;

    if (!file_exists($full_path)) {
        echo "❌ File not found: $file\n";
        $all_passed = false;
        continue;
    }

    $result = fp_esperienze_check_php_syntax($full_path);

    if ($result['status'] === true) {
        echo "✅ $description - Syntax OK\n";
        $syntax_checks_available = true;
    } elseif ($result['status'] === false) {
        echo "❌ $description - Syntax Error:\n";
        if (!empty($result['message'])) {
            echo "   " . $result['message'] . "\n";
        }
        $all_passed = false;
        $syntax_checks_available = true;
    } else {
        $message = $result['message'] ?: 'Manual linting required (e.g. run php -l).';
        echo "⚠️  $description - Unable to validate syntax: $message\n";
    }
}

if (!$syntax_checks_available) {
    echo "\n⚠️  Syntax checks were skipped. Enable the OPcache extension or lint files manually (php -l).\n";
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

if (!function_exists('fp_esperienze_bootstrap_wordpress')) {
    function fp_esperienze_bootstrap_wordpress(string $base_dir): void
    {
        if (defined('ABSPATH')) {
            return;
        }

        $directory = $base_dir;
        for ($depth = 0; $depth < 10; $depth++) {
            $wp_load = $directory . '/wp-load.php';
            if (file_exists($wp_load)) {
                require_once $wp_load;
                break;
            }

            $parent = dirname($directory);
            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        if (!defined('ABSPATH')) {
            http_response_code(403);
            exit('This tool must be executed from within a WordPress installation.');
        }
    }
}

if (!function_exists('fp_esperienze_assert_admin_access')) {
    function fp_esperienze_assert_admin_access(): void
    {
        if (!function_exists('is_user_logged_in') || !function_exists('current_user_can')) {
            http_response_code(403);
            exit('WordPress authentication functions are not available.');
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            $message = __('You do not have permission to access this tool.', 'fp-esperienze');

            if (function_exists('wp_die')) {
                wp_die(esc_html($message), esc_html__('Access denied', 'fp-esperienze'), ['response' => 403]);
            }

            http_response_code(403);
            exit($message);
        }
    }
}

if (!function_exists('fp_esperienze_check_php_syntax')) {
    function fp_esperienze_check_php_syntax(string $file): array
    {
        if (!function_exists('opcache_compile_file')) {
            return [
                'status' => null,
                'message' => 'OPcache extension not available. Run "php -l ' . $file . '" manually to lint this file.',
            ];
        }

        $error_message = null;

        set_error_handler(static function (int $severity, string $message) use (&$error_message): bool {
            $error_message = $message;
            return true;
        });

        $result = @opcache_compile_file($file);

        restore_error_handler();

        if ($result) {
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }

            return [
                'status' => true,
                'message' => '',
            ];
        }

        if ($error_message === null) {
            $error_message = 'Unknown parse error. Check PHP error logs for details.';
        }

        return [
            'status' => false,
            'message' => $error_message,
        ];
    }
}

?>