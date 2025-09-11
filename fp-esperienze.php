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
    // Lightweight PSR-4 autoloader for plugin classes with enhanced error handling.
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
            try {
                require $file;
            } catch (Throwable $e) {
                error_log('FP Esperienze: Failed to load class ' . $class . ': ' . $e->getMessage());
                throw $e;
            }
        }
    });

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
        // Log critical initialization errors
        error_log('FP Esperienze: Critical initialization error: ' . $e->getMessage());
        
        // Show admin notice for critical errors
        add_action('admin_notices', function() use ($e) {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-error"><p>' . 
                     sprintf(
                         esc_html__('FP Esperienze: Plugin initialization failed. Error: %s', 'fp-esperienze'),
                         esc_html($e->getMessage())
                     ) . 
                     '</p></div>';
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
        // Log the activation error
        error_log('FP Esperienze activation error: ' . $e->getMessage());
        
        wp_die(
            sprintf(
                esc_html__('FP Esperienze activation failed: %s', 'fp-esperienze'),
                esc_html($e->getMessage())
            ),
            esc_html__('Plugin Activation Error', 'fp-esperienze'),
            array('response' => 200, 'back_link' => true)
        );
    }
});

/**
 * Deactivation hook with error handling
 */
register_deactivation_hook(__FILE__, function() {
    try {
        if (class_exists('FP\Esperienze\Core\Installer')) {
            FP\Esperienze\Core\Installer::deactivate();
        }
        
        // Clean up transients and temporary data
        delete_option('fp_esperienze_just_activated');
        wp_cache_flush();
        
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