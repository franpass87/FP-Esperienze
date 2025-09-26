<?php
/**
 * Extra Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

defined('ABSPATH') || exit;

/**
 * Extra Manager class for CRUD operations
 */
class ExtraManager {

    private const CACHE_GROUP = 'fp_esperienze_extras';
    private const CACHE_TTL   = 600; // 10 minutes

    /**
     * Cached collections of extras keyed by context.
     *
     * @var array<string, array<int, object>>
     */
    private static array $allExtrasCache = [
        'all'    => [],
        'active' => [],
    ];

    /**
     * Flags that indicate whether the in-memory cache has been populated.
     *
     * @var array<string, bool>
     */
    private static array $allExtrasPrimed = [
        'all'    => false,
        'active' => false,
    ];

    /**
     * Runtime cache of extras indexed by ID.
     *
     * @var array<int, object>
     */
    private static array $extrasById = [];

    /**
     * Track which extra IDs have been cached so invalidation can clear them.
     *
     * @var array<int, bool>
     */
    private static array $cachedExtraIds = [];

    /**
     * Get all extras
     *
     * @param bool $active_only Whether to return only active extras
     * @return array
     */
    public static function getAllExtras(bool $active_only = false): array {
        $context = $active_only ? 'active' : 'all';

        if (self::$allExtrasPrimed[$context]) {
            return self::$allExtrasCache[$context];
        }

        $cache_key = $context;
        $cached    = self::cacheGet($cache_key);

        if (is_array($cached)) {
            self::$allExtrasCache[$context]  = $cached;
            self::$allExtrasPrimed[$context] = true;

            return $cached;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_extras';

        if ($active_only) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM `{$table_name}` WHERE is_active = %d ORDER BY name ASC",
                1
            ));
        } else {
            $results = $wpdb->get_results(
                "SELECT * FROM `{$table_name}` ORDER BY name ASC"
            );
        }

        $results = $results ?: [];

        self::$allExtrasCache[$context]  = $results;
        self::$allExtrasPrimed[$context] = true;
        self::cacheSet($cache_key, $results);

        return $results;
    }

    /**
     * Get extra by ID
     *
     * @param int $id Extra ID
     * @return object|null
     */
    public static function getExtra(int $id): ?object {
        if (isset(self::$extrasById[$id])) {
            return self::$extrasById[$id];
        }

        $cache_key = self::buildExtraCacheKey($id);
        $cached    = self::cacheGet($cache_key);

        if (is_object($cached)) {
            self::rememberExtraCache($id, $cached);

            return $cached;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_extras';
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));

        if (!$result) {
            return null;
        }

        self::rememberExtraCache($id, $result);

        return $result;
    }

    /**
     * Create a new extra
     *
     * @param array $data Extra data
     * @return int|false Extra ID or false on failure
     */
    public static function createExtra(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_extras';
        
        $defaults = [
            'name' => '',
            'description' => '',
            'price' => 0.00,
            'billing_type' => 'per_person',
            'tax_class' => '',
            'is_required' => 0,
            'max_quantity' => 1,
            'is_active' => 1
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $table_name,
            [
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_textarea_field($data['description']),
                'price' => floatval($data['price']),
                'billing_type' => in_array($data['billing_type'], ['per_person', 'per_booking']) ? $data['billing_type'] : 'per_person',
                'tax_class' => sanitize_text_field($data['tax_class']),
                'is_required' => absint($data['is_required']),
                'max_quantity' => absint($data['max_quantity']),
                'is_active' => absint($data['is_active'])
            ],
            [
                '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d'
            ]
        );

        if (!$result) {
            return false;
        }

        $insert_id = (int) $wpdb->insert_id;

        self::flushAllCaches($insert_id);

        return $insert_id;
    }

    /**
     * Update an extra
     *
     * @param int $id Extra ID
     * @param array $data Extra data
     * @return bool Success
     */
    public static function updateExtra(int $id, array $data): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_extras';
        
        $update_data = [];
        $format = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $format[] = '%s';
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        
        if (isset($data['price'])) {
            $update_data['price'] = floatval($data['price']);
            $format[] = '%f';
        }
        
        if (isset($data['billing_type'])) {
            $update_data['billing_type'] = in_array($data['billing_type'], ['per_person', 'per_booking']) ? $data['billing_type'] : 'per_person';
            $format[] = '%s';
        }
        
        if (isset($data['tax_class'])) {
            $update_data['tax_class'] = sanitize_text_field($data['tax_class']);
            $format[] = '%s';
        }
        
        if (isset($data['is_required'])) {
            $update_data['is_required'] = absint($data['is_required']);
            $format[] = '%d';
        }
        
        if (isset($data['max_quantity'])) {
            $update_data['max_quantity'] = absint($data['max_quantity']);
            $format[] = '%d';
        }
        
        if (isset($data['is_active'])) {
            $update_data['is_active'] = absint($data['is_active']);
            $format[] = '%d';
        }
        
        if (empty($update_data)) {
            return false;
        }
        
        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            $format,
            ['%d']
        );

        if ($result === false) {
            return false;
        }

        self::flushAllCaches($id);

        return true;
    }

    /**
     * Delete an extra
     *
     * @param int $id Extra ID
     * @return bool Success
     */
    public static function deleteExtra(int $id): bool {
        global $wpdb;

        // Check if extra is associated with any products
        $table_product_extras = $wpdb->prefix . 'fp_product_extras';
        $associations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_product_extras WHERE extra_id = %d",
            $id
        ));
        
        if ($associations > 0) {
            return false; // Cannot delete if in use
        }
        
        $table_name = $wpdb->prefix . 'fp_extras';
        $result = $wpdb->delete(
            $table_name,
            ['id' => $id],
            ['%d']
        );

        if ($result === false) {
            return false;
        }

        self::flushAllCaches($id);

        return true;
    }

    /**
     * Persist a single extra object in local and object caches.
     *
     * @param int    $id    Extra ID.
     * @param object $extra Extra payload.
     * @return void
     */
    private static function rememberExtraCache(int $id, object $extra): void {
        self::$extrasById[$id]      = $extra;
        self::$cachedExtraIds[$id] = true;

        self::cacheSet(self::buildExtraCacheKey($id), $extra);
    }

    /**
     * Flush cached extras after data mutations.
     *
     * @param int|null $id Optional extra ID to invalidate.
     * @return void
     */
    private static function flushAllCaches(?int $id = null): void {
        foreach (['all', 'active'] as $context) {
            self::$allExtrasCache[$context]  = [];
            self::$allExtrasPrimed[$context] = false;
            self::cacheDelete($context);
        }

        if ($id === null) {
            foreach (array_keys(self::$cachedExtraIds) as $cached_id) {
                self::cacheDelete(self::buildExtraCacheKey($cached_id));
            }

            self::$extrasById     = [];
            self::$cachedExtraIds = [];

            return;
        }

        unset(self::$extrasById[$id], self::$cachedExtraIds[$id]);
        self::cacheDelete(self::buildExtraCacheKey($id));
    }

    /**
     * Generate the cache key for a single extra record.
     *
     * @param int $id Extra ID.
     * @return string
     */
    private static function buildExtraCacheKey(int $id): string {
        return 'extra_' . $id;
    }

    /**
     * Read a value from the persistent object cache when available.
     *
     * @param string $key Cache key.
     * @return mixed|null
     */
    private static function cacheGet(string $key)
    {
        if (!function_exists('wp_cache_get')) {
            return null;
        }

        $value = wp_cache_get($key, self::CACHE_GROUP);

        if ($value === false) {
            return null;
        }

        return $value;
    }

    /**
     * Store a value in the persistent object cache when available.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Cached value.
     * @return void
     */
    private static function cacheSet(string $key, $value): void
    {
        if (!function_exists('wp_cache_set')) {
            return;
        }

        wp_cache_set($key, $value, self::CACHE_GROUP, self::CACHE_TTL);
    }

    /**
     * Remove an entry from the object cache when available.
     *
     * @param string $key Cache key.
     * @return void
     */
    private static function cacheDelete(string $key): void
    {
        if (!function_exists('wp_cache_delete')) {
            return;
        }

        wp_cache_delete($key, self::CACHE_GROUP);
    }

    /**
     * Get extras for a product
     *
     * @param int $product_id Product ID
     * @param bool $active_only Whether to return only active extras
     * @return array
     */
    public static function getProductExtras(int $product_id, bool $active_only = true): array {
        global $wpdb;
        
        $table_extras = $wpdb->prefix . 'fp_extras';
        $table_product_extras = $wpdb->prefix . 'fp_product_extras';
        
        $where_clause = $active_only ? "AND e.is_active = 1" : "";
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT e.*, pe.sort_order
            FROM $table_extras e
            INNER JOIN $table_product_extras pe ON e.id = pe.extra_id
            WHERE pe.product_id = %d $where_clause
            ORDER BY pe.sort_order ASC, e.name ASC
        ", $product_id));
        
        return $results ?: [];
    }

    /**
     * Associate extra with product
     *
     * @param int $product_id Product ID
     * @param int $extra_id Extra ID
     * @param int $sort_order Sort order
     * @return bool Success
     */
    public static function associateExtraWithProduct(int $product_id, int $extra_id, int $sort_order = 0): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->replace(
            $table_name,
            [
                'product_id' => $product_id,
                'extra_id' => $extra_id,
                'sort_order' => $sort_order
            ],
            ['%d', '%d', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Remove extra from product
     *
     * @param int $product_id Product ID
     * @param int $extra_id Extra ID
     * @return bool Success
     */
    public static function removeExtraFromProduct(int $product_id, int $extra_id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_product_extras';
        
        $result = $wpdb->delete(
            $table_name,
            [
                'product_id' => $product_id,
                'extra_id' => $extra_id
            ],
            ['%d', '%d']
        );
        
        return $result !== false;
    }

    /**
     * Update product extras associations
     *
     * @param int $product_id Product ID
     * @param array $extra_ids Array of extra IDs
     * @return bool Success
     */
    public static function updateProductExtras(int $product_id, array $extra_ids): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_product_extras';
        
        // First remove all existing associations
        $wpdb->delete($table_name, ['product_id' => $product_id], ['%d']);
        
        // Then add new associations
        $success = true;
        foreach ($extra_ids as $index => $extra_id) {
            $result = self::associateExtraWithProduct($product_id, $extra_id, $index);
            if (!$result) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Get available tax classes
     *
     * @return array
     */
    public static function getTaxClasses(): array {
        $tax_classes = [];
        $tax_classes[''] = __('Standard', 'fp-esperienze');
        
        if (class_exists('WC_Tax')) {
            $classes = \WC_Tax::get_tax_classes();
            foreach ($classes as $class) {
                $tax_classes[sanitize_title($class)] = $class;
            }
        }
        
        return $tax_classes;
    }
}
