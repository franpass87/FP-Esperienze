<?php
/**
 * FP Esperienze Plugin Activation Diagnostic Tool
 * 
 * This script identifies potential critical errors that could cause
 * plugin activation failures. Run this before activating the plugin.
 * 
 * Usage: Run from WordPress root or include from plugin activation
 */

// Ensure WordPress environment
if (!defined('ABSPATH')) {
    // Try to load WordPress if not already loaded
    $wp_load_candidates = [
        __DIR__ . '/../../../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../wp-load.php'
    ];

    foreach ($wp_load_candidates as $wp_load) {
        if (file_exists($wp_load)) {
            require_once $wp_load;
            break;
        }
    }

    if (!defined('ABSPATH')) {
        die('Error: Unable to load WordPress environment. Run this script from WordPress admin or include WordPress.');
    }
}

fp_esperienze_assert_admin_access();

class FPEsperienzeActivationDiagnostic {
    
    private static $errors = [];
    private static $warnings = [];
    private static $success = [];
    
    /**
     * Run complete diagnostic
     */
    public static function run() {
        echo "<h2>FP Esperienze Plugin Activation Diagnostic</h2>\n";
        echo "<style>
            .diagnostic-error { color: #d63638; background: #fdf0f0; padding: 8px; margin: 4px 0; border-left: 4px solid #d63638; }
            .diagnostic-warning { color: #b32d2e; background: #fcf9e8; padding: 8px; margin: 4px 0; border-left: 4px solid #dba617; }
            .diagnostic-success { color: #00a32a; background: #f0f6fc; padding: 8px; margin: 4px 0; border-left: 4px solid #00a32a; }
            .code-block { background: #f1f1f1; padding: 10px; margin: 5px 0; font-family: monospace; border-radius: 4px; }
        </style>\n";
        
        self::checkEnvironment();
        self::checkDependencies();
        self::checkFilePermissions();
        self::checkAutoloaderFunctionality();
        self::checkDatabaseReadiness();
        self::checkClassLoading();
        self::checkPotentialConflicts();
        self::displaySummary();
    }
    
    /**
     * Check environment requirements
     */
    private static function checkEnvironment() {
        echo "<h3>1. Environment Requirements</h3>\n";
        
        // PHP Version
        if (version_compare(PHP_VERSION, '8.1', '>=')) {
            self::success("PHP Version: " . PHP_VERSION . " ✓");
        } else {
            self::error("PHP Version: " . PHP_VERSION . " (Requires 8.1+)");
        }
        
        // WordPress Version
        if (version_compare(get_bloginfo('version'), '6.5', '>=')) {
            self::success("WordPress Version: " . get_bloginfo('version') . " ✓");
        } else {
            self::error("WordPress Version: " . get_bloginfo('version') . " (Requires 6.5+)");
        }
        
        // WooCommerce
        if (class_exists('WooCommerce')) {
            if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '>=')) {
                self::success("WooCommerce Version: " . WC_VERSION . " ✓");
            } else {
                self::error("WooCommerce Version: " . (WC_VERSION ?? 'Unknown') . " (Requires 8.0+)");
            }
        } else {
            self::error("WooCommerce not found or not activated");
        }
        
        // Memory limit
        $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $required_memory = 256 * 1024 * 1024; // 256MB
        if ($memory_limit >= $required_memory) {
            self::success("Memory Limit: " . size_format($memory_limit) . " ✓");
        } else {
            self::warning("Memory Limit: " . size_format($memory_limit) . " (Recommended: 256MB+)");
        }
    }
    
    /**
     * Check dependencies
     */
    private static function checkDependencies() {
        echo "<h3>2. Dependencies Check</h3>\n";
        
        $plugin_dir = dirname(__DIR__) . '/';
        
        // Composer autoloader
        if (file_exists($plugin_dir . 'vendor/autoload.php')) {
            self::success("Composer autoloader found ✓");
        } else {
            self::warning("Composer autoloader missing - using fallback PSR-4 autoloader");
        }
        
        // Required PHP extensions
        $required_extensions = ['json', 'mbstring', 'curl', 'gd'];
        foreach ($required_extensions as $ext) {
            if (extension_loaded($ext)) {
                self::success("PHP Extension '{$ext}' loaded ✓");
            } else {
                self::error("PHP Extension '{$ext}' missing");
            }
        }
        
        // WordPress functions
        $required_functions = ['wp_mkdir_p', 'dbDelta', 'wp_safe_remote_request'];
        foreach ($required_functions as $func) {
            if (function_exists($func)) {
                self::success("WordPress function '{$func}' available ✓");
            } else {
                self::error("WordPress function '{$func}' not available");
            }
        }
    }
    
    /**
     * Check file permissions
     */
    private static function checkFilePermissions() {
        echo "<h3>3. File Permissions</h3>\n";
        
        // Check wp-content writability
        if (is_writable(WP_CONTENT_DIR)) {
            self::success("WP Content directory writable ✓");
        } else {
            self::error("WP Content directory not writable: " . WP_CONTENT_DIR);
        }
        
        // Check critical directories
        $plugin_dir = dirname(__DIR__) . '/';
        $critical_dirs = [
            WP_CONTENT_DIR . '/fp-private' => 'FP Private directory',
            WP_CONTENT_DIR . '/fp-private/fp-esperienze-ics' => 'ICS files directory'
        ];
        
        foreach ($critical_dirs as $dir => $description) {
            if (wp_mkdir_p($dir)) {
                if (is_writable($dir)) {
                    self::success("$description writable ✓");
                } else {
                    self::error("$description created but not writable: $dir");
                }
            } else {
                self::error("Cannot create $description: $dir");
            }
        }
        
        // Check uploads directory
        $uploads = wp_upload_dir();
        if ($uploads['error']) {
            self::error("WordPress uploads directory error: " . $uploads['error']);
        } else {
            if (is_writable($uploads['basedir'])) {
                self::success("Uploads directory writable ✓");
            } else {
                self::error("Uploads directory not writable: " . $uploads['basedir']);
            }
        }
    }
    
    /**
     * Check autoloader functionality
     */
    private static function checkAutoloaderFunctionality() {
        echo "<h3>4. Autoloader Test</h3>\n";
        
        $plugin_dir = dirname(__DIR__) . '/';
        
        // Test PSR-4 autoloader without actually loading classes
        $test_classes = [
            'FP\\Esperienze\\Core\\Plugin' => 'includes/Core/Plugin.php',
            'FP\\Esperienze\\Core\\Installer' => 'includes/Core/Installer.php',
            'FP\\Esperienze\\ProductType\\Experience' => 'includes/ProductType/Experience.php',
            'FP\\Esperienze\\Admin\\MenuManager' => 'includes/Admin/MenuManager.php'
        ];
        
        foreach ($test_classes as $class => $expected_file) {
            $full_path = $plugin_dir . $expected_file;
            if (file_exists($full_path)) {
                $result = fp_esperienze_check_php_syntax($full_path);

                if ($result['status'] === true) {
                    self::success("Class file syntax valid: $class ✓");
                } elseif ($result['status'] === false) {
                    self::error("Syntax error in class file: $class");
                    if (!empty($result['message'])) {
                        echo "<div class='code-block'>" . esc_html($result['message']) . "</div>";
                    }
                } else {
                    $message = $result['message'] ?: 'Manual linting required (run php -l).';
                    self::warning("Unable to validate syntax for $class ($expected_file): " . esc_html($message));
                }
            } else {
                self::error("Class file missing: $class ($expected_file)");
            }
        }
    }
    
    /**
     * Check database readiness
     */
    private static function checkDatabaseReadiness() {
        echo "<h3>5. Database Readiness</h3>\n";
        
        global $wpdb;
        
        // Test database connectivity
        $result = $wpdb->get_var("SELECT 1");
        if ($result == 1) {
            self::success("Database connection ✓");
        } else {
            self::error("Database connection failed");
            return;
        }
        
        // Check database charset
        $charset = $wpdb->get_charset_collate();
        if (!empty($charset)) {
            self::success("Database charset: $charset ✓");
        } else {
            self::warning("Database charset not set");
        }
        
        // Test table creation capability
        $test_table = $wpdb->prefix . 'fp_test_table_' . time();
        $sql = "CREATE TABLE $test_table (id int(11) NOT NULL AUTO_INCREMENT, PRIMARY KEY (id)) $charset;";
        
        $result = $wpdb->query($sql);
        if ($result !== false) {
            self::success("Table creation capability ✓");
            // Clean up test table
            $wpdb->query("DROP TABLE IF EXISTS $test_table");
        } else {
            self::error("Cannot create database tables");
            echo "<div class='code-block'>Error: " . $wpdb->last_error . "</div>";
        }
        
        // Check for existing FP tables (potential conflicts)
        $existing_tables = $wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}fp_%'");
        if (!empty($existing_tables)) {
            self::warning("Existing FP tables found (may indicate previous installation):");
            echo "<div class='code-block'>" . implode(", ", $existing_tables) . "</div>";
        } else {
            self::success("No conflicting tables found ✓");
        }
    }
    
    /**
     * Check class loading without full initialization
     */
    private static function checkClassLoading() {
        echo "<h3>6. Class Loading Test</h3>\n";
        
        // Temporarily register the autoloader
        $plugin_dir = dirname(__DIR__) . '/';
        $autoloader_registered = false;
        
        if (!file_exists($plugin_dir . 'vendor/autoload.php')) {
            spl_autoload_register(function ($class) use ($plugin_dir) {
                $prefix = 'FP\\Esperienze\\';
                $base_dir = $plugin_dir . 'includes/';
                $len = strlen($prefix);
                if (strncmp($prefix, $class, $len) !== 0) {
                    return;
                }
                
                $relative_class = substr($class, $len);
                $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
                
                if (file_exists($file)) {
                    require $file;
                }
            });
            $autoloader_registered = true;
        }
        
        // Test critical class availability
        $critical_classes = [
            'FP\\Esperienze\\Core\\Plugin',
            'FP\\Esperienze\\Core\\Installer',
        ];
        
        foreach ($critical_classes as $class) {
            if (class_exists($class, true)) {
                self::success("Critical class loadable: $class ✓");
            } else {
                self::error("Critical class cannot be loaded: $class");
            }
        }
    }
    
    /**
     * Check for potential conflicts
     */
    private static function checkPotentialConflicts() {
        echo "<h3>7. Potential Conflicts</h3>\n";
        
        // Check for conflicting plugins
        $active_plugins = get_option('active_plugins', []);
        $conflicting_patterns = [
            'experience',
            'booking',
            'esperienze',
            'product-type'
        ];
        
        $potential_conflicts = [];
        foreach ($active_plugins as $plugin) {
            foreach ($conflicting_patterns as $pattern) {
                if (stripos($plugin, $pattern) !== false && stripos($plugin, 'fp-esperienze') === false) {
                    $potential_conflicts[] = $plugin;
                    break;
                }
            }
        }
        
        if (empty($potential_conflicts)) {
            self::success("No obvious plugin conflicts found ✓");
        } else {
            self::warning("Potential conflicting plugins:");
            echo "<div class='code-block'>" . implode("\n", $potential_conflicts) . "</div>";
        }
        
        // Check for debug settings
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::success("WP_DEBUG enabled (good for troubleshooting) ✓");
        } else {
            self::warning("WP_DEBUG disabled (enable for better error reporting)");
        }
        
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            self::success("WP_DEBUG_LOG enabled ✓");
        } else {
            self::warning("WP_DEBUG_LOG disabled (enable to capture errors in log)");
        }
    }
    
    /**
     * Display summary
     */
    private static function displaySummary() {
        echo "<h3>8. Diagnostic Summary</h3>\n";
        
        echo "<div class='diagnostic-success'>";
        echo "<strong>✓ " . count(self::$success) . " checks passed</strong><br>";
        echo "</div>";
        
        if (!empty(self::$warnings)) {
            echo "<div class='diagnostic-warning'>";
            echo "<strong>⚠ " . count(self::$warnings) . " warnings found:</strong><br>";
            foreach (self::$warnings as $warning) {
                echo "• $warning<br>";
            }
            echo "</div>";
        }
        
        if (!empty(self::$errors)) {
            echo "<div class='diagnostic-error'>";
            echo "<strong>❌ " . count(self::$errors) . " critical errors found:</strong><br>";
            foreach (self::$errors as $error) {
                echo "• $error<br>";
            }
            echo "<br><strong>Plugin activation will likely fail until these errors are resolved.</strong>";
            echo "</div>";
        } else {
            echo "<div class='diagnostic-success'>";
            echo "<strong>No critical errors found. Plugin activation should work.</strong>";
            echo "</div>";
        }
        
        // Provide recommendations
        echo "<h4>Recommendations:</h4>";
        if (!empty(self::$errors)) {
            echo "<div class='diagnostic-error'>";
            echo "1. Fix all critical errors before attempting plugin activation<br>";
            echo "2. Enable WP_DEBUG and WP_DEBUG_LOG for detailed error reporting<br>";
            echo "3. Check server error logs for additional details<br>";
            echo "</div>";
        } else {
            echo "<div class='diagnostic-success'>";
            echo "1. Consider running 'composer install --no-dev' for optimal performance<br>";
            echo "2. Enable WP_DEBUG temporarily during activation for monitoring<br>";
            echo "3. Monitor error logs during and after activation<br>";
            echo "</div>";
        }
    }
    
    private static function success($message) {
        self::$success[] = $message;
        echo "<div class='diagnostic-success'>$message</div>\n";
    }
    
    private static function warning($message) {
        self::$warnings[] = $message;
        echo "<div class='diagnostic-warning'>$message</div>\n";
    }
    
    private static function error($message) {
        self::$errors[] = $message;
        echo "<div class='diagnostic-error'>$message</div>\n";
    }
}

// Run diagnostic if called directly
if (!defined('FP_ESPERIENZE_DIAGNOSTIC_INCLUDED')) {
    FPEsperienzeActivationDiagnostic::run();
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
