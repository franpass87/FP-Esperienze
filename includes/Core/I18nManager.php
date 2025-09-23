<?php
/**
 * Internationalization Manager
 *
 * Handles WPML and Polylang compatibility for multilingual support
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * I18n Manager class for multilingual plugin support
 */
class I18nManager {

    /**
     * Current multilingual plugin
     *
     * @var string|null 'wpml', 'polylang', or null
     */
    private static $plugin = null;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'detectMultilingualPlugin'], 5);
        add_action('init', [$this, 'initHooks'], 10);
    }

    /**
     * Detect active multilingual plugin
     */
    public function detectMultilingualPlugin(): void {
        if (function_exists('icl_get_languages') || defined('ICL_SITEPRESS_VERSION')) {
            self::$plugin = 'wpml';
        } elseif (function_exists('pll_get_post_language') || function_exists('pll_current_language')) {
            self::$plugin = 'polylang';
        }
    }

    /**
     * Initialize hooks if multilingual plugin is active
     */
    public function initHooks(): void {
        if (!self::$plugin) {
            return;
        }

        // Filter experience queries by current language
        add_filter('fp_experience_archive_query_args', [$this, 'filterArchiveByLanguage']);
        
        // Add language parameter to archive URLs
        add_filter('fp_archive_pagination_url', [$this, 'addLanguageToUrl']);
        
        // Register meeting point translations
        add_action('init', [$this, 'registerMeetingPointTranslations'], 15);
        
        // Handle translated archive page URLs
        add_filter('fp_archive_page_url', [$this, 'getTranslatedArchiveUrl']);
    }

    /**
     * Get current language code
     *
     * @return string|null
     */
    public static function getCurrentLanguage(): ?string {
        switch (self::$plugin) {
            case 'wpml':
                return function_exists('icl_get_current_language') ? icl_get_current_language() : null;
            case 'polylang':
                return function_exists('pll_current_language') ? pll_current_language() : null;
            default:
                return null;
        }
    }

    /**
     * Translate a string using automatic translation with caching
     * and register it for manual review in WPML.
     *
     * @param string $original Original text.
     * @param string $key      Unique string key.
     *
     * @return string Translated text.
     */
    public static function translateString(string $original, string $key, bool $queue = true): string {
        $lang = self::getCurrentLanguage();

        if (empty($lang)) {
            return $original;
        }

        $cache_key = 'fp_i18n_' . md5($key . $lang);
        $cached    = get_transient($cache_key);

        if (false === $cached) {
            if ($queue) {
                TranslationQueue::addString($key, $original, $lang);
                $translated = $original;
            } else {
                $translated = AutoTranslator::translate($original, $lang);
                $ttl        = (int) get_option('fp_lt_cache_ttl', WEEK_IN_SECONDS);
                set_transient($cache_key, $translated, $ttl);

                if ($translated === $original) {
                    TranslationLogger::log(
                        'I18nManager translation unchanged',
                        [
                            'key'   => $key,
                            'text'  => $original,
                            'found' => $translated,
                        ]
                    );
                }
            }
        } else {
            $translated = (string) $cached;
        }

        do_action('wpml_register_single_string', 'fp-esperienze', $key, $original);

        return $translated;
    }

    /**
     * Get available languages
     *
     * @return array
     */
    public static function getAvailableLanguages(): array {
        switch (self::$plugin) {
            case 'wpml':
                if (function_exists('icl_get_languages')) {
                    $languages = icl_get_languages('skip_missing=0');
                    return array_keys($languages);
                }
                break;
            case 'polylang':
                if (function_exists('pll_the_languages')) {
                    $languages = pll_the_languages(['raw' => 1]);
                    return array_keys($languages);
                }
                break;
        }
        return [];
    }

    /**
     * Get post language
     *
     * @param int $post_id Post ID
     * @return string|null
     */
    public static function getPostLanguage(int $post_id): ?string {
        switch (self::$plugin) {
            case 'wpml':
                if (function_exists('wpml_get_language_information')) {
                    $lang_info = wpml_get_language_information(null, $post_id);
                    return $lang_info['language_code'] ?? null;
                }
                break;
            case 'polylang':
                if (function_exists('pll_get_post_language')) {
                    return pll_get_post_language($post_id);
                }
                break;
        }
        return null;
    }

    /**
     * Filter experience archive by current language
     *
     * @param array $args WP_Query arguments
     * @return array
     */
    public function filterArchiveByLanguage(array $args): array {
        $current_language = self::getCurrentLanguage();
        
        if (!$current_language) {
            return $args;
        }

        switch (self::$plugin) {
            case 'wpml':
                // WPML automatically filters posts by language
                // No additional filtering needed
                break;
                
            case 'polylang':
                if (function_exists('pll_get_posts_ids')) {
                    $translated_ids = [];
                    $paged          = 1;

                    do {
                        $query = new \WP_Query([
                            'post_type'              => 'product',
                            'post_status'            => 'publish',
                            'meta_query'             => [
                                [
                                    'key'   => '_product_type',
                                    'value' => 'experience'
                                ]
                            ],
                            'posts_per_page'         => 50,
                            'paged'                  => $paged,
                            'fields'                 => 'ids',
                            'no_found_rows'          => true,
                            'update_post_meta_cache' => false,
                            'update_post_term_cache' => false,
                        ]);

                        if (empty($query->posts)) {
                            break;
                        }

                        foreach ($query->posts as $post_id) {
                            if (pll_get_post_language($post_id) === $current_language) {
                                $translated_ids[] = $post_id;
                            }
                        }

                        $paged++;
                    } while (true);

                    if (!empty($translated_ids)) {
                        $translated_ids   = array_unique($translated_ids);
                        $args['post__in'] = isset($args['post__in'])
                            ? array_intersect($args['post__in'], $translated_ids)
                            : $translated_ids;
                    } else {
                        // No posts in current language, return empty
                        $args['post__in'] = [0];
                    }
                }
                break;
        }

        return $args;
    }

    /**
     * Add language parameter to pagination URLs
     *
     * @param string $url Pagination URL
     * @return string
     */
    public function addLanguageToUrl(string $url): string {
        $current_language = self::getCurrentLanguage();
        
        if (!$current_language) {
            return $url;
        }

        switch (self::$plugin) {
            case 'wpml':
                // WPML handles URL language parameters automatically
                break;
                
            case 'polylang':
                // Add language parameter for Polylang
                $url = add_query_arg('lang', $current_language, $url);
                break;
        }

        return $url;
    }

    /**
     * Register meeting point string translations
     */
    public function registerMeetingPointTranslations(): void {
        if (self::$plugin !== 'wpml') {
            return;
        }

        // Register meeting point strings for WPML String Translation
        add_action('fp_meeting_point_created', [$this, 'registerMeetingPointStrings']);
        add_action('fp_meeting_point_updated', [$this, 'registerMeetingPointStrings']);
    }

    /**
     * Register meeting point strings with WPML String Translation
     *
     * @param int $meeting_point_id Meeting point ID
     */
    public function registerMeetingPointStrings(int $meeting_point_id): void {
        if (self::$plugin !== 'wpml' || !function_exists('icl_register_string')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $meeting_point = $wpdb->get_row($wpdb->prepare(
            "SELECT name, address FROM $table_name WHERE id = %d",
            $meeting_point_id
        ));

        if (!$meeting_point) {
            return;
        }

        // Register name and address for translation
        icl_register_string(
            'fp-esperienze',
            'meeting_point_name_' . $meeting_point_id,
            $meeting_point->name
        );

        icl_register_string(
            'fp-esperienze',
            'meeting_point_address_' . $meeting_point_id,
            $meeting_point->address
        );

        if (!empty($meeting_point->note)) {
            icl_register_string(
                'fp-esperienze',
                'meeting_point_note_' . $meeting_point_id,
                $meeting_point->note
            );
        }
    }

    /**
     * Get translated meeting point data
     *
     * @param object $meeting_point Meeting point object
     * @return object
     */
    public static function getTranslatedMeetingPoint(object $meeting_point): object {
        if (self::$plugin !== 'wpml' || !function_exists('icl_t')) {
            return $meeting_point;
        }

        $current_lang = self::getCurrentLanguage();

        // Get translated strings
        $translated = clone $meeting_point;

        $translated->name = icl_t(
            'fp-esperienze',
            'meeting_point_name_' . $meeting_point->id,
            $meeting_point->name
        );

        do_action(
            'wpml_register_single_string',
            'fp-esperienze',
            'meeting_point_name_' . $meeting_point->id,
            $meeting_point->name
        );

        if ($current_lang && $translated->name === $meeting_point->name) {
            $translated->name = AutoTranslator::translate(
                $meeting_point->name,
                $current_lang
            );
        }

        $translated->address = icl_t(
            'fp-esperienze',
            'meeting_point_address_' . $meeting_point->id,
            $meeting_point->address
        );

        do_action(
            'wpml_register_single_string',
            'fp-esperienze',
            'meeting_point_address_' . $meeting_point->id,
            $meeting_point->address
        );

        if ($current_lang && $translated->address === $meeting_point->address) {
            $translated->address = AutoTranslator::translate(
                $meeting_point->address,
                $current_lang
            );
        }

        if (!empty($meeting_point->note)) {
            $translated->note = icl_t(
                'fp-esperienze',
                'meeting_point_note_' . $meeting_point->id,
                $meeting_point->note
            );

            do_action(
                'wpml_register_single_string',
                'fp-esperienze',
                'meeting_point_note_' . $meeting_point->id,
                $meeting_point->note
            );

            if ($current_lang && $translated->note === $meeting_point->note) {
                $translated->note = AutoTranslator::translate(
                    $meeting_point->note,
                    $current_lang
                );
            }
        }

        return $translated;
    }

    /**
     * Get translated archive page URL
     *
     * @param string $url Original archive URL
     * @return string
     */
    public function getTranslatedArchiveUrl(string $url): string {
        $current_language = self::getCurrentLanguage();
        
        if (!$current_language) {
            return $url;
        }

        // Get archive page ID from settings
        $archive_page_id = get_option('fp_esperienze_archive_page_id');
        
        if (!$archive_page_id) {
            return $url;
        }

        switch (self::$plugin) {
            case 'wpml':
                if (function_exists('icl_object_id')) {
                    $translated_page_id = icl_object_id($archive_page_id, 'page', false, $current_language);
                    if ($translated_page_id && $translated_page_id !== $archive_page_id) {
                        return get_permalink($translated_page_id);
                    }
                }
                break;
                
            case 'polylang':
                if (function_exists('pll_get_post')) {
                    $translated_page_id = pll_get_post($archive_page_id, $current_language);
                    if ($translated_page_id && $translated_page_id !== $archive_page_id) {
                        return get_permalink($translated_page_id);
                    }
                }
                break;
        }

        return $url;
    }

    /**
     * Check if multilingual plugin is active
     *
     * @return bool
     */
    public static function isMultilingualActive(): bool {
        return self::$plugin !== null;
    }

    /**
     * Get active multilingual plugin name
     *
     * @return string|null
     */
    public static function getActivePlugin(): ?string {
        return self::$plugin;
    }
}