<?php
/**
 * Auto Translation Queue
 *
 * Handles deferred translation of strings and posts
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Auto Translation Queue class
 */
class AutoTranslationQueue {

    /**
     * Queue option name
     *
     * @var string
     */
    private static $option_name = 'fp_es_translation_queue';

    /**
     * Cron hook name
     *
     * @var string
     */
    public const CRON_HOOK = 'fp_es_process_translation_queue';

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_action(self::CRON_HOOK, [self::class, 'processQueue']);

        // Schedule cron event if not already scheduled
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }

        // Queue posts when saved
        add_action('save_post', [self::class, 'queuePost'], 10, 2);
    }

    /**
     * Add a string translation job to the queue
     *
     * @param string $key   Unique string key
     * @param string $text  Text to translate
     * @param string $lang  Target language
     */
    public static function addString(string $key, string $text, string $lang): void {
        $queue   = self::getQueue();
        $job_key = md5('string|' . $key . '|' . $lang);

        if (!isset($queue[$job_key])) {
            $queue[$job_key] = [
                'type' => 'string',
                'key'  => $key,
                'text' => $text,
                'lang' => $lang,
            ];
            self::saveQueue($queue);
        }
    }

    /**
     * Add a post translation job to the queue
     *
     * @param int $post_id Post ID
     */
    public static function addPost(int $post_id): void {
        $queue   = self::getQueue();
        $job_key = md5('post|' . $post_id);

        if (!isset($queue[$job_key])) {
            $queue[$job_key] = [
                'type'    => 'post',
                'post_id' => $post_id,
            ];
            self::saveQueue($queue);
        }
    }

    /**
     * Handle post save action
     *
     * @param int      $post_id Post ID
     * @param \WP_Post $post    Post object
     */
    public static function queuePost(int $post_id, $post): void { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
        // Skip auto-drafts and revisions
        if ($post->post_status === 'auto-draft' || $post->post_type === 'revision') {
            return;
        }

        self::addPost($post_id);
    }

    /**
     * Process queued translations
     */
    public static function processQueue(): void {
        $queue = self::getQueue();

        if (empty($queue)) {
            return;
        }

        $batch_size = 5;
        $processed  = 0;

        foreach ($queue as $job_key => $job) {
            if ($processed >= $batch_size) {
                break;
            }

            if ($job['type'] === 'string') {
                self::processStringJob($job);
            } elseif ($job['type'] === 'post') {
                self::processPostJob($job);
            }

            unset($queue[$job_key]);
            $processed++;
        }

        self::saveQueue($queue);
    }

    /**
     * Process a string translation job
     *
     * @param array $job Job data
     */
    private static function processStringJob(array $job): void {
        $translated = AutoTranslator::translate($job['text'], $job['lang']);
        $cache_key  = 'fp_i18n_' . md5($job['key'] . $job['lang']);
        set_transient($cache_key, $translated, WEEK_IN_SECONDS);
    }

    /**
     * Process a post translation job
     *
     * @param array $job Job data
     */
    private static function processPostJob(array $job): void {
        $post = get_post($job['post_id']);
        if (!$post) {
            return;
        }

        $languages = I18nManager::getAvailableLanguages();
        foreach ($languages as $lang) {
            $title_key = 'post_title_' . $job['post_id'];
            $cache_key = 'fp_post_' . md5($title_key . $lang);
            $translated = AutoTranslator::translate($post->post_title, $lang);
            set_transient($cache_key, $translated, WEEK_IN_SECONDS);
        }
    }

    /**
     * Get current queue
     *
     * @return array
     */
    private static function getQueue(): array {
        $queue = get_option(self::$option_name, []);
        if (!is_array($queue)) {
            $queue = [];
        }
        return $queue;
    }

    /**
     * Save queue
     *
     * @param array $queue Queue data
     */
    private static function saveQueue(array $queue): void {
        update_option(self::$option_name, $queue, false);
    }
}
