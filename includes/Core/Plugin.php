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
use FP\Esperienze\Frontend\SEOManager;
use FP\Esperienze\Blocks\ArchiveBlock;
use FP\Esperienze\REST\AvailabilityAPI;
use FP\Esperienze\REST\BookingsAPI;
use FP\Esperienze\REST\ICSAPI;
use FP\Esperienze\REST\SecurePDFAPI;
use FP\Esperienze\Booking\Cart_Hooks;
use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Data\VoucherManager;
use FP\Esperienze\Data\NotificationManager;
use FP\Esperienze\Data\DynamicPricingHooks;
use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Integrations\TrackingManager;
use FP\Esperienze\Integrations\BrevoManager;
use FP\Esperienze\Integrations\GooglePlacesManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\WebhookManager;
use FP\Esperienze\Core\I18nManager;
use FP\Esperienze\Core\CacheManager;
use FP\Esperienze\Core\AssetOptimizer;

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
        
        // Add filter to defer non-critical scripts
        add_filter('script_loader_tag', [$this, 'deferNonCriticalScripts'], 10, 3);
        
        // Add lazy loading for images (when not in admin)
        if (!is_admin()) {
            add_filter('wp_get_attachment_image_attributes', [$this, 'addLazyLoadingToImages'], 10, 2);
            add_filter('the_content', [$this, 'addLazyLoadingToContentImages'], 20);
        }
        
        // Initialize blocks
        add_action('init', [$this, 'initBlocks']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockAssets']);
        
        // Initialize holds cleanup cron
        add_action('init', [$this, 'initHoldsCron']);
        add_action('fp_esperienze_cleanup_holds', [$this, 'cleanupExpiredHolds']);
        
        // Initialize performance monitoring
        if (defined('WP_DEBUG') && WP_DEBUG) {
            QueryMonitor::init();
        }
        
        // Initialize performance indexes on admin_init (only once)
        add_action('admin_init', [$this, 'maybeAddPerformanceIndexes'], 5);
    }

    /**
     * Initialize components
     */
    public function initComponents(): void {
        // Initialize capability manager first
        new CapabilityManager();
        
        // Initialize i18n manager for multilingual support
        new I18nManager();
        
        // Initialize cache manager for performance
        new CacheManager();
        
        // Initialize asset optimizer
        AssetOptimizer::init();
        
        // Initialize experience product type
        new Experience();
        
        // Initialize cart hooks for experience bookings
        new Cart_Hooks();
        
        // Initialize dynamic pricing hooks
        new DynamicPricingHooks();
        
        // Initialize booking manager for order processing
        new BookingManager();
        
        // Initialize voucher manager for gift vouchers
        new VoucherManager();
        
        // Initialize notification manager for ICS and staff emails
        new NotificationManager();
        
        // Initialize webhook manager for external integrations
        new WebhookManager();
        
        // Initialize tracking manager for GA4 and Meta Pixel
        new TrackingManager();
        
        // Initialize Brevo manager for email marketing
        new BrevoManager();
        
        // Initialize Google Places manager for meeting point reviews
        new GooglePlacesManager();
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
        new SEOManager();
    }

    /**
     * Initialize REST API
     */
    public function initREST(): void {
        new AvailabilityAPI();
        new BookingsAPI();
        new ICSAPI();
        new SecurePDFAPI();
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

        // Enqueue CSS (minified if available)
        $frontend_css_url = AssetOptimizer::getMinifiedAssetUrl('css', 'frontend');
        if (!$frontend_css_url) {
            $frontend_css_url = FP_ESPERIENZE_PLUGIN_URL . 'assets/css/frontend.css';
        }
        
        wp_enqueue_style(
            'fp-esperienze-frontend',
            $frontend_css_url,
            [],
            FP_ESPERIENZE_VERSION
        );

        // Enqueue JS (minified if available)  
        $frontend_js_url = AssetOptimizer::getMinifiedAssetUrl('js', 'frontend');
        if (!$frontend_js_url) {
            wp_enqueue_script(
                'fp-esperienze-frontend',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                FP_ESPERIENZE_VERSION,
                true
            );
            
            // Enqueue tracking script separately if not minified
            wp_enqueue_script(
                'fp-esperienze-tracking',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/js/tracking.js',
                ['jquery'],
                FP_ESPERIENZE_VERSION,
                true
            );
        } else {
            // Use minified combined version
            wp_enqueue_script(
                'fp-esperienze-frontend',
                $frontend_js_url,
                ['jquery'],
                FP_ESPERIENZE_VERSION,
                true
            );
        }

        // Enqueue booking widget JS only on single experience pages
        if (is_singular('product')) {
            $booking_widget_url = AssetOptimizer::getMinifiedAssetUrl('js', 'booking-widget');
            if (!$booking_widget_url) {
                $booking_widget_url = FP_ESPERIENZE_PLUGIN_URL . 'assets/js/booking-widget.js';
            }
            
            wp_enqueue_script(
                'fp-esperienze-booking-widget',
                $booking_widget_url,
                ['jquery', 'fp-esperienze-frontend'],
                FP_ESPERIENZE_VERSION,
                true
            );
            
            // Localize booking widget with translated strings
            wp_localize_script('fp-esperienze-booking-widget', 'fp_booking_widget_i18n', [
                'error_failed_load_availability' => __('Failed to load availability.', 'fp-esperienze'),
                'error_booking_unavailable' => __('Booking system temporarily unavailable. Please try again.', 'fp-esperienze'),
                'error_no_availability' => __('No availability for this date.', 'fp-esperienze'),
                'spots_left' => __('spots left', 'fp-esperienze'),
                'sold_out' => __('Sold out', 'fp-esperienze'),
                'loading_availability' => __('Loading available times...', 'fp-esperienze'),
            ]);
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
        // Enqueue CSS (minified if available)
        $admin_css_url = AssetOptimizer::getMinifiedAssetUrl('css', 'admin');
        if (!$admin_css_url) {
            $admin_css_url = FP_ESPERIENZE_PLUGIN_URL . 'assets/css/admin.css';
        }
        
        wp_enqueue_style(
            'fp-esperienze-admin',
            $admin_css_url,
            [],
            FP_ESPERIENZE_VERSION
        );

        // Enqueue JS (minified if available)
        $admin_js_url = AssetOptimizer::getMinifiedAssetUrl('js', 'admin');
        if (!$admin_js_url) {
            $admin_js_url = FP_ESPERIENZE_PLUGIN_URL . 'assets/js/admin.js';
        }
        
        wp_enqueue_script(
            'fp-esperienze-admin',
            $admin_js_url,
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

        // Enqueue reports script only on reports page
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->id === 'fp-esperienze_page_fp-esperienze-reports') {
            wp_enqueue_script(
                'fp-esperienze-reports',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/js/reports.js',
                ['jquery'],
                FP_ESPERIENZE_VERSION,
                true
            );

            // Localize reports script
            wp_localize_script('fp-esperienze-reports', 'fp_reports_i18n', [
                'no_data' => __('No data available', 'fp-esperienze'),
                'loading' => __('Loading...', 'fp-esperienze'),
                'error_load_data' => __('Failed to load data', 'fp-esperienze'),
                'ajax_url' => admin_url('admin-ajax.php'),
            ]);
        }
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
        $archive_block_url = AssetOptimizer::getMinifiedAssetUrl('js', 'archive-block');
        if (!$archive_block_url) {
            $archive_block_url = FP_ESPERIENZE_PLUGIN_URL . 'assets/js/archive-block.js';
        }
        
        wp_enqueue_script(
            'fp-esperienze-archive-block',
            $archive_block_url,
            ['wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            FP_ESPERIENZE_VERSION,
            true
        );

        wp_localize_script('fp-esperienze-archive-block', 'fpEsperienzeBlock', [
            'pluginUrl' => FP_ESPERIENZE_PLUGIN_URL,
        ]);
    }
    
    /**
     * Defer non-critical scripts for better performance
     *
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @param string $src Script source
     * @return string
     */
    public function deferNonCriticalScripts(string $tag, string $handle, string $src): string {
        // List of scripts to defer (non-critical)
        $defer_scripts = [
            'fp-esperienze-tracking',
            'fp-esperienze-archive-block',
            'fp-esperienze-reports',
        ];
        
        // List of scripts to load async (independent)
        $async_scripts = [
            'fp-esperienze-tracking',
        ];
        
        if (in_array($handle, $defer_scripts)) {
            $tag = str_replace(' src', ' defer src', $tag);
        }
        
        if (in_array($handle, $async_scripts)) {
            $tag = str_replace(' defer src', ' async defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Initialize holds cleanup cron
     */
    public function initHoldsCron(): void {
        if (!wp_next_scheduled('fp_esperienze_cleanup_holds')) {
            wp_schedule_event(time(), 'fp_esperienze_every_5_minutes', 'fp_esperienze_cleanup_holds');
        }
        
        // Add custom cron interval
        add_filter('cron_schedules', [$this, 'addHoldsCronInterval']);
    }
    
    /**
     * Add custom cron interval for holds cleanup
     *
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function addHoldsCronInterval($schedules): array {
        $schedules['fp_esperienze_every_5_minutes'] = [
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes (FP Esperienze)', 'fp-esperienze')
        ];
        
        return $schedules;
    }
    
    /**
     * Cleanup expired holds
     */
    public function cleanupExpiredHolds(): void {
        $count = HoldManager::cleanupExpiredHolds();
        if ($count > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Esperienze: Cleaned up {$count} expired holds");
        }
    }
    
    /**
     * Maybe add performance indexes (only once per plugin version)
     */
    public function maybeAddPerformanceIndexes(): void {
        $indexes_version = get_option('fp_esperienze_indexes_version', '0.0.0');
        $current_version = FP_ESPERIENZE_VERSION;
        
        // Only add indexes if they haven't been added for this version
        if (version_compare($indexes_version, $current_version, '<')) {
            Installer::addPerformanceIndexes();
            update_option('fp_esperienze_indexes_version', $current_version);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("FP Esperienze: Performance indexes updated to version {$current_version}");
            }
        }
    }
    
    /**
     * Add lazy loading to attachment images
     *
     * @param array $attributes Image attributes
     * @param WP_Post $attachment Attachment post object
     * @return array
     */
    public function addLazyLoadingToImages(array $attributes, $attachment): array {
        // Only add loading attribute if not already set
        if (!isset($attributes['loading'])) {
            $attributes['loading'] = 'lazy';
        }
        
        return $attributes;
    }
    
    /**
     * Add lazy loading to images in content
     *
     * @param string $content Post content
     * @return string
     */
    public function addLazyLoadingToContentImages(string $content): string {
        // Skip if this content doesn't contain images
        if (strpos($content, '<img') === false) {
            return $content;
        }
        
        // Add loading="lazy" to img tags that don't already have it
        $content = preg_replace_callback(
            '/<img([^>]*?)(?:\s+loading=["\'][^"\']*["\'])?([^>]*?)>/i',
            function($matches) {
                $before = $matches[1];
                $after = $matches[2];
                
                // Check if loading attribute already exists
                if (preg_match('/\bloading\s*=/i', $before . $after)) {
                    return $matches[0]; // Return original if loading attribute exists
                }
                
                return '<img' . $before . ' loading="lazy"' . $after . '>';
            },
            $content
        );
        
        return $content;
    }
}