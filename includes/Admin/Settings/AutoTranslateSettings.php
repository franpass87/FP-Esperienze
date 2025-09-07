<?php
/**
 * Auto Translation Settings.
 *
 * @package FP\Esperienze\Admin\Settings
 */

namespace FP\Esperienze\Admin\Settings;

defined('ABSPATH') || exit;

/**
 * Auto translation settings page.
 */
class AutoTranslateSettings {
    /** Option name for endpoint */
    public const OPTION_ENDPOINT = 'fp_lt_endpoint';

    /** Option name for API key */
    public const OPTION_API_KEY = 'fp_lt_api_key';

    /** Option name for cache TTL */
    public const OPTION_CACHE_TTL = 'fp_lt_cache_ttl';

    /**
     * Constructor.
     */
    public function __construct() {
        add_action('admin_init', [$this, 'registerSettings']);
    }

    /**
     * Register settings and fields.
     */
    public function registerSettings(): void {
        register_setting(
            'fp_lt_settings',
            self::OPTION_ENDPOINT,
            [
                'type'              => 'string',
                'default'           => 'https://libretranslate.de/translate',
                'sanitize_callback' => 'esc_url_raw',
            ]
        );

        register_setting(
            'fp_lt_settings',
            self::OPTION_API_KEY,
            [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ]
        );

        register_setting(
            'fp_lt_settings',
            self::OPTION_CACHE_TTL,
            [
                'type'              => 'integer',
                'default'           => WEEK_IN_SECONDS,
                'sanitize_callback' => 'absint',
            ]
        );

        add_settings_section(
            'fp_lt_section',
            __('Auto Translation', 'fp-esperienze'),
            [$this, 'sectionCallback'],
            'fp_lt_settings'
        );

        add_settings_field(
            self::OPTION_ENDPOINT,
            __('API Endpoint', 'fp-esperienze'),
            [$this, 'endpointField'],
            'fp_lt_settings',
            'fp_lt_section'
        );

        add_settings_field(
            self::OPTION_API_KEY,
            __('API Key', 'fp-esperienze'),
            [$this, 'apiKeyField'],
            'fp_lt_settings',
            'fp_lt_section'
        );

        add_settings_field(
            self::OPTION_CACHE_TTL,
            __('Cache TTL', 'fp-esperienze'),
            [$this, 'cacheTtlField'],
            'fp_lt_settings',
            'fp_lt_section'
        );
    }

    /**
     * Section description.
     */
    public function sectionCallback(): void {
        echo '<p>' . esc_html__('Configure automatic translation service.', 'fp-esperienze') . '</p>';
    }

    /**
     * Endpoint field callback.
     */
    public function endpointField(): void {
        $value = get_option(self::OPTION_ENDPOINT, 'https://libretranslate.de/translate');
        echo '<input type="url" id="' . esc_attr(self::OPTION_ENDPOINT) . '" name="' . esc_attr(self::OPTION_ENDPOINT) . '" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    /**
     * API key field callback.
     */
    public function apiKeyField(): void {
        $value = get_option(self::OPTION_API_KEY, '');
        echo '<input type="text" id="' . esc_attr(self::OPTION_API_KEY) . '" name="' . esc_attr(self::OPTION_API_KEY) . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Optional API key for the translation service.', 'fp-esperienze') . '</p>';
    }

    /**
     * Cache TTL field callback.
     */
    public function cacheTtlField(): void {
        $value = (int) get_option(self::OPTION_CACHE_TTL, WEEK_IN_SECONDS);
        echo '<input type="number" id="' . esc_attr(self::OPTION_CACHE_TTL) . '" name="' . esc_attr(self::OPTION_CACHE_TTL) . '" value="' . esc_attr($value) . '" class="small-text" min="0" />';
        echo '<p class="description">' . esc_html__('Time in seconds to cache translations.', 'fp-esperienze') . '</p>';
    }
}

