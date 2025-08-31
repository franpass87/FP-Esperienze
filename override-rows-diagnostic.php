<?php
/**
 * Diagnostic Script: Override Rows Fix Verification
 * 
 * This script helps verify that the override rows fix is working correctly.
 * Usage: Load this in WordPress admin to check for potential issues.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // For standalone testing, define basic constants
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (!defined('WP_DEBUG_LOG')) {
        define('WP_DEBUG_LOG', true);
    }
}

class FPOverrideRowsDiagnostic {
    
    /**
     * Run the diagnostic
     */
    public static function run() {
        echo "<h2>FP Esperienze Override Rows Fix - Diagnostic Report</h2>\n";
        echo "<style>
            .diagnostic-result { margin: 10px 0; padding: 10px; border-left: 4px solid #ccc; }
            .diagnostic-success { border-left-color: #00a32a; background: #f0f8f0; }
            .diagnostic-warning { border-left-color: #dba617; background: #fcf8e3; }
            .diagnostic-error { border-left-color: #d63638; background: #fdf0f0; }
            .code-block { background: #f1f1f1; padding: 10px; margin: 5px 0; font-family: monospace; border-radius: 4px; }
        </style>\n";
        
        self::checkPluginActive();
        self::checkAssetFiles();
        self::checkJavaScriptIntegrity();
        self::checkCSSIntegrity();
        self::checkWordPressHooks();
        self::provideSummary();
    }
    
    /**
     * Check if the plugin is active
     */
    private static function checkPluginActive() {
        echo "<h3>1. Plugin Status</h3>\n";
        
        if (function_exists('is_plugin_active')) {
            $plugin_file = 'fp-esperienze/fp-esperienze.php';
            if (is_plugin_active($plugin_file)) {
                echo "<div class='diagnostic-result diagnostic-success'>✅ FP Esperienze plugin is active</div>\n";
            } else {
                echo "<div class='diagnostic-result diagnostic-error'>❌ FP Esperienze plugin is not active</div>\n";
                return;
            }
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Cannot determine plugin status (not in WordPress admin)</div>\n";
        }
        
        // Check if plugin constants are defined
        if (defined('FP_ESPERIENZE_VERSION')) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Plugin constants defined (Version: " . FP_ESPERIENZE_VERSION . ")</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-error'>❌ Plugin constants not defined</div>\n";
        }
    }
    
    /**
     * Check if asset files exist and are readable
     */
    private static function checkAssetFiles() {
        echo "<h3>2. Asset Files</h3>\n";
        
        $plugin_dir = defined('FP_ESPERIENZE_PLUGIN_DIR') ? FP_ESPERIENZE_PLUGIN_DIR : dirname(__FILE__) . '/';
        
        $files = [
            'assets/js/admin.js' => 'JavaScript file',
            'assets/css/admin.css' => 'CSS file'
        ];
        
        foreach ($files as $file => $description) {
            $file_path = $plugin_dir . $file;
            if (file_exists($file_path) && is_readable($file_path)) {
                $file_size = filesize($file_path);
                echo "<div class='diagnostic-result diagnostic-success'>✅ {$description} exists and is readable ({$file_size} bytes)</div>\n";
            } else {
                echo "<div class='diagnostic-result diagnostic-error'>❌ {$description} not found or not readable: {$file_path}</div>\n";
            }
        }
    }
    
    /**
     * Check JavaScript file for key fixes
     */
    private static function checkJavaScriptIntegrity() {
        echo "<h3>3. JavaScript Fix Verification</h3>\n";
        
        $plugin_dir = defined('FP_ESPERIENZE_PLUGIN_DIR') ? FP_ESPERIENZE_PLUGIN_DIR : dirname(__FILE__) . '/';
        $js_file = $plugin_dir . 'assets/js/admin.js';
        
        if (!file_exists($js_file)) {
            echo "<div class='diagnostic-result diagnostic-error'>❌ JavaScript file not found</div>\n";
            return;
        }
        
        $js_content = file_get_contents($js_file);
        
        // Check for initialization fix
        if (strpos($js_content, 'window.FPEsperienzeAdmin && window.FPEsperienzeAdmin.initialized') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Initialization prevention check found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Initialization prevention check not found</div>\n";
        }
        
        // Check for namespaced events
        if (strpos($js_content, '.fp-override') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Namespaced event binding found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Namespaced event binding not found</div>\n";
        }
        
        // Check for event cleanup
        if (strpos($js_content, '.off(') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Event cleanup (off) calls found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Event cleanup calls not found</div>\n";
        }
        
        // Check for container validation
        if (strpos($js_content, 'if (!container.length)') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Container validation found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Container validation not found</div>\n";
        }
        
        // Check for stopPropagation
        if (strpos($js_content, 'stopPropagation') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Event propagation control found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Event propagation control not found</div>\n";
        }
    }
    
    /**
     * Check CSS file for layout fixes
     */
    private static function checkCSSIntegrity() {
        echo "<h3>4. CSS Fix Verification</h3>\n";
        
        $plugin_dir = defined('FP_ESPERIENZE_PLUGIN_DIR') ? FP_ESPERIENZE_PLUGIN_DIR : dirname(__FILE__) . '/';
        $css_file = $plugin_dir . 'assets/css/admin.css';
        
        if (!file_exists($css_file)) {
            echo "<div class='diagnostic-result diagnostic-error'>❌ CSS file not found</div>\n";
            return;
        }
        
        $css_content = file_get_contents($css_file);
        
        // Check for responsive grid
        if (strpos($css_content, 'minmax(') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Responsive grid with minmax() found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Responsive grid layout not found</div>\n";
        }
        
        // Check for overflow protection
        if (strpos($css_content, 'overflow: hidden') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Overflow protection found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Overflow protection not found</div>\n";
        }
        
        // Check for separated schedule/override styles
        if (strpos($css_content, '.fp-schedule-row {') !== false && strpos($css_content, '.fp-override-row {') !== false) {
            // Make sure they're not combined
            if (strpos($css_content, '.fp-schedule-row,') === false || strpos($css_content, '.fp-override-row {') > strpos($css_content, '.fp-schedule-row,')) {
                echo "<div class='diagnostic-result diagnostic-success'>✅ Separated schedule and override row styles found</div>\n";
            } else {
                echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Schedule and override styles may still be combined</div>\n";
            }
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Could not verify separated styles</div>\n";
        }
        
        // Check for container overflow handling
        if (strpos($css_content, 'overflow-x: auto') !== false) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Container overflow handling found</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Container overflow handling not found</div>\n";
        }
    }
    
    /**
     * Check WordPress hooks and filters
     */
    private static function checkWordPressHooks() {
        echo "<h3>5. WordPress Integration</h3>\n";
        
        if (function_exists('has_action')) {
            // Check if admin scripts are properly enqueued
            if (has_action('admin_enqueue_scripts')) {
                echo "<div class='diagnostic-result diagnostic-success'>✅ Admin script enqueue hooks found</div>\n";
            } else {
                echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Admin script enqueue hooks not detected</div>\n";
            }
        }
        
        if (class_exists('FP\\Esperienze\\ProductType\\Experience')) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ Experience product type class exists</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Experience product type class not found</div>\n";
        }
    }
    
    /**
     * Provide testing summary
     */
    private static function provideSummary() {
        echo "<h3>6. Testing Recommendations</h3>\n";
        
        echo "<div class='diagnostic-result'>";
        echo "<h4>Next Steps:</h4>";
        echo "<ol>";
        echo "<li><strong>Create a test experience product</strong> to verify the override functionality</li>";
        echo "<li><strong>Test the 'Add Date Override' button</strong> multiple times to ensure only one row is created per click</li>";
        echo "<li><strong>Test the 'Remove' buttons</strong> to ensure they work correctly</li>";
        echo "<li><strong>Check browser console</strong> for any JavaScript errors</li>";
        echo "<li><strong>Test on different screen sizes</strong> to verify responsive layout</li>";
        echo "</ol>";
        echo "</div>\n";
        
        echo "<div class='diagnostic-result'>";
        echo "<h4>Manual Test File:</h4>";
        echo "<p>Refer to <code>MANUAL_TEST_OVERRIDE_ROWS_FIX.md</code> for detailed testing instructions.</p>";
        echo "</div>\n";
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo "<div class='diagnostic-result diagnostic-success'>✅ WordPress debug mode is enabled (good for testing)</div>\n";
        } else {
            echo "<div class='diagnostic-result diagnostic-warning'>⚠️ Consider enabling WP_DEBUG for thorough testing</div>\n";
        }
    }
}

// Run the diagnostic if accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === 'override-rows-diagnostic.php') {
    echo "<!DOCTYPE html><html><head><title>Override Rows Fix Diagnostic</title></head><body>";
    FPOverrideRowsDiagnostic::run();
    echo "</body></html>";
}

// WordPress action hook for admin access
if (function_exists('add_action')) {
    add_action('wp_loaded', function() {
        if (isset($_GET['fp_diagnostic']) && $_GET['fp_diagnostic'] === 'override_rows' && current_user_can('manage_options')) {
            FPOverrideRowsDiagnostic::run();
            exit;
        }
    });
}