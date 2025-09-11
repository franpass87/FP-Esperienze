<?php
/**
 * FP Esperienze Plugin Activation Test
 * 
 * This script simulates plugin activation to identify potential issues
 * without actually activating the plugin. Run this to test if the 
 * critical errors have been resolved.
 * 
 * Usage: Access this file via browser from your WordPress site
 * URL: /wp-content/plugins/fp-esperienze/tools/activation-test.php
 */

// Determine WordPress root path
$wp_load_paths = [
    __DIR__ . '/../../../../wp-load.php',
    __DIR__ . '/../../../wp-load.php', 
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../wp-load.php'
];

foreach ($wp_load_paths as $wp_load) {
    if (file_exists($wp_load)) {
        require_once $wp_load;
        break;
    }
}

if (!defined('ABSPATH')) {
    die('Error: Unable to load WordPress. Make sure you\'re accessing this from a WordPress installation.');
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>FP Esperienze Activation Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f1f1f1; }
        .container { max-width: 800px; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .success { color: #00a32a; background: #f0f6fc; padding: 8px; margin: 4px 0; border-left: 4px solid #00a32a; }
        .error { color: #d63638; background: #fdf0f0; padding: 8px; margin: 4px 0; border-left: 4px solid #d63638; }
        .warning { color: #b32d2e; background: #fcf9e8; padding: 8px; margin: 4px 0; border-left: 4px solid #dba617; }
        .info { color: #2271b1; background: #f0f6fc; padding: 8px; margin: 4px 0; border-left: 4px solid #2271b1; }
        .code { background: #f1f1f1; padding: 10px; margin: 5px 0; font-family: monospace; border-radius: 4px; font-size: 14px; }
        h1 { color: #1d2327; }
        h2 { color: #1d2327; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .step { margin: 20px 0; }
        .button { background: #2271b1; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; display: inline-block; }
        .button:hover { background: #135e96; color: white; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 FP Esperienze Plugin Activation Test</h1>
        <p>This tool simulates plugin activation to identify potential critical errors before actual activation.</p>
        
        <?php
        
        $errors = [];
        $warnings = [];
        $success = [];
        
        // Test 1: Environment Check
        echo '<div class="step"><h2>Step 1: Environment Requirements</h2>';
        
        if (version_compare(PHP_VERSION, '8.1', '>=')) {
            $success[] = "PHP Version: " . PHP_VERSION;
        } else {
            $errors[] = "PHP Version: " . PHP_VERSION . " (Requires 8.1+)";
        }
        
        if (version_compare(get_bloginfo('version'), '6.5', '>=')) {
            $success[] = "WordPress Version: " . get_bloginfo('version');
        } else {
            $errors[] = "WordPress Version: " . get_bloginfo('version') . " (Requires 6.5+)";
        }
        
        if (class_exists('WooCommerce')) {
            if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '>=')) {
                $success[] = "WooCommerce Version: " . WC_VERSION;
            } else {
                $errors[] = "WooCommerce Version: " . (WC_VERSION ?? 'Unknown') . " (Requires 8.0+)";
            }
        } else {
            $errors[] = "WooCommerce not found or not activated";
        }
        
        foreach ($success as $msg) echo '<div class="success">✅ ' . $msg . '</div>';
        foreach ($warnings as $msg) echo '<div class="warning">⚠️ ' . $msg . '</div>';
        foreach ($errors as $msg) echo '<div class="error">❌ ' . $msg . '</div>';
        echo '</div>';
        
        // Test 2: Autoloader Test
        echo '<div class="step"><h2>Step 2: Autoloader Test</h2>';
        $autoloader_errors = [];
        $autoloader_success = [];
        
        // Define plugin constants if not already defined
        if (!defined('FP_ESPERIENZE_PLUGIN_DIR')) {
            define('FP_ESPERIENZE_PLUGIN_DIR', dirname(__DIR__) . '/');
        }
        
        // Test autoloader registration
        $plugin_dir = FP_ESPERIENZE_PLUGIN_DIR;
        if (file_exists($plugin_dir . 'vendor/autoload.php')) {
            $autoloader_success[] = "Composer autoloader available";
        } else {
            $autoloader_warnings[] = "Composer autoloader missing - testing PSR-4 fallback";
            
            // Test PSR-4 autoloader
            spl_autoload_register(function ($class) use ($plugin_dir) {
                $prefix = 'FP\\Esperienze\\';
                $base_dir = $plugin_dir . 'includes/';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }
                
                $relative_class = substr($class, $len);
                $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
                
                if (file_exists($file) && is_readable($file)) {
                    include_once $file;
                }
            });
        }
        
        // Test critical class loading
        $critical_classes = [
            'FP\\Esperienze\\Core\\Plugin',
            'FP\\Esperienze\\Core\\Installer',
            'FP\\Esperienze\\ProductType\\Experience'
        ];
        
        foreach ($critical_classes as $class) {
            if (class_exists($class, true)) {
                $autoloader_success[] = "Class loadable: " . $class;
            } else {
                $autoloader_errors[] = "Cannot load class: " . $class;
            }
        }
        
        foreach ($autoloader_success as $msg) echo '<div class="success">✅ ' . $msg . '</div>';
        foreach ($autoloader_errors as $msg) echo '<div class="error">❌ ' . $msg . '</div>';
        echo '</div>';
        
        // Test 3: Database Test
        echo '<div class="step"><h2>Step 3: Database Connection Test</h2>';
        global $wpdb;
        
        $db_test = $wpdb->get_var("SELECT 1");
        if ($db_test == 1) {
            echo '<div class="success">✅ Database connection working</div>';
            
            // Test table creation capability
            $test_table = $wpdb->prefix . 'fp_test_activation_' . time();
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE $test_table (id int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) $charset_collate;";
            
            $result = $wpdb->query($sql);
            if ($result !== false) {
                echo '<div class="success">✅ Can create database tables</div>';
                $wpdb->query("DROP TABLE IF EXISTS $test_table");
            } else {
                echo '<div class="error">❌ Cannot create database tables: ' . $wpdb->last_error . '</div>';
            }
        } else {
            echo '<div class="error">❌ Database connection failed</div>';
        }
        echo '</div>';
        
        // Test 4: File Permissions
        echo '<div class="step"><h2>Step 4: File Permissions Test</h2>';
        
        if (is_writable(WP_CONTENT_DIR)) {
            echo '<div class="success">✅ WP Content directory writable</div>';
        } else {
            echo '<div class="error">❌ WP Content directory not writable</div>';
        }
        
        $test_dirs = [
            WP_CONTENT_DIR . '/fp-private',
            WP_CONTENT_DIR . '/fp-private/fp-esperienze-ics'
        ];
        
        foreach ($test_dirs as $dir) {
            if (wp_mkdir_p($dir) && is_writable($dir)) {
                echo '<div class="success">✅ Can create and write to: ' . basename($dir) . '</div>';
            } else {
                echo '<div class="error">❌ Cannot create or write to: ' . $dir . '</div>';
            }
        }
        echo '</div>';
        
        // Test 5: Simulated Installer Test
        echo '<div class="step"><h2>Step 5: Installer Simulation</h2>';
        
        if (class_exists('FP\\Esperienze\\Core\\Installer')) {
            echo '<div class="success">✅ Installer class found</div>';
            
            // Test if installer methods exist
            $methods = ['activate', 'createTables', 'createDefaultOptions'];
            foreach ($methods as $method) {
                if (method_exists('FP\\Esperienze\\Core\\Installer', $method)) {
                    echo '<div class="success">✅ Installer method exists: ' . $method . '</div>';
                } else {
                    echo '<div class="error">❌ Installer method missing: ' . $method . '</div>';
                }
            }
        } else {
            echo '<div class="error">❌ Installer class not found</div>';
        }
        echo '</div>';
        
        // Summary
        echo '<div class="step"><h2>📊 Test Summary</h2>';
        
        $total_errors = count($errors) + count($autoloader_errors);
        
        if ($total_errors == 0) {
            echo '<div class="success"><strong>🎉 All tests passed! Plugin activation should work properly.</strong></div>';
            echo '<div class="info">You can now try activating the FP Esperienze plugin through the WordPress admin.</div>';
        } else {
            echo '<div class="error"><strong>❌ Found ' . $total_errors . ' critical error(s) that need to be resolved before activation.</strong></div>';
            echo '<div class="info">Please fix the errors above before attempting plugin activation.</div>';
        }
        
        echo '<div class="code">';
        echo '<strong>For additional diagnostics, run:</strong><br>';
        echo '<a href="' . plugin_dir_url(dirname(__FILE__)) . 'tools/activation-diagnostic.php" target="_blank" class="button">Full Diagnostic Tool</a>';
        echo '</div>';
        
        echo '</div>';
        ?>
        
        <div class="step">
            <h2>🛠️ Troubleshooting Tips</h2>
            <div class="info">
                <strong>If you encounter activation errors:</strong><br>
                1. Check the error logs in <code>wp-content/fp-esperienze-activation-errors.log</code><br>
                2. Enable WP_DEBUG and WP_DEBUG_LOG in wp-config.php<br>
                3. Ensure WooCommerce is activated and up to date<br>
                4. Check file permissions on wp-content directory<br>
                5. Consider running <code>composer install --no-dev</code> in the plugin directory
            </div>
        </div>
        
        <div class="step">
            <p><a href="<?php echo admin_url('plugins.php'); ?>" class="button">← Back to Plugins</a></p>
        </div>
    </div>
</body>
</html>