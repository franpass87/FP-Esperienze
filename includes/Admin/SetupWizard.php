<?php
/**
 * Setup Wizard
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

use FP\Esperienze\Admin\OnboardingHelper;
use FP\Esperienze\Core\AssetOptimizer;

defined('ABSPATH') || exit;

/**
 * Setup Wizard class for initial plugin configuration
 */
class SetupWizard {

    /**
     * Current step
     */
    private int $current_step = 1;

    /**
     * Total steps
     */
    private int $total_steps = 3;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'addSetupWizardMenu'], 15);
        add_action('admin_init', [$this, 'handleStepSubmission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_notices', [$this, 'maybeRenderWizardNotice']);
        add_action('admin_notices', [$this, 'maybeRenderChecklistNotice']);
    }

    /**
     * Add setup wizard menu item
     */
    public function addSetupWizardMenu(): void {
        if (!$this->isSetupComplete()) {
            add_submenu_page(
                'fp-esperienze',
                __('Setup Wizard', 'fp-esperienze'),
                __('Setup Wizard', 'fp-esperienze'),
                'manage_woocommerce',
                'fp-esperienze-setup-wizard',
                [$this, 'setupWizardPage']
            );
        }
    }

    /**
     * Enqueue setup wizard scripts and styles
     */
    public function enqueueScripts($hook): void {
        // The correct hook for submenus is: {parent_slug}_page_{menu_slug}
        if ($hook !== 'fp-esperienze_page_fp-esperienze-setup-wizard') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        $setup_tour = AssetOptimizer::getAssetInfo('js', 'setup-wizard-tour', 'assets/js/setup-wizard-tour.js');
        wp_enqueue_script(
            'fp-setup-tour',
            $setup_tour['url'],
            ['jquery'],
            $setup_tour['version'],
            true
        );

        $checklist = OnboardingHelper::getChecklistItems();
        $tour_steps = [
            [
                'title' => __('Create your first experience', 'fp-esperienze'),
                'content' => __('Use WooCommerce → Products to publish an “Experience” product with imagery and highlights.', 'fp-esperienze'),
            ],
            [
                'title' => __('Add schedules and capacity', 'fp-esperienze'),
                'content' => __('Open the schedule builder inside the product editor to add recurring or one-off time slots.', 'fp-esperienze'),
            ],
            [
                'title' => __('Preview the booking widget', 'fp-esperienze'),
                'content' => __('Visit the product on the frontend to verify availability, meeting point details, and pricing.', 'fp-esperienze'),
            ],
        ];

        wp_localize_script(
            'fp-setup-tour',
            'fpSetupTourData',
            [
                'steps' => $tour_steps,
                'checklist' => $checklist,
                'i18n' => [
                    'next' => __('Next', 'fp-esperienze'),
                    'previous' => __('Previous', 'fp-esperienze'),
                    'finish' => __('Done', 'fp-esperienze'),
                    'skip' => __('Skip tour', 'fp-esperienze'),
                ],
            ]
        );

        // Add inline styles for wizard - ensure we have a style to add it to
        wp_enqueue_style('wp-admin');
        wp_add_inline_style('wp-admin', $this->getWizardStyles());
    }

    /**
     * Get wizard CSS styles
     */
    private function getWizardStyles(): string {
        return "
        .fp-setup-wizard {
            max-width: 800px;
            margin: 20px 0;
        }
        .fp-wizard-header {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 20px;
            margin-bottom: 20px;
        }
        .fp-wizard-progress {
            display: flex;
            margin-bottom: 20px;
        }
        .fp-progress-step {
            flex: 1;
            text-align: center;
            padding: 10px;
            background: #f1f1f1;
            border-right: 1px solid #ccc;
            position: relative;
        }
        .fp-progress-step:last-child {
            border-right: none;
        }
        .fp-progress-step.active {
            background: #2271b1;
            color: white;
        }
        .fp-progress-step.completed {
            background: #00a32a;
            color: white;
        }
        .fp-wizard-content {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 30px;
        }
        .fp-wizard-navigation {
            text-align: right;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .fp-wizard-navigation .button {
            margin-left: 10px;
        }
        .fp-step-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }
        .fp-onboarding-checklist {
            background: #fff;
            border: 1px solid #c3c4c7;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #2271b1;
        }
        .fp-onboarding-checklist__title {
            margin: 0 0 10px 0;
        }
        .fp-onboarding-checklist__meta {
            font-size: 13px;
            color: #444;
            margin-bottom: 15px;
        }
        .fp-onboarding-checklist__list {
            margin: 0;
            padding-left: 1.2em;
        }
        .fp-onboarding-checklist__item {
            display: flex;
            align-items: center;
            margin-bottom: 6px;
            gap: 6px;
        }
        .fp-onboarding-checklist__item:last-child {
            margin-bottom: 0;
        }
        .fp-onboarding-checklist__item .dashicons {
            color: #2271b1;
        }
        .fp-onboarding-checklist__item.completed .dashicons {
            color: #00a32a;
        }
        .fp-onboarding-checklist__item a {
            text-decoration: none;
        }
        .fp-onboarding-helpers {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }
        .fp-tour-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 100000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .fp-tour-overlay.is-visible {
            display: flex;
        }
        .fp-tour-card {
            background: #fff;
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
            max-width: 420px;
            width: 100%;
            padding: 25px;
            text-align: left;
            position: relative;
        }
        .fp-tour-card h3 {
            margin-top: 0;
        }
        .fp-tour-actions {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        ";
    }

    /**
     * Handle step form submissions
     */
    public function handleStepSubmission(): void {
        if (!isset($_POST['fp_setup_wizard_nonce']) ||
            !wp_verify_nonce(wp_unslash($_POST['fp_setup_wizard_nonce']), 'fp_setup_wizard_step')) {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $step = absint(isset($_POST['current_step']) ? wp_unslash($_POST['current_step']) : 1);
        $action = sanitize_text_field(isset($_POST['wizard_action']) ? wp_unslash($_POST['wizard_action']) : '');

        switch ($action) {
            case 'seed_demo':
                $notice = OnboardingHelper::seedDemoContent();
                $this->redirectWithNotice($notice, $step);
                return;
            case 'next':
                $this->processStep($step);
                break;
            case 'skip':
                $this->skipStep($step);
                break;
            case 'finish':
                $this->finishSetup();
                break;
        }
    }

    /**
     * Process current step data
     */
    private function processStep(int $step): void {
        switch ($step) {
            case 1:
                $this->processBasicSettings();
                break;
            case 2:
                $this->processIntegrationsSettings();
                break;
            case 3:
                $this->processBrandSettings();
                break;
        }

        // Redirect to next step or finish
        if ($step < $this->total_steps) {
            wp_safe_redirect(add_query_arg(['step' => $step + 1], admin_url('admin.php?page=fp-esperienze-setup-wizard')));
            exit;
        } else {
            $this->finishSetup();
        }
    }

    /**
     * Process basic settings (Step 1)
     */
    private function processBasicSettings(): void {
        // Auto-detect language from WordPress locale if not provided
        $wp_locale = get_locale();
        $default_lang = 'en';
        if (strpos($wp_locale, 'it') === 0) {
            $default_lang = 'it';
        }
        
        $settings = [
            'fp_esperienze_currency' => sanitize_text_field(isset($_POST['currency']) ? wp_unslash($_POST['currency']) : (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD')),
            'fp_esperienze_default_duration' => absint(isset($_POST['default_duration']) ? wp_unslash($_POST['default_duration']) : 60),
            'fp_esperienze_default_capacity' => absint(isset($_POST['default_capacity']) ? wp_unslash($_POST['default_capacity']) : 10),
            'fp_esperienze_default_language' => sanitize_text_field(isset($_POST['default_language']) ? wp_unslash($_POST['default_language']) : $default_lang),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        // Update WordPress timezone if provided
        if (isset($_POST['timezone']) && $_POST['timezone'] !== '') {
            update_option('timezone_string', sanitize_text_field(wp_unslash($_POST['timezone'])));
        }
    }

    /**
     * Process integrations settings (Step 2)
     */
    private function processIntegrationsSettings(): void {
        $integrations = [
            'ga4_measurement_id' => sanitize_text_field(isset($_POST['ga4_measurement_id']) ? wp_unslash($_POST['ga4_measurement_id']) : ''),
            'ga4_ecommerce' => isset($_POST['ga4_ecommerce']) && (bool) $_POST['ga4_ecommerce'],
            'gads_conversion_id' => sanitize_text_field(isset($_POST['gads_conversion_id']) ? wp_unslash($_POST['gads_conversion_id']) : ''),
            'gads_purchase_label' => sanitize_text_field(isset($_POST['gads_purchase_label']) ? wp_unslash($_POST['gads_purchase_label']) : ''),
            'meta_pixel_id' => sanitize_text_field(isset($_POST['meta_pixel_id']) ? wp_unslash($_POST['meta_pixel_id']) : ''),
            'meta_capi_enabled' => isset($_POST['meta_capi_enabled']) && (bool) $_POST['meta_capi_enabled'],
            'meta_access_token' => sanitize_text_field(isset($_POST['meta_access_token']) ? wp_unslash($_POST['meta_access_token']) : ''),
            'meta_dataset_id' => sanitize_text_field(isset($_POST['meta_dataset_id']) ? wp_unslash($_POST['meta_dataset_id']) : ''),
            'brevo_api_key' => sanitize_text_field(isset($_POST['brevo_api_key']) ? wp_unslash($_POST['brevo_api_key']) : ''),
            'brevo_list_id_it' => sanitize_text_field(isset($_POST['brevo_list_id_it']) ? wp_unslash($_POST['brevo_list_id_it']) : ''),
            'brevo_list_id_en' => sanitize_text_field(isset($_POST['brevo_list_id_en']) ? wp_unslash($_POST['brevo_list_id_en']) : ''),
            'gplaces_api_key' => sanitize_text_field(isset($_POST['gplaces_api_key']) ? wp_unslash($_POST['gplaces_api_key']) : ''),
            'consent_mode_enabled' => isset($_POST['consent_mode_enabled']) && (bool) $_POST['consent_mode_enabled'],
            'consent_cookie_name' => sanitize_text_field(isset($_POST['consent_cookie_name']) ? wp_unslash($_POST['consent_cookie_name']) : 'marketing_consent'),
            'consent_js_function' => sanitize_text_field(isset($_POST['consent_js_function']) ? wp_unslash($_POST['consent_js_function']) : ''),
        ];

        update_option('fp_esperienze_integrations', $integrations);
    }

    /**
     * Process brand settings (Step 3)
     */
    private function processBrandSettings(): void {
        $settings = [
            'fp_esperienze_gift_pdf_logo' => esc_url_raw(isset($_POST['pdf_logo']) ? wp_unslash($_POST['pdf_logo']) : ''),
            'fp_esperienze_gift_pdf_brand_color' => sanitize_hex_color(isset($_POST['brand_color']) ? wp_unslash($_POST['brand_color']) : '#3498db'),
            'fp_esperienze_gift_terms' => sanitize_textarea_field(isset($_POST['voucher_terms']) ? wp_unslash($_POST['voucher_terms']) : ''),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
    }

    /**
     * Skip current step
     */
    private function skipStep(int $step): void {
        if ($step < $this->total_steps) {
            wp_safe_redirect(add_query_arg(['step' => $step + 1], admin_url('admin.php?page=fp-esperienze-setup-wizard')));
            exit;
        } else {
            $this->finishSetup();
        }
    }

    /**
     * Finish setup wizard
     */
    private function finishSetup(): void {
        update_option('fp_esperienze_setup_complete', 1);
        wp_safe_redirect(admin_url('admin.php?page=fp-esperienze&setup=complete'));
        exit;
    }

    /**
     * Check if setup is complete
     */
    public function isSetupComplete(): bool {
        return (bool) get_option('fp_esperienze_setup_complete', false);
    }

    /**
     * Setup wizard page
     */
    public function setupWizardPage(): void {
        $current_step = absint($_GET['step'] ?? 1);
        $current_step = max(1, min($current_step, $this->total_steps));

        ?>
        <div class="wrap">
            <h1><?php _e('FP Esperienze Setup Wizard', 'fp-esperienze'); ?></h1>

            <?php $this->renderChecklistOverview(); ?>

            <div class="fp-setup-wizard">
                <div class="fp-wizard-header">
                    <div class="fp-wizard-progress">
                        <?php for ($i = 1; $i <= $this->total_steps; $i++) : ?>
                            <div class="fp-progress-step <?php echo $i === $current_step ? 'active' : ($i < $current_step ? 'completed' : ''); ?>">
                                <strong><?php echo $i; ?>.</strong>
                                <?php
                                switch ($i) {
                                    case 1: _e('Basic Settings', 'fp-esperienze'); break;
                                    case 2: _e('Integrations', 'fp-esperienze'); break;
                                    case 3: _e('Brand & PDF', 'fp-esperienze'); break;
                                }
                                ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <form method="post" action="">
                    <?php wp_nonce_field('fp_setup_wizard_step', 'fp_setup_wizard_nonce'); ?>
                    <input type="hidden" name="current_step" value="<?php echo esc_attr($current_step); ?>">

                    <div class="fp-wizard-content">
                        <?php $this->renderStep($current_step); ?>

                        <div class="fp-wizard-navigation">
                            <?php if ($current_step > 1) : ?>
                                <a href="<?php echo esc_url(add_query_arg(['step' => $current_step - 1], admin_url('admin.php?page=fp-esperienze-setup-wizard'))); ?>" 
                                   class="button button-secondary"><?php _e('Previous', 'fp-esperienze'); ?></a>
                            <?php endif; ?>

                            <button type="submit" name="wizard_action" value="skip" class="button button-secondary">
                                <?php _e('Skip', 'fp-esperienze'); ?>
                            </button>

                            <button type="submit" name="wizard_action" value="<?php echo $current_step === $this->total_steps ? 'finish' : 'next'; ?>" class="button button-primary">
                                <?php echo $current_step === $this->total_steps ? __('Finish Setup', 'fp-esperienze') : __('Next', 'fp-esperienze'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('.fp-color-picker').wpColorPicker();
            
            // Media uploader for logo
            $('#upload_logo_button').click(function(e) {
                e.preventDefault();
                var image = wp.media({
                    title: '<?php echo esc_js(__('Upload Logo', 'fp-esperienze')); ?>',
                    multiple: false
                }).open().on('select', function() {
                    var uploaded_image = image.state().get('selection').first();
                    var image_url = uploaded_image.toJSON().url;
                    $('#pdf_logo').val(image_url);
                    $('#logo_preview').html('<img src="' + image_url + '" class="fp-logo-preview">');
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render specific step content
     */
    private function renderStep(int $step): void {
        switch ($step) {
            case 1:
                $this->renderBasicSettingsStep();
                break;
            case 2:
                $this->renderIntegrationsStep();
                break;
            case 3:
                $this->renderBrandSettingsStep();
                break;
        }
    }

    /**
     * Render basic settings step
     */
    private function renderBasicSettingsStep(): void {
        $currency = get_option('fp_esperienze_currency', function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD');
        $duration = get_option('fp_esperienze_default_duration', 60);
        $capacity = get_option('fp_esperienze_default_capacity', 10);
        
        // Auto-detect language from WordPress locale
        $wp_locale = get_locale();
        $default_lang = 'en';
        if (strpos($wp_locale, 'it') === 0) {
            $default_lang = 'it';
        }
        $language = get_option('fp_esperienze_default_language', $default_lang);
        $timezone = get_option('timezone_string', '');

        ?>
        <h2><?php _e('Basic Settings', 'fp-esperienze'); ?></h2>
        <p class="fp-step-description">
            <?php _e('Configure the basic settings for your experience booking system.', 'fp-esperienze'); ?>
        </p>

        <div class="fp-onboarding-helpers">
            <button type="submit" name="wizard_action" value="seed_demo" class="button button-secondary">
                <?php esc_html_e('Create demo data', 'fp-esperienze'); ?>
            </button>
            <button type="button" id="fp-start-tour" class="button button-link">
                <?php esc_html_e('Start guided tour', 'fp-esperienze'); ?>
            </button>
        </div>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="currency"><?php _e('Currency', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <select name="currency" id="currency">
                        <?php if (function_exists('get_woocommerce_currencies')) : ?>
                            <?php foreach (get_woocommerce_currencies() as $code => $name) : ?>
                                <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                    <?php echo esc_html($name . ' (' . (function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol($code) : $code) . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <option value="USD" <?php selected($currency, 'USD'); ?>>US Dollar (USD)</option>
                            <option value="EUR" <?php selected($currency, 'EUR'); ?>>Euro (EUR)</option>
                            <option value="GBP" <?php selected($currency, 'GBP'); ?>>British Pound (GBP)</option>
                        <?php endif; ?>
                    </select>
                    <p class="description"><?php _e('Default currency for experience pricing.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="timezone"><?php _e('Timezone', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <select name="timezone" id="timezone">
                        <option value=""><?php _e('Use WordPress default', 'fp-esperienze'); ?></option>
                        <?php 
                        $timezones = timezone_identifiers_list();
                        foreach ($timezones as $tz) :
                        ?>
                            <option value="<?php echo esc_attr($tz); ?>" <?php selected($timezone, $tz); ?>>
                                <?php echo esc_html($tz); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php _e('Timezone for booking schedules and availability.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="default_duration"><?php _e('Default Duration (minutes)', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="number" name="default_duration" id="default_duration" value="<?php echo esc_attr($duration); ?>" min="1" class="small-text">
                    <p class="description"><?php _e('Default duration for new experiences.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="default_capacity"><?php _e('Default Capacity', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="number" name="default_capacity" id="default_capacity" value="<?php echo esc_attr($capacity); ?>" min="1" class="small-text">
                    <p class="description"><?php _e('Default maximum participants for new experiences.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="default_language"><?php _e('Default Language', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <select name="default_language" id="default_language">
                        <option value="en" <?php selected($language, 'en'); ?>><?php _e('English', 'fp-esperienze'); ?></option>
                        <option value="it" <?php selected($language, 'it'); ?>><?php _e('Italian', 'fp-esperienze'); ?></option>
                    </select>
                    <p class="description"><?php _e('Default language for customer communications.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render integrations step
     */
    private function renderIntegrationsStep(): void {
        $integrations = get_option('fp_esperienze_integrations', []);

        ?>
        <h2><?php _e('Third-Party Integrations', 'fp-esperienze'); ?></h2>
        <p class="fp-step-description">
            <?php _e('Configure integrations with Google Analytics, Google Ads, Meta Pixel, Brevo, and Google Places. You can skip this step and configure these later in Settings.', 'fp-esperienze'); ?>
        </p>

        <h3><?php _e('Google Analytics 4', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="ga4_measurement_id"><?php _e('Measurement ID', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="ga4_measurement_id" id="ga4_measurement_id" value="<?php echo esc_attr($integrations['ga4_measurement_id'] ?? ''); ?>" class="regular-text" placeholder="G-XXXXXXXXXX">
                    <p class="description"><?php _e('Your GA4 Measurement ID for tracking events and conversions.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <label>
                        <input type="checkbox" name="ga4_ecommerce" value="1" <?php checked(!empty($integrations['ga4_ecommerce'])); ?>>
                        <?php _e('Enable Enhanced eCommerce tracking', 'fp-esperienze'); ?>
                    </label>
                </td>
            </tr>
        </table>

        <h3><?php _e('Google Ads', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="gads_conversion_id"><?php _e('Conversion ID', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="gads_conversion_id" id="gads_conversion_id" value="<?php echo esc_attr($integrations['gads_conversion_id'] ?? ''); ?>" class="regular-text" placeholder="AW-XXXXXXXXXX">
                    <p class="description"><?php _e('Your Google Ads Conversion ID for tracking bookings.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="gads_purchase_label"><?php _e('Purchase Conversion Label', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="gads_purchase_label" id="gads_purchase_label" value="<?php echo esc_attr($integrations['gads_purchase_label'] ?? ''); ?>" class="regular-text" placeholder="XXXXXXXXXXXX">
                    <p class="description"><?php _e('Conversion label for purchase events (optional but recommended).', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Meta Pixel (Facebook)', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="meta_pixel_id"><?php _e('Pixel ID', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="meta_pixel_id" id="meta_pixel_id" value="<?php echo esc_attr($integrations['meta_pixel_id'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Meta (Facebook) Pixel ID for frontend tracking.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"></th>
                <td>
                    <label>
                        <input type="checkbox" name="meta_capi_enabled" value="1" <?php checked(!empty($integrations['meta_capi_enabled'])); ?>>
                        <?php _e('Enable Meta Conversions API (server-side tracking)', 'fp-esperienze'); ?>
                    </label>
                    <p class="description"><?php _e('Enables server-side tracking for better data accuracy and iOS 14.5+ compliance.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr id="meta_capi_settings" style="<?php echo empty($integrations['meta_capi_enabled']) ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="meta_access_token"><?php _e('Access Token', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="meta_access_token" id="meta_access_token" value="<?php echo esc_attr($integrations['meta_access_token'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Meta app access token for Conversions API.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr id="meta_dataset_row" style="<?php echo empty($integrations['meta_capi_enabled']) ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="meta_dataset_id"><?php _e('Dataset ID', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="meta_dataset_id" id="meta_dataset_id" value="<?php echo esc_attr($integrations['meta_dataset_id'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Dataset ID for Meta Conversions API (found in Events Manager).', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="meta_capi_enabled"]').change(function() {
                if ($(this).is(':checked')) {
                    $('#meta_capi_settings, #meta_dataset_row').show();
                } else {
                    $('#meta_capi_settings, #meta_dataset_row').hide();
                }
            });
        });
        </script>

        <h3><?php _e('Brevo (Email Marketing)', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="brevo_api_key"><?php _e('API Key v3', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="brevo_api_key" id="brevo_api_key" value="<?php echo esc_attr($integrations['brevo_api_key'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Brevo API key for email list management.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brevo_list_id_it"><?php _e('List ID (Italian)', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="brevo_list_id_it" id="brevo_list_id_it" value="<?php echo esc_attr($integrations['brevo_list_id_it'] ?? ''); ?>" class="small-text">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brevo_list_id_en"><?php _e('List ID (English)', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="brevo_list_id_en" id="brevo_list_id_en" value="<?php echo esc_attr($integrations['brevo_list_id_en'] ?? ''); ?>" class="small-text">
                </td>
            </tr>
        </table>

        <h3><?php _e('Google Places API', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="gplaces_api_key"><?php _e('API Key', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="gplaces_api_key" id="gplaces_api_key" value="<?php echo esc_attr($integrations['gplaces_api_key'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Google Places API key for location and reviews integration.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Privacy & Consent', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"></th>
                <td>
                    <label>
                        <input type="checkbox" name="consent_mode_enabled" value="1" <?php checked(!empty($integrations['consent_mode_enabled'])); ?>>
                        <?php _e('Enable Consent Mode (GDPR Compliance)', 'fp-esperienze'); ?>
                    </label>
                    <p class="description"><?php _e('Requires user consent before loading tracking scripts.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr id="consent_settings" style="<?php echo empty($integrations['consent_mode_enabled']) ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="consent_cookie_name"><?php _e('Consent Cookie Name', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="consent_cookie_name" id="consent_cookie_name" value="<?php echo esc_attr($integrations['consent_cookie_name'] ?? 'marketing_consent'); ?>" class="regular-text">
                    <p class="description"><?php _e('Name of cookie that stores consent status.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr id="consent_function_row" style="<?php echo empty($integrations['consent_mode_enabled']) ? 'display: none;' : ''; ?>">
                <th scope="row">
                    <label for="consent_js_function"><?php _e('JavaScript Function (Optional)', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="consent_js_function" id="consent_js_function" value="<?php echo esc_attr($integrations['consent_js_function'] ?? ''); ?>" class="regular-text" placeholder="window.getConsentStatus">
                    <p class="description"><?php _e('JavaScript function to check consent status. Leave blank to use cookie only.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>

        <script>
        jQuery(document).ready(function($) {
            $('input[name="consent_mode_enabled"]').change(function() {
                if ($(this).is(':checked')) {
                    $('#consent_settings, #consent_function_row').show();
                } else {
                    $('#consent_settings, #consent_function_row').hide();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render brand settings step
     */
    private function renderBrandSettingsStep(): void {
        $logo = get_option('fp_esperienze_gift_pdf_logo', '');
        $color = get_option('fp_esperienze_gift_pdf_brand_color', '#3498db');
        $terms = get_option('fp_esperienze_gift_terms', __('This voucher is valid for one experience booking. Please present the QR code when redeeming.', 'fp-esperienze'));

        ?>
        <h2><?php _e('Brand & PDF Settings', 'fp-esperienze'); ?></h2>
        <p class="fp-step-description">
            <?php _e('Configure your brand settings for voucher PDFs and customer communications.', 'fp-esperienze'); ?>
        </p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="pdf_logo"><?php _e('PDF Logo', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="pdf_logo" id="pdf_logo" value="<?php echo esc_attr($logo); ?>" class="regular-text">
                    <button type="button" id="upload_logo_button" class="button button-secondary"><?php _e('Upload Logo', 'fp-esperienze'); ?></button>
                    <div id="logo_preview" class="fp-logo-preview-container">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url($logo); ?>" class="fp-logo-preview">
                        <?php endif; ?>
                    </div>
                    <p class="description"><?php _e('Logo to display on voucher PDFs and emails.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="brand_color"><?php _e('Brand Color', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="brand_color" id="brand_color" value="<?php echo esc_attr($color); ?>" class="fp-color-picker">
                    <p class="description"><?php _e('Primary brand color for PDFs and styling.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="voucher_terms"><?php _e('Voucher Terms', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <textarea name="voucher_terms" id="voucher_terms" rows="4" class="large-text"><?php echo esc_textarea($terms); ?></textarea>
                    <p class="description"><?php _e('Terms and conditions text displayed on vouchers.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Render the onboarding checklist summary box.
     */
    private function renderChecklistOverview(): void {
        $items = OnboardingHelper::getChecklistItems();
        $summary = OnboardingHelper::getCompletionSummary();

        ?>
        <div class="fp-onboarding-checklist">
            <h2 class="fp-onboarding-checklist__title">
                <?php esc_html_e('Onboarding checklist', 'fp-esperienze'); ?>
            </h2>
            <p class="fp-onboarding-checklist__meta">
                <?php
                $percentage_label = number_format_i18n($summary['percentage'], 2);
                $completed = $summary['completed'];
                $total = $summary['total'];
                printf(
                    esc_html__('Progress: %1$d of %2$d tasks complete (%3$s%%).', 'fp-esperienze'),
                    $completed,
                    $total,
                    esc_html($percentage_label)
                );
                ?>
            </p>
            <ul class="fp-onboarding-checklist__list">
                <?php foreach ($items as $item) :
                    $is_completed = isset($item['completed']) && (bool) $item['completed'];
                    $icon = $is_completed ? 'yes' : 'info';
                    $item_classes = 'fp-onboarding-checklist__item' . ($is_completed ? ' completed' : '');
                    $action = isset($item['action']) && is_string($item['action']) ? $item['action'] : '';
                    $label = isset($item['label']) && is_string($item['label']) ? $item['label'] : '';
                    ?>
                    <li class="<?php echo esc_attr($item_classes); ?>">
                        <span class="dashicons dashicons-<?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
                        <?php if ($action !== '') : ?>
                            <a href="<?php echo esc_url($action); ?>">
                                <?php echo esc_html($label); ?>
                            </a>
                        <?php else : ?>
                            <?php echo esc_html($label); ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="fp-tour-overlay" id="fp-tour-overlay" aria-hidden="true">
            <div class="fp-tour-card" role="dialog" aria-modal="true" aria-labelledby="fp-tour-title">
                <h3 id="fp-tour-title"></h3>
                <p id="fp-tour-content"></p>
                <div class="fp-tour-actions">
                    <button type="button" class="button" id="fp-tour-prev"></button>
                    <div>
                        <button type="button" class="button button-link" id="fp-tour-skip"></button>
                        <button type="button" class="button button-primary" id="fp-tour-next"></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render notice messages generated by onboarding actions.
     */
    public function maybeRenderWizardNotice(): void {
        if (!isset($_GET['page']) || $_GET['page'] !== 'fp-esperienze-setup-wizard') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $notice = get_transient('fp_esperienze_onboarding_notice');
        if (!is_array($notice) || $notice === []) {
            return;
        }

        delete_transient('fp_esperienze_onboarding_notice');

        $status = $notice['status'] ?? 'info';
        $message = $notice['message'] ?? '';

        $class = match ($status) {
            'success' => 'notice-success',
            'warning' => 'notice-warning',
            'error' => 'notice-error',
            default => 'notice-info',
        };

        echo '<div class="notice ' . esc_attr($class) . '"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * Display the onboarding progress notice across admin pages until complete.
     */
    public function maybeRenderChecklistNotice(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!($screen instanceof \WP_Screen) || strpos($screen->id, 'fp-esperienze') === false) {
            return;
        }

        if (isset($_GET['page']) && $_GET['page'] === 'fp-esperienze-setup-wizard') { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $summary = OnboardingHelper::getCompletionSummary();
        $completed = $summary['completed'];
        $total = $summary['total'];

        if ($completed >= $total) {
            return;
        }

        $items = OnboardingHelper::getChecklistItems();

        echo '<div class="notice notice-info fp-onboarding-checklist-notice">';
        echo '<p>' . sprintf(
            esc_html__('FP Esperienze onboarding progress: %1$d of %2$d tasks complete.', 'fp-esperienze'),
            $completed,
            $total
        ) . '</p>';
        echo '<ul>';
        foreach ($items as $item) {
            if (isset($item['completed']) && (bool) $item['completed']) {
                continue;
            }
            echo '<li>';
            $action = isset($item['action']) && is_string($item['action']) ? $item['action'] : '';
            if ($action !== '') {
                echo '<a href="' . esc_url($action) . '">';
            }
            $label = isset($item['label']) && is_string($item['label']) ? $item['label'] : '';
            echo esc_html($label);
            if ($action !== '') {
                echo '</a>';
            }
            echo '</li>';
        }
        echo '</ul>';
        echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=fp-esperienze-setup-wizard')) . '">' . esc_html__('Resume setup wizard', 'fp-esperienze') . '</a></p>';
        echo '</div>';
    }

    /**
     * Store a transient notice and redirect back to the current step.
     *
     * @param array<string,string> $notice Notice payload.
     * @param int                  $step   Current wizard step.
     */
    private function redirectWithNotice(array $notice, int $step): void {
        $expiration = defined('MINUTE_IN_SECONDS') ? (int) MINUTE_IN_SECONDS : 60;
        set_transient('fp_esperienze_onboarding_notice', $notice, $expiration);

        $target_step = max(1, min($this->total_steps, $step));
        wp_safe_redirect(add_query_arg(['step' => $target_step], admin_url('admin.php?page=fp-esperienze-setup-wizard')));
        exit;
    }
}
