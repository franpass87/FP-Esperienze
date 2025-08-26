<?php
/**
 * Main Plugin Class
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

/**
 * Main plugin class
 */
class Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize REST API
        new \FP\Esperienze\REST\AvailabilityController();
        
        // Initialize Frontend
        new \FP\Esperienze\Frontend\BookingWidget();
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('fp-esperienze', false, dirname(FP_ESPERIENZE_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        if (is_singular('product')) {
            wp_enqueue_script(
                'fp-esperienze-booking-widget',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/js/booking-widget.js',
                ['jquery', 'wp-api'],
                FP_ESPERIENZE_VERSION,
                true
            );
            
            wp_enqueue_style(
                'fp-esperienze-booking-widget',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/css/booking-widget.css',
                [],
                FP_ESPERIENZE_VERSION
            );
            
            // Localize script
            wp_localize_script('fp-esperienze-booking-widget', 'fpEsperienze', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'restUrl' => rest_url('fp-exp/v1/'),
                'nonce' => wp_create_nonce('fp_esperienze_nonce'),
                'strings' => [
                    'selectDate' => __('Select a date', 'fp-esperienze'),
                    'selectSlot' => __('Select a time slot', 'fp-esperienze'),
                    'adults' => __('Adults', 'fp-esperienze'),
                    'children' => __('Children', 'fp-esperienze'),
                    'addToCart' => __('Add to Cart', 'fp-esperienze'),
                    'lastSpots' => __('Last %d spots available!', 'fp-esperienze'),
                    'soldOut' => __('Sold Out', 'fp-esperienze'),
                    'loading' => __('Loading...', 'fp-esperienze')
                ]
            ]);
        }
    }
}