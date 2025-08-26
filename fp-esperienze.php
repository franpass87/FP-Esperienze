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

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             esc_html__('FP Esperienze requires WooCommerce to be installed and active.', 'fp-esperienze') . 
             '</p></div>';
    });
    return;
}

// Autoloader
require_once FP_ESPERIENZE_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Initialize the plugin
 */
function fp_esperienze_init() {
    // Load text domain
    load_plugin_textdomain('fp-esperienze', false, dirname(FP_ESPERIENZE_PLUGIN_BASENAME) . '/languages');
    
    // Initialize main plugin class
    FP\Esperienze\Core\Plugin::getInstance();
}

// Hook into plugins_loaded to ensure WooCommerce is loaded first
add_action('plugins_loaded', 'fp_esperienze_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
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