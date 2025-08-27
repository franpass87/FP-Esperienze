<?php
/**
 * Cache Management for Performance
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\Data\Availability;

defined('ABSPATH') || exit;

/**
 * Cache manager class for smart cache invalidation and pre-building
 */
class CacheManager {
    
    /**
     * Cache TTL in seconds (5-10 minutes)
     */
    const CACHE_TTL = 600; // 10 minutes
    const SHORT_CACHE_TTL = 300; // 5 minutes
    
    /**
     * Pre-build days setting key
     */
    const PREBUILD_DAYS_OPTION = 'fp_esperienze_prebuild_days';
    
    /**
     * Constructor - Initialize hooks
     */
    public function __construct() {
        // Cache invalidation hooks
        add_action('fp_esperienze_booking_created', [$this, 'invalidateBookingCache'], 10, 2);
        add_action('fp_esperienze_booking_cancelled', [$this, 'invalidateBookingCache'], 10, 2);
        add_action('fp_esperienze_booking_refunded', [$this, 'invalidateBookingCache'], 10, 2);
        add_action('fp_esperienze_override_saved', [$this, 'invalidateOverrideCache'], 10, 2);
        add_action('fp_esperienze_override_deleted', [$this, 'invalidateOverrideCache'], 10, 2);
        
        // Pre-build cron job
        add_action('fp_esperienze_prebuild_availability', [$this, 'prebuildAvailability']);
        
        // Schedule cron job if not already scheduled
        if (!wp_next_scheduled('fp_esperienze_prebuild_availability')) {
            wp_schedule_event(time(), 'hourly', 'fp_esperienze_prebuild_availability');
        }
    }
    
    /**
     * Get cached availability with smart TTL
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array|false Cached data or false if not cached
     */
    public static function getAvailabilityCache(int $product_id, string $date) {
        $cache_key = self::getAvailabilityCacheKey($product_id, $date);
        return get_transient($cache_key);
    }
    
    /**
     * Set availability cache
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @param array $data Data to cache
     * @param int|null $ttl TTL in seconds (optional, uses default)
     * @return bool
     */
    public static function setAvailabilityCache(int $product_id, string $date, array $data, ?int $ttl = null): bool {
        $cache_key = self::getAvailabilityCacheKey($product_id, $date);
        $ttl = $ttl ?: self::CACHE_TTL;
        
        // Add cache metadata
        $data['_cache_created'] = time();
        $data['_cache_ttl'] = $ttl;
        
        return set_transient($cache_key, $data, $ttl);
    }
    
    /**
     * Invalidate availability cache for specific product and date
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return bool
     */
    public static function invalidateAvailabilityCache(int $product_id, string $date): bool {
        $cache_key = self::getAvailabilityCacheKey($product_id, $date);
        return delete_transient($cache_key);
    }
    
    /**
     * Invalidate all availability cache for a product
     *
     * @param int $product_id Product ID
     * @return void
     */
    public static function invalidateProductCache(int $product_id): void {
        global $wpdb;
        
        // Delete all transients with this product pattern
        $pattern = '_transient_fp_availability_' . $product_id . '_%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        ));
        
        // Also delete timeout transients
        $timeout_pattern = '_transient_timeout_fp_availability_' . $product_id . '_%';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $timeout_pattern
        ));
    }
    
    /**
     * Invalidate cache when booking is created/modified
     *
     * @param int $product_id Product ID
     * @param string $date Booking date
     */
    public function invalidateBookingCache(int $product_id, string $date): void {
        // Invalidate specific date cache
        self::invalidateAvailabilityCache($product_id, $date);
        
        // Also invalidate archive cache for this date
        $archive_cache_key = 'fp_available_products_' . $date;
        delete_transient($archive_cache_key);
        
        // Log cache invalidation
        error_log("FP Cache: Invalidated availability cache for product {$product_id} on {$date}");
    }
    
    /**
     * Invalidate cache when override is saved/deleted
     *
     * @param int $product_id Product ID
     * @param string $date Override date
     */
    public function invalidateOverrideCache(int $product_id, string $date): void {
        // Same as booking cache invalidation
        $this->invalidateBookingCache($product_id, $date);
    }
    
    /**
     * Pre-build availability cache for next N days
     */
    public function prebuildAvailability(): void {
        $days = get_option(self::PREBUILD_DAYS_OPTION, 7); // Default 7 days
        
        if ($days <= 0) {
            return; // Pre-building disabled
        }
        
        // Get all experience products using WP_Query for better performance
        $query = new \WP_Query([
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_product_type',
                    'value' => 'experience'
                ]
            ],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        
        $experience_products = $query->posts;
        
        if (empty($experience_products)) {
            return;
        }
        
        $today = new \DateTime('now', wp_timezone());
        $prebuilt_count = 0;
        
        for ($i = 0; $i < $days; $i++) {
            $check_date = clone $today;
            $check_date->modify("+{$i} days");
            $date_str = $check_date->format('Y-m-d');
            
            foreach ($experience_products as $product_id) {
                // Check if already cached
                if (self::getAvailabilityCache($product_id, $date_str) !== false) {
                    continue;
                }
                
                // Pre-build the cache
                $slots = Availability::forDay($product_id, $date_str);
                
                $response_data = [
                    'product_id' => $product_id,
                    'date' => $date_str,
                    'slots' => $slots,
                    'total_slots' => count($slots),
                    '_prebuilt' => true,
                ];
                
                // Use longer TTL for pre-built cache
                self::setAvailabilityCache($product_id, $date_str, $response_data, self::CACHE_TTL);
                $prebuilt_count++;
                
                // Avoid overwhelming the server
                if ($prebuilt_count % 10 === 0) {
                    usleep(100000); // 0.1 second pause every 10 products
                }
            }
        }
        
        if ($prebuilt_count > 0 && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Cache: Pre-built {$prebuilt_count} availability caches for next {$days} days");
        }
    }
    
    /**
     * Get availability cache key
     *
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return string
     */
    private static function getAvailabilityCacheKey(int $product_id, string $date): string {
        return 'fp_availability_' . $product_id . '_' . $date;
    }
    
    /**
     * Clear all FP Esperienze caches
     *
     * @return int Number of caches cleared
     */
    public static function clearAllCaches(): int {
        global $wpdb;
        
        // Clear availability caches
        $result1 = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_availability_%'"
        );
        
        $result2 = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fp_availability_%'"
        );
        
        // Clear archive caches
        $result3 = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_available_products_%'"
        );
        
        $result4 = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_fp_available_products_%'"
        );
        
        $total_cleared = $result1 + $result2 + $result3 + $result4;
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Cache: Cleared {$total_cleared} cache entries");
        }
        
        return $total_cleared;
    }
    
    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function getCacheStats(): array {
        global $wpdb;
        
        $availability_caches = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_availability_%'"
        );
        
        $archive_caches = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_fp_available_products_%'"
        );
        
        return [
            'availability_caches' => (int) $availability_caches,
            'archive_caches' => (int) $archive_caches,
            'total_caches' => (int) $availability_caches + (int) $archive_caches,
            'prebuild_days' => get_option(self::PREBUILD_DAYS_OPTION, 7),
        ];
    }
}