<?php
/**
 * Meeting Point Manager
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use FP\Esperienze\Core\I18nManager;

defined('ABSPATH') || exit;

/**
 * Meeting Point Manager class for CRUD operations
 */
class MeetingPointManager {

    private const CACHE_GROUP = 'fp_esperienze_meeting_points';
    private const CACHE_TTL   = 600; // 10 minutes

    /**
     * Cached meeting points keyed by ID for fast reuse within a request.
     *
     * @var array<int, object>
     */
    private static array $singleCache = [];

    /**
     * Track which meeting point IDs have been cached so invalidation can clear them.
     *
     * @var array<int, bool>
     */
    private static array $cachedIds = [];

    /**
     * Cached collections of meeting points keyed by translation context.
     *
     * @var array<string, array<int, object>>
     */
    private static array $listCache = [
        'translated' => [],
        'raw'        => [],
    ];

    /**
     * Flags indicating whether the list cache has been primed for a context.
     *
     * @var array<string, bool>
     */
    private static array $listPrimed = [
        'translated' => false,
        'raw'        => false,
    ];

    /**
     * Get all meeting points
     *
     * @param bool $translate Whether to return translated versions
     * @return array
     */
    public static function getAllMeetingPoints(bool $translate = true): array {
        $context = $translate ? 'translated' : 'raw';

        if (self::$listPrimed[$context]) {
            return self::$listCache[$context];
        }

        $cache_key = self::buildListCacheKey($translate);
        $cached    = self::cacheGet($cache_key);

        if (is_array($cached)) {
            if ($context === 'raw') {
                foreach ($cached as $meeting_point) {
                    if (is_object($meeting_point) && isset($meeting_point->id)) {
                        $id = (int) $meeting_point->id;
                        self::$singleCache[$id] = $meeting_point;
                        self::$cachedIds[$id]   = true;
                    }
                }
            }

            self::$listCache[$context]  = $cached;
            self::$listPrimed[$context] = true;

            return $cached;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $results = $wpdb->get_results(
            // Query contains no external variables, so prepare() is unnecessary.
            "SELECT * FROM {$table_name} ORDER BY name ASC"
        );

        if (!$results) {
            self::$listCache[$context]  = [];
            self::$listPrimed[$context] = true;
            self::cacheSet($cache_key, []);

            return [];
        }

        if ($translate && I18nManager::isMultilingualActive()) {
            $results = array_map(static function($meeting_point) {
                return I18nManager::getTranslatedMeetingPoint(clone $meeting_point);
            }, $results);
        }

        if (!$translate) {
            foreach ($results as $meeting_point) {
                if (is_object($meeting_point) && isset($meeting_point->id)) {
                    self::rememberMeetingPoint((int) $meeting_point->id, $meeting_point);
                }
            }
        }

        self::$listCache[$context]  = $results;
        self::$listPrimed[$context] = true;
        self::cacheSet($cache_key, $results);

        return $results;
    }

    /**
     * Get meeting point by ID
     *
     * @param int $id Meeting point ID
     * @param bool $translate Whether to return translated version
     * @return object|null
     */
    public static function getMeetingPoint(int $id, bool $translate = true): ?object {
        if (isset(self::$singleCache[$id])) {
            $result = self::$singleCache[$id];

            if ($translate && I18nManager::isMultilingualActive()) {
                return I18nManager::getTranslatedMeetingPoint(clone $result);
            }

            return $result;
        }

        $cache_key = self::buildSingleCacheKey($id);
        $cached    = self::cacheGet($cache_key);

        if (is_object($cached)) {
            self::rememberMeetingPoint($id, $cached);

            if ($translate && I18nManager::isMultilingualActive()) {
                return I18nManager::getTranslatedMeetingPoint(clone $cached);
            }

            return $cached;
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $result     = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $id
        ));

        if (!$result) {
            return null;
        }

        self::rememberMeetingPoint($id, $result);

        if ($translate && I18nManager::isMultilingualActive()) {
            return I18nManager::getTranslatedMeetingPoint(clone $result);
        }

        return $result;
    }

    /**
     * Get meeting points assigned to a specific product through its schedules.
     *
     * Attempts to load the distinct meeting point IDs referenced by the product
     * schedules and resolve them to full meeting point records. When no meeting
     * points are associated with the product (or the referenced meeting points
     * no longer exist) it falls back to returning the complete meeting point
     * list.
     *
     * @param int  $product_id Product ID.
     * @param bool $translate  Whether to return translated versions of the meeting points.
     * @return array<int, object>
     */
    public static function getMeetingPointsForProduct(int $product_id, bool $translate = true): array {
        if ($product_id <= 0) {
            return [];
        }

        global $wpdb;

        $table_schedules = $wpdb->prefix . 'fp_schedules';

        $meeting_point_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT meeting_point_id FROM {$table_schedules} WHERE product_id = %d AND meeting_point_id IS NOT NULL",
            $product_id
        ));

        if (empty($meeting_point_ids)) {
            $all_meeting_points = self::getAllMeetingPoints($translate);

            return $all_meeting_points ?: [];
        }

        $meeting_points = [];

        foreach ($meeting_point_ids as $meeting_point_id) {
            $meeting_point_id = (int) $meeting_point_id;

            if ($meeting_point_id <= 0) {
                continue;
            }

            $meeting_point = self::getMeetingPoint($meeting_point_id, $translate);

            if ($meeting_point !== null) {
                $meeting_points[$meeting_point_id] = $meeting_point;
            }
        }

        if (!empty($meeting_points)) {
            return array_values($meeting_points);
        }

        $all_meeting_points = self::getAllMeetingPoints($translate);

        return $all_meeting_points ?: [];
    }

    /**
     * Create a new meeting point
     *
     * @param array $data Meeting point data
     * @return int|false Meeting point ID on success, false on failure
     */
    public static function createMeetingPoint(array $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        
        $defaults = [
            'name' => '',
            'address' => '',
            'lat' => null,
            'lng' => null,
            'place_id' => null,
            'note' => ''
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'address' => sanitize_textarea_field($data['address'])
        ];
        $insert_formats = ['%s', '%s'];

        if (array_key_exists('lat', $data) && is_numeric($data['lat'])) {
            $insert_data['lat'] = (float) $data['lat'];
            $insert_formats[] = '%f';
        }

        if (array_key_exists('lng', $data) && is_numeric($data['lng'])) {
            $insert_data['lng'] = (float) $data['lng'];
            $insert_formats[] = '%f';
        }

        $insert_data['place_id'] = ($data['place_id'] !== null && $data['place_id'] !== '')
            ? sanitize_text_field($data['place_id'])
            : null;
        $insert_formats[] = '%s';

        $insert_data['note'] = sanitize_textarea_field($data['note']);
        $insert_formats[] = '%s';

        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $insert_formats
        );
        
        if (!$result) {
            return false;
        }

        $meeting_point_id = (int) $wpdb->insert_id;

        self::flushCaches();

        // Fire hook for translation registration
        do_action('fp_meeting_point_created', $meeting_point_id);

        return $meeting_point_id;
    }

    /**
     * Update a meeting point
     *
     * @param int $id Meeting point ID
     * @param array $data Meeting point data
     * @return bool
     */
    public static function updateMeetingPoint(int $id, array $data): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        
        $update_data = [];
        $formats = [];
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
            $formats[] = '%s';
        }
        
        if (isset($data['address'])) {
            $update_data['address'] = sanitize_textarea_field($data['address']);
            $formats[] = '%s';
        }
        
        $clear_lat = false;
        $clear_lng = false;

        if (array_key_exists('lat', $data)) {
            if ($data['lat'] === '' || $data['lat'] === null) {
                $clear_lat = true;
            } elseif (is_numeric($data['lat'])) {
                $update_data['lat'] = (float) $data['lat'];
                $formats[] = '%f';
            }
        }

        if (array_key_exists('lng', $data)) {
            if ($data['lng'] === '' || $data['lng'] === null) {
                $clear_lng = true;
            } elseif (is_numeric($data['lng'])) {
                $update_data['lng'] = (float) $data['lng'];
                $formats[] = '%f';
            }
        }
        
        if (isset($data['place_id'])) {
            $update_data['place_id'] = $data['place_id'] ? sanitize_text_field($data['place_id']) : null;
            $formats[] = '%s';
        }
        
        if (isset($data['note'])) {
            $update_data['note'] = sanitize_textarea_field($data['note']);
            $formats[] = '%s';
        }
        
        if (empty($update_data) && !$clear_lat && !$clear_lng) {
            return false;
        }

        $updated = false;

        if (!empty($update_data)) {
            $result = $wpdb->update(
                $table_name,
                $update_data,
                ['id' => $id],
                $formats,
                ['%d']
            );

            if ($result === false) {
                return false;
            }

            $updated = true;
        }

        if ($clear_lat || $clear_lng) {
            $set_null_clauses = [];

            if ($clear_lat) {
                $set_null_clauses[] = 'lat = NULL';
            }

            if ($clear_lng) {
                $set_null_clauses[] = 'lng = NULL';
            }

            $null_query = sprintf(
                'UPDATE %s SET %s WHERE id = %%d',
                $table_name,
                implode(', ', $set_null_clauses)
            );

            $null_result = $wpdb->query($wpdb->prepare($null_query, $id));

            if ($null_result === false) {
                return false;
            }

            $updated = true;
        }

        if ($updated) {
            self::flushCaches($id);

            // Fire hook for translation registration
            do_action('fp_meeting_point_updated', $id);
        }

        return $updated;
    }

    /**
     * Delete a meeting point
     *
     * @param int $id Meeting point ID
     * @return bool
     */
    public static function deleteMeetingPoint(int $id): bool {
        global $wpdb;
        
        // Check if meeting point is used in schedules
        $table_schedules = $wpdb->prefix . 'fp_schedules';
        $used_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_schedules WHERE meeting_point_id = %d",
            $id
        ));
        
        if ($used_count > 0) {
            return false; // Cannot delete if in use
        }
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);

        if ($result === false) {
            return false;
        }

        self::flushCaches($id);

        return true;
    }

    /**
     * Remember a meeting point in the runtime and persistent cache layers.
     *
     * @param int    $id            Meeting point ID.
     * @param object $meeting_point Meeting point record.
     * @return void
     */
    private static function rememberMeetingPoint(int $id, object $meeting_point): void {
        self::$singleCache[$id] = $meeting_point;
        self::$cachedIds[$id]   = true;

        self::cacheSet(self::buildSingleCacheKey($id), $meeting_point);
    }

    /**
     * Flush cached meeting point data after mutations.
     *
     * @param int|null $id Optional meeting point ID to invalidate.
     * @return void
     */
    private static function flushCaches(?int $id = null): void {
        foreach (['translated', 'raw'] as $context) {
            self::$listCache[$context]  = [];
            self::$listPrimed[$context] = false;
            self::cacheDelete(self::buildListCacheKey($context === 'translated'));
        }

        if ($id === null) {
            foreach (array_keys(self::$cachedIds) as $cached_id) {
                self::cacheDelete(self::buildSingleCacheKey($cached_id));
            }

            self::$singleCache = [];
            self::$cachedIds   = [];

            return;
        }

        unset(self::$singleCache[$id], self::$cachedIds[$id]);
        self::cacheDelete(self::buildSingleCacheKey($id));
    }

    /**
     * Build the cache key for a single meeting point record.
     *
     * @param int $id Meeting point ID.
     * @return string
     */
    private static function buildSingleCacheKey(int $id): string {
        return 'meeting_point_' . $id;
    }

    /**
     * Build the cache key for meeting point collections.
     *
     * @param bool $translate Whether the cached set is translated.
     * @return string
     */
    private static function buildListCacheKey(bool $translate): string {
        return $translate ? 'meeting_points_translated' : 'meeting_points_raw';
    }

    /**
     * Retrieve a value from the object cache when available.
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
     * Store a value in the object cache when available.
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
     * Remove a value from the object cache when available.
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
     * Get meeting points for select dropdown
     *
     * @return array
     */
    public static function getMeetingPointsForSelect(): array {
        $options = [];
        $meeting_points = self::getAllMeetingPoints();

        foreach ($meeting_points as $meeting_point) {
            $options[$meeting_point->id] = $meeting_point->name;
        }

        return $options;
    }

    /**
     * Check if a meeting point exists
     *
     * @param int $id Meeting point ID
     * @return bool
     */
    public static function meetingPointExists(int $id): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE id = %d",
            $id
        ));
        
        return $exists > 0;
    }
}
