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
use FP\Esperienze\Blocks\ArchiveBlock;
use FP\Esperienze\REST\AvailabilityAPI;
use FP\Esperienze\REST\BookingsAPI;
use FP\Esperienze\Booking\Cart_Hooks;
use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Data\VoucherManager;
use FP\Esperienze\Integrations\TrackingManager;
use FP\Esperienze\Integrations\BrevoManager;

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
        
        // Initialize blocks
        add_action('init', [$this, 'initBlocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockAssets']);
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
        
        // Initialize voucher manager for gift vouchers
        new VoucherManager();
        
        // Initialize tracking manager for GA4 and Meta Pixel
        new TrackingManager();
        
        // Initialize Brevo manager for email marketing
        new BrevoManager();
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
        new BookingsAPI();
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueueScripts(): void {
        // Only enqueue on experience product pages or archive pages
        $should_enqueue = false;
        
        if (is_singular('product')) {
            global $post;
            $product = wc_get_product($post->ID);
            if ($product && $product->get_type() === 'experience') {
                $should_enqueue = true;
            }
        } elseif (is_shop() || is_product_category() || is_product_tag()) {
            // Also enqueue on shop pages in case there are experience products
            $should_enqueue = true;
        } elseif (is_page()) {
            // Check if page contains the shortcode
            global $post;
            if ($post && has_shortcode($post->post_content, 'fp_exp_archive')) {
                $should_enqueue = true;
            }
        }

        if (!$should_enqueue) {
            return;
        }

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

        // Enqueue booking widget JS only on single experience pages
        if (is_singular('product')) {
            wp_enqueue_script(
                'fp-esperienze-booking-widget',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/js/booking-widget.js',
                ['jquery', 'fp-esperienze-frontend'],
                FP_ESPERIENZE_VERSION,
                true
            );
        }

        // Localize script with WooCommerce data
        if (function_exists('wc_get_cart_url')) {
            wp_localize_script('fp-esperienze-frontend', 'fp_esperienze_params', [
                'cart_url' => wc_get_cart_url(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => get_rest_url(),
                'nonce' => wp_create_nonce('fp_esperienze_nonce'),
                'voucher_nonce' => wp_create_nonce('fp_voucher_nonce'),
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
        
        // Localize script with admin data
        wp_localize_script('fp-esperienze-admin', 'fp_esperienze_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => get_rest_url(),
            'nonce' => wp_create_nonce('fp_esperienze_admin_nonce'),
        ]);
    }

    /**
     * Initialize Gutenberg blocks
     */
    public function initBlocks(): void {
        new ArchiveBlock();
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueueBlockAssets(): void {
        wp_enqueue_script(
            'fp-esperienze-archive-block',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/js/archive-block.js',
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            FP_ESPERIENZE_VERSION,
            true
        );

        wp_localize_script('fp-esperienze-archive-block', 'fpEsperienzeBlock', [
            'pluginUrl' => FP_ESPERIENZE_PLUGIN_URL,
        ]);
    }
}