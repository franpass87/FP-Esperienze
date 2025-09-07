<?php
/**
 * WP-CLI commands for FP Esperienze.
 *
 * @package FP\Esperienze\CLI
 */

namespace FP\Esperienze\CLI;

use FP\Esperienze\Admin\Settings\AutoTranslateSettings;
use FP\Esperienze\Core\I18nManager;
use FP\Esperienze\Data\MeetingPointManager;
use WP_CLI;
use WP_CLI_Command;

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Translation related commands.
 */
class TranslateCommand extends WP_CLI_Command {
    /**
     * Queue plugin content for translation.
     *
     * ## EXAMPLES
     *
     *     wp fp-esperienze translate
     */
    public function translate($args, $assoc_args): void {
        $available = I18nManager::getAvailableLanguages();
        $selected  = (array) get_option(AutoTranslateSettings::OPTION_TARGET_LANGUAGES, []);
        $languages = !empty($selected) ? array_values(array_intersect($available, $selected)) : $available;

        // Process experience products.
        $experience_ids = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => '_product_type',
                    'value' => 'experience',
                ],
            ],
        ]);

        foreach ($experience_ids as $post_id) {
            foreach ($languages as $lang) {
                if (function_exists('wpml_tm_create_post_job')) {
                    wpml_tm_create_post_job($post_id, $lang);
                }
            }
        }

        // Process meeting points.
        $meeting_points = MeetingPointManager::getAllMeetingPoints(false);
        $default_lang   = $languages[0] ?? null;
        if ($default_lang && function_exists('do_action')) {
            do_action('wpml_switch_language', $default_lang); // Switch context for translation registration.
        }

        foreach ($meeting_points as $mp) {
            I18nManager::translateString($mp->name, 'meeting_point_name_' . $mp->id);
            I18nManager::translateString($mp->address, 'meeting_point_address_' . $mp->id);
            if (!empty($mp->note)) {
                I18nManager::translateString($mp->note, 'meeting_point_note_' . $mp->id);
            }
        }

        WP_CLI::success(sprintf(
            'Processed %d experiences and %d meeting points.',
            count($experience_ids),
            count($meeting_points)
        ));
    }
}
