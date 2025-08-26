<?php
/**
 * Experience Product Type
 *
 * @package FP\Esperienze\ProductType
 */

namespace FP\Esperienze\ProductType;

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
        
        $tabs['experience_extras'] = [
            'label'  => __('Extras', 'fp-esperienze'),
            'target' => 'experience_extras_data',
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
        
        <div id="experience_extras_data" class="panel woocommerce_options_panel">
            <?php
            
            // Get available extras
            $all_extras = ExtraManager::getAllExtras(['is_active' => 1]);
            $selected_extras = ExtraManager::getProductExtras($post->ID);
            $selected_extra_ids = array_column($selected_extras, 'id');
            
            ?>
            <div class="options_group">
                <p class="form-field">
                    <label><?php _e('Available Extras', 'fp-esperienze'); ?></label>
                </p>
                
                <?php if (empty($all_extras)) : ?>
                    <p>
                        <?php _e('No extras available.', 'fp-esperienze'); ?>
                        <a href="<?php echo admin_url('admin.php?page=fp-esperienze-extras&action=add'); ?>" target="_blank">
                            <?php _e('Create one now', 'fp-esperienze'); ?>
                        </a>
                    </p>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed" style="max-width: 800px;">
                        <thead>
                            <tr>
                                <th width="50"><?php _e('Select', 'fp-esperienze'); ?></th>
                                <th><?php _e('Name', 'fp-esperienze'); ?></th>
                                <th><?php _e('Price', 'fp-esperienze'); ?></th>
                                <th><?php _e('Type', 'fp-esperienze'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_extras as $extra) : ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               name="experience_extras[]" 
                                               value="<?php echo esc_attr($extra['id']); ?>" 
                                               <?php checked(in_array($extra['id'], $selected_extra_ids)); ?>>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($extra['name']); ?></strong>
                                        <?php if (!empty($extra['description'])) : ?>
                                            <br><small><?php echo esc_html(wp_trim_words($extra['description'], 15)); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo wc_price($extra['price']); ?></td>
                                    <td>
                                        <?php 
                                        echo $extra['pricing_type'] === 'per_person' 
                                            ? __('Per Person', 'fp-esperienze') 
                                            : __('Per Booking', 'fp-esperienze');
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="description">
                        <?php _e('Select the extras that should be available for this experience.', 'fp-esperienze'); ?>
                    </p>
                <?php endif; ?>
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
        
        // Save extras associations
        if (isset($_POST['experience_extras'])) {
            $extra_ids = array_map('intval', $_POST['experience_extras']);
            ExtraManager::setProductExtras($post_id, $extra_ids);
        } else {
            // Clear all associations if none selected
            ExtraManager::setProductExtras($post_id, []);
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