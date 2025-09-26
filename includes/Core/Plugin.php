<?php
/**
 * Main Plugin Class
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\ProductType\Experience;
use FP\Esperienze\Admin\AdvancedAnalytics;
use FP\Esperienze\Admin\MenuManager;
use FP\Esperienze\Admin\FeatureDemoPage;
use FP\Esperienze\Admin\OnboardingDashboardWidget;
use FP\Esperienze\Admin\OnboardingNotice;
use FP\Esperienze\Frontend\Shortcodes;
use FP\Esperienze\Frontend\Templates;
use FP\Esperienze\Frontend\SEOManager;
use FP\Esperienze\Frontend\WidgetCheckoutHandler;
use FP\Esperienze\Blocks\ArchiveBlock;
use FP\Esperienze\REST\AvailabilityAPI;
use FP\Esperienze\REST\BookingsAPI;
use FP\Esperienze\REST\BookingsController;
use FP\Esperienze\REST\ICSAPI;
use FP\Esperienze\REST\SecurePDFAPI;
use FP\Esperienze\Booking\Cart_Hooks;
use FP\Esperienze\Booking\BookingManager;
use FP\Esperienze\Data\VoucherManager;
use FP\Esperienze\Data\NotificationManager;
use FP\Esperienze\Data\DynamicPricingHooks;
use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Data\WPMLHooks;
use FP\Esperienze\Integrations\TrackingManager;
use FP\Esperienze\Integrations\BrevoManager;
use FP\Esperienze\Integrations\GooglePlacesManager;
use FP\Esperienze\Integrations\MetaCAPIManager;
use FP\Esperienze\Core\CapabilityManager;
use FP\Esperienze\Core\Installer;
use FP\Esperienze\Core\WebhookManager;
use FP\Esperienze\Core\I18nManager;
use FP\Esperienze\Core\CacheManager;
use FP\Esperienze\Core\AnalyticsTracker;
use FP\Esperienze\Core\AssetOptimizer;
use FP\Esperienze\Core\QueryMonitor;
use FP\Esperienze\Core\TranslationQueue;
use FP\Esperienze\Core\SecurityEnhancer;
use FP\Esperienze\Core\PerformanceOptimizer;
use FP\Esperienze\Core\UXEnhancer;
use FP\Esperienze\Core\FeatureTester;
use FP\Esperienze\Core\TranslationCompiler;
use FP\Esperienze\Core\SiteHealth;
use FP\Esperienze\Core\ServiceBooter;
use FP\Esperienze\Core\RuntimeLogger;
use FP\Esperienze\Core\UpgradeManager;
use FP\Esperienze\AI\AIFeaturesManager;
use Throwable;

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
     * Initialization error message for admin notices
     *
     * @var string|null
     */
    private static $init_error = null;

    /**
     * Product type initialization error message for admin notices
     *
     * @var string|null
     */
    private static $product_type_error = null;

    /**
     * Tracks whether the push token cleanup hooks have been registered.
     */
    private bool $push_token_cleanup_registered = false;

    /**
     * Shared service bootstrapping helper.
     */
    private ServiceBooter $booter;

    /**
     * Core components that rely on static bootstrap hooks.
     *
     * @var array<int, array<string, mixed>>
     */
    private const CORE_BOOTSTRAPS = [
        [
            'class' => TranslationQueue::class,
            'method' => 'init',
        ],
        [
            'class' => SecurityEnhancer::class,
            'method' => 'init',
        ],
        [
            'class' => PerformanceOptimizer::class,
            'method' => 'init',
        ],
        [
            'class' => QueryMonitor::class,
            'method' => 'init',
        ],
        [
            'class' => UXEnhancer::class,
            'method' => 'init',
        ],
        [
            'class' => SiteHealth::class,
            'method' => 'init',
        ],
    ];

    /**
     * Service list for the main runtime components.
     *
     * @var array<int, mixed>
     */
    private const CORE_SERVICES = [
        CapabilityManager::class,
        I18nManager::class,
        CacheManager::class,
        AnalyticsTracker::class,
        [
            'class' => AssetOptimizer::class,
            'method' => 'init',
        ],
        Cart_Hooks::class,
        DynamicPricingHooks::class,
        [
            'class' => BookingManager::class,
            'method' => 'getInstance',
        ],
        VoucherManager::class,
        NotificationManager::class,
        WebhookManager::class,
        TrackingManager::class,
        MetaCAPIManager::class,
        BrevoManager::class,
        GooglePlacesManager::class,
        EmailMarketingManager::class,
        AIFeaturesManager::class,
        WPMLHooks::class,
    ];

    /**
     * Admin-side services that need to be bootstrapped.
     *
     * @var array<int, mixed>
     */
    private const ADMIN_SERVICES = [
        MenuManager::class,
        OnboardingDashboardWidget::class,
        OnboardingNotice::class,
        AdvancedAnalytics::class,
    ];

    /**
     * Frontend services that need to be initialised on public requests.
     *
     * @var array<int, mixed>
     */
    private const FRONTEND_SERVICES = [
        Shortcodes::class,
        Templates::class,
        SEOManager::class,
        WidgetCheckoutHandler::class,
    ];

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
        $this->booter = new ServiceBooter();
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init(): void {
        try {
            RuntimeLogger::init();
            UpgradeManager::init();

            $this->initBlocks();
            $this->initExperienceProductType();
            $this->registerLifecycleHooks();
        } catch (Throwable $e) {
            error_log('FP Esperienze: Plugin initialization error: ' . $e->getMessage());

            self::$init_error = $e->getMessage();
            $this->hook('admin_notices', [__CLASS__, 'showInitializationError']);
        }
    }

    /**
     * Registers lifecycle hooks for both admin and frontend contexts.
     */
    private function registerLifecycleHooks(): void {
        $this->hook('plugins_loaded', [$this, 'loadTextDomain']);
        $this->hook('init', [$this, 'initCoreComponents'], 1);
        $this->hook('init', [$this, 'initComponents'], 20);

        if ($this->isRunningInAdmin()) {
            $this->hook('init', [$this, 'initAdmin']);
            $this->hook('admin_enqueue_scripts', [$this, 'enqueueAdminScripts'], 10, 0);
            $this->hook('enqueue_block_editor_assets', [$this, 'enqueueBlockAssets'], 10, 0);
        } else {
            $this->hook('init', [$this, 'initFrontend']);
            $this->hook('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        }

        if ($this->shouldDisplayDebugNotices()) {
            $this->hook('admin_notices', [__CLASS__, 'showDebugFeatures']);
        }
    }

    /**
     * Helper wrapper for add_action to keep declarations tidy.
     */
    private function hook(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): void {
        add_action($hook, $callback, $priority, $accepted_args);
    }

    /**
     * Determine if we are currently running within the admin area.
     */
    private function isRunningInAdmin(): bool {
        return function_exists('is_admin') ? is_admin() : false;
    }

    /**
     * Whether developer-facing debug notices should be displayed.
     */
    private function shouldDisplayDebugNotices(): bool {
        return defined('WP_DEBUG') && WP_DEBUG && $this->isRunningInAdmin();
    }

    /**
     * Check if the experimental feature demo page should be exposed.
     */
    private function shouldExposeFeatureDemo(): bool {
        return defined('WP_DEBUG') && WP_DEBUG;
    }
    
    /**
     * Initialize core components safely
     */
    public function initCoreComponents(): void {
        $errors = $this->booter->boot(self::CORE_BOOTSTRAPS);
        $this->logServiceErrors('core bootstrap', $errors);
    }

    /**
     * Load plugin text domain for translations
     */
    public function loadTextDomain(): void {
        TranslationCompiler::ensureMoFiles();

        load_plugin_textdomain(
            'fp-esperienze',
            false,
            dirname(FP_ESPERIENZE_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Initialize experience product type early with error handling
     */
    public function initExperienceProductType(): void {
        $errors = $this->booter->boot([
            [
                'class' => Experience::class,
                'label' => 'Experience product type',
            ],
        ]);

        if (empty($errors)) {
            return;
        }

        $failure       = $errors[0];
        $display_error = $failure->getPrevious() ? $failure->getPrevious()->getMessage() : $failure->getMessage();

        error_log('FP Esperienze: Failed to initialize Experience product type: ' . $failure->getMessage());

        self::$product_type_error = $display_error;
        $this->hook('admin_notices', [__CLASS__, 'showProductTypeError']);
    }

    /**
     * Initialize components
     */
    public function initComponents(): void {
        $errors = $this->booter->boot(self::CORE_SERVICES);
        $this->logServiceErrors('core services', $errors);

        $this->ensurePushTokenStorage();
        $this->initHoldsCron();
        $this->registerPushTokenCleanupHook();
        $this->initPushTokenCron();
        $this->maybeAddPerformanceIndexes();
        $this->hook('rest_api_init', [$this, 'initREST']);
    }

    /**
     * Ensure the push token storage table exists and log failures in debug mode.
     */
    private function ensurePushTokenStorage(): void {
        $result = Installer::ensurePushTokenStorage();

        if (is_wp_error($result) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FP Esperienze: Push token storage setup failed: ' . $result->get_error_message());
        }
    }

    /**
     * Register the push token cleanup hook once.
     */
    private function registerPushTokenCleanupHook(): void {
        if ($this->push_token_cleanup_registered) {
            return;
        }

        $this->hook('fp_cleanup_push_tokens', [$this, 'cleanupExpiredPushTokens'], 10, 0);
        $this->push_token_cleanup_registered = true;
    }

    /**
     * Log bootstrapping errors for easier debugging.
     *
     * @param string              $context Contextual label.
     * @param array<int,Throwable> $errors Collected errors.
     */
    private function logServiceErrors(string $context, array $errors): void {
        if ($errors === []) {
            return;
        }

        foreach ($errors as $error) {
            error_log(sprintf('FP Esperienze: %s error: %s', $context, $error->getMessage()));
        }
    }

    /**
     * Initialize admin
     */
    public function initAdmin(): void {
        $errors = $this->booter->boot(self::ADMIN_SERVICES);
        $this->logServiceErrors('admin services', $errors);

        $demoErrors = $this->booter->boot([
            [
                'class' => FeatureDemoPage::class,
                'method' => 'init',
                'optional' => true,
                'condition' => [$this, 'shouldExposeFeatureDemo'],
                'label' => 'Feature demo page',
            ],
        ]);

        $this->logServiceErrors('admin feature demo', $demoErrors);
    }

    /**
     * Initialize frontend
     */
    public function initFrontend(): void {
        $errors = $this->booter->boot(self::FRONTEND_SERVICES);
        $this->logServiceErrors('frontend services', $errors);

        $this->initShopFiltering();

        $this->hook('wp_head', [$this, 'outputBrandingCSS'], 90, 0);
    }

    /**
     * Initialize shop filtering to hide Experience products from normal WooCommerce shop
     */
    private function initShopFiltering(): void {
        $this->hook('woocommerce_product_query', [$this, 'hideExperienceProductsFromShop']);
        $this->hook('pre_get_posts', [$this, 'hideExperienceProductsFromQueries']);
    }

    /**
     * Hide Experience products from WooCommerce shop queries
     *
     * @param \WP_Query $query WooCommerce product query
     */
    public function hideExperienceProductsFromShop(\WP_Query $query): void {
        // Only affect main queries on frontend
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Only affect shop/catalog queries, not custom queries or shortcodes
        // Check if this is a standard WooCommerce shop query
        if ((is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy()) &&
            !wc_get_loop_prop('is_shortcode') &&
            !$this->isOurShortcodeQuery($query)) {
            
            $meta_query = $query->get('meta_query') ?: [];
            
            // Check if we already have an experience filter to avoid duplicates
            $has_experience_filter = false;
            foreach ($meta_query as $clause) {
                if (is_array($clause) && 
                    isset($clause['key']) && $clause['key'] === '_product_type' &&
                    isset($clause['value']) && $clause['value'] === 'experience') {
                    $has_experience_filter = true;
                    break;
                }
            }
            
            // Only add filter if not already present
            if (!$has_experience_filter) {
                $meta_query[] = [
                    'key' => '_product_type',
                    'value' => 'experience',
                    'compare' => '!='
                ];
                
                $query->set('meta_query', $meta_query);
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG && apply_filters('fp_esperienze_debug_shop_filtering', false)) {
                    error_log('FP Esperienze: Excluded experience products from shop query on ' . $_SERVER['REQUEST_URI'] ?? 'unknown');
                }
            }
        }
    }

    /**
     * Hide Experience products from general WordPress queries on shop pages
     *
     * @param \WP_Query $query WordPress query
     */
    public function hideExperienceProductsFromQueries(\WP_Query $query): void {
        // Only affect main queries on frontend
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Only affect product queries on shop-related pages
        // Make sure we don't interfere with our own shortcode queries
        if ($query->get('post_type') === 'product' && 
            (is_shop() || is_product_category() || is_product_tag() || is_product_taxonomy()) &&
            !$this->isOurShortcodeQuery($query)) {
            
            $meta_query = $query->get('meta_query') ?: [];
            
            // Check if we already have an experience filter to avoid duplicates
            $has_experience_filter = false;
            foreach ($meta_query as $clause) {
                if (is_array($clause) && 
                    isset($clause['key']) && $clause['key'] === '_product_type' &&
                    isset($clause['value']) && $clause['value'] === 'experience') {
                    $has_experience_filter = true;
                    break;
                }
            }
            
            // Only add filter if not already present
            if (!$has_experience_filter) {
                $meta_query[] = [
                    'key' => '_product_type',
                    'value' => 'experience',
                    'compare' => '!='
                ];
                
                $query->set('meta_query', $meta_query);
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG && apply_filters('fp_esperienze_debug_shop_filtering', false)) {
                    error_log('FP Esperienze: Excluded experience products from general query on ' . $_SERVER['REQUEST_URI'] ?? 'unknown');
                }
            }
        }
    }

    /**
     * Check if this is our experience archive shortcode query
     *
     * @param \WP_Query $query WordPress query
     * @return bool
     */
    private function isOurShortcodeQuery(\WP_Query $query): bool {
        // Check if this query was initiated by our shortcode
        // Our shortcode specifically looks for experience products
        $meta_query = $query->get('meta_query') ?: [];
        
        // Also check if we're in a shortcode context via global flag
        if (defined('DOING_FP_SHORTCODE') && DOING_FP_SHORTCODE) {
            return true;
        }
        
        foreach ($meta_query as $meta_clause) {
            if (is_array($meta_clause) &&
                isset($meta_clause['key']) && $meta_clause['key'] === '_product_type' &&
                isset($meta_clause['value']) && $meta_clause['value'] === 'experience' &&
                isset($meta_clause['compare']) && $meta_clause['compare'] === '=') {
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG && apply_filters('fp_esperienze_debug_shop_filtering', false)) {
                    error_log('FP Esperienze: Detected experience shortcode query on ' . $_SERVER['REQUEST_URI'] ?? 'unknown');
                }
                
                return true;
            }
        }
        
        return false;
    }

    /**
     * Initialize REST API
     */
    public function initREST(): void {
        new AvailabilityAPI();
        new BookingsAPI();
        new BookingsController();
        new ICSAPI();
        new SecurePDFAPI();
        new \FP\Esperienze\REST\WidgetAPI();
        new \FP\Esperienze\REST\SystemStatusAPI();

        // Initialize mobile API manager
        new \FP\Esperienze\REST\MobileAPIManager();
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
        $frontend_css = AssetOptimizer::getAssetInfo('css', 'frontend', 'assets/css/frontend.css');
        wp_enqueue_style(
            'fp-esperienze-frontend',
            $frontend_css['url'],
            [],
            $frontend_css['version']
        );

        // Enqueue JS (minified if available)
        $frontend_js = AssetOptimizer::getAssetInfo('js', 'frontend', 'assets/js/frontend.js');
        wp_enqueue_script(
            'fp-esperienze-frontend',
            $frontend_js['url'],
            ['jquery', 'wp-i18n'],
            $frontend_js['version'],
            true
        );

        if (!$frontend_js['is_minified']) {
            $tracking_js = AssetOptimizer::getAssetInfo('js', 'tracking', 'assets/js/tracking.js');
            wp_enqueue_script(
                'fp-esperienze-tracking',
                $tracking_js['url'],
                ['jquery'],
                $tracking_js['version'],
                true
            );
        }

        wp_set_script_translations(
            'fp-esperienze-frontend',
            'fp-esperienze',
            FP_ESPERIENZE_PLUGIN_DIR . 'languages'
        );

        // Enqueue booking widget JS only on single experience pages
        if (is_singular('product')) {
            global $post;
            $product = wc_get_product($post->ID);

            // Only enqueue for experience products
            if ($product && $product->get_type() === 'experience') {
                $gallery_css = AssetOptimizer::getAssetInfo('css', 'experience-gallery', 'assets/css/experience-gallery.css');
                wp_enqueue_style(
                    'fp-esperienze-experience-gallery',
                    $gallery_css['url'],
                    array('fp-esperienze-frontend'),
                    $gallery_css['version']
                );

                $gallery_js = AssetOptimizer::getAssetInfo('js', 'experience-gallery', 'assets/js/experience-gallery.js');
                wp_enqueue_script(
                    'fp-esperienze-experience-gallery',
                    $gallery_js['url'],
                    array(),
                    $gallery_js['version'],
                    true
                );

                $booking_widget = AssetOptimizer::getAssetInfo('js', 'booking-widget', 'assets/js/booking-widget.js');
                wp_enqueue_script(
                    'fp-esperienze-booking-widget',
                    $booking_widget['url'],
                    ['jquery', 'wp-i18n', 'fp-esperienze-frontend'],
                    $booking_widget['version'],
                    true
                );

                wp_set_script_translations(
                    'fp-esperienze-booking-widget',
                    'fp-esperienze',
                    FP_ESPERIENZE_PLUGIN_DIR . 'languages'
                );
                
                // Localize booking widget with translated strings and REST API endpoints
                wp_localize_script('fp-esperienze-booking-widget', 'fp_booking_widget_i18n', [
                    'error_failed_load_availability' => __('Failed to load availability.', 'fp-esperienze'),
                    'error_booking_unavailable' => __('Booking system temporarily unavailable. Please try again.', 'fp-esperienze'),
                    'error_no_availability' => __('No availability for this date.', 'fp-esperienze'),
                    'spots_left' => __('spots left', 'fp-esperienze'),
                    'sold_out' => __('Sold out', 'fp-esperienze'),
                    'loading_availability' => __('Loading available times...', 'fp-esperienze'),
                    'rest_url' => get_rest_url(null, 'fp-exp/v1/'),
                    'nonce' => wp_create_nonce('wp_rest'),
                    'product_id' => $product->get_id(),
                ]);
            }
        }

        // Localize frontend script with dynamic data
        $frontend_params = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => get_rest_url(),
            'nonce' => wp_create_nonce('fp_esperienze_nonce'),
            'voucher_nonce' => wp_create_nonce('fp_voucher_nonce'),
            'banner_offset' => apply_filters('fp_esperienze_banner_offset', 20),
        ];

        if (function_exists('wc_get_cart_url')) {
            $frontend_params['cart_url'] = wc_get_cart_url();
        }

        wp_localize_script('fp-esperienze-frontend', 'fp_esperienze_params', $frontend_params);
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueueAdminScripts(): void {
        // Enqueue CSS (minified if available)
        $admin_css = AssetOptimizer::getAssetInfo('css', 'admin', 'assets/css/admin.css');

        wp_enqueue_style(
            'fp-esperienze-admin',
            $admin_css['url'],
            [],
            $admin_css['version']
        );

        // Enqueue admin controller based on modular flag
        $use_modular = apply_filters('fp_esperienze_use_modular_admin', true);

        $weekday_names  = [
            '1' => __('Monday', 'fp-esperienze'),
            '2' => __('Tuesday', 'fp-esperienze'),
            '3' => __('Wednesday', 'fp-esperienze'),
            '4' => __('Thursday', 'fp-esperienze'),
            '5' => __('Friday', 'fp-esperienze'),
            '6' => __('Saturday', 'fp-esperienze'),
            '0' => __('Sunday', 'fp-esperienze'),
        ];

        $weekday_abbrev = [
            '1' => __('Mon', 'fp-esperienze'),
            '2' => __('Tue', 'fp-esperienze'),
            '3' => __('Wed', 'fp-esperienze'),
            '4' => __('Thu', 'fp-esperienze'),
            '5' => __('Fri', 'fp-esperienze'),
            '6' => __('Sat', 'fp-esperienze'),
            '0' => __('Sun', 'fp-esperienze'),
        ];

        global $wp_locale;
        if (isset($wp_locale) && $wp_locale instanceof \WP_Locale) {
            $map = [
                '1' => 1,
                '2' => 2,
                '3' => 3,
                '4' => 4,
                '5' => 5,
                '6' => 6,
                '0' => 0,
            ];

            foreach ($map as $day_key => $wp_index) {
                $weekday_name = isset($wp_locale->weekday[$wp_index]) ? $wp_locale->weekday[$wp_index] : '';

                if ('' !== $weekday_name) {
                    $weekday_names[$day_key] = $weekday_name;

                    if (isset($wp_locale->weekday_abbrev[$weekday_name])) {
                        $weekday_abbrev[$day_key] = $wp_locale->weekday_abbrev[$weekday_name];
                    } else {
                        $weekday_abbrev[$day_key] = $wp_locale->get_weekday_abbrev($weekday_name);
                    }
                }
            }
        }

        if ($use_modular) {
            // Ensure the monolithic controller is not loaded
            wp_deregister_script('fp-esperienze-admin');

            // Load modular system: first load modules, then main controller
            $this->enqueueAdminModules();

            $admin_js = AssetOptimizer::getAssetInfo('js', 'admin-modular', 'assets/js/admin-modular.js');

            wp_enqueue_script(
                'fp-esperienze-admin-modular',
                $admin_js['url'],
                ['jquery', 'fp-esperienze-modules'],
                $admin_js['version'],
                true
            );

            wp_set_script_translations(
                'fp-esperienze-admin-modular',
                'fp-esperienze',
                FP_ESPERIENZE_PLUGIN_DIR . 'languages'
            );

            // Localize the modular script
            wp_localize_script('fp-esperienze-admin-modular', 'fp_esperienze_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => get_rest_url(),
                'experience_rest_url' => get_rest_url(null, 'fp-exp/v1/'),
                'rest_namespace' => 'fp-exp/v1/',
                'nonce' => wp_create_nonce('fp_esperienze_admin_nonce'),
                'banner_offset' => apply_filters('fp_esperienze_banner_offset', 20),
                'plugin_url' => FP_ESPERIENZE_PLUGIN_URL,
                'strings' => [
                    'confirm_remove_override' => __('Are you sure you want to remove this date override?', 'fp-esperienze'),
                    'distant_date_warning' => __('This date is very far in the future. Please verify it\'s correct.', 'fp-esperienze'),
                    'weekday_names' => array_map('esc_html', $weekday_names),
                    'weekday_abbrev' => array_map('esc_html', $weekday_abbrev),
                ],
            ]);
        } else {
            // Ensure the modular controller is not loaded
            wp_deregister_script('fp-esperienze-admin-modular');

            $admin_js = AssetOptimizer::getAssetInfo('js', 'admin', 'assets/js/admin.js');

            wp_enqueue_script(
                'fp-esperienze-admin',
                $admin_js['url'],
                ['jquery'],
                $admin_js['version'],
                true
            );

            wp_set_script_translations(
                'fp-esperienze-admin',
                'fp-esperienze',
                FP_ESPERIENZE_PLUGIN_DIR . 'languages'
            );

            // Localize script with admin data
            wp_localize_script('fp-esperienze-admin', 'fp_esperienze_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => get_rest_url(),
                'experience_rest_url' => get_rest_url(null, 'fp-exp/v1/'),
                'rest_namespace' => 'fp-exp/v1/',
                'nonce' => wp_create_nonce('fp_esperienze_admin_nonce'),
                'banner_offset' => apply_filters('fp_esperienze_banner_offset', 20),
                'plugin_url' => FP_ESPERIENZE_PLUGIN_URL,
                'strings' => [
                    'confirm_remove_override' => __('Are you sure you want to remove this date override?', 'fp-esperienze'),
                    'distant_date_warning' => __('This date is very far in the future. Please verify it\'s correct.', 'fp-esperienze'),
                    'weekday_names' => array_map('esc_html', $weekday_names),
                    'weekday_abbrev' => array_map('esc_html', $weekday_abbrev),
                ],
            ]);
        }

        // Enqueue reports script only on reports page
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->id === 'fp-esperienze_page_fp-esperienze-reports') {
            $reports_js = AssetOptimizer::getAssetInfo('js', 'reports', 'assets/js/reports.js');
            wp_enqueue_script(
                'fp-esperienze-reports',
                $reports_js['url'],
                ['jquery'],
                $reports_js['version'],
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
     * Enqueue individual admin modules
     */
    private function enqueueAdminModules(): void {
        $modules = [
            'error-handler' => 'error-handler.js',
            'performance' => 'performance.js', 
            'accessibility' => 'accessibility.js',
            'schedule-builder' => 'schedule-builder.js'
        ];

        $module_handles = [];
        
        foreach ($modules as $handle => $filename) {
            $module_handle = 'fp-esperienze-module-' . $handle;
            $module_asset = AssetOptimizer::getAssetInfo('js', 'module-' . $handle, 'assets/js/modules/' . $filename);

            wp_enqueue_script(
                $module_handle,
                $module_asset['url'],
                ['jquery'],
                $module_asset['version'],
                true
            );

            $module_handles[] = $module_handle;
        }
        
        // Register combined modules handle for dependency management
        wp_register_script(
            'fp-esperienze-modules',
            '', // No source file - virtual handle
            $module_handles,
            FP_ESPERIENZE_VERSION,
            true
        );
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
        $archive_block = AssetOptimizer::getAssetInfo('js', 'archive-block', 'assets/js/archive-block.js');

        wp_enqueue_script(
            'fp-esperienze-archive-block',
            $archive_block['url'],
            ['jquery', 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            $archive_block['version'],
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
        // Add custom cron interval first
        add_filter('cron_schedules', [$this, 'addHoldsCronInterval']);

        // Register cleanup action
        add_action('fp_esperienze_cleanup_holds', [$this, 'cleanupExpiredHolds']);

        // Schedule the event only after the interval is available
        if (!wp_next_scheduled('fp_esperienze_cleanup_holds')) {
            wp_schedule_event(time(), 'fp_esperienze_every_5_minutes', 'fp_esperienze_cleanup_holds');
        }
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
     * Initialize push token cleanup cron
     */
    public function initPushTokenCron(): void {
        if (!wp_next_scheduled('fp_cleanup_push_tokens')) {
            wp_schedule_event(time(), 'daily', 'fp_cleanup_push_tokens');
        }
    }

    /**
     * Cleanup expired push notification tokens
     */
    public function cleanupExpiredPushTokens(): void {
        global $wpdb;

        $start       = microtime(true);
        $table_name  = $wpdb->prefix . 'fp_push_tokens';

        $table_exists = true;

        if (method_exists($wpdb, 'get_var') && method_exists($wpdb, 'prepare')) {
            $table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));

            if (!$table_exists) {
                $creation_result = Installer::maybeCreatePushTokensTable();
                if (is_wp_error($creation_result)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('FP Esperienze: Push token cleanup skipped - unable to create table: ' . $creation_result->get_error_message());
                    }

                    return;
                }

                $table_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_name));
                if (!$table_exists) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('FP Esperienze: Push token cleanup skipped - table still missing after creation attempt.');
                    }

                    return;
                }
            }
        }

        $now         = gmdate('Y-m-d H:i:s', current_time('timestamp', true));
        $batch_size  = 500;
        $total_deleted = 0;

        do {
            $expired = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT token FROM {$table_name} WHERE expires_at IS NOT NULL AND expires_at <= %s LIMIT %d",
                    $now,
                    $batch_size
                )
            );

            if (empty($expired)) {
                break;
            }

            foreach ($expired as $row) {
                $token_value = isset($row->token) ? (string) $row->token : '';

                if ($token_value === '') {
                    continue;
                }

                $deleted = $wpdb->delete($table_name, ['token' => $token_value], ['%s']);

                if ($deleted !== false) {
                    $total_deleted += (int) $deleted;
                }
            }
        } while (!empty($expired));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(
                sprintf(
                    'FP Esperienze: Push token cleanup removed %d tokens in %.4f seconds',
                    $total_deleted,
                    microtime(true) - $start
                )
            );
        }
    }
    
    /**
     * Maybe add performance indexes (only once per plugin version)
     */
    public function maybeAddPerformanceIndexes(): void {
        if (!function_exists('get_option') || !function_exists('update_option') || !function_exists('esc_sql')) {
            return;
        }

        $indexes_version = get_option('fp_esperienze_indexes_version', '0.0.0');
        $current_version = FP_ESPERIENZE_VERSION;

        // Only add indexes if they haven't been added for this version
        if (version_compare($indexes_version, $current_version, '<')) {
            try {
                Installer::addPerformanceIndexes();
                update_option('fp_esperienze_indexes_version', $current_version);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("FP Esperienze: Performance indexes updated to version {$current_version}");
                }
            } catch (Throwable $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FP Esperienze: Failed to add performance indexes: ' . $e->getMessage());
                }
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
    
    /**
     * Output custom branding CSS
     */
    public function outputBrandingCSS(): void {
        $branding_settings = get_option('fp_esperienze_branding', []);
        
        // Skip if no custom branding settings
        if (empty($branding_settings)) {
            return;
        }
        
        $primary_font = $branding_settings['primary_font'] ?? 'inherit';
        $heading_font = $branding_settings['heading_font'] ?? 'inherit';
        $primary_color = self::sanitizeBrandColor($branding_settings['primary_color'] ?? '', '#ff6b35');
        $secondary_color = self::sanitizeBrandColor($branding_settings['secondary_color'] ?? '', '#2c3e50');
        $derived_colors = self::getDerivedBrandColors($primary_color);
        
        // Skip if all values are defaults
        if ($primary_font === 'inherit' && 
            $heading_font === 'inherit' && 
            $primary_color === '#ff6b35' && 
            $secondary_color === '#2c3e50') {
            return;
        }
        
        echo "\n<!-- FP Esperienze Custom Branding CSS -->\n";
        echo "<style type=\"text/css\">\n";
        
        // Update CSS custom properties
        echo ":root {\n";
        if ($primary_color !== '#ff6b35') {
            echo "    --fp-brand-primary: " . esc_attr($primary_color) . ";\n";
        }
        if ($secondary_color !== '#2c3e50') {
            echo "    --fp-brand-secondary: " . esc_attr($secondary_color) . ";\n";
        }
        foreach ($derived_colors as $variable => $value) {
            echo '    ' . esc_attr($variable) . ': ' . esc_attr($value) . ";\n";
        }
        echo "}\n";
        
        // Apply fonts to FP Esperienze elements
        if ($primary_font !== 'inherit') {
            echo ".fp-experience-single,\n";
            echo ".fp-experience-archive,\n";
            echo ".fp-booking-form,\n";
            echo ".fp-experience-grid .fp-experience-card,\n";
            echo ".fp-experience-details,\n";
            echo ".fp-experience-description {\n";
            echo "    font-family: " . esc_attr($primary_font) . " !important;\n";
            echo "}\n";
        }
        
        if ($heading_font !== 'inherit') {
            echo ".fp-experience-title,\n";
            echo ".fp-experience-single h1,\n";
            echo ".fp-experience-single h2,\n";
            echo ".fp-experience-single h3,\n";
            echo ".fp-experience-grid .fp-experience-card h3,\n";
            echo ".fp-hero-title,\n";
            echo ".fp-section-title {\n";
            echo "    font-family: " . esc_attr($heading_font) . " !important;\n";
            echo "}\n";
        }
        
        // Load Google Fonts if needed
        $google_fonts = [];
        if ($primary_font !== 'inherit' && strpos($primary_font, 'sans-serif') !== false && strpos($primary_font, "'") !== false) {
            $font_name = str_replace(["'", ', sans-serif', ', serif'], '', $primary_font);
            if (in_array($font_name, ['Open Sans', 'Roboto', 'Lato', 'Montserrat', 'Poppins'])) {
                $google_fonts[] = $font_name;
            }
        }
        
        if ($heading_font !== 'inherit' && strpos($heading_font, "'") !== false) {
            $font_name = str_replace(["'", ', sans-serif', ', serif'], '', $heading_font);
            if (in_array($font_name, ['Open Sans', 'Roboto', 'Lato', 'Montserrat', 'Poppins', 'Playfair Display', 'Merriweather'])) {
                $google_fonts[] = $font_name;
            }
        }
        
        echo "</style>\n";
        
        // Add Google Fonts if needed
        if (!empty($google_fonts)) {
            $google_fonts = array_unique($google_fonts);
            $fonts_query = str_replace(' ', '+', implode('|', $google_fonts));
            echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
            echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
            echo '<link href="https://fonts.googleapis.com/css2?family=' . esc_attr($fonts_query) . ':wght@400;600;700&display=swap" rel="stylesheet">' . "\n";
        }
        
        echo "<!-- End FP Esperienze Custom Branding CSS -->\n\n";
    }

    /**
     * Build derived brand colors for CSS helper variables.
     *
     * @param string $primary_color Primary brand color in hex format.
     * @return array<string, string> Map of CSS variable names to color values.
     */
    private static function getDerivedBrandColors(string $primary_color): array {
        $base_color = self::sanitizeBrandColor($primary_color, '#ff6b35');

        return [
            '--fp-brand-primary-hover'      => self::mixColors($base_color, 88, '#000000', 12),
            '--fp-brand-primary-soft'       => self::mixColors($base_color, 75, '#ffffff', 25),
            '--fp-brand-primary-focus-ring' => self::hexToRgba($base_color, 0.18),
            '--fp-brand-primary-shadow'     => self::hexToRgba($base_color, 0.30),
            '--fp-brand-primary-tint'       => self::mixColors($base_color, 12, '#ffffff', 88),
        ];
    }

    /**
     * Sanitize a brand color value.
     *
     * @param mixed  $color    Raw color value from settings.
     * @param string $fallback Fallback color if the value is invalid.
     * @return string
     */
    private static function sanitizeBrandColor($color, string $fallback): string {
        if (!is_string($color)) {
            $color = '';
        }

        $sanitized = self::maybeSanitizeHexColor($color);
        if ($sanitized === null) {
            $sanitized = self::maybeSanitizeHexColor($fallback);
        }

        if ($sanitized === null) {
            $sanitized = '#ff6b35';
        }

        return $sanitized;
    }

    /**
     * Sanitize a hex color value when WordPress helpers are unavailable.
     *
     * @param string $color Potential hex color string.
     * @return string|null
     */
    private static function maybeSanitizeHexColor(string $color): ?string {
        if ($color === '') {
            return null;
        }

        if (function_exists('sanitize_hex_color')) {
            $sanitized = sanitize_hex_color($color);
            if (is_string($sanitized) && $sanitized !== '') {
                return strtolower($sanitized);
            }

            return null;
        }

        if (preg_match('/^#(?:[0-9a-f]{3}){1,2}$/i', $color)) {
            return strtolower($color);
        }

        return null;
    }

    /**
     * Convert a hex color to an RGB triplet.
     *
     * @param string $hex_color Hex color string.
     * @return array<int, int>
     */
    private static function hexToRgb(string $hex_color): array {
        $hex = ltrim($hex_color, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || preg_match('/[^0-9a-f]/i', $hex)) {
            return [255, 107, 53];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Mix two colors together using weighted ratios.
     *
     * @param string $first_color  First color in hex format.
     * @param float  $first_weight Weight for the first color.
     * @param string $second_color Second color in hex format.
     * @param float  $second_weight Weight for the second color.
     * @return string
     */
    private static function mixColors(string $first_color, float $first_weight, string $second_color, float $second_weight): string {
        [$r1, $g1, $b1] = self::hexToRgb($first_color);
        [$r2, $g2, $b2] = self::hexToRgb($second_color);

        $first_weight = max(0.0, $first_weight);
        $second_weight = max(0.0, $second_weight);
        $total = $first_weight + $second_weight;

        if ($total <= 0.0) {
            $total = 1.0;
            $first_weight = 1.0;
            $second_weight = 0.0;
        }

        $first_ratio = $first_weight / $total;
        $second_ratio = $second_weight / $total;

        $red = (int) round(($r1 * $first_ratio) + ($r2 * $second_ratio));
        $green = (int) round(($g1 * $first_ratio) + ($g2 * $second_ratio));
        $blue = (int) round(($b1 * $first_ratio) + ($b2 * $second_ratio));

        return strtolower(sprintf('#%02X%02X%02X', $red, $green, $blue));
    }

    /**
     * Convert a hex color to an RGBA string with the given alpha.
     *
     * @param string $hex_color Hex color string.
     * @param float  $alpha     Alpha value between 0 and 1.
     * @return string
     */
    private static function hexToRgba(string $hex_color, float $alpha): string {
        [$red, $green, $blue] = self::hexToRgb($hex_color);
        $alpha = max(0.0, min(1.0, $alpha));
        $alpha_formatted = rtrim(rtrim(sprintf('%.2F', $alpha), '0'), '.');

        if ($alpha_formatted === '') {
            $alpha_formatted = '0';
        }

        return sprintf('rgba(%d, %d, %d, %s)', $red, $green, $blue, $alpha_formatted);
    }

    /**
     * Show initialization error admin notice
     */
    public static function showInitializationError(): void {
        if (current_user_can('manage_options') && self::$init_error) {
            echo '<div class="notice notice-warning"><p>' . 
                 sprintf(
                     esc_html__('FP Esperienze: Some features may not work properly. Error: %s', 'fp-esperienze'),
                     esc_html(self::$init_error)
                 ) . 
                 '</p></div>';
        }
    }

    /**
     * Show product type initialization error admin notice
     */
    public static function showProductTypeError(): void {
        if (current_user_can('manage_options') && self::$product_type_error) {
            echo '<div class="notice notice-error"><p>' . 
                 sprintf(
                     esc_html__('FP Esperienze: Experience product type initialization failed. Error: %s', 'fp-esperienze'),
                     esc_html(self::$product_type_error)
                 ) . 
                 '</p></div>';
        }
    }

    /**
     * Show debug features for testing (temporary)
     */
    public static function showDebugFeatures(): void {
        if (isset($_GET['fp_test_features']) && current_user_can('manage_options')) {
            if (class_exists('FP\Esperienze\Core\FeatureTester')) {
                FeatureTester::displayTestResults();
            }
        }
    }
}