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
define('FP_ESPERIENZE_COMPOSER_NOTICE_KEY', 'fp_esperienze_composer_notice_dismissed');

// Feature flags
// Set to true to enable NULL migration for schedule override fields
define('FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION', false);

/**
 * Check if the composer notice has been dismissed.
 *
 * @return bool
 */
function fp_esperienze_is_composer_notice_dismissed() {
    return (bool) get_option(FP_ESPERIENZE_COMPOSER_NOTICE_KEY, false);
}

/**
 * Persist the dismissal of the composer notice.
 *
 * @return void
 */
function fp_esperienze_dismiss_composer_notice() {
    if (!fp_esperienze_is_composer_notice_dismissed()) {
        update_option(FP_ESPERIENZE_COMPOSER_NOTICE_KEY, true, false);
    }
}

/**
 * Handle composer notice dismissal requests.
 *
 * @return void
 */
function fp_esperienze_handle_composer_notice_dismiss() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!isset($_GET['fp-esperienze-dismiss-composer'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return;
    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

    if (!wp_verify_nonce($nonce, 'fp-esperienze-dismiss-composer')) {
        return;
    }

    fp_esperienze_dismiss_composer_notice();

    $redirect_url = remove_query_arg(array('fp-esperienze-dismiss-composer', '_wpnonce'));

    if (!headers_sent()) {
        wp_safe_redirect($redirect_url);
    }

    exit;
}

/**
 * Determine if the composer notice should be displayed.
 *
 * @return bool
 */
function fp_esperienze_should_show_composer_notice() {
    if (defined('WP_CLI') && WP_CLI) {
        return false;
    }

    if (!function_exists('is_admin') || !is_admin()) {
        return false;
    }

    if (defined('DOING_AJAX') && DOING_AJAX) {
        return false;
    }

    return !fp_esperienze_is_composer_notice_dismissed();
}

/**
 * Display the admin notice when composer dependencies are missing.
 *
 * @return void
 */
function fp_esperienze_display_composer_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }

    if (!fp_esperienze_should_show_composer_notice()) {
        return;
    }

    $message_template = __('FP Esperienze: Some advanced features (PDF generation, QR codes) require composer dependencies. Run %s in the plugin directory to enable all features.', 'fp-esperienze');
    $message           = sprintf($message_template, '<code>composer install --no-dev</code>');
    $message           = wp_kses($message, array('code' => array()));

    $cta_text    = esc_html__('Run "composer install --no-dev" to enable all features', 'fp-esperienze');
    $dismiss_url = wp_nonce_url(add_query_arg('fp-esperienze-dismiss-composer', '1'), 'fp-esperienze-dismiss-composer');
    $dismiss_txt = esc_html__('Dismiss this notice.', 'default');

    echo '<div class="notice notice-warning fp-esperienze-composer-notice">';
    echo '<p>' . $message . '</p>';
    echo '<p><strong>' . $cta_text . '</strong></p>';
    echo '<p><a class="button-secondary" href="' . esc_url($dismiss_url) . '">' . esc_html($dismiss_txt) . '</a></p>';
    echo '</div>';
}

/**
 * Register hooks for the composer notice when dependencies are missing.
 *
 * @return void
 */
function fp_esperienze_register_composer_notice_hooks() {
    if (!fp_esperienze_should_show_composer_notice()) {
        return;
    }

    add_action('admin_init', 'fp_esperienze_handle_composer_notice_dismiss');
    add_action('admin_notices', 'fp_esperienze_display_composer_notice');
    add_action('network_admin_notices', 'fp_esperienze_display_composer_notice');
}

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

/**
 * Show WordPress version error notice
 */
function fp_esperienze_wp_version_notice() {
    echo '<div class="notice notice-error"><p>' . 
         esc_html__('FP Esperienze requires WordPress 6.5 or higher.', 'fp-esperienze') . 
         '</p></div>';
}

/**
 * Show PHP version error notice
 */
function fp_esperienze_php_version_notice() {
    echo '<div class="notice notice-error"><p>' . 
         esc_html__('FP Esperienze requires PHP 8.1 or higher.', 'fp-esperienze') . 
         '</p></div>';
}

// Check WordPress version
if (version_compare(get_bloginfo('version'), '6.5', '<')) {
    add_action('admin_notices', 'fp_esperienze_wp_version_notice');
    return;
}

// Check PHP version
if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', 'fp_esperienze_php_version_notice');
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

    // Register admin notice hooks when composer dependencies are unavailable.
    fp_esperienze_register_composer_notice_hooks();
}

// Register WP-CLI commands with error handling.
if (defined('WP_CLI') && WP_CLI) {
    try {
        \WP_CLI::add_command('fp-esperienze', \FP\Esperienze\CLI\TranslateCommand::class);
        \WP_CLI::add_command('fp-esperienze production-check', \FP\Esperienze\CLI\ProductionCheckCommand::class);
        \WP_CLI::add_command('fp-esperienze onboarding', \FP\Esperienze\CLI\OnboardingCommand::class);
        \WP_CLI::add_command('fp-esperienze operations', \FP\Esperienze\CLI\OperationsCommand::class);
        \WP_CLI::add_command('fp-esperienze qa', \FP\Esperienze\CLI\QualityAssuranceCommand::class);
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
            add_action('admin_notices', 'fp_esperienze_woocommerce_missing_notice');
            return;
        }

        // Check WooCommerce version
        if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
            add_action('admin_notices', 'fp_esperienze_woocommerce_version_notice');
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
        
        if (class_exists('FP\Esperienze\Core\Installer')) {
            $staff_attendance_result = FP\Esperienze\Core\Installer::maybeCreateStaffAttendanceTable();
            if (is_wp_error($staff_attendance_result) && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FP Esperienze: ' . $staff_attendance_result->get_error_message());
            }
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
        $GLOBALS['fp_esperienze_init_error'] = $e->getMessage();
        add_action('admin_notices', 'fp_esperienze_show_init_error');
        
        return;
    }
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action('plugins_loaded', 'fp_esperienze_init');

/**
 * Show WooCommerce missing notice
 */
function fp_esperienze_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>' . 
         esc_html__('FP Esperienze requires WooCommerce to be installed and activated.', 'fp-esperienze') . 
         '</p></div>';
}

/**
 * Show WooCommerce version notice
 */
function fp_esperienze_woocommerce_version_notice() {
    echo '<div class="notice notice-error"><p>' . 
         esc_html__('FP Esperienze requires WooCommerce 8.0 or higher.', 'fp-esperienze') . 
         '</p></div>';
}

/**
 * Declare compatibility with WooCommerce features
 */
add_action('before_woocommerce_init', 'fp_esperienze_declare_wc_compatibility');

/**
 * Declare WooCommerce HPOS compatibility
 */
function fp_esperienze_declare_wc_compatibility() {
    // Declare HPOS compatibility
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', FP_ESPERIENZE_PLUGIN_FILE, true);
    }
}

/**
 * Show initialization error notice
 */
function fp_esperienze_show_init_error() {
    if (current_user_can('manage_options') && isset($GLOBALS['fp_esperienze_init_error'])) {
        echo '<div class="notice notice-error"><p>' . 
             sprintf(
                 esc_html__('FP Esperienze: Plugin initialization failed. Error: %s', 'fp-esperienze'),
                 esc_html($GLOBALS['fp_esperienze_init_error'])
             ) . 
             '</p><p><small>' .
             esc_html__('Check wp-content/fp-esperienze-errors.log for detailed error information.', 'fp-esperienze') .
             '</small></p></div>';
    }
}

/**
 * Activation hook with enhanced validation
 */
/**
 * Plugin activation function
 */
function fp_esperienze_activate_plugin() {
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
}

register_activation_hook(__FILE__, 'fp_esperienze_activate_plugin');

/**
 * Deactivation hook with error handling.
 * Performs targeted cleanup of plugin transients and temporary options
 * without flushing the global cache.
 */
/**
 * Plugin deactivation function
 */
function fp_esperienze_deactivate_plugin() {
    try {
        if (class_exists('FP\Esperienze\Core\Installer')) {
            FP\Esperienze\Core\Installer::deactivate();
        }
        
        // Clean up only plugin-specific transients and temporary options
        // to avoid flushing unrelated cache data.
        delete_option('fp_esperienze_just_activated');
        delete_transient('fp_esperienze_activation_redirect');

        if (class_exists('FP\Esperienze\Core\CacheManager')) {
            \FP\Esperienze\Core\CacheManager::clearAllCaches();
        }
        
    } catch (Throwable $e) {
        error_log('FP Esperienze deactivation error: ' . $e->getMessage());
    }
}

register_deactivation_hook(__FILE__, 'fp_esperienze_deactivate_plugin');

/**
 * Uninstall hook with error handling
 */
register_uninstall_hook(__FILE__, 'fp_esperienze_uninstall_plugin');

/**
 * Plugin uninstall function
 */
function fp_esperienze_uninstall_plugin() {
    try {
        if (class_exists('FP\Esperienze\Core\Installer')) {
            FP\Esperienze\Core\Installer::uninstall();
        }
    } catch (Throwable $e) {
        error_log('FP Esperienze uninstall error: ' . $e->getMessage());
    }
}