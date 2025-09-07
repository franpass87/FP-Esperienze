<?php
/**
 * WPML Hooks for automatic translation job creation.
 *
 * @package FP\Esperienze\Data
 */

namespace FP\Esperienze\Data;

use FP\Esperienze\Core\I18nManager;

defined('ABSPATH') || exit;

/**
 * Handles sending posts to WPML on save.
 */
class WPMLHooks {
    /**
     * Constructor.
     */
    public function __construct() {
        add_action('save_post_experience', [$this, 'sendToWpml'], 10, 3);
        add_action('save_post_meeting_point', [$this, 'sendToWpml'], 10, 3);
    }

    /**
     * Send post to WPML translation management.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an existing post being updated.
     */
    public function sendToWpml(int $post_id, \WP_Post $post, bool $update): void {
        // Check if automatic sending is enabled.
        $enabled = (bool) get_option('fp_esperienze_wpml_auto_send', false);
        if (!$enabled) {
            return;
        }

        // Ignore autosaves and revisions.
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Determine available languages.
        $languages = I18nManager::getAvailableLanguages();
        if (empty($languages)) {
            return;
        }

        $source_lang = I18nManager::getPostLanguage($post_id);

        foreach ($languages as $lang) {
            if ($lang === $source_lang) {
                continue;
            }

            do_action('wpml_tm_create_post_job', $post_id, $lang);
        }
    }
}
