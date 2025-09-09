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

// Handle composer dependencies gracefully.
$autoloader_path = FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($autoloader_path)) {
    // Use composer autoloader when available.
    require_once $autoloader_path;
} else {
    // Lightweight PSR-4 autoloader for plugin classes.
    spl_autoload_register(function ($class) {
        $prefix   = 'FP\\Esperienze\\';
        $base_dir = FP_ESPERIENZE_PLUGIN_DIR . 'includes/';
        $len      = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });

    // Show admin notice for missing dependencies.
    add_action('admin_notices', function () {
        echo '<div class="notice notice-warning"><p>' .
            sprintf(
                esc_html__('FP Esperienze: Composer dependencies are missing. Run %s in the plugin directory to enable all features.', 'fp-esperienze'),
                '<code>composer install --no-dev</code>'
            ) .
            '</p></div>';
    });
}

// Register WP-CLI commands.
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('fp-esperienze', \FP\Esperienze\CLI\TranslateCommand::class);
}

/**
 * Initialize the plugin
 */
function fp_esperienze_init() {
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

    // Load text domain
    load_plugin_textdomain('fp-esperienze', false, dirname(FP_ESPERIENZE_PLUGIN_BASENAME) . '/languages');
    
    // Initialize main plugin class
    FP\Esperienze\Core\Plugin::getInstance();
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
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Ensure composer dependencies are installed.
    if (!file_exists(FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php')) {
        wp_die(
            sprintf(
                esc_html__('FP Esperienze cannot be activated because composer dependencies are missing. Run %s in the plugin directory.', 'fp-esperienze'),
                '<code>composer install --no-dev</code>'
            )
        );
    }

    // Check WooCommerce is available during activation
    if (!class_exists('WooCommerce')) {
        wp_die(esc_html__('FP Esperienze requires WooCommerce to be installed and activated.', 'fp-esperienze'));
    }
    
    // Check WooCommerce version during activation
    if (defined('WC_VERSION') && version_compare(WC_VERSION, '8.0', '<')) {
        wp_die(esc_html__('FP Esperienze requires WooCommerce 8.0 or higher.', 'fp-esperienze'));
    }
    
    // Run installer
    FP\Esperienze\Core\Installer::activate();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    FP\Esperienze\Core\Installer::deactivate();
});

/**
 * Uninstall hook
 */
register_uninstall_hook(__FILE__, [FP\Esperienze\Core\Installer::class, 'uninstall']);