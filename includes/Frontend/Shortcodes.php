<?php
/**
 * Shortcodes
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

use FP\Esperienze\Admin\Settings\AutoTranslateSettings;
use FP\Esperienze\Data\Availability;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Core\I18nManager;
use FP\Esperienze\Data\ScheduleManager;

defined('ABSPATH') || exit;

/**
 * Shortcodes class
 */
class Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('fp_exp_archive', [$this, 'experienceArchive']);
        add_shortcode('wcefp_experiences', [$this, 'experienceArchive']); // Alternative name for compatibility
        add_shortcode('fp_event_archive', [$this, 'eventArchive']); // New shortcode for events
    }

    /**
     * Experience archive shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function experienceArchive(array $atts = []): string {
        $atts = shortcode_atts([
            'posts_per_page' => 12,
            'per_page'       => 12, // Alias for posts_per_page 
            'columns'        => 3,
            'orderby'        => 'date',
            'order_by'       => '', // New parameter
            'order'          => 'DESC',
            'filters'        => '', // New parameter: mp,lang,duration,date
        ], $atts, 'fp_exp_archive');

        // Handle per_page alias
        if (!empty($atts['per_page'])) {
            $atts['posts_per_page'] = $atts['per_page'];
        }

        // Handle order_by alias
        if (!empty($atts['order_by'])) {
            $atts['orderby'] = $atts['order_by'];
        }

        // Parse enabled filters
        $enabled_filters = !empty($atts['filters']) ? 
            array_map('trim', explode(',', $atts['filters'])) : [];

        // Handle pagination
        $paged = 1;
        if (isset($_GET['paged'])) {
            $paged = max(1, absint(wp_unslash($_GET['paged'])));
        }

        // Build query args
        $args = $this->buildArchiveQuery($atts, $paged);
        
        // Apply filters from GET parameters
        $args = $this->applyFilters($args, $enabled_filters);

        // Set global flag to indicate we're in shortcode context
        if (!defined('DOING_FP_SHORTCODE')) {
            define('DOING_FP_SHORTCODE', true);
        }

        $products = new \WP_Query($args);
        $total_products = $products->found_posts;
        $max_pages = $products->max_num_pages;

        ob_start();
        ?>
        <div class="fp-experience-archive <?php echo esc_attr('columns-' . intval($atts['columns'])); ?>" 
             data-total="<?php echo esc_attr($total_products); ?>">
            
            <?php if (!empty($enabled_filters)) : ?>
                <?php $this->renderFilters($enabled_filters); ?>
            <?php endif; ?>

            <div class="fp-experience-results" aria-live="polite">
                <?php if (!$products->have_posts()) : ?>
                    <p class="fp-no-results"><?php esc_html_e('No experiences found.', 'fp-esperienze'); ?></p>
                <?php else : ?>
                    <div class="fp-experience-grid">
                        <?php while ($products->have_posts()) : $products->the_post(); ?>
                            <?php $this->renderExperienceCard(get_the_ID()); ?>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($max_pages > 1) : ?>
                        <?php $this->renderPagination($paged, $max_pages); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        
        // Reset the global flag when shortcode is done
        if (defined('DOING_FP_SHORTCODE')) {
            // Note: We can't undefine constants in PHP, but this check helps with nested shortcodes
            // The constant will be reset on next page load
        }
        
        return ob_get_clean();
    }

    /**
     * Event archive shortcode - for fixed-date events
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function eventArchive(array $atts = []): string {
        $atts = shortcode_atts([
            'posts_per_page' => 12,
            'per_page'       => 12, // Alias for posts_per_page 
            'columns'        => 3,
            'orderby'        => 'meta_value', // Order by event date by default
            'order_by'       => '', // New parameter
            'order'          => 'ASC', // Chronological order for events
            'filters'        => '', // New parameter: mp,lang,duration,date_range
            'future_only'    => 'yes', // Show only future events by default
        ], $atts, 'fp_event_archive');

        // Handle per_page alias
        if (!empty($atts['per_page'])) {
            $atts['posts_per_page'] = $atts['per_page'];
        }

        // Handle order_by alias
        if (!empty($atts['order_by'])) {
            $atts['orderby'] = $atts['order_by'];
        }

        // Parse enabled filters
        $enabled_filters = !empty($atts['filters']) ? 
            array_map('trim', explode(',', $atts['filters'])) : [];

        // Handle pagination
        $paged = 1;
        if (isset($_GET['paged'])) {
            $paged = max(1, absint(wp_unslash($_GET['paged'])));
        }

        // Build query args specifically for events
        $args = $this->buildEventArchiveQuery($atts, $paged);
        
        // Apply filters from GET parameters
        $args = $this->applyEventFilters($args, $enabled_filters, $atts);

        // Set global flag to indicate we're in shortcode context
        if (!defined('DOING_FP_SHORTCODE')) {
            define('DOING_FP_SHORTCODE', true);
        }

        $products = new \WP_Query($args);
        $total_products = $products->found_posts;
        $max_pages = $products->max_num_pages;

        ob_start();
        ?>
        <div class="fp-event-archive <?php echo esc_attr('columns-' . intval($atts['columns'])); ?>" 
             data-total="<?php echo esc_attr($total_products); ?>">
            
            <?php if (!empty($enabled_filters)) : ?>
                <?php $this->renderEventFilters($enabled_filters); ?>
            <?php endif; ?>

            <div class="fp-event-results" aria-live="polite">
                <?php if (!$products->have_posts()) : ?>
                    <p class="fp-no-results"><?php esc_html_e('No events found.', 'fp-esperienze'); ?></p>
                <?php else : ?>
                    <div class="fp-event-grid">
                        <?php while ($products->have_posts()) : $products->the_post(); ?>
                            <?php $this->renderEventCard(get_the_ID()); ?>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($max_pages > 1) : ?>
                        <?php $this->renderPagination($paged, $max_pages); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();
        
        return ob_get_clean();
    }

    /**
     * Build query arguments for archive
     *
     * @param array $atts Shortcode attributes
     * @param int $paged Page number
     * @return array
     */
    private function buildArchiveQuery(array $atts, int $paged): array {
        $orderby = sanitize_text_field($atts['orderby']);
        $order = strtoupper(sanitize_text_field($atts['order']));

        // Handle custom order by fields
        switch ($orderby) {
            case 'name':
                $orderby = 'title';
                break;
            case 'price':
                $orderby = 'meta_value_num';
                $meta_key = '_fp_exp_adult_price';
                break;
            case 'duration':
                // Duration sorting is not available without product meta; fallback to title
                $orderby = 'title';
                break;
        }

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => intval($atts['posts_per_page']),
            'paged'          => $paged,
            'orderby'        => $orderby,
            'order'          => $order,
            'meta_query'     => [
                [
                    'key'     => '_product_type',
                    'value'   => 'experience',
                    'compare' => '='
                ]
            ]
        ];

        // Add meta key for meta-based sorting
        if (isset($meta_key)) {
            $args['meta_key'] = $meta_key;
        }

        // Apply language filtering hook for multilingual plugins
        $args = apply_filters('fp_experience_archive_query_args', $args);

        return $args;
    }

    /**
     * Apply filters to query
     *
     * @param array $args Query arguments
     * @param array $enabled_filters Enabled filters
     * @return array
     */
    private function applyFilters(array $args, array $enabled_filters): array {
        if (empty($enabled_filters)) {
            return $args;
        }

        // Language filter
        if (in_array('lang', $enabled_filters) && !empty($_GET['fp_lang'])) {
            $language = sanitize_text_field(wp_unslash($_GET['fp_lang']));
            $args['meta_query'][] = [
                'key'     => '_fp_exp_langs',
                'value'   => $language,
                'compare' => 'LIKE'
            ];
        }

        global $wpdb;

        // Meeting point filter using schedules table
        if (in_array('mp', $enabled_filters) && !empty($_GET['fp_mp'])) {
            $meeting_point_id = absint(wp_unslash($_GET['fp_mp']));
            $product_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT product_id FROM {$wpdb->prefix}fp_schedules WHERE meeting_point_id = %d",
                $meeting_point_id
            ));
            if (!empty($product_ids)) {
                $args['post__in'] = !empty($args['post__in']) ? array_intersect($args['post__in'], $product_ids) : $product_ids;
            } else {
                $args['post__in'] = [0];
            }
        }

        // Duration filter using schedules table
        if (in_array('duration', $enabled_filters) && !empty($_GET['fp_duration'])) {
            $duration_range = sanitize_text_field(wp_unslash($_GET['fp_duration']));
            $query = '';
            switch ($duration_range) {
                case '<=90':
                    $query = $wpdb->prepare(
                        "SELECT DISTINCT product_id FROM {$wpdb->prefix}fp_schedules WHERE duration_min <= %d",
                        90
                    );
                    break;
                case '91-180':
                    $query = $wpdb->prepare(
                        "SELECT DISTINCT product_id FROM {$wpdb->prefix}fp_schedules WHERE duration_min BETWEEN %d AND %d",
        91,
        180
                    );
                    break;
                case '>180':
                    $query = $wpdb->prepare(
                        "SELECT DISTINCT product_id FROM {$wpdb->prefix}fp_schedules WHERE duration_min > %d",
                        180
                    );
                    break;
            }

            if ($query) {
                $product_ids = $wpdb->get_col($query);
                if (!empty($product_ids)) {
                    $args['post__in'] = !empty($args['post__in']) ? array_intersect($args['post__in'], $product_ids) : $product_ids;
                } else {
                    $args['post__in'] = [0];
                }
            }
        }

        // Date availability filter
        if (in_array('date', $enabled_filters) && !empty($_GET['fp_date'])) {
            $date = sanitize_text_field(wp_unslash($_GET['fp_date']));
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                // Get products with availability on this date
                $available_products = $this->getAvailableProductsForDate($date);
                if (!empty($available_products)) {
                    if (!empty($args['post__in'])) {
                        $args['post__in'] = array_intersect($args['post__in'], $available_products);
                        if (empty($args['post__in'])) {
                            $args['post__in'] = [0];
                        }
                    } else {
                        $args['post__in'] = $available_products;
                    }
                } else {
                    // No products available, return empty result
                    $args['post__in'] = [0];
                }
            }
        }

        return $args;
    }

    /**
     * Get products with availability for a specific date
     *
     * @param string $date Date in Y-m-d format
     * @return array Array of product IDs
     */
    private function getAvailableProductsForDate(string $date): array {
        // Use shared cache key that matches CacheManager pattern
        $cache_key = 'fp_available_products_' . $date;
        $cached_result = get_transient($cache_key);
        
        if ($cached_result !== false) {
            return $cached_result;
        }

        $available_products = [];
        $posts_per_page    = 50;
        $paged             = 1;
        $batch_count       = 0;

        while (true) {
            $query = new \WP_Query([
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $posts_per_page,
                'paged'          => $paged,
                'fields'         => 'ids',
                'meta_query'     => [
                    [
                        'key'     => '_product_type',
                        'value'   => 'experience',
                        'compare' => '='
                    ]
                ],
                'no_found_rows'           => true,
                'update_post_meta_cache'  => false,
                'update_post_term_cache'  => false,
            ]);

            $products = $query->posts;

            if (empty($products)) {
                break;
            }

            foreach ($products as $product_id) {
                // Use the cached availability check from Availability::forDay
                $slots = Availability::forDay($product_id, $date);

                if (!empty($slots)) {
                    // Check if any slot has availability
                    foreach ($slots as $slot) {
                        if ($slot['is_available'] && $slot['available'] > 0) {
                            $available_products[] = $product_id;
                            break;
                        }
                    }
                }
            }

            $batch_count++;

            if (count($products) < $posts_per_page) {
                break;
            }

            $paged++;
        }

        $ttl = $batch_count === 0 ? 5 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS;
        set_transient($cache_key, $available_products, $ttl);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[FP_Esperienze] Processed %d batch(es) for %s', $batch_count, $date));
        }

        return $available_products;
    }

    /**
     * Render filters section
     *
     * @param array $enabled_filters Enabled filters
     */
    private function renderFilters(array $enabled_filters): void {
        ?>
        <div class="fp-archive-filters">
            <form method="get" class="fp-filters-form" id="fp-filters-form">
                <div class="fp-filters-grid">
                    
                    <?php if (in_array('mp', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_mp"><?php esc_html_e('Meeting Point', 'fp-esperienze'); ?></label>
                            <select name="fp_mp" id="fp_mp" class="fp-filter-select" aria-label="<?php esc_attr_e('Filter by meeting point', 'fp-esperienze'); ?>">
                                <option value=""><?php esc_html_e('All locations', 'fp-esperienze'); ?></option>
                                <?php
                                $meeting_points = MeetingPointManager::getAllMeetingPoints();
                                foreach ($meeting_points as $mp) {
                                    $selected = isset($_GET['fp_mp']) && absint($_GET['fp_mp']) == $mp->id ? 'selected' : '';
                                    echo '<option value="' . esc_attr($mp->id) . '" ' . $selected . '>' . esc_html($mp->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('lang', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_lang"><?php esc_html_e('Language', 'fp-esperienze'); ?></label>
                            <select name="fp_lang" id="fp_lang" class="fp-filter-select" aria-label="<?php esc_attr_e('Filter by language', 'fp-esperienze'); ?>">
                                <option value=""><?php esc_html_e('All languages', 'fp-esperienze'); ?></option>
                                <?php
                                $languages = $this->getAvailableLanguages();
                                foreach ($languages as $lang) {
                                    $selected = isset($_GET['fp_lang']) && sanitize_text_field($_GET['fp_lang']) == $lang ? 'selected' : '';
                                    echo '<option value="' . esc_attr($lang) . '" ' . $selected . '>' . esc_html($lang) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('duration', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_duration"><?php esc_html_e('Duration', 'fp-esperienze'); ?></label>
                            <select name="fp_duration" id="fp_duration" class="fp-filter-select" aria-label="<?php esc_attr_e('Filter by duration', 'fp-esperienze'); ?>">
                                <option value=""><?php esc_html_e('Any duration', 'fp-esperienze'); ?></option>
                                <option value="<=90" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '<=90'); ?>><?php esc_html_e('Up to 1.5 hours', 'fp-esperienze'); ?></option>
                                <option value="91-180" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '91-180'); ?>><?php esc_html_e('1.5 - 3 hours', 'fp-esperienze'); ?></option>
                <option value=">180" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '>180'); ?>><?php esc_html_e('More than 3 hours', 'fp-esperienze'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('date', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_date"><?php esc_html_e('Available on', 'fp-esperienze'); ?></label>
                            <input type="date" name="fp_date" id="fp_date" class="fp-filter-date" 
                                   value="<?php echo esc_attr(isset($_GET['fp_date']) ? sanitize_text_field($_GET['fp_date']) : ''); ?>"
                                   min="<?php echo esc_attr(date('Y-m-d')); ?>"
                                   aria-label="<?php esc_attr_e('Filter by available date', 'fp-esperienze'); ?>">
                        </div>
                    <?php endif; ?>

                </div>
                
                <div class="fp-filters-actions">
                    <button type="submit" class="fp-btn fp-btn-primary fp-filter-apply">
                        <?php esc_html_e('Apply Filters', 'fp-esperienze'); ?>
                    </button>
                    <a href="?" class="fp-btn fp-btn-secondary fp-filter-reset">
                        <?php esc_html_e('Clear', 'fp-esperienze'); ?>
                    </a>
                </div>

                <!-- Preserve other query parameters -->
                <?php foreach ($_GET as $key => $value) : ?>
                    <?php if (!in_array($key, ['fp_mp', 'fp_lang', 'fp_duration', 'fp_date', 'paged'])) : ?>
                        <input type="hidden" name="<?php echo esc_attr(sanitize_key($key)); ?>" value="<?php echo esc_attr(sanitize_text_field($value)); ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </form>
        </div>
        <?php
    }

    /**
     * Get available languages from experience products
     * Uses multilingual plugin when available, falls back to product meta
     *
     * @return array
     */
    private function getAvailableLanguages(): array {
        // Use multilingual plugin languages if available
        if (I18nManager::isMultilingualActive()) {
            $available_languages = I18nManager::getAvailableLanguages();
            $selected            = (array) get_option(AutoTranslateSettings::OPTION_TARGET_LANGUAGES, []);
            if (!empty($selected)) {
                $available_languages = array_values(array_intersect($available_languages, $selected));
            }
            if (!empty($available_languages)) {
                return $available_languages;
            }
        }

        // Fallback to experience product meta (for legacy setups)
        global $wpdb;

        $results = $wpdb->get_col("
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->postmeta} pt ON pm.post_id = pt.post_id
            WHERE pm.meta_key = '_fp_exp_langs' 
            AND pt.meta_key = '_product_type' 
            AND pt.meta_value = 'experience'
            AND pm.meta_value != ''
        ");

        $languages = [];
        foreach ($results as $lang_string) {
            $langs = array_map('trim', explode(',', $lang_string));
            $languages = array_merge($languages, $langs);
        }

        $languages = array_unique(array_filter($languages));
        $selected  = (array) get_option(AutoTranslateSettings::OPTION_TARGET_LANGUAGES, []);
        if (!empty($selected)) {
            $languages = array_values(array_intersect($languages, $selected));
        }
        return $languages;
    }

    /**
     * Render pagination
     *
     * @param int $current_page Current page
     * @param int $max_pages Maximum pages
     */
    private function renderPagination(int $current_page, int $max_pages): void {
        if ($max_pages <= 1) {
            return;
        }

        ?>
        <nav class="fp-archive-pagination" aria-label="<?php esc_attr_e('Archive pagination', 'fp-esperienze'); ?>">
            <ul class="fp-pagination-list">
                
                <?php if ($current_page > 1) : ?>
                    <li class="fp-pagination-item">
                        <a href="<?php echo esc_url($this->getPaginationUrl($current_page - 1)); ?>" class="fp-pagination-link fp-pagination-prev">
                            <?php esc_html_e('Previous', 'fp-esperienze'); ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $max_pages; $i++) : ?>
                    <?php if ($i == $current_page) : ?>
                        <li class="fp-pagination-item fp-pagination-current">
                            <span class="fp-pagination-link"><?php echo esc_html($i); ?></span>
                        </li>
                    <?php elseif ($i <= 3 || $i > $max_pages - 3 || abs($i - $current_page) <= 2) : ?>
                        <li class="fp-pagination-item">
                            <a href="<?php echo esc_url($this->getPaginationUrl($i)); ?>" class="fp-pagination-link">
                                <?php echo esc_html($i); ?>
                            </a>
                        </li>
                    <?php elseif ($i == 4 || $i == $max_pages - 3) : ?>
                        <li class="fp-pagination-item fp-pagination-dots">
                            <span class="fp-pagination-link">...</span>
                        </li>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $max_pages) : ?>
                    <li class="fp-pagination-item">
                        <a href="<?php echo esc_url($this->getPaginationUrl($current_page + 1)); ?>" class="fp-pagination-link fp-pagination-next">
                            <?php esc_html_e('Next', 'fp-esperienze'); ?>
                        </a>
                    </li>
                <?php endif; ?>

            </ul>
        </nav>
        <?php
    }

    /**
     * Generate pagination URL
     *
     * @param int $page Page number
     * @return string
     */
    private function getPaginationUrl(int $page): string {
        $current_url = add_query_arg(null, null);
        $url = add_query_arg('paged', $page, $current_url);
        
        // Apply language filtering hook for multilingual plugins
        return apply_filters('fp_archive_pagination_url', $url);
    }

    /**
     * Render experience card
     *
     * @param int $product_id Product ID
     */
    private function renderExperienceCard(int $product_id): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();

        // Derive data from schedules
        $schedules = ScheduleManager::getSchedules($product_id);
        $first_schedule = $schedules[0] ?? null;
        $duration = $first_schedule->duration_min ?? null;
        $adult_price = $first_schedule->price_adult ?? null;
        $language_codes = [];
        if ( $schedules ) {
            foreach ( $schedules as $schedule ) {
                if ( empty( $schedule->lang ) ) {
                    continue;
                }

                $lang_parts = array_map( 'trim', explode( ',', (string) $schedule->lang ) );
                foreach ( $lang_parts as $lang_code ) {
                    if ( '' === $lang_code ) {
                        continue;
                    }

                    $normalized_code = strtolower( $lang_code );
                    if ( ! isset( $language_codes[ $normalized_code ] ) ) {
                        $language_codes[ $normalized_code ] = $lang_code;
                    }
                }
            }
        }

        $language_codes = array_values( $language_codes );
        $languages_label = '';

        if ( ! empty( $language_codes ) ) {
            $labels = array_map(
                static function ( $code ) {
                    $code = trim( (string) $code );
                    $code = str_replace( '_', '-', $code );

                    return strtoupper( $code );
                },
                $language_codes
            );

            $languages_label = implode( ', ', $labels );
        }

        ?>
        <div class="fp-experience-card" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="fp-experience-image">
                <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($product->get_name()); ?>" 
                         loading="lazy" />
                </a>
                <?php if ( $duration || ! empty( $language_codes ) ) : ?>
                    <div class="fp-experience-badge">
                        <?php if ( $duration ) : ?>
                            <span class="fp-experience-duration">
                                <?php printf( esc_html__( '%d min', 'fp-esperienze' ), intval( $duration ) ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( ! empty( $language_codes ) ) : ?>
                            <?php
                            $languages_aria_label = '';
                            if ( $languages_label ) {
                                /* translators: %s: comma separated list of language codes. */
                                $languages_aria_label = sprintf( esc_html__( 'Languages: %s', 'fp-esperienze' ), $languages_label );
                            }
                            ?>
                            <span class="fp-experience-languages" role="img" <?php echo $languages_aria_label ? 'aria-label="' . esc_attr( $languages_aria_label ) . '"' : ''; ?>>
                                <?php foreach ( $language_codes as $language_code ) : ?>
                                    <?php
                                    $language_label = strtoupper( trim( (string) $language_code ) );
                                    $language_asset = $this->getLanguageAsset( $language_code );
                                    $language_classes = [ 'fp-experience-language' ];

                                    if ( $language_asset === $language_label ) {
                                        $language_classes[] = 'fp-experience-language--text';
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr( implode( ' ', $language_classes ) ); ?>" title="<?php echo esc_attr( $language_label ); ?>" aria-hidden="true">
                                        <?php echo esc_html( $language_asset ); ?>
                                    </span>
                                <?php endforeach; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="fp-experience-content">
                <h3 class="fp-experience-title">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h3>
                
                <div class="fp-experience-excerpt">
                    <?php echo wp_kses_post(wp_trim_words($product->get_short_description(), 20)); ?>
                </div>
                
                <div class="fp-experience-meta">
                    <?php if ($adult_price !== null) : ?>
                        <div class="fp-experience-price">
                            <?php printf( esc_html__( 'From %s', 'fp-esperienze' ), wp_kses_post( wc_price( (float) $adult_price ) ) ); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="fp-experience-actions">
                        <a href="<?php echo esc_url(get_permalink($product_id)); ?>" 
                           class="fp-btn fp-btn-primary fp-details-btn"
                           data-item-id="<?php echo esc_attr($product_id); ?>"
                           data-item-name="<?php echo esc_attr($product->get_name()); ?>">
                            <?php esc_html_e('Dettagli', 'fp-esperienze'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Convert a language code into the corresponding icon/emoji asset.
     *
     * Provides a graceful textual fallback when the language code is not
     * mapped to a dedicated asset.
     *
     * @param string $language_code Language code coming from schedules.
     * @return string
     */
    private function getLanguageAsset( string $language_code ): string {
        $normalized = strtolower( trim( $language_code ) );
        $normalized = str_replace( '_', '-', $normalized );

        $map = [
            'ar'      => '🇸🇦',
            'bg'      => '🇧🇬',
            'br'      => '🇧🇷',
            'cs'      => '🇨🇿',
            'cz'      => '🇨🇿',
            'da'      => '🇩🇰',
            'de'      => '🇩🇪',
            'el'      => '🇬🇷',
            'en'      => '🇬🇧',
            'en-au'   => '🇦🇺',
            'en-ca'   => '🇨🇦',
            'en-gb'   => '🇬🇧',
            'en-nz'   => '🇳🇿',
            'en-us'   => '🇺🇸',
            'en-za'   => '🇿🇦',
            'es'      => '🇪🇸',
            'es-es'   => '🇪🇸',
            'es-mx'   => '🇲🇽',
            'fi'      => '🇫🇮',
            'fr'      => '🇫🇷',
            'fr-ca'   => '🇨🇦',
            'fr-fr'   => '🇫🇷',
            'gb'      => '🇬🇧',
            'he'      => '🇮🇱',
            'hk'      => '🇭🇰',
            'hr'      => '🇭🇷',
            'hu'      => '🇭🇺',
            'id'      => '🇮🇩',
            'is'      => '🇮🇸',
            'it'      => '🇮🇹',
            'it-it'   => '🇮🇹',
            'ja'      => '🇯🇵',
            'jp'      => '🇯🇵',
            'ko'      => '🇰🇷',
            'kr'      => '🇰🇷',
            'nb'      => '🇳🇴',
            'nl'      => '🇳🇱',
            'no'      => '🇳🇴',
            'pl'      => '🇵🇱',
            'pt'      => '🇵🇹',
            'pt-br'   => '🇧🇷',
            'pt-pt'   => '🇵🇹',
            'ro'      => '🇷🇴',
            'ru'      => '🇷🇺',
            'ru-ru'   => '🇷🇺',
            'se'      => '🇸🇪',
            'sk'      => '🇸🇰',
            'sl'      => '🇸🇮',
            'sv'      => '🇸🇪',
            'th'      => '🇹🇭',
            'tr'      => '🇹🇷',
            'tw'      => '🇹🇼',
            'ua'      => '🇺🇦',
            'uk'      => '🇺🇦',
            'us'      => '🇺🇸',
            'vi'      => '🇻🇳',
            'zh'      => '🇨🇳',
            'zh-cn'   => '🇨🇳',
            'zh-hans' => '🇨🇳',
            'zh-hant' => '🇹🇼',
            'zh-hk'   => '🇭🇰',
            'zh-tw'   => '🇹🇼',
        ];

        if ( isset( $map[ $normalized ] ) ) {
            return $map[ $normalized ];
        }

        if ( str_contains( $normalized, '-' ) ) {
            $primary_parts = explode( '-', $normalized );
            $primary       = $primary_parts[0];

            if ( isset( $map[ $primary ] ) ) {
                return $map[ $primary ];
            }
        }

        $fallback = str_replace( '_', '-', trim( (string) $language_code ) );
        if ( '' === $fallback ) {
            $fallback = $normalized;
        }

        if ( function_exists( 'mb_strtoupper' ) ) {
            $fallback = mb_strtoupper( $fallback );
        } else {
            $fallback = strtoupper( $fallback );
        }

        return '' !== $fallback ? $fallback : '--';
    }

    /**
     * Build query arguments for event archive
     *
     * @param array $atts Shortcode attributes
     * @param int $paged Page number
     * @return array
     */
    private function buildEventArchiveQuery(array $atts, int $paged): array {
        global $wpdb;
        
        // Get product IDs that have event schedules
        $event_product_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT product_id FROM {$wpdb->prefix}fp_schedules 
             WHERE schedule_type = 'fixed' AND is_active = 1"
        ));
        
        if (empty($event_product_ids)) {
            $event_product_ids = [0]; // No events found
        }

        $orderby = sanitize_text_field($atts['orderby']);
        $order = strtoupper(sanitize_text_field($atts['order']));

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => intval($atts['posts_per_page']),
            'paged'          => $paged,
            'orderby'        => 'post__in', // Default to preserving order from schedule query
            'order'          => $order,
            'post__in'       => $event_product_ids,
            'meta_query'     => [
                [
                    'key'     => '_product_type',
                    'value'   => 'experience',
                    'compare' => '='
                ],
                [
                    'key'     => '_fp_experience_type',
                    'value'   => 'event',
                    'compare' => '='
                ]
            ]
        ];

        // Handle different ordering options
        if ($orderby === 'event_date' || $orderby === 'meta_value') {
            // Order by earliest event date
            $ordered_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT s.product_id 
                 FROM {$wpdb->prefix}fp_schedules s
                 WHERE s.schedule_type = 'fixed' 
                 AND s.is_active = 1 
                 AND s.product_id IN (" . implode(',', array_map('absint', $event_product_ids)) . ")
                 ORDER BY s.event_date {$order}, s.start_time ASC"
            ));
            
            if (!empty($ordered_ids)) {
                $args['post__in'] = $ordered_ids;
                $args['orderby'] = 'post__in';
            }
        } elseif ($orderby === 'title') {
            $args['orderby'] = 'title';
        }

        // Apply language filtering hook for multilingual plugins
        $args = apply_filters('fp_event_archive_query_args', $args);

        return $args;
    }

    /**
     * Apply filters to event query
     *
     * @param array $args Query arguments
     * @param array $enabled_filters Enabled filters
     * @param array $atts Shortcode attributes
     * @return array
     */
    private function applyEventFilters(array $args, array $enabled_filters, array $atts): array {
        global $wpdb;
        
        if (empty($enabled_filters)) {
            return $args;
        }

        $schedule_filters = [];
        
        // Date range filter
        if (in_array('date_range', $enabled_filters)) {
            if (!empty($_GET['fp_date_from'])) {
                $date_from = sanitize_text_field(wp_unslash($_GET['fp_date_from']));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
                    $schedule_filters[] = $wpdb->prepare("event_date >= %s", $date_from);
                }
            }
            
            if (!empty($_GET['fp_date_to'])) {
                $date_to = sanitize_text_field(wp_unslash($_GET['fp_date_to']));
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
                    $schedule_filters[] = $wpdb->prepare("event_date <= %s", $date_to);
                }
            }
        }
        
        // Future only filter
        if ($atts['future_only'] === 'yes') {
            $schedule_filters[] = $wpdb->prepare("event_date >= %s", date('Y-m-d'));
        }

        // Meeting point filter
        if (in_array('mp', $enabled_filters) && !empty($_GET['fp_mp'])) {
            $meeting_point_id = absint(wp_unslash($_GET['fp_mp']));
            $schedule_filters[] = $wpdb->prepare("meeting_point_id = %d", $meeting_point_id);
        }

        // Language filter
        if (in_array('lang', $enabled_filters) && !empty($_GET['fp_lang'])) {
            $language = sanitize_text_field(wp_unslash($_GET['fp_lang']));
            $schedule_filters[] = $wpdb->prepare("lang = %s", $language);
        }

        // Duration filter
        if (in_array('duration', $enabled_filters) && !empty($_GET['fp_duration'])) {
            $duration_range = sanitize_text_field(wp_unslash($_GET['fp_duration']));
            switch ($duration_range) {
                case '<=90':
                    $schedule_filters[] = "duration_min <= 90";
                    break;
                case '91-180':
                    $schedule_filters[] = "duration_min BETWEEN 91 AND 180";
                    break;
                case '>180':
                    $schedule_filters[] = "duration_min > 180";
                    break;
            }
        }

        // Apply schedule filters if any
        if (!empty($schedule_filters)) {
            $where_clause = "WHERE schedule_type = 'fixed' AND is_active = 1";
            if (!empty($schedule_filters)) {
                $where_clause .= " AND " . implode(" AND ", $schedule_filters);
            }
            
            $filtered_product_ids = $wpdb->get_col(
                "SELECT DISTINCT product_id FROM {$wpdb->prefix}fp_schedules {$where_clause}"
            );
            
            if (!empty($filtered_product_ids)) {
                $args['post__in'] = !empty($args['post__in']) ? 
                    array_intersect($args['post__in'], $filtered_product_ids) : 
                    $filtered_product_ids;
            } else {
                $args['post__in'] = [0]; // No matching events
            }
        }

        return $args;
    }

    /**
     * Render filters section for events
     *
     * @param array $enabled_filters Enabled filters
     */
    private function renderEventFilters(array $enabled_filters): void {
        ?>
        <div class="fp-event-filters">
            <form method="get" class="fp-filters-form" id="fp-event-filters-form">
                <div class="fp-filters-grid">
                    
                    <?php if (in_array('date_range', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_date_from"><?php esc_html_e('From Date', 'fp-esperienze'); ?></label>
                            <input type="date" name="fp_date_from" id="fp_date_from" 
                                   value="<?php echo esc_attr(isset($_GET['fp_date_from']) ? sanitize_text_field($_GET['fp_date_from']) : ''); ?>"
                                   min="<?php echo esc_attr(date('Y-m-d')); ?>">
                        </div>
                        <div class="fp-filter-group">
                            <label for="fp_date_to"><?php esc_html_e('To Date', 'fp-esperienze'); ?></label>
                            <input type="date" name="fp_date_to" id="fp_date_to" 
                                   value="<?php echo esc_attr(isset($_GET['fp_date_to']) ? sanitize_text_field($_GET['fp_date_to']) : ''); ?>"
                                   min="<?php echo esc_attr(date('Y-m-d')); ?>">
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('mp', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_mp"><?php esc_html_e('Meeting Point', 'fp-esperienze'); ?></label>
                            <select name="fp_mp" id="fp_mp" class="fp-filter-select">
                                <option value=""><?php esc_html_e('All locations', 'fp-esperienze'); ?></option>
                                <?php
                                $meeting_points = MeetingPointManager::getAllMeetingPoints();
                                foreach ($meeting_points as $mp) {
                                    $selected = isset($_GET['fp_mp']) && absint($_GET['fp_mp']) == $mp->id ? 'selected' : '';
                                    echo '<option value="' . esc_attr($mp->id) . '" ' . $selected . '>' . esc_html($mp->name) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('lang', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_lang"><?php esc_html_e('Language', 'fp-esperienze'); ?></label>
                            <select name="fp_lang" id="fp_lang" class="fp-filter-select">
                                <option value=""><?php esc_html_e('All languages', 'fp-esperienze'); ?></option>
                                <?php
                                $languages = $this->getAvailableLanguages();
                                foreach ($languages as $lang) {
                                    $selected = isset($_GET['fp_lang']) && sanitize_text_field($_GET['fp_lang']) == $lang ? 'selected' : '';
                                    echo '<option value="' . esc_attr($lang) . '" ' . $selected . '>' . esc_html($lang) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('duration', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_duration"><?php esc_html_e('Duration', 'fp-esperienze'); ?></label>
                            <select name="fp_duration" id="fp_duration" class="fp-filter-select">
                                <option value=""><?php esc_html_e('Any duration', 'fp-esperienze'); ?></option>
                                <option value="<=90" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '<=90'); ?>><?php esc_html_e('Up to 1.5 hours', 'fp-esperienze'); ?></option>
                                <option value="91-180" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '91-180'); ?>><?php esc_html_e('1.5 - 3 hours', 'fp-esperienze'); ?></option>
                                <option value=">180" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '>180'); ?>><?php esc_html_e('More than 3 hours', 'fp-esperienze'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>
                
                <div class="fp-filters-actions">
                    <button type="submit" class="fp-btn fp-btn-primary fp-filter-apply">
                        <?php esc_html_e('Apply Filters', 'fp-esperienze'); ?>
                    </button>
                    <a href="?" class="fp-btn fp-btn-secondary fp-filter-reset">
                        <?php esc_html_e('Clear', 'fp-esperienze'); ?>
                    </a>
                </div>

                <!-- Preserve other query parameters -->
                <?php foreach ($_GET as $key => $value) : ?>
                    <?php if (!in_array($key, ['fp_mp', 'fp_lang', 'fp_duration', 'fp_date_from', 'fp_date_to', 'paged'])) : ?>
                        <input type="hidden" name="<?php echo esc_attr(sanitize_key($key)); ?>" value="<?php echo esc_attr(sanitize_text_field($value)); ?>">
                    <?php endif; ?>
                <?php endforeach; ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render event card with event-specific styling
     *
     * @param int $product_id Product ID
     */
    private function renderEventCard(int $product_id): void {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : wc_placeholder_img_src();

        // Get event schedules to display dates
        $event_schedules = ScheduleManager::getEventSchedules($product_id);
        $first_schedule = $event_schedules[0] ?? null;
        
        // Get upcoming events only
        $upcoming_events = array_filter($event_schedules, function($schedule) {
            return strtotime($schedule->event_date) >= strtotime('today');
        });
        
        // Sort by date
        usort($upcoming_events, function($a, $b) {
            return strcmp($a->event_date, $b->event_date);
        });
        
        $next_event = $upcoming_events[0] ?? null;
        $duration = $first_schedule->duration_min ?? null;
        $adult_price = $first_schedule->price_adult ?? null;
        $languages = '';
        
        if ($event_schedules) {
            $lang_list = array_unique(array_filter(array_map(static function ($s) {
                return $s->lang;
            }, $event_schedules)));
            $languages = implode(', ', $lang_list);
        }

        ?>
        <div class="fp-event-card" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="fp-event-image">
                <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($product->get_name()); ?>" 
                         loading="lazy" />
                </a>
                
                <?php if ($next_event) : ?>
                    <div class="fp-event-date-badge">
                        <div class="fp-event-month"><?php echo esc_html(date_i18n('M', strtotime($next_event->event_date))); ?></div>
                        <div class="fp-event-day"><?php echo esc_html(date_i18n('j', strtotime($next_event->event_date))); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($duration) : ?>
                    <div class="fp-event-duration">
                        <?php printf( esc_html__( '%d min', 'fp-esperienze' ), intval( $duration ) ); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="fp-event-content">
                <h3 class="fp-event-title">
                    <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                        <?php echo esc_html($product->get_name()); ?>
                    </a>
                </h3>
                
                <?php if ($next_event) : ?>
                    <div class="fp-event-next-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php 
                        printf(
                            esc_html__('Next: %s at %s', 'fp-esperienze'),
                            esc_html(date_i18n(get_option('date_format'), strtotime($next_event->event_date))),
                            esc_html(date_i18n(get_option('time_format'), strtotime($next_event->start_time)))
                        );
                        ?>
                        <?php if (count($upcoming_events) > 1) : ?>
                            <span class="fp-event-count">
                                <?php printf( esc_html__( '(+%d more)', 'fp-esperienze' ), count($upcoming_events) - 1 ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <div class="fp-event-excerpt">
                    <?php echo wp_kses_post(wp_trim_words($product->get_short_description(), 20)); ?>
                </div>
                
                <?php if ($languages) : ?>
                    <div class="fp-event-languages">
                        <?php
                        $lang_list = array_map('trim', explode(',', $languages));
                        foreach ($lang_list as $lang) {
                            echo '<span class="fp-language-chip">' . esc_html($lang) . '</span>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="fp-event-meta">
                    <?php if ($adult_price) : ?>
                        <div class="fp-event-price">
                            <?php printf( esc_html__( 'From %s', 'fp-esperienze' ), wp_kses_post( wc_price( $adult_price ) ) ); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="fp-event-actions">
                        <a href="<?php echo esc_url(get_permalink($product_id)); ?>" 
                           class="fp-btn fp-btn-primary fp-details-btn"
                           data-item-id="<?php echo esc_attr($product_id); ?>"
                           data-item-name="<?php echo esc_attr($product->get_name()); ?>">
                            <?php esc_html_e('View Dates', 'fp-esperienze'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}