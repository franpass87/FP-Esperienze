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
                    $args['post__in'] = $available_products;
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

        // Get all experience products with optimized query
        $query = new \WP_Query([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => '_product_type',
                    'value'   => 'experience',
                    'compare' => '='
                ]
            ],
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ]);
        
        $all_products = $query->posts;

        if (empty($all_products)) {
            set_transient($cache_key, [], 5 * MINUTE_IN_SECONDS);
            return [];
        }

        $available_products = [];
        
        // Batch process availability checks for better performance
        $batch_size = 10; // Process 10 products at a time
        $total_products = count($all_products);
        
        for ($i = 0; $i < $total_products; $i += $batch_size) {
            $batch = array_slice($all_products, $i, $batch_size);
            
            foreach ($batch as $product_id) {
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
            
            // Small delay between batches to prevent server overload
            if ($i + $batch_size < $total_products) {
                usleep(1000); // 1ms pause
            }
        }

        // Cache for 10 minutes (same as availability cache TTL)
        set_transient($cache_key, $available_products, 10 * MINUTE_IN_SECONDS);
        
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
        $languages = '';
        if ($schedules) {
            $lang_list = array_unique(array_filter(array_map(static function ($s) {
                return $s->lang;
            }, $schedules)));
            $languages = implode(', ', $lang_list);
        }

        ?>
        <div class="fp-experience-card" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="fp-experience-image">
                <a href="<?php echo esc_url(get_permalink($product_id)); ?>">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($product->get_name()); ?>" 
                         loading="lazy" />
                </a>
                <?php if ($duration) : ?>
                    <div class="fp-experience-duration">
                        <?php printf( esc_html__( '%d min', 'fp-esperienze' ), intval( $duration ) ); ?>
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
                
                <?php if ($languages) : ?>
                    <div class="fp-experience-languages">
                        <?php
                        $lang_list = array_map('trim', explode(',', $languages));
                        foreach ($lang_list as $lang) {
                            echo '<span class="fp-language-chip">' . esc_html($lang) . '</span>';
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="fp-experience-meta">
                    <?php if ($adult_price) : ?>
                        <div class="fp-experience-price">
                            <?php printf( esc_html__( 'From %s', 'fp-esperienze' ), wp_kses_post( wc_price( $adult_price ) ) ); ?>
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
}