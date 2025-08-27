<?php
/**
 * Shortcodes
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

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
        $paged = get_query_var('paged') ? get_query_var('paged') : 1;
        if (isset($_GET['paged'])) {
            $paged = absint($_GET['paged']);
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
                    <p class="fp-no-results"><?php _e('No experiences found.', 'fp-esperienze'); ?></p>
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
                $orderby = 'meta_value_num';
                $meta_key = '_fp_exp_duration';
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
            $language = sanitize_text_field($_GET['fp_lang']);
            $args['meta_query'][] = [
                'key'     => '_fp_exp_langs',
                'value'   => $language,
                'compare' => 'LIKE'
            ];
        }

        // Meeting point filter
        if (in_array('mp', $enabled_filters) && !empty($_GET['fp_mp'])) {
            $meeting_point_id = absint($_GET['fp_mp']);
            $args['meta_query'][] = [
                'key'     => '_fp_exp_meeting_point_id',
                'value'   => $meeting_point_id,
                'compare' => '='
            ];
        }

        // Duration filter
        if (in_array('duration', $enabled_filters) && !empty($_GET['fp_duration'])) {
            $duration_range = sanitize_text_field($_GET['fp_duration']);
            switch ($duration_range) {
                case '<=90':
                    $args['meta_query'][] = [
                        'key'     => '_fp_exp_duration',
                        'value'   => 90,
                        'type'    => 'NUMERIC',
                        'compare' => '<='
                    ];
                    break;
                case '91-180':
                    $args['meta_query'][] = [
                        'key'     => '_fp_exp_duration',
                        'value'   => [91, 180],
                        'type'    => 'NUMERIC',
                        'compare' => 'BETWEEN'
                    ];
                    break;
                case '>180':
                    $args['meta_query'][] = [
                        'key'     => '_fp_exp_duration',
                        'value'   => 180,
                        'type'    => 'NUMERIC',
                        'compare' => '>'
                    ];
                    break;
            }
        }

        // Date availability filter
        if (in_array('date', $enabled_filters) && !empty($_GET['fp_date'])) {
            $date = sanitize_text_field($_GET['fp_date']);
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
        $all_products = get_posts([
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
            ]
        ]);

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
                $slots = \FP\Esperienze\Data\Availability::forDay($product_id, $date);
                
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
                            <label for="fp_mp"><?php _e('Meeting Point', 'fp-esperienze'); ?></label>
                            <select name="fp_mp" id="fp_mp" class="fp-filter-select" aria-label="<?php esc_attr_e('Filter by meeting point', 'fp-esperienze'); ?>">
                                <option value=""><?php _e('All locations', 'fp-esperienze'); ?></option>
                                <?php
                                $meeting_points = \FP\Esperienze\Data\MeetingPointManager::getAllMeetingPoints();
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
                            <label for="fp_lang"><?php _e('Language', 'fp-esperienze'); ?></label>
                            <select name="fp_lang" id="fp_lang" class="fp-filter-select" aria-label="<?php esc_attr_e('Filter by language', 'fp-esperienze'); ?>">
                                <option value=""><?php _e('All languages', 'fp-esperienze'); ?></option>
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
                            <label for="fp_duration"><?php _e('Duration', 'fp-esperienze'); ?></label>
                            <select name="fp_duration" id="fp_duration" class="fp-filter-select" aria-label="<?php esc_attr_e('Filter by duration', 'fp-esperienze'); ?>">
                                <option value=""><?php _e('Any duration', 'fp-esperienze'); ?></option>
                                <option value="<=90" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '<=90'); ?>><?php _e('Up to 1.5 hours', 'fp-esperienze'); ?></option>
                                <option value="91-180" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '91-180'); ?>><?php _e('1.5 - 3 hours', 'fp-esperienze'); ?></option>
                                <option value=">180" <?php selected(isset($_GET['fp_duration']) ? sanitize_text_field($_GET['fp_duration']) : '', '>180'); ?>><?php _e('More than 3 hours', 'fp-esperienze'); ?></option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <?php if (in_array('date', $enabled_filters)) : ?>
                        <div class="fp-filter-group">
                            <label for="fp_date"><?php _e('Available on', 'fp-esperienze'); ?></label>
                            <input type="date" name="fp_date" id="fp_date" class="fp-filter-date" 
                                   value="<?php echo esc_attr(isset($_GET['fp_date']) ? sanitize_text_field($_GET['fp_date']) : ''); ?>"
                                   min="<?php echo esc_attr(date('Y-m-d')); ?>"
                                   aria-label="<?php esc_attr_e('Filter by available date', 'fp-esperienze'); ?>">
                        </div>
                    <?php endif; ?>

                </div>
                
                <div class="fp-filters-actions">
                    <button type="submit" class="fp-btn fp-btn-primary fp-filter-apply">
                        <?php _e('Apply Filters', 'fp-esperienze'); ?>
                    </button>
                    <a href="?" class="fp-btn fp-btn-secondary fp-filter-reset">
                        <?php _e('Clear', 'fp-esperienze'); ?>
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
        if (\FP\Esperienze\Core\I18nManager::isMultilingualActive()) {
            $available_languages = \FP\Esperienze\Core\I18nManager::getAvailableLanguages();
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

        return array_unique(array_filter($languages));
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
                            <?php _e('Previous', 'fp-esperienze'); ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $max_pages; $i++) : ?>
                    <?php if ($i == $current_page) : ?>
                        <li class="fp-pagination-item fp-pagination-current">
                            <span class="fp-pagination-link"><?php echo $i; ?></span>
                        </li>
                    <?php elseif ($i <= 3 || $i > $max_pages - 3 || abs($i - $current_page) <= 2) : ?>
                        <li class="fp-pagination-item">
                            <a href="<?php echo esc_url($this->getPaginationUrl($i)); ?>" class="fp-pagination-link">
                                <?php echo $i; ?>
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
                            <?php _e('Next', 'fp-esperienze'); ?>
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
        $duration = get_post_meta($product_id, '_fp_exp_duration', true) ?: get_post_meta($product_id, '_experience_duration', true);
        $adult_price = get_post_meta($product_id, '_fp_exp_adult_price', true) ?: get_post_meta($product_id, '_experience_adult_price', true);
        $languages = get_post_meta($product_id, '_fp_exp_langs', true) ?: get_post_meta($product_id, '_experience_languages', true);

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
                        <?php printf(__('%d min', 'fp-esperienze'), intval($duration)); ?>
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
                            <?php printf(__('From %s', 'fp-esperienze'), wc_price($adult_price)); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="fp-experience-actions">
                        <a href="<?php echo esc_url(get_permalink($product_id)); ?>" 
                           class="fp-btn fp-btn-primary fp-details-btn"
                           data-item-id="<?php echo esc_attr($product_id); ?>"
                           data-item-name="<?php echo esc_attr($product->get_name()); ?>">
                            <?php _e('Dettagli', 'fp-esperienze'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}