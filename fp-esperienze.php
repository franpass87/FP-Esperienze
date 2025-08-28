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

// Feature flags
define('FP_ESPERIENZE_ENABLE_SCHEDULE_NULL_MIGRATION', false); // Set to true to enable NULL migration for schedule override fields

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

// Check for Composer autoloader
$autoloader_path = FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';
if (!file_exists($autoloader_path)) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             sprintf(
                 esc_html__('FP Esperienze requires composer dependencies to be installed. Please run %s in the plugin directory.', 'fp-esperienze'),
                 '<code>composer install --no-dev</code>'
             ) . 
             '</p></div>';
    });
    return;
}

// Autoloader
require_once $autoloader_path;

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