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
use FP\Esperienze\Helpers\ScheduleHelper;

defined('ABSPATH') || exit;

/**
 * Experience product type class
 */
class Experience {

    /**
     * Constructor
     */
    public function __construct() {
        // Load the WC_Product_Experience class immediately to ensure it's available
        $this->loadProductClass();
        
        add_action('init', [$this, 'init']);
        add_filter('product_type_selector', [$this, 'addProductType']);
        add_filter('woocommerce_product_class', [$this, 'getProductClass'], 10, 2);
        add_filter('woocommerce_product_data_tabs', [$this, 'addProductDataTabs']);
        add_action('woocommerce_product_data_panels', [$this, 'addProductDataPanels']);
        
        // Hook into product type saving with higher priority and multiple hooks
        add_action('woocommerce_process_product_meta', [$this, 'saveProductData'], 20);
        // Also hook into the product save process to ensure type is preserved
        add_action('woocommerce_update_product', [$this, 'ensureProductType'], 5);
        add_action('woocommerce_new_product', [$this, 'ensureProductType'], 5);
        
        add_action('admin_notices', [$this, 'showScheduleValidationNotices']);
        
        // Additional hooks for proper WooCommerce integration
        add_filter('woocommerce_data_stores', [$this, 'registerDataStore'], 10, 1);
        add_action('woocommerce_product_options_general_product_data', [$this, 'addExperienceProductFields']);
        
        // Ensure admin scripts are loaded on product edit pages
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
    }

    /**
     * Load the WC_Product_Experience class
     */
    private function loadProductClass(): void {
        // Only load if not already loaded and WooCommerce is available
        if (!class_exists('WC_Product_Experience') && class_exists('WC_Product')) {
            require_once FP_ESPERIENZE_PLUGIN_DIR . 'includes/ProductType/WC_Product_Experience.php';
        }
    }

    /**
     * Initialize
     */
    public function init(): void {
        // Class is already loaded in constructor, but keep this for any future initialization needs
        $this->loadProductClass();
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
     * Register data store for experience products
     *
     * @param array $stores Data stores
     * @return array
     */
    public function registerDataStore(array $stores): array {
        $stores['product-experience'] = 'WC_Product_Data_Store_CPT';
        return $stores;
    }

    /**
     * Get product class for experience products
     *
     * @param string $classname Current class name
     * @param string $product_type Product type
     * @return string
     */
    public function getProductClass(string $classname, string $product_type): string {
        if ($product_type === 'experience') {
            // Ensure the WC_Product_Experience class is loaded when needed
            if (!class_exists('WC_Product_Experience')) {
                $this->loadProductClass();
            }
            return 'WC_Product_Experience';
        }
        return $classname;
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
                'id'          => '_fp_exp_duration',
                'label'       => __('Default Duration (minutes)', 'fp-esperienze'),
                'placeholder' => '60',
                'desc_tip'    => true,
                'description' => __('Default experience duration in minutes (used as fallback for schedules)', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '1',
                    'min'  => '1'
                ]
            ]);

            // Capacity
            woocommerce_wp_text_input([
                'id'          => '_fp_exp_capacity',
                'label'       => __('Default Max Capacity', 'fp-esperienze'),
                'placeholder' => '10',
                'desc_tip'    => true,
                'description' => __('Default maximum number of participants (used as fallback for schedules)', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '1',
                    'min'  => '1'
                ]
            ]);

            // Default Language
            woocommerce_wp_text_input([
                'id'          => '_fp_exp_language',
                'label'       => __('Default Language', 'fp-esperienze'),
                'placeholder' => 'en',
                'desc_tip'    => true,
                'description' => __('Default language code for this experience (e.g., en, it, es)', 'fp-esperienze'),
                'custom_attributes' => [
                    'maxlength' => '10'
                ]
            ]);

            // Child Price
            woocommerce_wp_text_input([
                'id'          => '_fp_exp_price_child',
                'label'       => __('Default Child Price', 'fp-esperienze') . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => '0.00',
                'desc_tip'    => true,
                'description' => __('Default price per child participant (used as fallback for schedules)', 'fp-esperienze'),
                'type'        => 'number',
                'custom_attributes' => [
                    'step' => '0.01',
                    'min'  => '0'
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

            // What's included
            woocommerce_wp_textarea_input([
                'id'          => '_fp_exp_included',
                'label'       => __("What's Included", 'fp-esperienze'),
                'placeholder' => __("Professional guide\nAll activities as described\nSmall group experience", 'fp-esperienze'),
                'desc_tip'    => true,
                'description' => __('List what is included in the experience (one item per line)', 'fp-esperienze'),
                'rows'        => 5
            ]);

            // What's excluded  
            woocommerce_wp_textarea_input([
                'id'          => '_fp_exp_excluded',
                'label'       => __("What's Not Included", 'fp-esperienze'),
                'placeholder' => __("Hotel pickup and drop-off\nFood and drinks\nPersonal expenses\nGratuities", 'fp-esperienze'),
                'desc_tip'    => true,
                'description' => __('List what is not included in the experience (one item per line)', 'fp-esperienze'),
                'rows'        => 5
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
            
            <div class="options_group fp-schedules-section">
                <h4>
                    <?php _e('Recurring Time Slots', 'fp-esperienze'); ?>
                </h4>
                
                <div class="fp-section-content">
                    <div class="fp-section-description">
                        <?php _e('Configure weekly recurring time slots for your experience. Each slot can run on multiple days and can have custom settings that override the default product values above.', 'fp-esperienze'); ?>
                    </div>
                    
                    <div id="fp-schedule-builder-container" style="margin-bottom: 20px;">
                        <?php $this->renderScheduleBuilder($post->ID); ?>
                    </div>
                    
                    <div id="fp-schedule-raw-container" style="display: none;">
                        <h5><?php _e('Advanced Mode (Raw Schedules)', 'fp-esperienze'); ?></h5>
                        <div id="fp-schedules-container">
                            <?php $this->renderSchedulesSection($post->ID); ?>
                        </div>
                        <button type="button" class="button" id="fp-add-schedule">
                            <?php _e('Add Schedule', 'fp-esperienze'); ?>
                        </button>
                    </div>
                    
                    <p>
                        <label>
                            <input type="checkbox" id="fp-toggle-raw-mode"> 
                            <?php _e('Show Advanced Mode', 'fp-esperienze'); ?>
                        </label>
                        <span class="description"><?php _e('Enable to view/edit individual schedule rows directly', 'fp-esperienze'); ?></span>
                    </p>
                </div>
            </div>
            
            <div class="options_group fp-overrides-section-wrapper">
                <h4><?php _e('Date-Specific Overrides', 'fp-esperienze'); ?></h4>
                
                <div class="fp-section-content">
                    <div class="fp-section-description">
                        <?php _e('Add exceptions for specific dates: close the experience, change capacity, or modify prices for particular days.', 'fp-esperienze'); ?>
                    </div>
                    
                    <div id="fp-overrides-container">
                        <?php $this->renderOverridesSection($post->ID); ?>
                    </div>
                    <button type="button" class="button fp-add-override" id="fp-add-override">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Add Date Override', 'fp-esperienze'); ?>
                    </button>
                </div>
            </div>
            
            <div class="options_group">
                <h4><?php _e('Extras', 'fp-esperienze'); ?></h4>
                <div id="fp-extras-container">
                    <?php $this->renderExtrasSection($post->ID); ?>
                </div>
            </div>
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
     * Render the schedule builder UI
     *
     * @param int $product_id Product ID
     */
    private function renderScheduleBuilder(int $product_id): void {
        $schedules = ScheduleManager::getSchedules($product_id);
        $meeting_points = $this->getMeetingPoints();
        
        // Get product defaults for placeholders
        $default_duration = get_post_meta($product_id, '_fp_exp_duration', true) ?: '60';
        $default_capacity = get_post_meta($product_id, '_fp_exp_capacity', true) ?: '10';
        $default_language = get_post_meta($product_id, '_fp_exp_language', true) ?: 'en';
        $default_meeting_point = get_post_meta($product_id, '_fp_exp_meeting_point_id', true);
        $default_price_adult = get_post_meta($product_id, '_regular_price', true) ?: '0.00';
        $default_price_child = get_post_meta($product_id, '_fp_exp_price_child', true) ?: '0.00';
        
        // Aggregate existing schedules for builder view
        $aggregated = ScheduleHelper::aggregateSchedulesForBuilder($schedules, $product_id);
        
        $days = [
            1 => __('Monday', 'fp-esperienze'),
            2 => __('Tuesday', 'fp-esperienze'),
            3 => __('Wednesday', 'fp-esperienze'),
            4 => __('Thursday', 'fp-esperienze'),
            5 => __('Friday', 'fp-esperienze'),
            6 => __('Saturday', 'fp-esperienze'),
            0 => __('Sunday', 'fp-esperienze'),
        ];
        
        ?>
        <div id="fp-schedule-builder">
            <!-- Summary table -->
            <?php $this->renderSlotsSummaryTable($aggregated['time_slots'], $days); ?>
            
            <div class="fp-builder-section">
                <div id="fp-time-slots-container">
                    <?php foreach ($aggregated['time_slots'] as $index => $slot): ?>
                        <div class="fp-time-slot" data-index="<?php echo esc_attr($index); ?>">
                            <?php $this->renderTimeSlot($slot, $index, $days, $meeting_points, $default_duration, $default_capacity, $default_language, $default_meeting_point, $default_price_adult, $default_price_child, $product_id); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" class="button fp-add-time-slot" id="fp-add-time-slot">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Time Slot', 'fp-esperienze'); ?>
                </button>
            </div>
        </div>
        
        <!-- Hidden container for generated schedule inputs -->
        <div id="fp-generated-schedules" style="display: none;"></div>
        <?php
    }
    
    /**
     * Render a single time slot in the builder
     */
    private function renderTimeSlot($slot, $index, $days, $meeting_points, $default_duration, $default_capacity, $default_language, $default_meeting_point, $default_price_adult, $default_price_child, $product_id): void {
        $overrides = $slot['overrides'] ?? [];
        ?>
        <div class="fp-time-slot-row">
            <div class="fp-time-slot-header">
                <div class="fp-time-field">
                    <label>
                        <span class="dashicons dashicons-clock"></span>
                        <?php _e('Start Time', 'fp-esperienze'); ?> <span style="color: red;">*</span>
                    </label>
                    <input type="time" 
                           name="builder_slots[<?php echo esc_attr($index); ?>][start_time]" 
                           value="<?php echo esc_attr($slot['start_time'] ?? ''); ?>" 
                           required 
                           aria-describedby="fp-time-help-<?php echo esc_attr($index); ?>">
                    <div id="fp-time-help-<?php echo esc_attr($index); ?>" class="screen-reader-text">
                        <?php _e('Enter the start time for this experience slot in 24-hour format', 'fp-esperienze'); ?>
                    </div>
                </div>
                
                <div class="fp-days-field">
                    <label>
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php _e('Days of Week', 'fp-esperienze'); ?> <span style="color: red;">*</span>
                    </label>
                    <div class="fp-days-selector" aria-describedby="fp-days-help-<?php echo esc_attr($index); ?>">
                        <div class="fp-days-pills">
                            <?php foreach ($days as $day_value => $day_label): ?>
                                <div class="fp-day-pill">
                                    <input type="checkbox" 
                                           id="day-<?php echo esc_attr($index); ?>-<?php echo esc_attr($day_value); ?>"
                                           name="builder_slots[<?php echo esc_attr($index); ?>][days][]" 
                                           value="<?php echo esc_attr($day_value); ?>"
                                           <?php checked(in_array($day_value, $slot['days'] ?? [])); ?>>
                                    <label for="day-<?php echo esc_attr($index); ?>-<?php echo esc_attr($day_value); ?>">
                                        <?php echo esc_html(substr($day_label, 0, 3)); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="fp-days-help-<?php echo esc_attr($index); ?>" class="screen-reader-text">
                        <?php _e('Select which days of the week this time slot is available', 'fp-esperienze'); ?>
                    </div>
                </div>
                
                <div>
                    <button type="button" class="fp-remove-time-slot">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Remove', 'fp-esperienze'); ?>
                    </button>
                </div>
            </div>
            
            <div class="fp-override-toggle">
                <label>
                    <input type="checkbox" class="fp-show-overrides-toggle" <?php checked($this->hasActualOverrides($overrides, $index, $product_id)); ?>>
                    <span class="dashicons dashicons-admin-tools"></span>
                    <?php _e('Advanced Settings', 'fp-esperienze'); ?>
                </label>
                <span class="description"><?php _e('Override default values for this specific time slot', 'fp-esperienze'); ?></span>
                <!-- Hidden field to track if advanced settings are enabled for this slot -->
                <input type="hidden" name="builder_slots[<?php echo esc_attr($index); ?>][advanced_enabled]" value="<?php echo $this->hasActualOverrides($overrides, $index, $product_id) ? '1' : '0'; ?>" class="fp-advanced-enabled">
            </div>
            
            <div class="fp-overrides-section" style="<?php echo $this->hasActualOverrides($overrides, $index, $product_id) ? '' : 'display: none;'; ?>">
                <div>
                    <div>
                        <label>
                            <?php _e('Duration (minutes)', 'fp-esperienze'); ?>
                        </label>
                        <input type="number" 
                               name="builder_slots[<?php echo esc_attr($index); ?>][duration_min]" 
                               value="<?php echo esc_attr($overrides['duration_min'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr(sprintf(__('Default: %s', 'fp-esperienze'), $default_duration)); ?>" 
                               min="1">
                    </div>
                    
                    <div>
                        <label>
                            <?php _e('Capacity', 'fp-esperienze'); ?>
                        </label>
                        <input type="number" 
                               name="builder_slots[<?php echo esc_attr($index); ?>][capacity]" 
                               value="<?php echo esc_attr($overrides['capacity'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr(sprintf(__('Default: %s', 'fp-esperienze'), $default_capacity)); ?>">
                    </div>
                    
                    <div>
                        <label>
                            <?php _e('Language', 'fp-esperienze'); ?>
                        </label>
                        <input type="text" 
                               name="builder_slots[<?php echo esc_attr($index); ?>][lang]" 
                               value="<?php echo esc_attr($overrides['lang'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr(sprintf(__('Default: %s', 'fp-esperienze'), $default_language)); ?>" 
                               maxlength="10">
                    </div>
                </div>
                
                <div>
                    <div>
                        <label>
                            <?php _e('Meeting Point', 'fp-esperienze'); ?>
                        </label>
                        <select name="builder_slots[<?php echo esc_attr($index); ?>][meeting_point_id]">
                            <option value=""><?php echo esc_html(sprintf(__('Default: %s', 'fp-esperienze'), $meeting_points[$default_meeting_point] ?? __('None', 'fp-esperienze'))); ?></option>
                            <?php foreach ($meeting_points as $mp_id => $mp_name): ?>
                                <option value="<?php echo esc_attr($mp_id); ?>" <?php selected($overrides['meeting_point_id'] ?? '', $mp_id); ?>>
                                    <?php echo esc_html($mp_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>
                            <?php _e('Adult Price', 'fp-esperienze'); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)
                        </label>
                        <input type="number" 
                               name="builder_slots[<?php echo esc_attr($index); ?>][price_adult]" 
                               value="<?php echo esc_attr($overrides['price_adult'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr(sprintf(__('Default: %.2f', 'fp-esperienze'), $default_price_adult)); ?>" 
                               min="0" 
                               step="0.01">
                    </div>
                    
                    <div>
                        <label>
                            <?php _e('Child Price', 'fp-esperienze'); ?> (<?php echo get_woocommerce_currency_symbol(); ?>)
                        </label>
                        <input type="number" 
                               name="builder_slots[<?php echo esc_attr($index); ?>][price_child]" 
                               value="<?php echo esc_attr($overrides['price_child'] ?? ''); ?>" 
                               placeholder="<?php echo esc_attr(sprintf(__('Default: %.2f', 'fp-esperienze'), $default_price_child)); ?>" 
                               min="0" 
                               step="0.01">
                    </div>
                </div>
            </div>
            
            <!-- Store schedule IDs for updates -->
            <?php if (!empty($slot['schedule_ids'])): ?>
                <?php foreach ($slot['schedule_ids'] as $schedule_id): ?>
                    <input type="hidden" name="builder_slots[<?php echo esc_attr($index); ?>][schedule_ids][]" value="<?php echo esc_attr($schedule_id); ?>">
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render slots summary table
     *
     * @param array $time_slots Time slots data
     * @param array $days Days mapping
     */
    private function renderSlotsSummaryTable(array $time_slots, array $days): void {
        ?>
        <div class="fp-slots-summary">
            <div class="fp-slots-summary-header">
                <span class="dashicons dashicons-clock"></span>
                <?php _e('Configured Time Slots Overview', 'fp-esperienze'); ?>
            </div>
            <div class="fp-slots-summary-content">
                <?php if (empty($time_slots)): ?>
                    <div class="fp-summary-table">
                        <div class="fp-empty-state">
                            <?php _e('No time slots configured yet. Click "Add Time Slot" below to get started.', 'fp-esperienze'); ?>
                        </div>
                    </div>
                <?php else: ?>
                    <table class="fp-summary-table">
                        <thead>
                            <tr>
                                <th><?php _e('Time', 'fp-esperienze'); ?></th>
                                <th><?php _e('Days', 'fp-esperienze'); ?></th>
                                <th><?php _e('Duration', 'fp-esperienze'); ?></th>
                                <th><?php _e('Capacity', 'fp-esperienze'); ?></th>
                                <th><?php _e('Customized', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_slots as $slot): ?>
                                <tr>
                                    <td>
                                        <span class="fp-time-badge"><?php echo esc_html($slot['start_time'] ?? ''); ?></span>
                                    </td>
                                    <td>
                                        <div class="fp-days-summary">
                                            <?php 
                                            $slot_days = $slot['days'] ?? [];
                                            // Sort days to show in week order
                                            $sorted_days = array_intersect(array_keys($days), $slot_days);
                                            foreach ($sorted_days as $day): 
                                                $day_short = substr($days[$day], 0, 3);
                                            ?>
                                                <span class="fp-day-badge"><?php echo esc_html($day_short); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $duration = $slot['overrides']['duration_min'] ?? null;
                                        if ($duration) {
                                            echo esc_html($duration . ' min');
                                        } else {
                                            echo '<em>' . esc_html__('Default', 'fp-esperienze') . '</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $capacity = $slot['overrides']['capacity'] ?? null;
                                        if ($capacity) {
                                            echo esc_html($capacity);
                                        } else {
                                            echo '<em>' . esc_html__('Default', 'fp-esperienze') . '</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $overrides = $slot['overrides'] ?? [];
                                        $custom_count = count(array_filter($overrides));
                                        if ($custom_count > 0) {
                                            /* translators: %d: number of customized settings */
                                            printf(_n('%d setting', '%d settings', $custom_count, 'fp-esperienze'), $custom_count);
                                        } else {
                                            echo '<em>' . esc_html__('None', 'fp-esperienze') . '</em>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
            
            <input type="date" 
                   name="overrides[<?php echo esc_attr($index); ?>][date]" 
                   value="<?php echo esc_attr($override->date ?? ''); ?>" 
                   required
                   aria-label="<?php esc_attr_e('Override date', 'fp-esperienze'); ?>">
            
            <label>
                <input type="checkbox" 
                       name="overrides[<?php echo esc_attr($index); ?>][is_closed]" 
                       value="1" 
                       <?php checked($override->is_closed ?? 0, 1); ?>>
                <?php _e('Closed', 'fp-esperienze'); ?>
            </label>
            
            <input type="number" 
                   name="overrides[<?php echo esc_attr($index); ?>][capacity_override]" 
                   value="<?php echo esc_attr($override->capacity_override ?? ''); ?>" 
                   placeholder="<?php esc_attr_e('Capacity', 'fp-esperienze'); ?>" 
                   min="0" 
                   step="1"
                   aria-label="<?php esc_attr_e('Capacity override', 'fp-esperienze'); ?>">
            
            <input type="number" 
                   name="overrides[<?php echo esc_attr($index); ?>][price_adult]" 
                   value="<?php echo esc_attr($price_override['adult'] ?? ''); ?>" 
                   placeholder="<?php esc_attr_e('Adult €', 'fp-esperienze'); ?>" 
                   min="0" 
                   step="0.01"
                   aria-label="<?php esc_attr_e('Adult price override', 'fp-esperienze'); ?>">
            
            <input type="number" 
                   name="overrides[<?php echo esc_attr($index); ?>][price_child]" 
                   value="<?php echo esc_attr($price_override['child'] ?? ''); ?>" 
                   placeholder="<?php esc_attr_e('Child €', 'fp-esperienze'); ?>" 
                   min="0" 
                   step="0.01"
                   aria-label="<?php esc_attr_e('Child price override', 'fp-esperienze'); ?>">
            
            <input type="text" 
                   name="overrides[<?php echo esc_attr($index); ?>][reason]" 
                   value="<?php echo esc_attr($override->reason ?? ''); ?>" 
                   placeholder="<?php esc_attr_e('Reason (optional)', 'fp-esperienze'); ?>"
                   aria-label="<?php esc_attr_e('Reason for this override', 'fp-esperienze'); ?>">
            
            <button type="button" class="fp-remove-override" aria-label="<?php esc_attr_e('Remove this override', 'fp-esperienze'); ?>">
                <?php _e('Remove', 'fp-esperienze'); ?>
            </button>
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
                            <?php if (function_exists('wc_price')) : ?>
                                (<?php echo wc_price($extra->price); ?> 
                            <?php else : ?>
                                (<?php echo '$' . number_format($extra->price, 2); ?> 
                            <?php endif; ?>
                            <?php echo esc_html($extra->billing_type === 'per_person' ? __('per person', 'fp-esperienze') : __('per booking', 'fp-esperienze')); ?>)
                            <?php if ($extra->description) : ?>
                                <br><span class="description"><?php echo esc_html($extra->description); ?></span>
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
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
        
        // Only proceed if this is an experience product
        $product_type = sanitize_text_field($_POST['product-type'] ?? '');
        if ($product_type !== 'experience') {
            return;
        }
        
        // Ensure product type is set to 'experience' - this MUST happen
        // Use multiple approaches to ensure it sticks
        update_post_meta($post_id, '_product_type', 'experience');
        
        // Also set it on the global $_POST to ensure WooCommerce core picks it up
        $_POST['product-type'] = 'experience';
        
        // Save basic experience fields
        $fields = [
            '_fp_exp_duration',
            '_fp_exp_capacity', 
            '_fp_exp_language',
            '_fp_exp_price_child',
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
        
        // Save textarea fields with appropriate sanitization
        $textarea_fields = [
            '_fp_exp_included',
            '_fp_exp_excluded'
        ];
        
        foreach ($textarea_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
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
     * Ensure product type is preserved during save
     *
     * @param int $product_id Product ID
     */
    public function ensureProductType(int $product_id): void {
        // Only proceed if we're saving an experience product
        $product_type = sanitize_text_field($_POST['product-type'] ?? '');
        if ($product_type !== 'experience') {
            return;
        }
        
        // Double-check that product type is properly set
        $current_type = get_post_meta($product_id, '_product_type', true);
        if ($current_type !== 'experience') {
            update_post_meta($product_id, '_product_type', 'experience');
        }
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
            // Sanitize rule data
            $sanitized_rule = [
                'rule_name' => sanitize_text_field($rule_data['rule_name'] ?? ''),
                'rule_type' => sanitize_text_field($rule_data['rule_type'] ?? ''),
                'product_id' => $product_id
            ];
            
            if (empty($sanitized_rule['rule_name']) || empty($sanitized_rule['rule_type'])) {
                continue;
            }
            
            // Copy other sanitized fields if they exist
            foreach ($rule_data as $key => $value) {
                if (!in_array($key, ['rule_name', 'rule_type', 'product_id'])) {
                    if (is_numeric($value)) {
                        $sanitized_rule[$key] = is_float($value) ? floatval($value) : absint($value);
                    } else {
                        $sanitized_rule[$key] = sanitize_text_field($value);
                    }
                }
            }
            
            DynamicPricingManager::saveRule($sanitized_rule);
        }
    }
    
    /**
     * Save schedules data
     *
     * @param int $product_id Product ID
     */
    private function saveSchedules(int $product_id): void {
        // Get existing schedules
        $existing_schedules = ScheduleManager::getSchedules($product_id);
        $existing_ids = array_column($existing_schedules, 'id');
        $processed_ids = [];
        $validation_errors = [];
        
        // Process builder slots first if they exist
        if (isset($_POST['builder_slots']) && is_array($_POST['builder_slots'])) {
            $processed_ids = array_merge($processed_ids, $this->processBuilderSlots($product_id, $_POST['builder_slots'], $validation_errors));
        }
        
        // Process raw schedules if they exist (for advanced mode)
        if (isset($_POST['schedules']) && is_array($_POST['schedules'])) {
            $processed_ids = array_merge($processed_ids, $this->processRawSchedules($product_id, $_POST['schedules'], $validation_errors));
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
        
        // Set success notice if schedules were saved
        if (!empty($processed_ids)) {
            set_transient("fp_schedule_saved_{$product_id}", count($processed_ids), 60);
        }
    }
    
    /**
     * Process builder slots and create individual schedule records
     *
     * @param int $product_id Product ID
     * @param array $builder_slots Builder slot data
     * @param array &$validation_errors Reference to validation errors array
     * @return array Array of processed schedule IDs
     */
    private function processBuilderSlots(int $product_id, array $builder_slots, array &$validation_errors): array {
        $processed_ids = [];
        
        foreach ($builder_slots as $slot_index => $slot_data) {
            // Validate required fields
            if (empty($slot_data['start_time']) || empty($slot_data['days'])) {
                continue;
            }
            
            // Validate time format
            if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $slot_data['start_time'])) {
                $validation_errors[] = sprintf(__('Time slot %d: Invalid time format. Use HH:MM format.', 'fp-esperienze'), $slot_index + 1);
                continue;
            }
            
            // Get override values or null for inheritance
            // Only process overrides if advanced settings are explicitly enabled
            $advanced_enabled = !empty($slot_data['advanced_enabled']) && $slot_data['advanced_enabled'] === '1';
            
            // Process overrides with better validation - allow empty strings as "reset to default"
            $duration_override = null;
            if ($advanced_enabled && isset($slot_data['duration_min']) && $slot_data['duration_min'] !== '') {
                $duration_override = max(1, (int) $slot_data['duration_min']); // Ensure minimum 1 minute
            }
            
            $capacity_override = null;
            if ($advanced_enabled && isset($slot_data['capacity']) && $slot_data['capacity'] !== '') {
                $capacity_override = max(1, (int) $slot_data['capacity']); // Ensure minimum 1 person
            }
            
            $lang_override = null;
            if ($advanced_enabled && isset($slot_data['lang']) && $slot_data['lang'] !== '') {
                $lang_override = sanitize_text_field($slot_data['lang']);
            }
            
            $meeting_point_override = null;
            if ($advanced_enabled && isset($slot_data['meeting_point_id']) && $slot_data['meeting_point_id'] !== '') {
                $meeting_point_override = (int) $slot_data['meeting_point_id'];
            }
            
            $price_adult_override = null;
            if ($advanced_enabled && isset($slot_data['price_adult']) && $slot_data['price_adult'] !== '') {
                $price_adult_override = max(0, (float) $slot_data['price_adult']); // Ensure non-negative
            }
            
            $price_child_override = null;
            if ($advanced_enabled && isset($slot_data['price_child']) && $slot_data['price_child'] !== '') {
                $price_child_override = max(0, (float) $slot_data['price_child']); // Ensure non-negative
            }
            
            // Track existing schedule IDs for this slot
            $existing_slot_ids = !empty($slot_data['schedule_ids']) ? array_map('intval', $slot_data['schedule_ids']) : [];
            $slot_processed_ids = [];
            
            // Create or update schedule for each selected day
            foreach ($slot_data['days'] as $day_of_week) {
                $day_of_week = (int) $day_of_week;
                
                // Prepare schedule data
                $schedule_data = [
                    'product_id' => $product_id,
                    'day_of_week' => $day_of_week,
                    'start_time' => sanitize_text_field($slot_data['start_time']),
                    'duration_min' => $duration_override,
                    'capacity' => $capacity_override,
                    'lang' => $lang_override,
                    'meeting_point_id' => $meeting_point_override,
                    'price_adult' => $price_adult_override,
                    'price_child' => $price_child_override,
                    'is_active' => 1
                ];
                
                // Try to find existing schedule for this day/time combination
                $existing_schedule_id = null;
                foreach ($existing_slot_ids as $id) {
                    $existing = ScheduleManager::getSchedule($id);
                    if ($existing && $existing->day_of_week == $day_of_week && $existing->start_time == $slot_data['start_time']) {
                        $existing_schedule_id = $id;
                        break;
                    }
                }
                
                if ($existing_schedule_id) {
                    // Update existing schedule
                    ScheduleManager::updateSchedule($existing_schedule_id, $schedule_data);
                    $slot_processed_ids[] = $existing_schedule_id;
                } else {
                    // Create new schedule
                    $new_id = ScheduleManager::createSchedule($schedule_data);
                    if ($new_id) {
                        $slot_processed_ids[] = $new_id;
                    }
                }
            }
            
            $processed_ids = array_merge($processed_ids, $slot_processed_ids);
        }
        
        return $processed_ids;
    }
    
    /**
     * Process raw schedules (advanced mode)
     *
     * @param int $product_id Product ID
     * @param array $schedules Raw schedule data
     * @param array &$validation_errors Reference to validation errors array
     * @return array Array of processed schedule IDs
     */
    private function processRawSchedules(int $product_id, array $schedules, array &$validation_errors): array {
        $processed_ids = [];
        $discarded_count = 0;
        
        foreach ($schedules as $index => $schedule_data) {
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
            
            $schedule_id = !empty($schedule_data['id']) ? (int) $schedule_data['id'] : 0;
            
            // Prepare data for raw schedule (use defaults if empty, but allow overrides)
            $data = [
                'product_id' => $product_id,
                'day_of_week' => (int) $schedule_data['day_of_week'],
                'start_time' => sanitize_text_field($schedule_data['start_time']),
                'duration_min' => !empty($schedule_data['duration_min']) ? (int) $schedule_data['duration_min'] : null,
                'capacity' => !empty($schedule_data['capacity']) ? (int) $schedule_data['capacity'] : null,
                'lang' => !empty($schedule_data['lang']) ? sanitize_text_field($schedule_data['lang']) : null,
                'meeting_point_id' => !empty($schedule_data['meeting_point_id']) ? (int) $schedule_data['meeting_point_id'] : null,
                'price_adult' => !empty($schedule_data['price_adult']) ? (float) $schedule_data['price_adult'] : null,
                'price_child' => !empty($schedule_data['price_child']) ? (float) $schedule_data['price_child'] : null,
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
        
        if ($discarded_count > 0) {
            set_transient("fp_schedule_discarded_{$product_id}", $discarded_count, 60);
        }
        
        return $processed_ids;
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
    
    /**
     * Add experience product fields to general tab for better admin integration
     */
    public function addExperienceProductFields(): void {
        global $product_object;
        
        // Only show for experience products
        if (!$product_object || $product_object->get_type() !== 'experience') {
            return;
        }
        
        echo '<div class="options_group show_if_experience">';
        
        woocommerce_wp_text_input([
            'id'          => '_experience_duration_general',
            'label'       => __('Duration (minutes)', 'fp-esperienze'),
            'placeholder' => '60',
            'desc_tip'    => true,
            'description' => __('Experience duration in minutes', 'fp-esperienze'),
            'type'        => 'number',
            'custom_attributes' => [
                'step' => '1',
                'min'  => '1'
            ],
            'value' => get_post_meta($product_object->get_id(), '_experience_duration', true)
        ]);
        
        echo '</div>';
    }
    
    /**
     * Enqueue admin scripts for product edit pages
     */
    public function enqueueAdminScripts($hook): void {
        // Only load on product edit pages
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== 'product') {
            return;
        }
        
        wp_enqueue_script(
            'fp-esperienze-product-admin',
            FP_ESPERIENZE_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wc-admin-product-meta-boxes'],
            FP_ESPERIENZE_VERSION,
            true
        );
        
        wp_localize_script('fp-esperienze-product-admin', 'fp_esperienze_admin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fp_esperienze_admin'),
            'rest_url' => rest_url('fp-exp/v1/'),
            'strings' => [
                'experience_type' => __('Experience', 'fp-esperienze'),
                'select_date' => __('Select Date', 'fp-esperienze'),
                'loading' => __('Loading...', 'fp-esperienze')
            ]
        ]);
        
        // Add custom CSS for experience product type
        wp_add_inline_style('woocommerce_admin_styles', '
            .product-type-experience .show_if_simple,
            .product-type-experience .show_if_variable,
            .product-type-experience .show_if_grouped,
            .product-type-experience .show_if_external {
                display: none !important;
            }
            .show_if_experience {
                display: block !important;
            }
            body:not(.product-type-experience) .show_if_experience {
                display: none !important;
            }
        ');
    }
    
    /**
     * Check if overrides contain actual differences from product defaults
     *
     * @param array $overrides Override values from the slot
     * @param int $index Slot index (for debugging)
     * @param int $product_id Product ID
     * @return bool True if there are actual overrides that differ from defaults
     */
    private function hasActualOverrides(array $overrides, int $index, int $product_id): bool {
        // If no overrides array provided, definitely no overrides
        if (empty($overrides)) {
            return false;
        }
        
        // Get product defaults
        $default_duration = get_post_meta($product_id, '_fp_exp_duration', true) ?: 60;
        $default_capacity = get_post_meta($product_id, '_fp_exp_capacity', true) ?: 10;
        $default_lang = get_post_meta($product_id, '_fp_exp_language', true) ?: 'en';
        $default_meeting_point = get_post_meta($product_id, '_fp_exp_meeting_point_id', true);
        $default_price_adult = get_post_meta($product_id, '_regular_price', true) ?: 0.00;
        $default_price_child = get_post_meta($product_id, '_fp_exp_price_child', true) ?: 0.00;
        
        // Check each override field for actual differences (including empty values as valid overrides)
        // Duration override: check if set and different from default
        if (isset($overrides['duration_min']) && $overrides['duration_min'] !== '' && 
            (int)$overrides['duration_min'] !== (int)$default_duration) {
            return true;
        }
        
        // Capacity override: check if set and different from default
        if (isset($overrides['capacity']) && $overrides['capacity'] !== '' && 
            (int)$overrides['capacity'] !== (int)$default_capacity) {
            return true;
        }
        
        // Language override: check if set and different from default
        if (isset($overrides['lang']) && $overrides['lang'] !== '' && 
            trim($overrides['lang']) !== trim($default_lang)) {
            return true;
        }
        
        // Meeting point override: check if set and different from default
        if (isset($overrides['meeting_point_id']) && $overrides['meeting_point_id'] !== '' && 
            (int)$overrides['meeting_point_id'] !== (int)$default_meeting_point) {
            return true;
        }
        
        // Adult price override: check if set and different from default (with float comparison)
        if (isset($overrides['price_adult']) && $overrides['price_adult'] !== '' && 
            abs((float)$overrides['price_adult'] - (float)$default_price_adult) >= 0.01) {
            return true;
        }
        
        // Child price override: check if set and different from default (with float comparison)
        if (isset($overrides['price_child']) && $overrides['price_child'] !== '' && 
            abs((float)$overrides['price_child'] - (float)$default_price_child) >= 0.01) {
            return true;
        }
        
        // No actual differences found
        return false;
    }
}