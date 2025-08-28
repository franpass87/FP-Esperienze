<?php
/**
 * Experience Product Type
 *
 * @package FP\Esperienze\ProductType
 */

namespace FP\Esperienze\ProductType;

use FP\Esperienze\Data\ScheduleManager;
use FP\Esperienze\Data\OverrideManager;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\ExtraManager;
use FP\Esperienze\Data\DynamicPricingManager;

defined('ABSPATH') || exit;

/**
 * Experience product type class
 */
class Experience {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init']);
        add_filter('product_type_selector', [$this, 'addProductType']);
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductDataTabs']);
        add_action('woocommerce_product_data_panels', [$this, 'addProductDataPanels']);
        add_action('woocommerce_process_product_meta', [$this, 'saveProductData']);
        add_action('admin_notices', [$this, 'showScheduleValidationNotices']);
    }

    /**
     * Initialize
     */
    public function init(): void {
        // Register the experience product type
        if (class_exists('WC_Product')) {
            require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/ProductType/WC_Product_Experience.php';
        }
    }

    /**
     * Add experience to product type selector
     *
     * @param array $types Product types
     * @return array
     */
    public function addProductType(array $types): array {
        $types['experience'] = __('Experience', 'fp-esperienze');
        return $types;
    }

    /**
     * Add product data tabs
     *
     * @param array $tabs Product data tabs
     * @return array
     */
    public function addProductDataTabs(array $tabs): array {
        $tabs['experience'] = [
            'label'  => __('Experience', 'fp-esperienze'),
            'target' => 'experience_product_data',
            'class'  => ['show_if_experience'],
        ];
        $tabs['dynamic_pricing'] = [
            'label'  => __('Dynamic Pricing', 'fp-esperienze'),
            'target' => 'dynamic_pricing_product_data',
            'class'  => ['show_if_experience'],
        ];
        return $tabs;
    }

    /**
     * Add product data panels
     */
    public function addProductDataPanels(): void {
        global $post;
        
        ?>
        <div id="experience_product_data" class="panel woocommerce_options_panel">
            <?php
            
            // Duration
            woocommerce_wp_text_input([
                'id'          => '_experience_duration',
                'label'       => __('Duration (minutes)', 'fp-esperienze'),
                'placeholder' => '60',
                'desc_tip'    => true,
                'description' => __('Experience duration in minutes', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '1',
                    'min'  => '1'
                ]
            ]);

            // Capacity
            woocommerce_wp_text_input([
                'id'          => '_experience_capacity',
                'label'       => __('Max Capacity', 'fp-esperienze'),
                'placeholder' => '10',
                'desc_tip'    => true,
                'description' => __('Maximum number of participants', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '1',
                    'min'  => '1'
                ]
            ]);

            // Adult price
            woocommerce_wp_text_input([
                'id'          => '_experience_adult_price',
                'label'       => __('Adult Price', 'fp-esperienze') . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => '0.00',
                'desc_tip'    => true,
                'description' => __('Price per adult participant', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0'
                ]
            ]);

            // Child price
            woocommerce_wp_text_input([
                'id'          => '_experience_child_price',
                'label'       => __('Child Price', 'fp-esperienze') . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => '0.00',
                'desc_tip'    => true,
                'description' => __('Price per child participant', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0'
                ]
            ]);

            // Tax class for adult price
            $tax_classes = ExtraManager::getTaxClasses();
            woocommerce_wp_select([
                'id'          => '_experience_adult_tax_class',
                'label'       => __('Adult Tax Class', 'fp-esperienze'),
                'options'     => $tax_classes,
                'desc_tip'    => true,
                'description' => __('Tax class for adult price', 'fp-esperienze')
            ]);

            // Tax class for child price
            woocommerce_wp_select([
                'id'          => '_experience_child_tax_class',
                'label'       => __('Child Tax Class', 'fp-esperienze'),
                'options'     => $tax_classes,
                'desc_tip'    => true,
                'description' => __('Tax class for child price', 'fp-esperienze')
            ]);

            // Languages
            woocommerce_wp_textarea_input([
                'id'          => '_experience_languages',
                'label'       => __('Languages', 'fp-esperienze'),
                'placeholder' => __('Italian, English, Spanish', 'fp-esperienze'),
                'desc_tip'    => true,
                'description' => __('Available languages for this experience', 'fp-esperienze'),
                'rows'        => 3
            ]);

            // Default meeting point
            $meeting_points = $this->getMeetingPoints();
            woocommerce_wp_select([
                'id'          => '_fp_exp_meeting_point_id',
                'label'       => __('Default Meeting Point', 'fp-esperienze'),
                'options'     => $meeting_points,
                'desc_tip'    => true,
                'description' => __('Default meeting point for this experience', 'fp-esperienze')
            ]);

            // Cutoff minutes
            woocommerce_wp_text_input([
                'id'          => '_fp_exp_cutoff_minutes',
                'label'       => __('Booking Cutoff (minutes)', 'fp-esperienze'),
                'placeholder' => '120',
                'desc_tip'    => true,
                'description' => __('Minimum minutes before experience start time to allow bookings', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '1',
                    'min'  => '0'
                ]
            ]);

            ?>
            
            <div class="options_group">
                <h4><?php _e('Cancellation Rules', 'fp-esperienze'); ?></h4>
                
                <?php
                
                // Free cancellation until (minutes)
                woocommerce_wp_text_input([
                    'id'          => '_fp_exp_free_cancel_until_minutes',
                    'label'       => __('Free Cancellation Until (minutes)', 'fp-esperienze'),
                    'placeholder' => '1440',
                    'desc_tip'    => true,
                    'description' => __('Minutes before experience start when customers can cancel for free (e.g., 1440 = 24 hours)', 'fp-esperienze'),
                    'type'        => 'number',
                    'custom_attributes' => [
                        'step' => '1',
                        'min'  => '0'
                    ]
                ]);

                // Cancellation fee percentage
                woocommerce_wp_text_input([
                    'id'          => '_fp_exp_cancel_fee_percent',
                    'label'       => __('Cancellation Fee (%)', 'fp-esperienze'),
                    'placeholder' => '20',
                    'desc_tip'    => true,
                    'description' => __('Percentage of total price to charge as cancellation fee after free cancellation period', 'fp-esperienze'),
                    'type'        => 'number',
                    'custom_attributes' => [
                        'step' => '0.01',
                        'min'  => '0',
                        'max'  => '100'
                    ]
                ]);

                // No-show policy
                woocommerce_wp_select([
                    'id'          => '_fp_exp_no_show_policy',
                    'label'       => __('No-Show Policy', 'fp-esperienze'),
                    'options'     => [
                        'no_refund'     => __('No refund', 'fp-esperienze'),
                        'partial_refund' => __('Partial refund (use cancellation fee %)', 'fp-esperienze'),
                        'full_refund'   => __('Full refund', 'fp-esperienze'),
                    ],
                    'desc_tip'    => true,
                    'description' => __('Policy for customers who do not show up for their experience', 'fp-esperienze')
                ]);
                
                ?>
            </div>
            
            <div class="options_group">
                <h4><?php _e('Schedules', 'fp-esperienze'); ?></h4>
                <div id="fp-schedules-container">
                    <?php $this->renderSchedulesSection($post->ID); ?>
                </div>
                <button type="button" class="button" id="fp-add-schedule">
                    <?php _e('Add Schedule', 'fp-esperienze'); ?>
                </button>
            </div>
            
            <div class="options_group">
                <h4><?php _e('Date Overrides', 'fp-esperienze'); ?></h4>
                <div id="fp-overrides-container">
                    <?php $this->renderOverridesSection($post->ID); ?>
                </div>
                <button type="button" class="button" id="fp-add-override">
                    <?php _e('Add Override', 'fp-esperienze'); ?>
                </button>
            </div>
            
            <div class="options_group">
                <h4><?php _e('Extras', 'fp-esperienze'); ?></h4>
                <div id="fp-extras-container">
                    <?php $this->renderExtrasSection($post->ID); ?>
                </div>
            </div>

            ?>
        </div>
        
        <div id="dynamic_pricing_product_data" class="panel woocommerce_options_panel">
            <?php $this->renderDynamicPricingPanel($post->ID); ?>
        </div>
        <?php
    }
    
    /**
     * Render schedules section
     *
     * @param int $product_id Product ID
     */
    private function renderSchedulesSection(int $product_id): void {
        $schedules = ScheduleManager::getSchedules($product_id);
        $meeting_points = $this->getMeetingPoints();
        
        foreach ($schedules as $index => $schedule) {
            $this->renderScheduleRow($schedule, $index, $meeting_points);
        }
    }
    
    /**
     * Render a single schedule row
     *
     * @param object $schedule Schedule object
     * @param int $index Row index
     * @param array $meeting_points Meeting points options
     */
    private function renderScheduleRow($schedule, int $index, array $meeting_points): void {
        $days = [
            0 => __('Sunday', 'fp-esperienze'),
            1 => __('Monday', 'fp-esperienze'),
            2 => __('Tuesday', 'fp-esperienze'),
            3 => __('Wednesday', 'fp-esperienze'),
            4 => __('Thursday', 'fp-esperienze'),
            5 => __('Friday', 'fp-esperienze'),
            6 => __('Saturday', 'fp-esperienze'),
        ];
        
        ?>
        <div class="fp-schedule-row" data-index="<?php echo esc_attr($index); ?>" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; background: #f9f9f9; border-radius: 4px;">
            <input type="hidden" name="schedules[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($schedule->id ?? ''); ?>">
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px;">
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Day of Week', 'fp-esperienze'); ?> <span style="color: red;">*</span>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('Which day of the week this schedule applies to', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <select name="schedules[<?php echo esc_attr($index); ?>][day_of_week]" required style="width: 100%;">
                        <option value=""><?php _e('Select Day', 'fp-esperienze'); ?></option>
                        <?php foreach ($days as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($schedule->day_of_week ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Start Time', 'fp-esperienze'); ?> <span style="color: red;">*</span>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('When the experience starts (24-hour format)', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <input type="time" 
                           name="schedules[<?php echo esc_attr($index); ?>][start_time]" 
                           value="<?php echo esc_attr($schedule->start_time ?? ''); ?>" 
                           required 
                           style="width: 100%;"
                           title="<?php esc_attr_e('Experience start time', 'fp-esperienze'); ?>">
                </div>
                
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Duration (minutes)', 'fp-esperienze'); ?> <span style="color: red;">*</span>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('How long the experience lasts in minutes', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <input type="number" 
                           name="schedules[<?php echo esc_attr($index); ?>][duration_min]" 
                           value="<?php echo esc_attr($schedule->duration_min ?? 60); ?>" 
                           placeholder="60" 
                           min="1" 
                           step="1" 
                           required 
                           style="width: 100%;"
                           title="<?php esc_attr_e('Duration in minutes (minimum 1)', 'fp-esperienze'); ?>">
                </div>
                
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Max Capacity', 'fp-esperienze'); ?> <span style="color: red;">*</span>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('Maximum number of participants for this schedule', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <input type="number" 
                           name="schedules[<?php echo esc_attr($index); ?>][capacity]" 
                           value="<?php echo esc_attr($schedule->capacity ?? 10); ?>" 
                           placeholder="10" 
                           min="1" 
                           step="1" 
                           required 
                           style="width: 100%;"
                           title="<?php esc_attr_e('Maximum participants (minimum 1)', 'fp-esperienze'); ?>">
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 10px;">
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Language', 'fp-esperienze'); ?>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('Experience language code (e.g., en, it, es)', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <input type="text" 
                           name="schedules[<?php echo esc_attr($index); ?>][lang]" 
                           value="<?php echo esc_attr($schedule->lang ?? 'en'); ?>" 
                           placeholder="en" 
                           maxlength="10" 
                           style="width: 100%;"
                           title="<?php esc_attr_e('Language code (ISO format preferred)', 'fp-esperienze'); ?>">
                </div>
                
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Meeting Point', 'fp-esperienze'); ?>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('Where participants should meet for this experience', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <select name="schedules[<?php echo esc_attr($index); ?>][meeting_point_id]" style="width: 100%;">
                        <?php foreach ($meeting_points as $value => $label): ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($schedule->meeting_point_id ?? '', $value); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Adult Price', 'fp-esperienze'); ?>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('Price per adult participant (leave empty to use default product price)', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <input type="number" 
                           name="schedules[<?php echo esc_attr($index); ?>][price_adult]" 
                           value="<?php echo esc_attr($schedule->price_adult ?? ''); ?>" 
                           placeholder="0.00" 
                           min="0" 
                           step="0.01" 
                           style="width: 100%;"
                           title="<?php esc_attr_e('Adult price (optional override)', 'fp-esperienze'); ?>">
                </div>
                
                <div>
                    <label style="font-weight: bold; display: block; margin-bottom: 5px;">
                        <?php _e('Child Price', 'fp-esperienze'); ?>
                        <span class="dashicons dashicons-info" title="<?php esc_attr_e('Price per child participant (leave empty to use default or no child pricing)', 'fp-esperienze'); ?>" style="font-size: 14px; color: #666;"></span>
                    </label>
                    <input type="number" 
                           name="schedules[<?php echo esc_attr($index); ?>][price_child]" 
                           value="<?php echo esc_attr($schedule->price_child ?? ''); ?>" 
                           placeholder="0.00" 
                           min="0" 
                           step="0.01" 
                           style="width: 100%;"
                           title="<?php esc_attr_e('Child price (optional)', 'fp-esperienze'); ?>">
                </div>
            </div>
            
            <div style="text-align: right;">
                <button type="button" class="button fp-remove-schedule" style="color: #dc3545;">
                    <span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>
                    <?php _e('Remove Schedule', 'fp-esperienze'); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render overrides section
     *
     * @param int $product_id Product ID
     */
    private function renderOverridesSection(int $product_id): void {
        $overrides = OverrideManager::getOverrides($product_id);
        
        foreach ($overrides as $index => $override) {
            $this->renderOverrideRow($override, $index);
        }
    }
    
    /**
     * Render a single override row
     *
     * @param object $override Override object
     * @param int $index Row index
     */
    private function renderOverrideRow($override, int $index): void {
        $price_override = $override->price_override_json ? json_decode($override->price_override_json, true) : [];
        
        ?>
        <div class="fp-override-row" data-index="<?php echo esc_attr($index); ?>">
            <input type="hidden" name="overrides[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($override->id ?? ''); ?>">
            
            <input type="date" name="overrides[<?php echo esc_attr($index); ?>][date]" 
                   value="<?php echo esc_attr($override->date ?? ''); ?>" required>
            
            <label>
                <input type="checkbox" name="overrides[<?php echo esc_attr($index); ?>][is_closed]" 
                       value="1" <?php checked($override->is_closed ?? 0, 1); ?>>
                <?php _e('Closed', 'fp-esperienze'); ?>
            </label>
            
            <input type="number" name="overrides[<?php echo esc_attr($index); ?>][capacity_override]" 
                   value="<?php echo esc_attr($override->capacity_override ?? ''); ?>" 
                   placeholder="<?php _e('Capacity Override', 'fp-esperienze'); ?>" min="0" step="1">
            
            <input type="number" name="overrides[<?php echo esc_attr($index); ?>][price_adult]" 
                   value="<?php echo esc_attr($price_override['adult'] ?? ''); ?>" 
                   placeholder="<?php _e('Adult Price', 'fp-esperienze'); ?>" min="0" step="0.01">
            
            <input type="number" name="overrides[<?php echo esc_attr($index); ?>][price_child]" 
                   value="<?php echo esc_attr($price_override['child'] ?? ''); ?>" 
                   placeholder="<?php _e('Child Price', 'fp-esperienze'); ?>" min="0" step="0.01">
            
            <input type="text" name="overrides[<?php echo esc_attr($index); ?>][reason]" 
                   value="<?php echo esc_attr($override->reason ?? ''); ?>" 
                   placeholder="<?php _e('Reason', 'fp-esperienze'); ?>">
            
            <button type="button" class="button fp-remove-override"><?php _e('Remove', 'fp-esperienze'); ?></button>
        </div>
        <?php
    }

    /**
     * Render extras section
     *
     * @param int $product_id Product ID
     */
    private function renderExtrasSection(int $product_id): void {
        $all_extras = ExtraManager::getAllExtras(true); // Only active extras
        $product_extras = ExtraManager::getProductExtras($product_id, false); // Include inactive for editing
        $selected_extra_ids = array_column($product_extras, 'id');
        
        ?>
        <div class="fp-extras-selection">
            <p><?php _e('Select which extras are available for this experience:', 'fp-esperienze'); ?></p>
            
            <?php if (empty($all_extras)) : ?>
                <p class="description">
                    <?php 
                    printf(
                        __('No extras available. <a href="%s">Create some extras</a> first.', 'fp-esperienze'),
                        admin_url('admin.php?page=fp-esperienze-extras')
                    ); 
                    ?>
                </p>
            <?php else : ?>
                <div class="fp-available-extras">
                    <?php foreach ($all_extras as $extra) : ?>
                        <label class="fp-extra-checkbox">
                            <input type="checkbox" 
                                   name="fp_product_extras[]" 
                                   value="<?php echo esc_attr($extra->id); ?>"
                                   <?php checked(in_array($extra->id, $selected_extra_ids)); ?>>
                            <strong><?php echo esc_html($extra->name); ?></strong>
                            (<?php echo wc_price($extra->price); ?> 
                            <?php echo esc_html($extra->billing_type === 'per_person' ? __('per person', 'fp-esperienze') : __('per booking', 'fp-esperienze')); ?>)
                            <?php if ($extra->description) : ?>
                                <br><span class="description"><?php echo esc_html($extra->description); ?></span>
                            <?php endif; ?>
                        </label><br>
                    <?php endforeach; ?>
                </div>
                
                <style>
                .fp-extra-checkbox {
                    display: block;
                    margin: 8px 0;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    background: #f9f9f9;
                }
                .fp-extra-checkbox:hover {
                    background: #f0f0f0;
                }
                .fp-extra-checkbox input {
                    margin-right: 8px;
                }
                </style>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save product data
     *
     * @param int $post_id Post ID
     */
    public function saveProductData(int $post_id): void {
        // Check nonce
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }
        
        // Save basic experience fields
        $fields = [
            '_experience_duration',
            '_experience_capacity',
            '_experience_adult_price',
            '_experience_child_price',
            '_experience_adult_tax_class',
            '_experience_child_tax_class',
            '_experience_languages',
            '_fp_exp_meeting_point_id',
            '_fp_exp_cutoff_minutes',
            '_fp_exp_free_cancel_until_minutes',
            '_fp_exp_cancel_fee_percent',
            '_fp_exp_no_show_policy'
        ];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Save schedules
        $this->saveSchedules($post_id);
        
        // Save overrides
        $this->saveOverrides($post_id);
        
        // Save extras
        $this->saveExtras($post_id);
        
        // Save dynamic pricing rules
        $this->savePricingRules($post_id);
    }
    
    /**
     * Save dynamic pricing rules
     *
     * @param int $product_id Product ID
     */
    private function savePricingRules(int $product_id): void {
        if (!isset($_POST['pricing_rules']) || !is_array($_POST['pricing_rules'])) {
            return;
        }
        
        // First, delete all existing rules for this product
        global $wpdb;
        $table_name = $wpdb->prefix . 'fp_dynamic_pricing_rules';
        $wpdb->delete($table_name, ['product_id' => $product_id], ['%d']);
        
        // Save new rules
        foreach ($_POST['pricing_rules'] as $rule_data) {
            if (empty($rule_data['rule_name']) || empty($rule_data['rule_type'])) {
                continue;
            }
            
            $rule_data['product_id'] = $product_id;
            DynamicPricingManager::saveRule($rule_data);
        }
    }
    
    /**
     * Save schedules data
     *
     * @param int $product_id Product ID
     */
    private function saveSchedules(int $product_id): void {
        if (!isset($_POST['schedules']) || !is_array($_POST['schedules'])) {
            return;
        }
        
        // Get existing schedules
        $existing_schedules = ScheduleManager::getSchedules($product_id);
        $existing_ids = array_column($existing_schedules, 'id');
        $processed_ids = [];
        $validation_errors = [];
        $discarded_count = 0;
        
        foreach ($_POST['schedules'] as $index => $schedule_data) {
            // Validate required fields
            if (empty($schedule_data['day_of_week']) || empty($schedule_data['start_time'])) {
                $discarded_count++;
                continue;
            }
            
            // Validate time format (HH:MM)
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $schedule_data['start_time'])) {
                $validation_errors[] = sprintf(__('Row %d: Invalid time format. Use HH:MM format.', 'fp-esperienze'), $index + 1);
                $discarded_count++;
                continue;
            }
            
            // Validate duration (must be > 0)
            $duration = (int) ($schedule_data['duration_min'] ?: 60);
            if ($duration <= 0) {
                $validation_errors[] = sprintf(__('Row %d: Duration must be greater than 0 minutes.', 'fp-esperienze'), $index + 1);
                $discarded_count++;
                continue;
            }
            
            // Validate capacity (must be >= 1)
            $capacity = (int) ($schedule_data['capacity'] ?: 10);
            if ($capacity < 1) {
                $validation_errors[] = sprintf(__('Row %d: Capacity must be at least 1 participant.', 'fp-esperienze'), $index + 1);
                $discarded_count++;
                continue;
            }
            
            $schedule_id = !empty($schedule_data['id']) ? (int) $schedule_data['id'] : 0;
            
            $data = [
                'product_id' => $product_id,
                'day_of_week' => (int) $schedule_data['day_of_week'],
                'start_time' => sanitize_text_field($schedule_data['start_time']),
                'duration_min' => $duration,
                'capacity' => $capacity,
                'lang' => sanitize_text_field($schedule_data['lang'] ?: 'en'),
                'meeting_point_id' => !empty($schedule_data['meeting_point_id']) ? (int) $schedule_data['meeting_point_id'] : null,
                'price_adult' => (float) ($schedule_data['price_adult'] ?: 0),
                'price_child' => (float) ($schedule_data['price_child'] ?: 0),
                'is_active' => 1
            ];
            
            if ($schedule_id > 0) {
                // Update existing schedule
                ScheduleManager::updateSchedule($schedule_id, $data);
                $processed_ids[] = $schedule_id;
            } else {
                // Create new schedule
                $new_id = ScheduleManager::createSchedule($data);
                if ($new_id) {
                    $processed_ids[] = $new_id;
                }
            }
        }
        
        // Delete schedules that were removed
        $ids_to_delete = array_diff($existing_ids, $processed_ids);
        foreach ($ids_to_delete as $id) {
            ScheduleManager::deleteSchedule($id);
        }
        
        // Store validation feedback in transients for display
        if (!empty($validation_errors)) {
            set_transient("fp_schedule_validation_errors_{$product_id}", $validation_errors, 60);
        }
        
        if ($discarded_count > 0) {
            set_transient("fp_schedule_discarded_{$product_id}", $discarded_count, 60);
        }
        
        // Set success notice if schedules were saved
        if (!empty($processed_ids)) {
            set_transient("fp_schedule_saved_{$product_id}", count($processed_ids), 60);
        }
    }
    
    /**
     * Save overrides data
     *
     * @param int $product_id Product ID
     */
    private function saveOverrides(int $product_id): void {
        if (!isset($_POST['overrides']) || !is_array($_POST['overrides'])) {
            return;
        }
        
        foreach ($_POST['overrides'] as $override_data) {
            if (empty($override_data['date'])) {
                continue;
            }
            
            $price_override = [];
            if (!empty($override_data['price_adult'])) {
                $price_override['adult'] = (float) $override_data['price_adult'];
            }
            if (!empty($override_data['price_child'])) {
                $price_override['child'] = (float) $override_data['price_child'];
            }
            
            $data = [
                'product_id' => $product_id,
                'date' => sanitize_text_field($override_data['date']),
                'is_closed' => !empty($override_data['is_closed']) ? 1 : 0,
                'capacity_override' => !empty($override_data['capacity_override']) ? (int) $override_data['capacity_override'] : null,
                'price_override_json' => !empty($price_override) ? $price_override : null,
                'reason' => sanitize_text_field($override_data['reason'] ?? '')
            ];
            
            OverrideManager::saveOverride($data);
        }
    }

    /**
     * Save extras
     *
     * @param int $product_id Product ID
     */
    private function saveExtras(int $product_id): void {
        $selected_extras = isset($_POST['fp_product_extras']) ? array_map('absint', $_POST['fp_product_extras']) : [];
        ExtraManager::updateProductExtras($product_id, $selected_extras);
    }

    /**
     * Get meeting points for select dropdown
     *
     * @return array
     */
    private function getMeetingPoints(): array {
        return MeetingPointManager::getMeetingPointsForSelect();
    }
    
    /**
     * Render dynamic pricing panel
     *
     * @param int $product_id Product ID
     */
    private function renderDynamicPricingPanel(int $product_id): void {
        $rules = DynamicPricingManager::getProductRules($product_id, false);
        wp_nonce_field('fp_pricing_nonce', 'fp_pricing_nonce');
        ?>
        
        <div class="options_group">
            <h4><?php _e('Dynamic Pricing Rules', 'fp-esperienze'); ?></h4>
            
            <div id="fp-pricing-rules-container">
                <?php foreach ($rules as $index => $rule) {
                    $this->renderPricingRuleRow($rule, $index);
                } ?>
            </div>
            
            <button type="button" id="fp-add-pricing-rule" class="button">
                <?php _e('Add Pricing Rule', 'fp-esperienze'); ?>
            </button>
        </div>
        
        <div class="options_group">
            <h4><?php _e('Pricing Preview', 'fp-esperienze'); ?></h4>
            
            <div class="fp-pricing-preview">
                <div class="fp-preview-inputs">
                    <div>
                        <label><?php _e('Booking Date', 'fp-esperienze'); ?></label>
                        <input type="date" id="fp-preview-booking-date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label><?php _e('Purchase Date', 'fp-esperienze'); ?></label>
                        <input type="date" id="fp-preview-purchase-date" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div>
                        <label><?php _e('Adults', 'fp-esperienze'); ?></label>
                        <input type="number" id="fp-preview-qty-adult" value="2" min="0">
                    </div>
                    <div>
                        <label><?php _e('Children', 'fp-esperienze'); ?></label>
                        <input type="number" id="fp-preview-qty-child" value="0" min="0">
                    </div>
                    <div>
                        <button type="button" id="fp-preview-calculate" class="button">
                            <?php _e('Calculate', 'fp-esperienze'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="fp-preview-results" style="margin-top: 15px;"></div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var ruleIndex = <?php echo count($rules); ?>;
                
                // Add pricing rule
                $('#fp-add-pricing-rule').click(function() {
                    var html = buildPricingRuleTemplate(ruleIndex);
                    $('#fp-pricing-rules-container').append(html);
                    ruleIndex++;
                });
                
                // Remove pricing rule
                $(document).on('click', '.fp-remove-pricing-rule', function() {
                    $(this).closest('.fp-pricing-rule-row').remove();
                });
                
                // Preview calculation
                $('#fp-preview-calculate').click(function() {
                    var data = {
                        action: 'fp_preview_pricing',
                        product_id: <?php echo $product_id; ?>,
                        booking_date: $('#fp-preview-booking-date').val(),
                        purchase_date: $('#fp-preview-purchase-date').val(),
                        qty_adult: $('#fp-preview-qty-adult').val(),
                        qty_child: $('#fp-preview-qty-child').val(),
                        nonce: $('#fp_pricing_nonce').val()
                    };
                    
                    $.post(ajaxurl, data, function(response) {
                        if (response.success) {
                            var result = response.data;
                            var html = '<h5><?php _e("Price Breakdown", "fp-esperienze"); ?></h5>';
                            
                            html += '<table class="widefat">';
                            html += '<tr><td><?php _e("Base Adult Price", "fp-esperienze"); ?></td><td>' + result.base_prices.adult + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
                            html += '<tr><td><?php _e("Base Child Price", "fp-esperienze"); ?></td><td>' + result.base_prices.child + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
                            html += '<tr><td><?php _e("Final Adult Price", "fp-esperienze"); ?></td><td>' + result.final_prices.adult + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
                            html += '<tr><td><?php _e("Final Child Price", "fp-esperienze"); ?></td><td>' + result.final_prices.child + ' <?php echo get_woocommerce_currency_symbol(); ?></td></tr>';
                            html += '<tr><td><strong><?php _e("Total Base", "fp-esperienze"); ?></strong></td><td><strong>' + result.total.base + ' <?php echo get_woocommerce_currency_symbol(); ?></strong></td></tr>';
                            html += '<tr><td><strong><?php _e("Total Final", "fp-esperienze"); ?></strong></td><td><strong>' + result.total.final + ' <?php echo get_woocommerce_currency_symbol(); ?></strong></td></tr>';
                            html += '</table>';
                            
                            if (result.applied_rules.adult.length > 0 || result.applied_rules.child.length > 0) {
                                html += '<h5><?php _e("Applied Rules", "fp-esperienze"); ?></h5>';
                                // Add applied rules details here
                            }
                            
                            $('#fp-preview-results').html(html);
                        } else {
                            $('#fp-preview-results').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    });
                });
                
                // Rule type change handler
                $(document).on('change', '.fp-rule-type', function() {
                    var ruleType = $(this).val();
                    var container = $(this).closest('.fp-pricing-rule-row');
                    
                    // Hide all conditional fields first
                    container.find('.fp-rule-field').hide();
                    
                    // Show relevant fields based on rule type
                    switch(ruleType) {
                        case 'seasonal':
                            container.find('.fp-field-dates').show();
                            break;
                        case 'weekend_weekday':
                            container.find('.fp-field-applies-to').show();
                            break;
                        case 'early_bird':
                            container.find('.fp-field-days-before').show();
                            break;
                        case 'group':
                            container.find('.fp-field-min-participants').show();
                            break;
                    }
                });
                
                // Trigger change event for existing rules
                $('.fp-rule-type').trigger('change');
                
                // Build pricing rule template
                function buildPricingRuleTemplate(index) {
                    return '<div class="fp-pricing-rule-row" data-index="' + index + '" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 10px;">' +
                        '<input type="hidden" name="pricing_rules[' + index + '][id]" value="">' +
                        '<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">' +
                            '<div>' +
                                '<label><?php _e('Rule Name', 'fp-esperienze'); ?></label>' +
                                '<input type="text" name="pricing_rules[' + index + '][rule_name]" value="" placeholder="<?php _e('Rule Name', 'fp-esperienze'); ?>" required style="width: 200px;">' +
                            '</div>' +
                            '<div>' +
                                '<label><?php _e('Type', 'fp-esperienze'); ?></label>' +
                                '<select name="pricing_rules[' + index + '][rule_type]" class="fp-rule-type" required>' +
                                    '<option value=""><?php _e('Select Type', 'fp-esperienze'); ?></option>' +
                                    '<option value="seasonal"><?php _e('Seasonal', 'fp-esperienze'); ?></option>' +
                                    '<option value="weekend_weekday"><?php _e('Weekend/Weekday', 'fp-esperienze'); ?></option>' +
                                    '<option value="early_bird"><?php _e('Early Bird', 'fp-esperienze'); ?></option>' +
                                    '<option value="group"><?php _e('Group Discount', 'fp-esperienze'); ?></option>' +
                                '</select>' +
                            '</div>' +
                            '<div>' +
                                '<label><?php _e('Priority', 'fp-esperienze'); ?></label>' +
                                '<input type="number" name="pricing_rules[' + index + '][priority]" value="0" min="0" step="1" style="width: 80px;">' +
                            '</div>' +
                            '<div>' +
                                '<label>' +
                                    '<input type="checkbox" name="pricing_rules[' + index + '][is_active]" value="1" checked>' +
                                    '<?php _e('Active', 'fp-esperienze'); ?>' +
                                '</label>' +
                            '</div>' +
                            '<button type="button" class="button fp-remove-pricing-rule"><?php _e('Remove', 'fp-esperienze'); ?></button>' +
                        '</div>' +
                        '<div class="fp-rule-field fp-field-dates" style="display: none; margin-bottom: 10px;">' +
                            '<label><?php _e('Date Range', 'fp-esperienze'); ?></label>' +
                            '<input type="date" name="pricing_rules[' + index + '][date_start]" value="" placeholder="<?php _e('Start Date', 'fp-esperienze'); ?>">' +
                            '<input type="date" name="pricing_rules[' + index + '][date_end]" value="" placeholder="<?php _e('End Date', 'fp-esperienze'); ?>">' +
                        '</div>' +
                        '<div class="fp-rule-field fp-field-applies-to" style="display: none; margin-bottom: 10px;">' +
                            '<label><?php _e('Applies To', 'fp-esperienze'); ?></label>' +
                            '<select name="pricing_rules[' + index + '][applies_to]">' +
                                '<option value=""><?php _e('Select...', 'fp-esperienze'); ?></option>' +
                                '<option value="weekend"><?php _e('Weekend', 'fp-esperienze'); ?></option>' +
                                '<option value="weekday"><?php _e('Weekday', 'fp-esperienze'); ?></option>' +
                            '</select>' +
                        '</div>' +
                        '<div class="fp-rule-field fp-field-days-before" style="display: none; margin-bottom: 10px;">' +
                            '<label><?php _e('Days Before', 'fp-esperienze'); ?></label>' +
                            '<input type="number" name="pricing_rules[' + index + '][days_before]" value="" placeholder="<?php _e('Days', 'fp-esperienze'); ?>" min="1">' +
                        '</div>' +
                        '<div class="fp-rule-field fp-field-min-participants" style="display: none; margin-bottom: 10px;">' +
                            '<label><?php _e('Minimum Participants', 'fp-esperienze'); ?></label>' +
                            '<input type="number" name="pricing_rules[' + index + '][min_participants]" value="" placeholder="<?php _e('Min Participants', 'fp-esperienze'); ?>" min="1">' +
                        '</div>' +
                        '<div style="display: flex; gap: 10px; align-items: center;">' +
                            '<div>' +
                                '<label><?php _e('Adjustment Type', 'fp-esperienze'); ?></label>' +
                                '<select name="pricing_rules[' + index + '][adjustment_type]">' +
                                    '<option value="percentage"><?php _e('Percentage (%)', 'fp-esperienze'); ?></option>' +
                                    '<option value="fixed_amount"><?php _e('Fixed Amount', 'fp-esperienze'); ?></option>' +
                                '</select>' +
                            '</div>' +
                            '<div>' +
                                '<label><?php _e('Adult Adjustment', 'fp-esperienze'); ?></label>' +
                                '<input type="number" name="pricing_rules[' + index + '][adult_adjustment]" value="0" step="0.01" style="width: 100px;">' +
                            '</div>' +
                            '<div>' +
                                '<label><?php _e('Child Adjustment', 'fp-esperienze'); ?></label>' +
                                '<input type="number" name="pricing_rules[' + index + '][child_adjustment]" value="0" step="0.01" style="width: 100px;">' +
                            '</div>' +
                        '</div>' +
                    '</div>';
                }
            });
        </script>
        
        <?php
    }
    
    /**
     * Render a single pricing rule row
     *
     * @param object $rule Rule object
     * @param int $index Row index
     */
    private function renderPricingRuleRow($rule, int $index): void {
        echo $this->getPricingRuleRowTemplate($index, $rule);
    }
    
    /**
     * Get pricing rule row template
     *
     * @param mixed $index Row index or placeholder
     * @param object|null $rule Rule object
     * @return string HTML template
     */
    private function getPricingRuleRowTemplate(mixed $index, ?object $rule = null): string {
        ob_start();
        ?>
        <div class="fp-pricing-rule-row" data-index="<?php echo esc_attr($index); ?>" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 10px;">
            <input type="hidden" name="pricing_rules[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($rule->id ?? ''); ?>">
            
            <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px;">
                <div>
                    <label><?php _e('Rule Name', 'fp-esperienze'); ?></label>
                    <input type="text" name="pricing_rules[<?php echo esc_attr($index); ?>][rule_name]" 
                           value="<?php echo esc_attr($rule->rule_name ?? ''); ?>" 
                           placeholder="<?php _e('Rule Name', 'fp-esperienze'); ?>" required style="width: 200px;">
                </div>
                
                <div>
                    <label><?php _e('Type', 'fp-esperienze'); ?></label>
                    <select name="pricing_rules[<?php echo esc_attr($index); ?>][rule_type]" class="fp-rule-type" required>
                        <option value=""><?php _e('Select Type', 'fp-esperienze'); ?></option>
                        <option value="seasonal" <?php selected($rule->rule_type ?? '', 'seasonal'); ?>><?php _e('Seasonal', 'fp-esperienze'); ?></option>
                        <option value="weekend_weekday" <?php selected($rule->rule_type ?? '', 'weekend_weekday'); ?>><?php _e('Weekend/Weekday', 'fp-esperienze'); ?></option>
                        <option value="early_bird" <?php selected($rule->rule_type ?? '', 'early_bird'); ?>><?php _e('Early Bird', 'fp-esperienze'); ?></option>
                        <option value="group" <?php selected($rule->rule_type ?? '', 'group'); ?>><?php _e('Group Discount', 'fp-esperienze'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label><?php _e('Priority', 'fp-esperienze'); ?></label>
                    <input type="number" name="pricing_rules[<?php echo esc_attr($index); ?>][priority]" 
                           value="<?php echo esc_attr($rule->priority ?? 0); ?>" 
                           min="0" step="1" style="width: 80px;">
                </div>
                
                <div>
                    <label>
                        <input type="checkbox" name="pricing_rules[<?php echo esc_attr($index); ?>][is_active]" 
                               value="1" <?php checked($rule->is_active ?? 1, 1); ?>>
                        <?php _e('Active', 'fp-esperienze'); ?>
                    </label>
                </div>
                
                <button type="button" class="button fp-remove-pricing-rule"><?php _e('Remove', 'fp-esperienze'); ?></button>
            </div>
            
            <!-- Rule-specific fields -->
            <div class="fp-rule-field fp-field-dates" style="display: none; margin-bottom: 10px;">
                <label><?php _e('Date Range', 'fp-esperienze'); ?></label>
                <input type="date" name="pricing_rules[<?php echo esc_attr($index); ?>][date_start]" 
                       value="<?php echo esc_attr($rule->date_start ?? ''); ?>" placeholder="<?php _e('Start Date', 'fp-esperienze'); ?>">
                <input type="date" name="pricing_rules[<?php echo esc_attr($index); ?>][date_end]" 
                       value="<?php echo esc_attr($rule->date_end ?? ''); ?>" placeholder="<?php _e('End Date', 'fp-esperienze'); ?>">
            </div>
            
            <div class="fp-rule-field fp-field-applies-to" style="display: none; margin-bottom: 10px;">
                <label><?php _e('Applies To', 'fp-esperienze'); ?></label>
                <select name="pricing_rules[<?php echo esc_attr($index); ?>][applies_to]">
                    <option value=""><?php _e('Select...', 'fp-esperienze'); ?></option>
                    <option value="weekend" <?php selected($rule->applies_to ?? '', 'weekend'); ?>><?php _e('Weekend', 'fp-esperienze'); ?></option>
                    <option value="weekday" <?php selected($rule->applies_to ?? '', 'weekday'); ?>><?php _e('Weekday', 'fp-esperienze'); ?></option>
                </select>
            </div>
            
            <div class="fp-rule-field fp-field-days-before" style="display: none; margin-bottom: 10px;">
                <label><?php _e('Days Before', 'fp-esperienze'); ?></label>
                <input type="number" name="pricing_rules[<?php echo esc_attr($index); ?>][days_before]" 
                       value="<?php echo esc_attr($rule->days_before ?? ''); ?>" 
                       placeholder="<?php _e('Days', 'fp-esperienze'); ?>" min="1">
            </div>
            
            <div class="fp-rule-field fp-field-min-participants" style="display: none; margin-bottom: 10px;">
                <label><?php _e('Minimum Participants', 'fp-esperienze'); ?></label>
                <input type="number" name="pricing_rules[<?php echo esc_attr($index); ?>][min_participants]" 
                       value="<?php echo esc_attr($rule->min_participants ?? ''); ?>" 
                       placeholder="<?php _e('Min Participants', 'fp-esperienze'); ?>" min="1">
            </div>
            
            <!-- Adjustment fields -->
            <div style="display: flex; gap: 10px; align-items: center;">
                <div>
                    <label><?php _e('Adjustment Type', 'fp-esperienze'); ?></label>
                    <select name="pricing_rules[<?php echo esc_attr($index); ?>][adjustment_type]">
                        <option value="percentage" <?php selected($rule->adjustment_type ?? 'percentage', 'percentage'); ?>><?php _e('Percentage (%)', 'fp-esperienze'); ?></option>
                        <option value="fixed_amount" <?php selected($rule->adjustment_type ?? 'percentage', 'fixed_amount'); ?>><?php _e('Fixed Amount', 'fp-esperienze'); ?></option>
                    </select>
                </div>
                
                <div>
                    <label><?php _e('Adult Adjustment', 'fp-esperienze'); ?></label>
                    <input type="number" name="pricing_rules[<?php echo esc_attr($index); ?>][adult_adjustment]" 
                           value="<?php echo esc_attr($rule->adult_adjustment ?? 0); ?>" 
                           step="0.01" style="width: 100px;">
                </div>
                
                <div>
                    <label><?php _e('Child Adjustment', 'fp-esperienze'); ?></label>
                    <input type="number" name="pricing_rules[<?php echo esc_attr($index); ?>][child_adjustment]" 
                           value="<?php echo esc_attr($rule->child_adjustment ?? 0); ?>" 
                           step="0.01" style="width: 100px;">
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Show schedule validation notices
     */
    public function showScheduleValidationNotices(): void {
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'product') {
            return;
        }
        
        $product_id = get_the_ID();
        if (!$product_id) {
            return;
        }
        
        // Check for validation errors
        $validation_errors = get_transient("fp_schedule_validation_errors_{$product_id}");
        if ($validation_errors) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>' . __('Schedule Validation Errors:', 'fp-esperienze') . '</strong><br>';
            foreach ($validation_errors as $error) {
                echo '• ' . esc_html($error) . '<br>';
            }
            echo '</p></div>';
            delete_transient("fp_schedule_validation_errors_{$product_id}");
        }
        
        // Check for discarded schedules
        $discarded_count = get_transient("fp_schedule_discarded_{$product_id}");
        if ($discarded_count) {
            echo '<div class="notice notice-warning"><p>';
            echo sprintf(
                _n('%d invalid schedule was discarded.', '%d invalid schedules were discarded.', $discarded_count, 'fp-esperienze'),
                $discarded_count
            );
            echo '</p></div>';
            delete_transient("fp_schedule_discarded_{$product_id}");
        }
        
        // Check for successful saves
        $saved_count = get_transient("fp_schedule_saved_{$product_id}");
        if ($saved_count) {
            echo '<div class="notice notice-success"><p>';
            echo sprintf(
                _n('%d schedule saved successfully.', '%d schedules saved successfully.', $saved_count, 'fp-esperienze'),
                $saved_count
            );
            echo '</p></div>';
            delete_transient("fp_schedule_saved_{$product_id}");
        }
    }
}