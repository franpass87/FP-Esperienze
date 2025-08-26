<?php
/**
 * Plugin Name: FP Esperienze
 * Plugin URI: https://github.com/franpass87/FP-Esperienze
 * Description: WordPress + WooCommerce plugin for managing experiences with booking functionality.
 * Version: 1.0.0
 * Author: Francesco Passeri
 * Requires at least: 6.5
 * Tested up to: 6.5
 * Requires PHP: 8.1
 * Text Domain: fp-esperienze
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 8.0
 *
 * @package FP\Esperienze
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('FP_ESPERIENZE_VERSION', '1.0.0');
define('FP_ESPERIENZE_PLUGIN_FILE', __FILE__);
define('FP_ESPERIENZE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FP_ESPERIENZE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FP_ESPERIENZE_PLUGIN_BASENAME', plugin_basename(__FILE__));

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
spl_autoload_register(function ($class) {
    $prefix = 'FP\\Esperienze\\';
    $base_dir = __DIR__ . '/includes/';

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

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('FP\\Esperienze\\Core\\Plugin')) {
        FP\Esperienze\Core\Plugin::get_instance();
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    if (class_exists('FP\\Esperienze\\Core\\Activator')) {
        FP\Esperienze\Core\Activator::activate();
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    if (class_exists('FP\\Esperienze\\Core\\Deactivator')) {
        FP\Esperienze\Core\Deactivator::deactivate();
    }
});