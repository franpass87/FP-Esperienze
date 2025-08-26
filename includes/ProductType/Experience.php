<?php
/**
 * Experience Product Type
 *
 * Hooks and Filters available:
 * - woocommerce_product_data_tabs: Adds experience, schedules, and overrides tabs
 * - woocommerce_product_data_panels: Renders tab content with CRUD interfaces
 * - woocommerce_process_product_meta: Saves experience, schedule, and override data
 * 
 * Custom hooks:
 * - fp_experience_before_save_schedules: Before saving schedules
 * - fp_experience_after_save_schedules: After saving schedules  
 * - fp_experience_before_save_overrides: Before saving overrides
 * - fp_experience_after_save_overrides: After saving overrides
 *
 * @package FP\Esperienze\ProductType
 */

namespace FP\Esperienze\ProductType;

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
        
        $tabs['experience_schedules'] = [
            'label'  => __('Schedules', 'fp-esperienze'),
            'target' => 'experience_schedules_data',
            'class'  => ['show_if_experience'],
        ];
        
        $tabs['experience_overrides'] = [
            'label'  => __('Overrides', 'fp-esperienze'),
            'target' => 'experience_overrides_data',
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
                'id'          => '_experience_default_meeting_point',
                'label'       => __('Default Meeting Point', 'fp-esperienze'),
                'options'     => $meeting_points,
                'desc_tip'    => true,
                'description' => __('Default meeting point for this experience', 'fp-esperienze')
            ]);

            ?>
        </div>
        
        <!-- Schedules Tab -->
        <div id="experience_schedules_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h3><?php _e('Weekly Schedules', 'fp-esperienze'); ?></h3>
                <p><?php _e('Configure when this experience runs during the week.', 'fp-esperienze'); ?></p>
                
                <div id="schedule-list">
                    <?php $this->renderSchedulesList($post->ID); ?>
                </div>
                
                <button type="button" class="button" id="add-schedule">
                    <?php _e('Add Schedule', 'fp-esperienze'); ?>
                </button>
            </div>
        </div>
        
        <!-- Overrides Tab -->
        <div id="experience_overrides_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <h3><?php _e('Date Overrides', 'fp-esperienze'); ?></h3>
                <p><?php _e('Override capacity, pricing, or close specific dates.', 'fp-esperienze'); ?></p>
                
                <div id="override-list">
                    <?php $this->renderOverridesList($post->ID); ?>
                </div>
                
                <button type="button" class="button" id="add-override">
                    <?php _e('Add Override', 'fp-esperienze'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Save product data
     *
     * @param int $post_id Post ID
     */
    public function saveProductData(int $post_id): void {
        // Verify nonce for security
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce($_POST['woocommerce_meta_nonce'], 'woocommerce_save_data')) {
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save basic experience data
        $fields = [
            '_experience_duration',
            '_experience_capacity',
            '_experience_adult_price',
            '_experience_child_price',
            '_experience_languages',
            '_experience_default_meeting_point'
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
    }

    /**
     * Save schedules data
     *
     * @param int $post_id Product ID
     */
    private function saveSchedules(int $post_id): void {
        if (!isset($_POST['schedules']) || !is_array($_POST['schedules'])) {
            return;
        }

        // Allow plugins to hook before saving schedules
        do_action('fp_experience_before_save_schedules', $post_id, $_POST['schedules']);

        global $wpdb;
        $table = $wpdb->prefix . 'fp_schedules';
        
        // Delete existing schedules
        $wpdb->delete($table, ['product_id' => $post_id]);
        
        // Insert new schedules
        foreach ($_POST['schedules'] as $schedule_data) {
            if (empty($schedule_data['day_of_week']) || empty($schedule_data['start_time'])) {
                continue;
            }
            
            $wpdb->insert($table, [
                'product_id' => $post_id,
                'day_of_week' => intval($schedule_data['day_of_week']),
                'start_time' => sanitize_text_field($schedule_data['start_time']),
                'duration_min' => intval($schedule_data['duration_min'] ?: 60),
                'capacity' => intval($schedule_data['capacity'] ?: 1),
                'lang' => sanitize_text_field($schedule_data['lang'] ?: ''),
                'meeting_point_id' => intval($schedule_data['meeting_point_id'] ?: 0) ?: null,
                'price_adult' => floatval($schedule_data['price_adult'] ?: 0),
                'price_child' => floatval($schedule_data['price_child'] ?: 0),
                'is_active' => intval($schedule_data['is_active'] ?: 1),
                'created_at' => current_time('mysql')
            ]);
        }

        // Allow plugins to hook after saving schedules
        do_action('fp_experience_after_save_schedules', $post_id);
    }

    /**
     * Save overrides data
     *
     * @param int $post_id Product ID
     */
    private function saveOverrides(int $post_id): void {
        if (!isset($_POST['overrides']) || !is_array($_POST['overrides'])) {
            return;
        }

        // Allow plugins to hook before saving overrides
        do_action('fp_experience_before_save_overrides', $post_id, $_POST['overrides']);

        global $wpdb;
        $table = $wpdb->prefix . 'fp_overrides';
        
        // Delete existing overrides
        $wpdb->delete($table, ['product_id' => $post_id]);
        
        // Insert new overrides
        foreach ($_POST['overrides'] as $override_data) {
            if (empty($override_data['date'])) {
                continue;
            }
            
            $price_override = null;
            if (!empty($override_data['price_adult']) || !empty($override_data['price_child'])) {
                $price_override = json_encode([
                    'adult' => floatval($override_data['price_adult'] ?: 0),
                    'child' => floatval($override_data['price_child'] ?: 0)
                ]);
            }
            
            $wpdb->insert($table, [
                'product_id' => $post_id,
                'date' => sanitize_text_field($override_data['date']),
                'is_closed' => intval($override_data['is_closed'] ?: 0),
                'capacity_override' => intval($override_data['capacity_override']) ?: null,
                'price_override_json' => $price_override,
                'reason' => sanitize_text_field($override_data['reason'] ?: ''),
                'created_at' => current_time('mysql')
            ]);
        }

        // Allow plugins to hook after saving overrides
        do_action('fp_experience_after_save_overrides', $post_id);
    }

    /**
     * Render schedules list
     *
     * @param int $product_id Product ID
     */
    private function renderSchedulesList(int $product_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_schedules';
        $schedules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d ORDER BY day_of_week, start_time",
            $product_id
        ));
        
        $days = [
            0 => __('Sunday', 'fp-esperienze'),
            1 => __('Monday', 'fp-esperienze'),
            2 => __('Tuesday', 'fp-esperienze'),
            3 => __('Wednesday', 'fp-esperienze'),
            4 => __('Thursday', 'fp-esperienze'),
            5 => __('Friday', 'fp-esperienze'),
            6 => __('Saturday', 'fp-esperienze'),
        ];
        
        $meeting_points = $this->getMeetingPoints();
        
        foreach ($schedules as $index => $schedule) {
            ?>
            <div class="schedule-row" data-index="<?php echo $index; ?>">
                <table class="widefat">
                    <tr>
                        <td>
                            <label><?php _e('Day', 'fp-esperienze'); ?></label>
                            <select name="schedules[<?php echo $index; ?>][day_of_week]">
                                <?php foreach ($days as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php selected($schedule->day_of_week, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <label><?php _e('Start Time', 'fp-esperienze'); ?></label>
                            <input type="time" name="schedules[<?php echo $index; ?>][start_time]" 
                                   value="<?php echo esc_attr($schedule->start_time); ?>" required />
                        </td>
                        <td>
                            <label><?php _e('Duration (min)', 'fp-esperienze'); ?></label>
                            <input type="number" name="schedules[<?php echo $index; ?>][duration_min]" 
                                   value="<?php echo esc_attr($schedule->duration_min); ?>" min="1" required />
                        </td>
                        <td>
                            <label><?php _e('Capacity', 'fp-esperienze'); ?></label>
                            <input type="number" name="schedules[<?php echo $index; ?>][capacity]" 
                                   value="<?php echo esc_attr($schedule->capacity); ?>" min="1" required />
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label><?php _e('Language', 'fp-esperienze'); ?></label>
                            <input type="text" name="schedules[<?php echo $index; ?>][lang]" 
                                   value="<?php echo esc_attr($schedule->lang); ?>" placeholder="en" />
                        </td>
                        <td>
                            <label><?php _e('Meeting Point', 'fp-esperienze'); ?></label>
                            <select name="schedules[<?php echo $index; ?>][meeting_point_id]">
                                <?php foreach ($meeting_points as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php selected($schedule->meeting_point_id, $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <label><?php _e('Adult Price', 'fp-esperienze'); ?></label>
                            <input type="number" name="schedules[<?php echo $index; ?>][price_adult]" 
                                   value="<?php echo esc_attr($schedule->price_adult); ?>" step="0.01" min="0" />
                        </td>
                        <td>
                            <label><?php _e('Child Price', 'fp-esperienze'); ?></label>
                            <input type="number" name="schedules[<?php echo $index; ?>][price_child]" 
                                   value="<?php echo esc_attr($schedule->price_child); ?>" step="0.01" min="0" />
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3">
                            <label>
                                <input type="checkbox" name="schedules[<?php echo $index; ?>][is_active]" 
                                       value="1" <?php checked($schedule->is_active, 1); ?> />
                                <?php _e('Active', 'fp-esperienze'); ?>
                            </label>
                        </td>
                        <td>
                            <button type="button" class="button remove-schedule">
                                <?php _e('Remove', 'fp-esperienze'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }
    }

    /**
     * Render overrides list
     *
     * @param int $product_id Product ID
     */
    private function renderOverridesList(int $product_id): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_overrides';
        $overrides = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE product_id = %d ORDER BY date",
            $product_id
        ));
        
        foreach ($overrides as $index => $override) {
            $price_data = json_decode($override->price_override_json, true) ?: [];
            ?>
            <div class="override-row" data-index="<?php echo $index; ?>">
                <table class="widefat">
                    <tr>
                        <td>
                            <label><?php _e('Date', 'fp-esperienze'); ?></label>
                            <input type="date" name="overrides[<?php echo $index; ?>][date]" 
                                   value="<?php echo esc_attr($override->date); ?>" required />
                        </td>
                        <td>
                            <label>
                                <input type="checkbox" name="overrides[<?php echo $index; ?>][is_closed]" 
                                       value="1" <?php checked($override->is_closed, 1); ?> />
                                <?php _e('Closed', 'fp-esperienze'); ?>
                            </label>
                        </td>
                        <td>
                            <label><?php _e('Capacity Override', 'fp-esperienze'); ?></label>
                            <input type="number" name="overrides[<?php echo $index; ?>][capacity_override]" 
                                   value="<?php echo esc_attr($override->capacity_override); ?>" min="0" />
                        </td>
                        <td>
                            <button type="button" class="button remove-override">
                                <?php _e('Remove', 'fp-esperienze'); ?>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label><?php _e('Adult Price Override', 'fp-esperienze'); ?></label>
                            <input type="number" name="overrides[<?php echo $index; ?>][price_adult]" 
                                   value="<?php echo esc_attr($price_data['adult'] ?? ''); ?>" step="0.01" min="0" />
                        </td>
                        <td>
                            <label><?php _e('Child Price Override', 'fp-esperienze'); ?></label>
                            <input type="number" name="overrides[<?php echo $index; ?>][price_child]" 
                                   value="<?php echo esc_attr($price_data['child'] ?? ''); ?>" step="0.01" min="0" />
                        </td>
                        <td colspan="2">
                            <label><?php _e('Reason', 'fp-esperienze'); ?></label>
                            <input type="text" name="overrides[<?php echo $index; ?>][reason]" 
                                   value="<?php echo esc_attr($override->reason); ?>" placeholder="<?php _e('Optional reason', 'fp-esperienze'); ?>" />
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }
    }

    /**
     * Get meeting points for select dropdown
     *
     * @return array
     */
    private function getMeetingPoints(): array {
        global $wpdb;
        
        $options = ['' => __('Select a meeting point', 'fp-esperienze')];
        
        $table_name = $wpdb->prefix . 'fp_meeting_points';
        $results = $wpdb->get_results("SELECT id, name FROM $table_name ORDER BY name ASC");
        
        if ($results) {
            foreach ($results as $row) {
                $options[$row->id] = $row->name;
            }
        }
        
        return $options;
    }
}