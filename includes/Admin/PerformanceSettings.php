<?php
/**
 * Performance Settings Admin Page
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Core\CacheManager;
use FP\Esperienze\Core\AssetOptimizer;

defined('ABSPATH') || exit;

/**
 * Performance settings page
 */
class PerformanceSettings {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('fp_esperienze_admin_menu_pages', [$this, 'addMenuPage']);
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void {
        register_setting('fp_esperienze_performance', CacheManager::PREBUILD_DAYS_OPTION, [
            'type' => 'integer',
            'default' => 7,
            'sanitize_callback' => 'absint',
        ]);
    }
    
    /**
     * Add menu page
     *
     * @param array $pages Menu pages
     * @return array
     */
    public function addMenuPage(array $pages): array {
        $pages[] = [
            'page_title' => __('Performance Settings', 'fp-esperienze'),
            'menu_title' => __('Performance', 'fp-esperienze'),
            'capability' => 'manage_options',
            'menu_slug' => 'fp-esperienze-performance',
            'callback' => [$this, 'renderPage'],
            'position' => 90,
        ];
        
        return $pages;
    }
    
    /**
     * Render settings page
     */
    public function renderPage(): void {
        // Handle cache clear action
        if (isset($_POST['clear_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'fp_esperienze_clear_cache')) {
            $cleared = CacheManager::clearAllCaches();
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('Cleared %d cache entries.', 'fp-esperienze'), $cleared) . 
                 '</p></div>';
        }
        
        // Handle minification regeneration
        if (isset($_POST['regenerate_assets']) && wp_verify_nonce($_POST['_wpnonce'], 'fp_esperienze_regenerate_assets')) {
            AssetOptimizer::clearMinified();
            AssetOptimizer::maybeGenerateMinified();
            echo '<div class="notice notice-success"><p>' . 
                 __('Regenerated minified assets.', 'fp-esperienze') . 
                 '</p></div>';
        }
        
        // Handle manual pre-build
        if (isset($_POST['prebuild_cache']) && wp_verify_nonce($_POST['_wpnonce'], 'fp_esperienze_prebuild_cache')) {
            $cache_manager = new CacheManager();
            $cache_manager->prebuildAvailability();
            echo '<div class="notice notice-success"><p>' . 
                 __('Started pre-building availability cache.', 'fp-esperienze') . 
                 '</p></div>';
        }
        
        $cache_stats = CacheManager::getCacheStats();
        $asset_stats = AssetOptimizer::getOptimizationStats();
        $prebuild_days = get_option(CacheManager::PREBUILD_DAYS_OPTION, 7);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('fp_esperienze_performance'); ?>
                
                <h2><?php _e('Cache Settings', 'fp-esperienze'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="<?php echo esc_attr(CacheManager::PREBUILD_DAYS_OPTION); ?>">
                                <?php _e('Pre-build Days', 'fp-esperienze'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="<?php echo esc_attr(CacheManager::PREBUILD_DAYS_OPTION); ?>" 
                                   name="<?php echo esc_attr(CacheManager::PREBUILD_DAYS_OPTION); ?>" 
                                   value="<?php echo esc_attr($prebuild_days); ?>" 
                                   min="0" max="30" />
                            <p class="description">
                                <?php _e('Number of days ahead to pre-build availability cache. Set to 0 to disable pre-building.', 'fp-esperienze'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2><?php _e('Cache Statistics', 'fp-esperienze'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><?php _e('Availability Caches', 'fp-esperienze'); ?></td>
                        <td><?php echo esc_html($cache_stats['availability_caches']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Archive Caches', 'fp-esperienze'); ?></td>
                        <td><?php echo esc_html($cache_stats['archive_caches']); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Total Caches', 'fp-esperienze'); ?></td>
                        <td><strong><?php echo esc_html($cache_stats['total_caches']); ?></strong></td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php _e('Asset Optimization Statistics', 'fp-esperienze'); ?></h2>
            <table class="widefat">
                <tbody>
                    <tr>
                        <td><?php _e('Original Size', 'fp-esperienze'); ?></td>
                        <td><?php echo esc_html(size_format($asset_stats['total_original_size'])); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Minified Size', 'fp-esperienze'); ?></td>
                        <td><?php echo esc_html(size_format($asset_stats['total_minified_size'])); ?></td>
                    </tr>
                    <tr>
                        <td><?php _e('Compression Ratio', 'fp-esperienze'); ?></td>
                        <td><strong><?php echo esc_html($asset_stats['compression_ratio']); ?>%</strong></td>
                    </tr>
                    <tr>
                        <td><?php _e('Minified Assets Available', 'fp-esperienze'); ?></td>
                        <td><?php echo AssetOptimizer::hasMinifiedAssets() ? 
                                 '<span class="fp-status-success">✓ ' . __('Yes', 'fp-esperienze') . '</span>' : 
                                 '<span class="fp-status-error">✗ ' . __('No', 'fp-esperienze') . '</span>'; ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h2><?php _e('Performance Actions', 'fp-esperienze'); ?></h2>
            <div class="fp-performance-actions">
                
                <!-- Clear Cache -->
                <form method="post" class="fp-inline-form">
                    <?php wp_nonce_field('fp_esperienze_clear_cache'); ?>
                    <input type="submit" name="clear_cache" class="button button-secondary" 
                           value="<?php esc_attr_e('Clear All Caches', 'fp-esperienze'); ?>" 
                           onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all caches?', 'fp-esperienze'); ?>');" />
                </form>
                
                <!-- Regenerate Assets -->
                <form method="post" class="fp-inline-form">
                    <?php wp_nonce_field('fp_esperienze_regenerate_assets'); ?>
                    <input type="submit" name="regenerate_assets" class="button button-secondary" 
                           value="<?php esc_attr_e('Regenerate Minified Assets', 'fp-esperienze'); ?>" />
                </form>
                
                <!-- Manual Pre-build -->
                <form method="post" class="fp-inline-form">
                    <?php wp_nonce_field('fp_esperienze_prebuild_cache'); ?>
                    <input type="submit" name="prebuild_cache" class="button button-primary" 
                           value="<?php esc_attr_e('Pre-build Cache Now', 'fp-esperienze'); ?>" />
                </form>
                
            </div>
            
            <h2><?php _e('Performance Tips', 'fp-esperienze'); ?></h2>
            <div class="fp-performance-tips">
                <ul>
                    <li><strong><?php _e('Cache TTL', 'fp-esperienze'); ?>:</strong> <?php _e('Availability caches are stored for 10 minutes by default.', 'fp-esperienze'); ?></li>
                    <li><strong><?php _e('Smart Invalidation', 'fp-esperienze'); ?>:</strong> <?php _e('Caches are automatically cleared when bookings are made or overrides are changed.', 'fp-esperienze'); ?></li>
                    <li><strong><?php _e('Asset Optimization', 'fp-esperienze'); ?>:</strong> <?php _e('CSS and JS files are automatically minified and concatenated for better performance.', 'fp-esperienze'); ?></li>
                    <li><strong><?php _e('Lazy Loading', 'fp-esperienze'); ?>:</strong> <?php _e('All images include lazy loading attributes for improved page load times.', 'fp-esperienze'); ?></li>
                    <li><strong><?php _e('Script Deferring', 'fp-esperienze'); ?>:</strong> <?php _e('Non-critical scripts are deferred to improve page rendering speed.', 'fp-esperienze'); ?></li>
                </ul>
            </div>
            
        </div>
        <?php
    }
}