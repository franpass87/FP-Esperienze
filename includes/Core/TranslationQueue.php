<?php
/**
 * Translation Queue
 *
 * Manages queued translation jobs for strings and posts.
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

use FP\Esperienze\Admin\Settings\AutoTranslateSettings;

defined('ABSPATH') || exit;

/**
 * Handles queueing of translation jobs and cron processing.
 */
class TranslationQueue {
    /**
     * Custom post type used to store queued jobs.
     */
    private const POST_TYPE = 'fp_es_translation_job';

    /**
     * Meta keys for job data.
     */
    private const META_TYPE    = '_fp_es_job_type';
    private const META_KEY     = '_fp_es_string_key';
    private const META_TEXT    = '_fp_es_string_text';
    private const META_POST_ID = '_fp_es_post_id';
    private const META_LANG    = '_fp_es_lang';

    /**
     * Cron hook name.
     */
    public const CRON_HOOK = 'fp_es_process_translation_queue';

    /**
     * Initialize hooks.
     */
    public static function init(): void {
        add_action('init', [self::class, 'registerPostType']);
        add_action(self::CRON_HOOK, [self::class, 'processQueue']);
        add_action('save_post', [self::class, 'queuePost'], 10, 2);

        // Ensure the cron event is scheduled.
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Register internal post type for queued jobs.
     */
    public static function registerPostType(): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        register_post_type(
            self::POST_TYPE,
            [
                'public'              => false,
                'show_ui'             => false,
                'rewrite'             => false,
                'supports'            => ['custom-fields'],
                'label'               => 'FP Translation Job',
                'capability_type'     => 'post',
            ]
        );
    }

    /**
     * Queue a string for translation.
     *
     * @param string $key   Unique string key.
     * @param string $text  Text to translate.
     * @param string $lang  Target language.
     */
    public static function addString(string $key, string $text, string $lang): void {
        wp_insert_post(
            [
                'post_type'   => self::POST_TYPE,
                'post_status' => 'draft',
                'post_title'  => 'String: ' . $key,
                'meta_input'  => [
                    self::META_TYPE => 'string',
                    self::META_KEY  => $key,
                    self::META_TEXT => $text,
                    self::META_LANG => $lang,
                ],
            ]
        );
    }

    /**
     * Queue a post for translation.
     *
     * @param int $post_id Post ID.
     */
    public static function addPost(int $post_id): void {
        wp_insert_post(
            [
                'post_type'   => self::POST_TYPE,
                'post_status' => 'draft',
                'post_title'  => 'Post: ' . $post_id,
                'meta_input'  => [
                    self::META_TYPE    => 'post',
                    self::META_POST_ID => $post_id,
                ],
            ]
        );
    }

    /**
     * Handle post save action to queue for translation.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function queuePost(int $post_id, $post): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        if ($post->post_status === 'auto-draft' || $post->post_type === 'revision') {
            return;
        }

        self::addPost($post_id);
    }

    /**
     * Process queued translation jobs.
     */
    public static function processQueue(): void {
        $jobs = get_posts(
            [
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'draft',
                'posts_per_page' => 5,
                'fields'         => 'ids',
            ]
        );

        if (empty($jobs)) {
            return;
        }

        foreach ($jobs as $job_id) {
            $type = get_post_meta($job_id, self::META_TYPE, true);

            if ($type === 'string') {
                self::processStringJob($job_id);
            } elseif ($type === 'post') {
                self::processPostJob($job_id);
            }

            // Remove job from queue.
            wp_delete_post($job_id, true);
        }
    }

    /**
     * Process a queued string translation job.
     *
     * @param int $job_id Job post ID.
     */
    private static function processStringJob(int $job_id): void {
        $key  = get_post_meta($job_id, self::META_KEY, true);
        $text = get_post_meta($job_id, self::META_TEXT, true);

        if ($key === '' || $text === '') {
            return;
        }

        // Call I18nManager to handle translation/registration.
        $translated = I18nManager::translateString($text, $key, false);
        if ($translated === $text) {
            TranslationLogger::log(
                'TranslationQueue string job untranslated',
                [
                    'key'  => $key,
                    'job'  => $job_id,
                    'lang' => get_post_meta($job_id, self::META_LANG, true),
                ]
            );
        }
    }

    /**
     * Process a queued post translation job.
     *
     * @param int $job_id Job post ID.
     */
    private static function processPostJob(int $job_id): void {
        $post_id = (int) get_post_meta($job_id, self::META_POST_ID, true);
        if ($post_id <= 0) {
            return;
        }

        $available       = I18nManager::getAvailableLanguages();
        $target_langs    = (array) get_option(AutoTranslateSettings::OPTION_TARGET_LANGUAGES, []);
        $languages       = !empty($target_langs) ? array_values(array_intersect($available, $target_langs)) : $available;

        foreach ($languages as $lang) {
            if (function_exists('wpml_tm_create_post_job')) {
                wpml_tm_create_post_job($post_id, $lang);
            }
        }
    }
}

