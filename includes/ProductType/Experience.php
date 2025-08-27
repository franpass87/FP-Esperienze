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

            ?>
            
            <div class="options_group">
                <h4><?php _e('Cancellation Policy', 'fp-esperienze'); ?></h4>
                
                <?php
                // Cutoff time for bookings
                woocommerce_wp_text_input([
                    'id'          => '_fp_exp_cutoff_minutes',
                    'label'       => __('Booking Cutoff Time (minutes)', 'fp-esperienze'),
                    'placeholder' => '120',
                    'desc_tip'    => true,
                    'description' => __('Minutes before experience when booking/changes are no longer allowed', 'fp-esperienze'),
                    'type'        => 'number',
                    'custom_attributes' => [
                        'step' => '1',
                        'min'  => '0'
                    ]
                ]);

                // Free cancellation time
                woocommerce_wp_text_input([
                    'id'          => '_fp_exp_free_cancel_until_minutes',
                    'label'       => __('Free Cancellation Until (minutes)', 'fp-esperienze'),
                    'placeholder' => '1440',
                    'desc_tip'    => true,
                    'description' => __('Minutes before experience when cancellation is free (default: 1440 = 24 hours)', 'fp-esperienze'),
                    'type'        => 'number',
                    'custom_attributes' => [
                        'step' => '1',
                        'min'  => '0'
                    ]
                ]);

                // Cancellation fee percentage
                woocommerce_wp_text_input([
                    'id'          => '_fp_exp_cancellation_fee_percentage',
                    'label'       => __('Cancellation Fee (%)', 'fp-esperienze'),
                    'placeholder' => '0',
                    'desc_tip'    => true,
                    'description' => __('Percentage fee charged for cancellations after free cancellation period', 'fp-esperienze'),
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
                        'no_refund'     => __('No Refund', 'fp-esperienze'),
                        'partial_refund' => __('Partial Refund', 'fp-esperienze'),
                        'full_refund'   => __('Full Refund', 'fp-esperienze')
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
        <div class="fp-schedule-row" data-index="<?php echo esc_attr($index); ?>">
            <input type="hidden" name="schedules[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($schedule->id ?? ''); ?>">
            
            <select name="schedules[<?php echo esc_attr($index); ?>][day_of_week]" required>
                <option value=""><?php _e('Select Day', 'fp-esperienze'); ?></option>
                <?php foreach ($days as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($schedule->day_of_week ?? '', $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="time" name="schedules[<?php echo esc_attr($index); ?>][start_time]" 
                   value="<?php echo esc_attr($schedule->start_time ?? ''); ?>" required>
            
            <input type="number" name="schedules[<?php echo esc_attr($index); ?>][duration_min]" 
                   value="<?php echo esc_attr($schedule->duration_min ?? 60); ?>" 
                   placeholder="60" min="1" step="1" required>
            
            <input type="number" name="schedules[<?php echo esc_attr($index); ?>][capacity]" 
                   value="<?php echo esc_attr($schedule->capacity ?? 10); ?>" 
                   placeholder="10" min="1" step="1" required>
            
            <input type="text" name="schedules[<?php echo esc_attr($index); ?>][lang]" 
                   value="<?php echo esc_attr($schedule->lang ?? 'en'); ?>" 
                   placeholder="en" maxlength="10">
            
            <select name="schedules[<?php echo esc_attr($index); ?>][meeting_point_id]">
                <?php foreach ($meeting_points as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($schedule->meeting_point_id ?? '', $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <input type="number" name="schedules[<?php echo esc_attr($index); ?>][price_adult]" 
                   value="<?php echo esc_attr($schedule->price_adult ?? ''); ?>" 
                   placeholder="0.00" min="0" step="0.01">
            
            <input type="number" name="schedules[<?php echo esc_attr($index); ?>][price_child]" 
                   value="<?php echo esc_attr($schedule->price_child ?? ''); ?>" 
                   placeholder="0.00" min="0" step="0.01">
            
            <button type="button" class="button fp-remove-schedule"><?php _e('Remove', 'fp-esperienze'); ?></button>
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
            '_fp_exp_cancellation_fee_percentage',
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
        
        foreach ($_POST['schedules'] as $schedule_data) {
            if (empty($schedule_data['day_of_week']) || empty($schedule_data['start_time'])) {
                continue;
            }
            
            $schedule_id = !empty($schedule_data['id']) ? (int) $schedule_data['id'] : 0;
            
            $data = [
                'product_id' => $product_id,
                'day_of_week' => (int) $schedule_data['day_of_week'],
                'start_time' => sanitize_text_field($schedule_data['start_time']),
                'duration_min' => (int) ($schedule_data['duration_min'] ?: 60),
                'capacity' => (int) ($schedule_data['capacity'] ?: 10),
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
}