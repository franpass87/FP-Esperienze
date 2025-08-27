<?php
/**
 * Main Plugin Class
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\ProductType\Experience;
use FP\Esperienze\Admin\MenuManager;
use FP\Esperienze\Frontend\Shortcodes;
use FP\Esperienze\Frontend\Templates;
use FP\Esperienze\REST\AvailabilityAPI;
use FP\Esperienze\Booking\Cart_Hooks;
use FP\Esperienze\Booking\BookingManager;

defined('ABSPATH') || exit;

/**
 * Main plugin class
 */
class Plugin {
    
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init(): void {
        // Initialize components
        add_action('init', [$this, 'initComponents'], 20);
        
        // Initialize admin
        if (is_admin()) {
            add_action('init', [$this, 'initAdmin']);
        }
        
        // Initialize frontend
        if (!is_admin()) {
            add_action('init', [$this, 'initFrontend']);
        }
        
        // Initialize REST API
        add_action('rest_api_init', [$this, 'initREST']);
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Initialize components
     */
    public function initComponents(): void {
        // Initialize experience product type
        new Experience();
        
        // Initialize cart hooks for experience bookings
        new Cart_Hooks();
        
        // Initialize booking manager for order processing
        new BookingManager();
    }

    /**
     * Initialize admin
     */
    public function initAdmin(): void {
        new MenuManager();
    }

    /**
     * Initialize frontend
     */
    public function initFrontend(): void {
        new Shortcodes();
        new Templates();
    }

    /**
     * Initialize REST API
     */
    public function initREST(): void {
        new AvailabilityAPI();
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts(): void {
        wp_enqueue_style(
            'fp-esperienze-frontend',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            FP_ESPERIENZE_VERSION
        );

        wp_enqueue_script(
            'fp-esperienze-frontend',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            FP_ESPERIENZE_VERSION,
            true
        );

        // Localize script with WooCommerce data
        if (function_exists('wc_get_cart_url')) {
            wp_localize_script('fp-esperienze-frontend', 'fp_esperienze_params', [
                'cart_url' => wc_get_cart_url(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => get_rest_url(),
                'nonce' => wp_create_nonce('fp_esperienze_nonce'),
            ]);
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts(): void {
        wp_enqueue_style(
            'fp-esperienze-admin',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/css/admin.css',
            [],
            FP_ESPERIENZE_VERSION
        );

        wp_enqueue_script(
            'fp-esperienze-admin',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            FP_ESPERIENZE_VERSION,
            true
        );
    }
}