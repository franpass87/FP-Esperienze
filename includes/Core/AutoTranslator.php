<?php
/**
 * Auto Translator using LibreTranslate
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Provides automatic translation with caching
 */
class AutoTranslator {

    /**
     * Translate text using LibreTranslate with caching
     *
     * @param string $text   Text to translate
     * @param string $target Target language code
     * @param string $source Source language code, defaults to 'auto'
     *
     * @return string Translated text or original text on failure
     */
    public static function translate(string $text, string $target, string $source = 'auto'): string {
        $cache_key = 'fp_tr_' . md5($text . '|' . $target);
        $cached    = get_transient($cache_key);
        if (false !== $cached) {
            return (string) $cached;
        }

        $endpoint = get_option('fp_lt_endpoint', 'https://libretranslate.de/translate');
        $endpoint = apply_filters('fp_es_auto_translator_endpoint', $endpoint);

        $body = [
            'q'      => $text,
            'source' => $source,
            'target' => $target,
            'format' => 'text',
        ];

        $api_key = get_option('fp_lt_api_key');
        if (!empty($api_key)) {
            $body['api_key'] = $api_key;
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            TranslationLogger::log('AutoTranslator request error: ' . $response->get_error_message());
            return $text;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            TranslationLogger::log('AutoTranslator HTTP status ' . $code);
            return $text;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['translatedText'])) {
            TranslationLogger::log('AutoTranslator invalid response: ' . wp_remote_retrieve_body($response));
            return $text;
        }

        $translated = (string) $data['translatedText'];
        $cache_ttl = (int) get_option('fp_lt_cache_ttl', WEEK_IN_SECONDS);
        set_transient($cache_key, $translated, $cache_ttl);

        return $translated;
    }
}
