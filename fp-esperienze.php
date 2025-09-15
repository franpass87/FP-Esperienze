<?php
/**
 * Plugin Name: FP Esperienze
 * Plugin URI: https://github.com/franpass87/FP-Esperienze
 * Description: Experience booking management plugin for WordPress and WooCommerce
 * Version: 1.0.0
 * Author: Francesco Passeri
 * Author URI: https://francescopasseri.com
 * Text Domain: fp-esperienze
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.7
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FP_ESPERIENZE_VERSION', '1.0.0');
define('FP_ESPERIENZE_PLUGIN_FILE', __FILE__);
define('FP_ESPERIENZE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FP_ESPERIENZE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FP_ESPERIENZE_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('FP_ESPERIENZE_ICS_DIR', WP_CONTENT_DIR . '/fp-private/fp-esperienze-ics');

// Feature flags
// Set to true to enable NULL migration for schedule override fields
define('FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION', false);

/**
 * Safely write content to a file using WP_Filesystem.
 * Falls back to error_log if the filesystem is unavailable.
 *
 * @param string $file_path Absolute path to the file.
 * @param string $content   Content to write.
 * @param bool   $append    Whether to append to existing content.
 * @return bool             True on success, false on failure.
 */
function fp_esperienze_write_file( $file_path, $content, $append = true ) {
    global $wp_filesystem;

    if ( ! function_exists( 'WP_Filesystem' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    if ( ! WP_Filesystem() ) {
        error_log( 'FP Esperienze: WP_Filesystem could not be initialized.' );
        error_log( $content );
        return false;
    }

    $dir = dirname( $file_path );

    if ( ! $wp_filesystem->exists( $dir ) ) {
        if ( ! $wp_filesystem->mkdir( $dir ) ) {
            error_log( 'FP Esperienze: Unable to create directory ' . $dir );
            error_log( $content );
            return false;
        }
    }

    if ( ! $wp_filesystem->is_writable( $dir ) ) {
        error_log( 'FP Esperienze: Directory not writable: ' . $dir );
        error_log( $content );
        return false;
    }

    if ( $append && $wp_filesystem->exists( $file_path ) ) {
        $existing = $wp_filesystem->get_contents( $file_path );
        if ( false === $existing ) {
            $existing = '';
        }
        $content = $existing . $content;
    }

    if ( ! $wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE ) ) {
        error_log( 'FP Esperienze: Failed to write to file: ' . $file_path );
        error_log( $content );
        return false;
    }

    return true;
}

// Check WordPress version
if (version_compare(get_bloginfo('version'), '6.5', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             esc_html__('FP Esperienze requires WordPress 6.5 or higher.', 'fp-esperienze') . 
             '</p></div>';
    });
    return;
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             esc_html__('FP Esperienze requires PHP 8.1 or higher.', 'fp-esperienze') . 
             '</p></div>';
    });
    return;
}

// WooCommerce dependency checks will be performed in plugins_loaded hook

// Handle composer dependencies gracefully with enhanced error handling.
$autoloader_path = FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';
$autoloader_loaded = false;

if (file_exists($autoloader_path)) {
    try {
        // Use composer autoloader when available.
        require_once $autoloader_path;
        $autoloader_loaded = true;
    } catch (Throwable $e) {
        // Log the error and fall back to PSR-4 autoloader
        error_log('FP Esperienze: Failed to load composer autoloader: ' . $e->getMessage());
    }
}

if (!$autoloader_loaded) {
    // Enhanced PSR-4 autoloader for plugin classes with better error handling
    spl_autoload_register(function ($class) {
        $prefix   = 'FP\\Esperienze\\';
        $base_dir = FP_ESPERIENZE_PLUGIN_DIR . 'includes/';
        $len      = strlen($prefix);
        
        // Check if class uses our namespace
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        // Enhanced file loading with error handling
        if (file_exists($file)) {
            try {
                // Check file is readable
                if (!is_readable($file)) {
                    error_log("FP Esperienze: Class file not readable: $file");
                    return;
                }
                
                // Include with error capture
                $include_result = include_once $file;
                
                // Verify class was actually loaded
                if ($include_result === false) {
                    error_log("FP Esperienze: Failed to include class file: $file");
                    return;
                }
                
                // Verify class exists after include
                if (!class_exists($class, false) && !interface_exists($class, false) && !trait_exists($class, false)) {
                    error_log("FP Esperienze: Class $class not found in file $file after inclusion");
                }
                
            } catch (ParseError $e) {
                error_log("FP Esperienze: Parse error in class file $file: " . $e->getMessage());
                throw $e;
            } catch (Throwable $e) {
                error_log("FP Esperienze: Error loading class $class from $file: " . $e->getMessage());
                throw $e;
            }
        } else {
            // Only log missing files for classes we should have
            if (strpos($class, 'FP\\Esperienze\\') === 0) {
                error_log("FP Esperienze: Class file not found: $file for class $class");
            }
        }
    }, true, true); // Prepend and throw exceptions

    // Show admin notice for missing dependencies only once per user session.
    add_action('admin_notices', function () {
        $notice_key = 'fp_esperienze_composer_notice_dismissed';
        if (get_user_meta(get_current_user_id(), $notice_key, true)) {
            return;
        }
        
        echo '<div class="notice notice-warning is-dismissible" data-dismissible="' . esc_attr($notice_key) . '"><p>' .
            sprintf(
                esc_html__('FP Esperienze: Composer dependencies are missing. Run %s in the plugin directory to enable all features.', 'fp-esperienze'),
                '<code>composer install --no-dev</code>'
            ) .
            '</p></div>';
    });
    
    // Handle notice dismissal with proper security
    add_action('wp_ajax_fp_esperienze_dismiss_notice', function() {
        if (!current_user_can('manage_options')) {
            wp_die(-1, 403);
        }
        
        if (!wp_verify_nonce($_POST['_ajax_nonce'] ?? '', 'fp_esperienze_dismiss_notice')) {
            wp_die(-1, 403);
        }
        
        $notice_key = sanitize_text_field($_POST['notice_key'] ?? '');
        if ($notice_key === 'fp_esperienze_composer_notice_dismissed') {
            update_user_meta(get_current_user_id(), $notice_key, true);
            wp_die(1);
        }
        wp_die(0);
    });
}

// Register WP-CLI commands with error handling.
if (defined('WP_CLI') && WP_CLI) {
    try {
        \WP_CLI::add_command('fp-esperienze', \FP\Esperienze\CLI\TranslateCommand::class);
    } catch (Throwable $e) {
        error_log('FP Esperienze: Failed to register WP-CLI command: ' . $e->getMessage());
    }
}

/**
 * Initialize the plugin with enhanced error handling
 */
function fp_esperienze_init() {
    try {
        // Check WooCommerce dependency first
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                     esc_html__('FP Esperienze requires WooCommerce to be installed and activated.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }

        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>' . 
                     esc_html__('FP Esperienze requires WooCommerce 8.0 or higher.', 'fp-esperienze') . 
                     '</p></div>';
            });
            return;
        }

        // Load text domain with error handling
        $textdomain_loaded = load_plugin_textdomain(
            'fp-esperienze', 
            false, 
            dirname(FP_ESPERIENZE_PLUGIN_BASENAME) . '/languages'
        );
        
        if (!$textdomain_loaded && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FP Esperienze: Failed to load text domain for localization');
        }
        
        // Initialize main plugin class with error handling
        if (class_exists('FP\Esperienze\Core\Plugin')) {
            FP\Esperienze\Core\Plugin::getInstance();
        } else {
            throw new Exception('Main plugin class FP\Esperienze\Core\Plugin not found. Check autoloader configuration.');
        }
        
    } catch (Throwable $e) {
        // Enhanced error logging with more details
        $error_details = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not Available',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ];
        
        error_log('FP Esperienze: Critical initialization error: ' . wp_json_encode($error_details));
        
        // Try to write to a dedicated error log file
        if (defined('WP_CONTENT_DIR')) {
            $error_log_file = WP_CONTENT_DIR . '/fp-esperienze-errors.log';
            $timestamp = current_time('mysql');
            $log_entry = "[$timestamp] INITIALIZATION ERROR: " . wp_json_encode($error_details) . PHP_EOL;
            fp_esperienze_write_file( $error_log_file, $log_entry );
        }
        
        // Show admin notice for critical errors
        add_action('admin_notices', function() use ($e) {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(
                         esc_html__('FP Esperienze: Plugin initialization failed. Error: %s', 'fp-esperienze'),
                         esc_html($e->getMessage())
                     ) . 
                     '</p><p><small>' .
                     esc_html__('Check wp-content/fp-esperienze-errors.log for detailed error information.', 'fp-esperienze') .
                     '</small></p></div>';
            }
        });
        
        return;
    }
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action('plugins_loaded', 'fp_esperienze_init');

/**
 * Declare compatibility with WooCommerce features
 */
add_action('before_woocommerce_init', function() {
    // Declare HPOS compatibility
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FP_ESPERIENZE_PLUGIN_FILE, true);
    }
});

/**
 * Enqueue admin scripts for notice handling
 */
add_action('admin_enqueue_scripts', function() {
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            $(document).on("click", ".notice[data-dismissible] .notice-dismiss", function(e) {
                var notice = $(this).closest(".notice");
                var noticeKey = notice.data("dismissible");
                if (noticeKey) {
                    $.post(ajaxurl, {
                        action: "fp_esperienze_dismiss_notice",
                        notice_key: noticeKey,
                        _ajax_nonce: "' . wp_create_nonce('fp_esperienze_dismiss_notice') . '"
                    });
                }
            });
        });
    ');
});

/**
 * Activation hook with enhanced validation
 */
register_activation_hook(__FILE__, function() {
    try {
        // Ensure composer dependencies are installed.
        if (!file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php')) {
            wp_die(
                sprintf(
                    esc_html__('FP Esperienze cannot be activated because composer dependencies are missing. Run %s in the plugin directory.', 'fp-esperienze'),
                    '<code>composer install --no-dev</code>'
                ),
                esc_html__('Plugin Activation Error', 'fp-esperienze'),
                array('response' => 200, 'back_link' => true)
            );
        }

        // Check WooCommerce is available during activation
        if (!class_exists('WooCommerce')) {
            wp_die(
                esc_html__('FP Esperienze requires WooCommerce to be installed and activated.', 'fp-esperienze'),
                esc_html__('Plugin Activation Error', 'fp-esperienze'),
                array('response' => 200, 'back_link' => true)
            );
        }
        
        // Check WooCommerce version during activation
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
            wp_die(
                esc_html__('FP Esperienze requires WooCommerce 8.0 or higher.', 'fp-esperienze'),
                esc_html__('Plugin Activation Error', 'fp-esperienze'),
                array('response' => 200, 'back_link' => true)
            );
        }
        
        // Verify critical directories are writable
        $critical_dirs = [
            WP_CONTENT_DIR . '/fp-private' => 'FP Private directory',
            FP_ESPERIENZE_ICS_DIR => 'ICS files directory'
        ];
        
        foreach ($critical_dirs as $dir => $description) {
            if (!wp_mkdir_p($dir)) {
                wp_die(
                    sprintf(
                        esc_html__('FP Esperienze cannot create required directory: %s (%s). Please check file permissions.', 'fp-esperienze'),
                        esc_html($description),
                        esc_html($dir)
                    ),
                    esc_html__('Plugin Activation Error', 'fp-esperienze'),
                    array('response' => 200, 'back_link' => true)
                );
            }
        }
        
        // Ensure installer class is available
        if (!class_exists('FP\Esperienze\Core\Installer')) {
            wp_die(
                esc_html__('FP Esperienze installer class not found. Check autoloader configuration.', 'fp-esperienze'),
                esc_html__('Plugin Activation Error', 'fp-esperienze'),
                array('response' => 200, 'back_link' => true)
            );
        }
        
        // Run installer
        $result = FP\Esperienze\Core\Installer::activate();
        if (is_wp_error($result)) {
            wp_die(
                $result->get_error_message(),
                esc_html__('Plugin Activation Error', 'fp-esperienze'),
                array('response' => 200, 'back_link' => true)
            );
        }
        
        // Set activation flag for welcome message
        update_option('fp_esperienze_just_activated', true);
        
    } catch (Throwable $e) {
        // Enhanced activation error logging
        $error_details = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'Not Available',
            'memory_limit' => ini_get('memory_limit'),
            'plugins_loaded' => did_action('plugins_loaded'),
            'woocommerce_loaded' => class_exists('WooCommerce'),
            'composer_autoloader' => file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php') ? 'Available' : 'Missing'
        ];
        
        // Log the activation error with full context
        error_log('FP Esperienze activation error: ' . wp_json_encode($error_details));
        
        // Try to write to dedicated error log
        if (defined('WP_CONTENT_DIR')) {
            $error_log_file = WP_CONTENT_DIR . '/fp-esperienze-activation-errors.log';
            $timestamp = current_time('mysql');
            $log_entry = "[$timestamp] ACTIVATION ERROR: " . wp_json_encode($error_details) . PHP_EOL;
            fp_esperienze_write_file( $error_log_file, $log_entry );
        }
        
        // Create a recovery info file
        $recovery_info = [
            'error' => $e->getMessage(),
            'timestamp' => current_time('mysql'),
            'diagnostic_url' => admin_url('admin.php?page=fp-esperienze-diagnostic'),
            'steps' => [
                '1. Check server error logs',
                '2. Verify file permissions on wp-content/',
                '3. Ensure WooCommerce is active and up to date',
                '4. Run diagnostic: ' . plugin_dir_url(__FILE__) . 'tools/activation-diagnostic.php',
                '5. Consider running composer install --no-dev'
            ]
        ];
        
        if (defined('WP_CONTENT_DIR')) {
            $recovery_file = WP_CONTENT_DIR . '/fp-esperienze-recovery-info.json';
            fp_esperienze_write_file( $recovery_file, wp_json_encode( $recovery_info, JSON_PRETTY_PRINT ), false );
        }
        
        wp_die(
            sprintf(
                esc_html__('FP Esperienze activation failed: %s', 'fp-esperienze'),
                esc_html($e->getMessage())
            ) . '<br><br>' .
            '<strong>' . esc_html__('Troubleshooting Information:', 'fp-esperienze') . '</strong><br>' .
            '• ' . esc_html__('Error details logged to wp-content/fp-esperienze-activation-errors.log', 'fp-esperienze') . '<br>' .
            '• ' . esc_html__('Recovery info saved to wp-content/fp-esperienze-recovery-info.json', 'fp-esperienze') . '<br>' .
            '• ' . sprintf(
                esc_html__('Run diagnostic tool: %s', 'fp-esperienze'),
                '<a href="' . esc_url(plugin_dir_url(__FILE__) . 'tools/activation-diagnostic.php') . '" target="_blank">Diagnostic Tool</a>'
            ),
            esc_html__('Plugin Activation Error', 'fp-esperienze'),
            array('response' => 200, 'back_link' => true)
        );
    }
});

/**
 * Deactivation hook with error handling.
 * Performs targeted cleanup of plugin transients and temporary options
 * without flushing the global cache.
 */
register_deactivation_hook(__FILE__, function() {
    try {
        if (class_exists('FP\Esperienze\Core\Installer')) {
            FP\Esperienze\Core\Installer::deactivate();
        }
        
        // Clean up only plugin-specific transients and temporary options
        // to avoid flushing unrelated cache data.
        delete_option('fp_esperienze_just_activated');
        delete_transient('fp_esperienze_activation_redirect');

        if (class_exists('FP\\Esperienze\\Core\\CacheManager')) {
            FP\\Esperienze\\Core\\CacheManager::clearAllCaches();
        }
        
    } catch (Throwable $e) {
        error_log('FP Esperienze deactivation error: ' . $e->getMessage());
    }
});

/**
 * Uninstall hook with error handling
 */
register_uninstall_hook(__FILE__, function() {
    try {
        if (class_exists('FP\Esperienze\Core\Installer')) {
            FP\Esperienze\Core\Installer::uninstall();
        }
    } catch (Throwable $e) {
        error_log('FP Esperienze uninstall error: ' . $e->getMessage());
    }
});