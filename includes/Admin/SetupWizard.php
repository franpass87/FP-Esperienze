<?php
/**
 * Setup Wizard
 *
 * @package FP\Esperienze\Admin
 */

namespace FP\Esperienze\Admin;

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
        add_action('admin_menu', [$this, 'addSetupWizardMenu'], 5);
        add_action('admin_init', [$this, 'handleStepSubmission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
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
                'manage_options',
                'fp-esperienze-setup-wizard',
                [$this, 'setupWizardPage']
            );
        }
    }

    /**
     * Enqueue setup wizard scripts and styles
     */
    public function enqueueScripts($hook): void {
        if ($hook !== 'fp-esperienze_page_fp-esperienze-setup-wizard') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Add inline styles for wizard
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
        ";
    }

    /**
     * Handle step form submissions
     */
    public function handleStepSubmission(): void {
        if (!isset($_POST['fp_setup_wizard_nonce']) || 
            !wp_verify_nonce($_POST['fp_setup_wizard_nonce'], 'fp_setup_wizard_step')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $step = absint($_POST['current_step'] ?? 1);
        $action = sanitize_text_field($_POST['wizard_action'] ?? '');

        switch ($action) {
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
            wp_redirect(add_query_arg(['step' => $step + 1], admin_url('admin.php?page=fp-esperienze-setup-wizard')));
        } else {
            $this->finishSetup();
        }
        exit;
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
            'fp_esperienze_currency' => sanitize_text_field($_POST['currency'] ?? get_woocommerce_currency()),
            'fp_esperienze_default_duration' => absint($_POST['default_duration'] ?? 60),
            'fp_esperienze_default_capacity' => absint($_POST['default_capacity'] ?? 10),
            'fp_esperienze_default_language' => sanitize_text_field($_POST['default_language'] ?? $default_lang),
        ];

        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }

        // Update WordPress timezone if provided
        if (!empty($_POST['timezone'])) {
            update_option('timezone_string', sanitize_text_field($_POST['timezone']));
        }
    }

    /**
     * Process integrations settings (Step 2)
     */
    private function processIntegrationsSettings(): void {
        $integrations = [
            'ga4_measurement_id' => sanitize_text_field($_POST['ga4_measurement_id'] ?? ''),
            'ga4_ecommerce' => !empty($_POST['ga4_ecommerce']),
            'gads_conversion_id' => sanitize_text_field($_POST['gads_conversion_id'] ?? ''),
            'meta_pixel_id' => sanitize_text_field($_POST['meta_pixel_id'] ?? ''),
            'brevo_api_key' => sanitize_text_field($_POST['brevo_api_key'] ?? ''),
            'brevo_list_id_it' => sanitize_text_field($_POST['brevo_list_id_it'] ?? ''),
            'brevo_list_id_en' => sanitize_text_field($_POST['brevo_list_id_en'] ?? ''),
            'gplaces_api_key' => sanitize_text_field($_POST['gplaces_api_key'] ?? ''),
        ];

        update_option('fp_esperienze_integrations', $integrations);
    }

    /**
     * Process brand settings (Step 3)
     */
    private function processBrandSettings(): void {
        $settings = [
            'fp_esperienze_gift_pdf_logo' => esc_url_raw($_POST['pdf_logo'] ?? ''),
            'fp_esperienze_gift_pdf_brand_color' => sanitize_hex_color($_POST['brand_color'] ?? '#ff6b35'),
            'fp_esperienze_gift_terms' => sanitize_textarea_field($_POST['voucher_terms'] ?? ''),
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
            wp_redirect(add_query_arg(['step' => $step + 1], admin_url('admin.php?page=fp-esperienze-setup-wizard')));
        } else {
            $this->finishSetup();
        }
        exit;
    }

    /**
     * Finish setup wizard
     */
    private function finishSetup(): void {
        update_option('fp_esperienze_setup_complete', 1);
        wp_redirect(admin_url('admin.php?page=fp-esperienze&setup=complete'));
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
                    $('#logo_preview').html('<img src="' + image_url + '" style="max-width: 200px; height: auto;">');
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
        $currency = get_option('fp_esperienze_currency', get_woocommerce_currency());
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

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="currency"><?php _e('Currency', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <select name="currency" id="currency">
                        <?php foreach (get_woocommerce_currencies() as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                                <?php echo esc_html($name . ' (' . get_woocommerce_currency_symbol($code) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
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
        </table>

        <h3><?php _e('Meta Pixel (Facebook)', 'fp-esperienze'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="meta_pixel_id"><?php _e('Pixel ID', 'fp-esperienze'); ?></label>
                </th>
                <td>
                    <input type="text" name="meta_pixel_id" id="meta_pixel_id" value="<?php echo esc_attr($integrations['meta_pixel_id'] ?? ''); ?>" class="regular-text">
                    <p class="description"><?php _e('Your Meta (Facebook) Pixel ID for tracking conversions.', 'fp-esperienze'); ?></p>
                </td>
            </tr>
        </table>

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
        <?php
    }

    /**
     * Render brand settings step
     */
    private function renderBrandSettingsStep(): void {
        $logo = get_option('fp_esperienze_gift_pdf_logo', '');
        $color = get_option('fp_esperienze_gift_pdf_brand_color', '#ff6b35');
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
                    <div id="logo_preview" style="margin-top: 10px;">
                        <?php if ($logo) : ?>
                            <img src="<?php echo esc_url($logo); ?>" style="max-width: 200px; height: auto;">
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
}