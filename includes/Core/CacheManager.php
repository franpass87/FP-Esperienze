<?php
/**
 * Cache Management for Performance
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\Data\Availability;
use FP\Esperienze\Data\HoldManager;
use FP\Esperienze\Helpers\TimezoneHelper;

defined('ABSPATH') || exit;

if (!class_exists('FP\\Esperienze\\Helpers\\TimezoneHelper')) {
    require_once dirname(__DIR__) . '/Helpers/TimezoneHelper.php';
}

/**
 * Cache manager class for smart cache invalidation and pre-building
 */
class CacheManager {
    
    /**
     * Cache TTL in seconds (5-10 minutes)
     */
    const CACHE_TTL = 600; // 10 minutes
    const SHORT_CACHE_TTL = 300; // 5 minutes
    private const AVAILABILITY_INDEX_OPTION = 'fp_esperienze_availability_cache_index';
    
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

        $result = set_transient($cache_key, $data, $ttl);

        if ($result) {
            self::addAvailabilityKeyToIndex($product_id, $cache_key);
        }

        return $result;
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
        $deleted = delete_transient($cache_key);

        self::removeAvailabilityKeysFromIndex($product_id, [$cache_key]);

        if (!self::isUsingExternalObjectCache() && function_exists('delete_option')) {
            delete_option('_transient_' . $cache_key);
            delete_option('_transient_timeout_' . $cache_key);
        }

        return $deleted;
    }
    
    /**
     * Invalidate all availability cache for a product
     *
     * @param int $product_id Product ID
     * @return void
     */
    public static function invalidateProductCache(int $product_id): void {
        $keys = self::getAvailabilityKeysForProduct($product_id);

        if (empty($keys)) {
            return;
        }

        self::deleteTransientKeys($keys);

        self::removeAvailabilityKeysFromIndex($product_id, $keys);
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Cache: Invalidated availability cache for product {$product_id} on {$date}");
        }
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

        if (HoldManager::isEnabled()) {
            // Holds require real-time availability adjustments; skip prebuilding cache.
            return;
        }

        if ($days <= 0) {
            return; // Pre-building disabled
        }
        
        $today         = new \DateTime('now', TimezoneHelper::getSiteTimezone());
        $prebuilt_count = 0;
        $page          = 1;
        $per_page      = 50;

        while (true) {
            // Get experience products in paginated batches
            $query = new \WP_Query([
                'post_type'              => 'product',
                'meta_query'             => [
                    [
                        'key'   => '_product_type',
                        'value' => 'experience',
                    ],
                ],
                'posts_per_page'        => $per_page,
                'paged'                 => $page,
                'fields'                => 'ids',
                'no_found_rows'         => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $experience_products = $query->posts;

            if (empty($experience_products)) {
                break;
            }

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
                        'product_id'  => $product_id,
                        'date'        => $date_str,
                        'slots'       => $slots,
                        'total_slots' => count($slots),
                        '_prebuilt'   => true,
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

            $page++;
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
        $availability_keys = array_unique(array_merge(
            self::getTrackedAvailabilityKeys(),
            self::collectAvailabilityKeysFromDatabase()
        ));

        $archive_keys = self::collectTransientKeysFromDatabase('fp_available_products_');

        $total_cleared = self::deleteTransientKeys($availability_keys) + self::deleteTransientKeys($archive_keys);

        if (!empty($availability_keys)) {
            self::clearAvailabilityIndex();
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FP Cache: Cleared {$total_cleared} cache entries");
        }

        return $total_cleared;
    }

    /**
     * Determine if an external object cache is in use.
     */
    private static function isUsingExternalObjectCache(): bool {
        return function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();
    }

    /**
     * Get transient keys for a product from index/database.
     *
     * @param int $product_id Product ID.
     * @return array<string>
     */
    private static function getAvailabilityKeysForProduct(int $product_id): array {
        $tracked = self::getAvailabilityKeysFromIndex($product_id);
        $database = self::collectAvailabilityKeysFromDatabase($product_id);

        return array_values(array_unique(array_merge($tracked, $database)));
    }

    /**
     * Collect availability keys from the persistent index.
     *
     * @param int $product_id Product ID.
     * @return array<string>
     */
    private static function getAvailabilityKeysFromIndex(int $product_id): array {
        $index = self::getAvailabilityIndex();

        if (!isset($index[$product_id]) || !is_array($index[$product_id])) {
            return [];
        }

        $keys = array_values(array_filter($index[$product_id], 'is_string'));

        return array_values(array_unique($keys));
    }

    /**
     * Collect all tracked availability keys.
     *
     * @return array<string>
     */
    private static function getTrackedAvailabilityKeys(): array {
        $index = self::getAvailabilityIndex();
        $keys = [];

        foreach ($index as $product_keys) {
            if (!is_array($product_keys)) {
                continue;
            }

            foreach ($product_keys as $key) {
                if (is_string($key)) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Retrieve availability keys from database storage.
     *
     * @param int|null $product_id Product ID to filter by.
     * @return array<string>
     */
    private static function collectAvailabilityKeysFromDatabase(?int $product_id = null): array {
        $prefix = 'fp_availability_';

        if (null !== $product_id) {
            $prefix .= $product_id . '_';
        }

        return self::collectTransientKeysFromDatabase($prefix);
    }

    /**
     * Collect transient keys stored in the database matching a prefix.
     *
     * @param string $transient_prefix Transient key prefix without the `_transient_` prefix.
     * @return array<string>
     */
    private static function collectTransientKeysFromDatabase(string $transient_prefix): array {
        if (self::isUsingExternalObjectCache()) {
            return [];
        }

        global $wpdb;

        if (!isset($wpdb) || !isset($wpdb->options) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_col')) {
            return [];
        }

        $option_like = $wpdb->esc_like('_transient_' . $transient_prefix) . '%';
        $timeout_like = $wpdb->esc_like('_transient_timeout_' . $transient_prefix) . '%';

        $option_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $option_like
            )
        ) ?: [];

        $timeout_names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $timeout_like
            )
        ) ?: [];

        return self::convertOptionNamesToTransientKeys(array_merge($option_names, $timeout_names));
    }

    /**
     * Convert option names into transient keys.
     *
     * @param array<int, mixed> $option_names Option names from database queries.
     * @return array<string>
     */
    private static function convertOptionNamesToTransientKeys(array $option_names): array {
        $keys = [];

        foreach ($option_names as $option_name) {
            if (!is_string($option_name)) {
                continue;
            }

            if (str_starts_with($option_name, '_transient_timeout_')) {
                $keys[] = substr($option_name, strlen('_transient_timeout_'));
                continue;
            }

            if (str_starts_with($option_name, '_transient_')) {
                $keys[] = substr($option_name, strlen('_transient_'));
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Delete a list of transient keys.
     *
     * @param array<int, string> $keys Transient names.
     * @return int Number of keys removed.
     */
    private static function deleteTransientKeys(array $keys): int {
        $unique_keys = array_values(array_unique(array_filter($keys, 'is_string')));

        if (empty($unique_keys)) {
            return 0;
        }

        $using_object_cache = self::isUsingExternalObjectCache();

        if ($using_object_cache && function_exists('wp_cache_get_multiple')) {
            wp_cache_get_multiple($unique_keys, 'transient');
        }

        $deleted = 0;

        foreach ($unique_keys as $cache_key) {
            $removed = false;

            if (delete_transient($cache_key)) {
                $removed = true;
            } elseif ($using_object_cache && function_exists('wp_cache_delete')) {
                $removed = wp_cache_delete($cache_key, 'transient') || $removed;
            }

            if (!$using_object_cache && function_exists('delete_option')) {
                $removed = delete_option('_transient_' . $cache_key) || $removed;
                $removed = delete_option('_transient_timeout_' . $cache_key) || $removed;
            }

            if ($removed) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Retrieve the availability index from options storage.
     *
     * @return array<int, array<int, string>>
     */
    private static function getAvailabilityIndex(): array {
        if (!function_exists('get_option')) {
            return [];
        }

        $index = get_option(self::AVAILABILITY_INDEX_OPTION, []);

        return is_array($index) ? $index : [];
    }

    /**
     * Persist the availability index.
     *
     * @param array<int, array<int, string>> $index Index data.
     */
    private static function saveAvailabilityIndex(array $index): void {
        if (!function_exists('update_option')) {
            return;
        }

        if (empty($index)) {
            if (function_exists('delete_option')) {
                delete_option(self::AVAILABILITY_INDEX_OPTION);
            }

            return;
        }

        update_option(self::AVAILABILITY_INDEX_OPTION, $index);
    }

    /**
     * Track a transient key for a product.
     *
     * @param int $product_id Product ID.
     * @param string $cache_key Transient key.
     */
    private static function addAvailabilityKeyToIndex(int $product_id, string $cache_key): void {
        $cache_key = trim($cache_key);

        if ($cache_key === '') {
            return;
        }

        $index = self::getAvailabilityIndex();
        $product_keys = $index[$product_id] ?? [];

        if (!is_array($product_keys)) {
            $product_keys = [];
        }

        if (!in_array($cache_key, $product_keys, true)) {
            $product_keys[] = $cache_key;
            $index[$product_id] = array_values($product_keys);
            self::saveAvailabilityIndex($index);
        }
    }

    /**
     * Remove transient keys from the product index.
     *
     * @param int $product_id Product ID.
     * @param array<int, string> $keys Transient names.
     */
    private static function removeAvailabilityKeysFromIndex(int $product_id, array $keys): void {
        if (empty($keys)) {
            return;
        }

        $index = self::getAvailabilityIndex();

        if (!isset($index[$product_id]) || !is_array($index[$product_id])) {
            return;
        }

        $current = array_flip(array_filter($index[$product_id], 'is_string'));
        $changed = false;

        foreach ($keys as $key) {
            if (!is_string($key)) {
                continue;
            }

            if (isset($current[$key])) {
                unset($current[$key]);
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        if (empty($current)) {
            unset($index[$product_id]);
        } else {
            $index[$product_id] = array_keys($current);
        }

        self::saveAvailabilityIndex($index);
    }

    /**
     * Clear the availability key index.
     */
    private static function clearAvailabilityIndex(): void {
        self::saveAvailabilityIndex([]);
    }
    
    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function getCacheStats(): array {
        global $wpdb;
        
        $availability_caches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_fp_availability_%'
        ));
        
        $archive_caches = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_fp_available_products_%'
        ));
        
        return [
            'availability_caches' => (int) $availability_caches,
            'archive_caches' => (int) $archive_caches,
            'total_caches' => (int) $availability_caches + (int) $archive_caches,
            'prebuild_days' => get_option(self::PREBUILD_DAYS_OPTION, 7),
        ];
    }
}