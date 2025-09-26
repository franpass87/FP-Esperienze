<?php
/**
 * SEO Settings Admin Page
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

defined('ABSPATH') || exit;

/**
 * SEO settings page
 */
class SEOSettings {
    
    /**
     * Settings option name
     */
    const OPTION_NAME = 'fp_esperienze_seo_settings';
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('fp_esperienze_admin_menu_pages', [$this, 'addMenuPage']);
    }
    
    /**
     * Register settings
     */
    public function registerSettings(): void {
        register_setting('fp_esperienze_seo', self::OPTION_NAME, [
            'type' => 'array',
            'default' => $this->getDefaultSettings(),
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);
        
        // Add settings sections and fields
        add_settings_section(
            'fp_seo_schema_section',
            __('Schema.org Settings', 'fp-esperienze'),
            [$this, 'schemaSectionCallback'],
            'fp_esperienze_seo'
        );
        
        add_settings_field(
            'enable_enhanced_schema',
            __('Enhanced Schema.org', 'fp-esperienze'),
            [$this, 'enhancedSchemaFieldCallback'],
            'fp_esperienze_seo',
            'fp_seo_schema_section'
        );
        
        add_settings_field(
            'enable_faq_schema',
            __('FAQ Schema', 'fp-esperienze'),
            [$this, 'faqSchemaFieldCallback'],
            'fp_esperienze_seo',
            'fp_seo_schema_section'
        );
        
        add_settings_field(
            'enable_breadcrumb_schema',
            __('Breadcrumb Schema', 'fp-esperienze'),
            [$this, 'breadcrumbSchemaFieldCallback'],
            'fp_esperienze_seo',
            'fp_seo_schema_section'
        );
        
        add_settings_section(
            'fp_seo_social_section',
            __('Social Media Settings', 'fp-esperienze'),
            [$this, 'socialSectionCallback'],
            'fp_esperienze_seo'
        );
        
        add_settings_field(
            'enable_og_tags',
            __('Open Graph Tags', 'fp-esperienze'),
            [$this, 'ogTagsFieldCallback'],
            'fp_esperienze_seo',
            'fp_seo_social_section'
        );
        
        add_settings_field(
            'enable_twitter_cards',
            __('Twitter Cards', 'fp-esperienze'),
            [$this, 'twitterCardsFieldCallback'],
            'fp_esperienze_seo',
            'fp_seo_social_section'
        );
    }
    
    /**
     * Get default settings
     */
    public function getDefaultSettings(): array {
        return [
            'enable_enhanced_schema' => true,
            'enable_faq_schema' => true,
            'enable_breadcrumb_schema' => true,
            'enable_og_tags' => true,
            'enable_twitter_cards' => true,
        ];
    }
    
    /**
     * Sanitize settings
     */
    public function sanitizeSettings($input): array {
        $defaults = $this->getDefaultSettings();
        $sanitized = [];

        foreach ($defaults as $key => $default) {
            $sanitized[$key] = (bool) rest_sanitize_boolean($input[$key] ?? $default);
        }

        return $sanitized;
    }
    
    /**
     * Add menu page
     *
     * @param array $pages Menu pages
     * @return array
     */
    public function addMenuPage(array $pages): array {
        $pages[] = [
            'page_title' => __('SEO Settings', 'fp-esperienze'),
            'menu_title' => __('SEO', 'fp-esperienze'),
            'capability' => 'manage_options',
            'menu_slug' => 'fp-esperienze-seo',
            'callback' => [$this, 'renderPage'],
            'position' => 95,
        ];
        
        return $pages;
    }
    
    /**
     * Render settings page
     */
    public function renderPage(): void {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('fp_esperienze_seo');
                do_settings_sections('fp_esperienze_seo');
                submit_button();
                ?>
            </form>
            
            <div class="fp-seo-info">
                <h2><?php _e('SEO Features Overview', 'fp-esperienze'); ?></h2>
                <div class="fp-seo-info-grid">
                    <div class="fp-seo-feature">
                        <h3><?php _e('Enhanced Schema.org', 'fp-esperienze'); ?></h3>
                        <p><?php _e('Automatically adds Event or Trip schema based on experience type, includes meeting point location and pricing information.', 'fp-esperienze'); ?></p>
                    </div>
                    
                    <div class="fp-seo-feature">
                        <h3><?php _e('FAQ Schema', 'fp-esperienze'); ?></h3>
                        <p><?php _e('When FAQ data is available, adds FAQPage schema markup for better search visibility.', 'fp-esperienze'); ?></p>
                    </div>
                    
                    <div class="fp-seo-feature">
                        <h3><?php _e('Breadcrumb Schema', 'fp-esperienze'); ?></h3>
                        <p><?php _e('Adds structured breadcrumb navigation for experiences: Shop → Experience.', 'fp-esperienze'); ?></p>
                    </div>
                    
                    <div class="fp-seo-feature">
                        <h3><?php _e('Social Media Tags', 'fp-esperienze'); ?></h3>
                        <p><?php _e('Open Graph and Twitter Card meta tags for better social media sharing of experiences.', 'fp-esperienze'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .fp-seo-info {
            margin-top: 2em;
            padding: 1.5em;
            background: #f9f9f9;
            border-left: 4px solid #0073aa;
        }
        .fp-seo-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5em;
            margin-top: 1em;
        }
        .fp-seo-feature {
            background: white;
            padding: 1em;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .fp-seo-feature h3 {
            margin-top: 0;
            color: #23282d;
        }
        </style>
        <?php
    }
    
    /**
     * Schema section callback
     */
    public function schemaSectionCallback(): void {
        echo '<p>' . esc_html__('Configure Schema.org structured data markup for better search engine visibility.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * Social section callback
     */
    public function socialSectionCallback(): void {
        echo '<p>' . esc_html__('Configure social media meta tags for better sharing on social platforms.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * Enhanced schema field callback
     */
    public function enhancedSchemaFieldCallback(): void {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $checked = !empty($settings['enable_enhanced_schema']) ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[enable_enhanced_schema]" value="1" ' . $checked . ' />';
        echo ' ' . esc_html__('Enable enhanced Event/Trip schema markup', 'fp-esperienze');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Automatically selects Event schema for guided experiences with specific times, or Trip schema for tour experiences.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * FAQ schema field callback
     */
    public function faqSchemaFieldCallback(): void {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $checked = !empty($settings['enable_faq_schema']) ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[enable_faq_schema]" value="1" ' . $checked . ' />';
        echo ' ' . esc_html__('Enable FAQ schema markup', 'fp-esperienze');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Adds FAQPage schema when FAQ data is available for the experience.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * Breadcrumb schema field callback
     */
    public function breadcrumbSchemaFieldCallback(): void {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $checked = !empty($settings['enable_breadcrumb_schema']) ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[enable_breadcrumb_schema]" value="1" ' . $checked . ' />';
        echo ' ' . esc_html__('Enable breadcrumb schema markup', 'fp-esperienze');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Adds BreadcrumbList schema for experience pages.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * OG tags field callback
     */
    public function ogTagsFieldCallback(): void {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $checked = !empty($settings['enable_og_tags']) ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[enable_og_tags]" value="1" ' . $checked . ' />';
        echo ' ' . esc_html__('Enable Open Graph meta tags', 'fp-esperienze');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Adds Open Graph meta tags for better Facebook and LinkedIn sharing.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * Twitter cards field callback
     */
    public function twitterCardsFieldCallback(): void {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $checked = !empty($settings['enable_twitter_cards']) ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="' . self::OPTION_NAME . '[enable_twitter_cards]" value="1" ' . $checked . ' />';
        echo ' ' . esc_html__('Enable Twitter Card meta tags', 'fp-esperienze');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Adds Twitter Card meta tags for better Twitter sharing.', 'fp-esperienze') . '</p>';
    }
    
    /**
     * Get current settings
     */
    public static function getSettings(): array {
        $instance = new self();
        return get_option(self::OPTION_NAME, $instance->getDefaultSettings());
    }
    
    /**
     * Check if enhanced schema is enabled
     */
    public static function isEnhancedSchemaEnabled(): bool {
        $settings = self::getSettings();
        return !empty($settings['enable_enhanced_schema']);
    }
    
    /**
     * Check if FAQ schema is enabled
     */
    public static function isFaqSchemaEnabled(): bool {
        $settings = self::getSettings();
        return !empty($settings['enable_faq_schema']);
    }
    
    /**
     * Check if breadcrumb schema is enabled
     */
    public static function isBreadcrumbSchemaEnabled(): bool {
        $settings = self::getSettings();
        return !empty($settings['enable_breadcrumb_schema']);
    }
    
    /**
     * Check if OG tags are enabled
     */
    public static function isOgTagsEnabled(): bool {
        $settings = self::getSettings();
        return !empty($settings['enable_og_tags']);
    }
    
    /**
     * Check if Twitter cards are enabled
     */
    public static function isTwitterCardsEnabled(): bool {
        $settings = self::getSettings();
        return !empty($settings['enable_twitter_cards']);
    }
}