<?php
/**
 * Archive Block
 *
 * @package FP\Esperienze\Blocks
 */

namespace FP\Esperienze\Blocks;

use FP\Esperienze\Frontend\Shortcodes;

defined('ABSPATH') || exit;

/**
 * Archive Block class for Gutenberg
 */
class ArchiveBlock {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'registerBlock']);
    }

    /**
     * Register the block
     */
    public function registerBlock(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('fp-esperienze/archive', [
            'render_callback' => [$this, 'renderBlock'],
            'attributes' => [
                'postsPerPage' => [
                    'type' => 'number',
                    'default' => 12
                ],
                'columns' => [
                    'type' => 'number', 
                    'default' => 3
                ],
                'orderBy' => [
                    'type' => 'string',
                    'default' => 'date'
                ],
                'order' => [
                    'type' => 'string',
                    'default' => 'DESC'
                ],
                'filters' => [
                    'type' => 'array',
                    'default' => []
                ],
                'enableLanguageFilter' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'enableMeetingPointFilter' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'enableDurationFilter' => [
                    'type' => 'boolean',
                    'default' => false
                ],
                'enableDateFilter' => [
                    'type' => 'boolean',
                    'default' => false
                ]
            ]
        ]);
    }

    /**
     * Render the block
     *
     * @param array $attributes Block attributes
     * @return string
     */
    public function renderBlock(array $attributes): string {
        // Convert block attributes to shortcode format
        $shortcode_atts = [
            'posts_per_page' => $attributes['postsPerPage'] ?? 12,
            'columns' => $attributes['columns'] ?? 3,
            'orderby' => $attributes['orderBy'] ?? 'date',
            'order' => $attributes['order'] ?? 'DESC'
        ];

        // Build filters string
        $filters = [];
        if (!empty($attributes['enableLanguageFilter'])) {
            $filters[] = 'lang';
        }
        if (!empty($attributes['enableMeetingPointFilter'])) {
            $filters[] = 'mp';
        }
        if (!empty($attributes['enableDurationFilter'])) {
            $filters[] = 'duration';
        }
        if (!empty($attributes['enableDateFilter'])) {
            $filters[] = 'date';
        }

        if (!empty($filters)) {
            $shortcode_atts['filters'] = implode(',', $filters);
        }

        // Use the same shortcode logic for rendering
        $shortcodes = new Shortcodes();
        return $shortcodes->experienceArchive($shortcode_atts);
    }

    /**
     * Enqueue block editor assets
     */
    public function enqueueBlockAssets(): void {
        if (!is_admin()) {
            return;
        }

        wp_enqueue_script(
            'fp-esperienze-archive-block',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/js/archive-block.js',
            ['jquery', 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'],
            FP_ESPERIENZE_VERSION,
            true
        );

        wp_localize_script('fp-esperienze-archive-block', 'fpEsperienzeBlock', [
            'pluginUrl' => FP_ESPERIENZE_PLUGIN_URL,
        ]);
    }
}