<?php
/**
 * Booking Widget Frontend
 *
 * @package FP\Esperienze\Frontend
 */

namespace FP\Esperienze\Frontend;

/**
 * Handles the booking widget display and functionality
 */
class BookingWidget {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'init_hooks']);
        add_action('wp_ajax_fp_add_experience_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_fp_add_experience_to_cart', [$this, 'ajax_add_to_cart']);
    }
    
    /**
     * Initialize hooks
     */
    public function init_hooks() {
        // Hook into WooCommerce single product page
        add_action('woocommerce_single_product_summary', [$this, 'display_booking_widget'], 25);
        add_filter('wc_get_template', [$this, 'override_single_product_template'], 10, 2);
    }
    
    /**
     * Override single product template for experiences
     *
     * @param string $template
     * @param string $template_name
     * @return string
     */
    public function override_single_product_template($template, $template_name) {
        if ($template_name === 'single-product.php') {
            global $product;
            
            if ($product && $this->is_experience_product($product)) {
                $custom_template = FP_ESPERIENZE_PLUGIN_DIR . 'templates/single-experience.php';
                if (file_exists($custom_template)) {
                    return $custom_template;
                }
            }
        }
        
        return $template;
    }
    
    /**
     * Check if product is an experience
     *
     * @param \WC_Product $product
     * @return bool
     */
    private function is_experience_product($product) {
        // For now, check if product has a specific meta field or category
        // In a full implementation, this would check for the custom product type
        return $product->get_meta('_is_experience') === 'yes' || 
               has_term('experience', 'product_cat', $product->get_id());
    }
    
    /**
     * Display booking widget on single product page
     */
    public function display_booking_widget() {
        global $product;
        
        if (!$product || !$this->is_experience_product($product)) {
            return;
        }
        
        $this->render_booking_widget($product);
    }
    
    /**
     * Render the booking widget
     *
     * @param \WC_Product $product
     */
    public function render_booking_widget($product) {
        $product_id = $product->get_id();
        ?>
        <div id="fp-booking-widget" class="fp-booking-widget" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="fp-booking-step fp-step-date">
                <h3><?php esc_html_e('Select Date', 'fp-esperienze'); ?></h3>
                <input type="date" id="fp-date-picker" class="fp-date-picker" 
                       min="<?php echo esc_attr(date('Y-m-d')); ?>">
            </div>
            
            <div class="fp-booking-step fp-step-slots" style="display: none;">
                <h3><?php esc_html_e('Select Time', 'fp-esperienze'); ?></h3>
                <div id="fp-slots-container" class="fp-slots-container">
                    <!-- Slots will be loaded via AJAX -->
                </div>
            </div>
            
            <div class="fp-booking-step fp-step-quantity" style="display: none;">
                <h3><?php esc_html_e('Select Quantity', 'fp-esperienze'); ?></h3>
                <div class="fp-quantity-controls">
                    <div class="fp-quantity-item">
                        <label for="fp-adults"><?php esc_html_e('Adults', 'fp-esperienze'); ?></label>
                        <div class="fp-quantity-input">
                            <button type="button" class="fp-qty-btn fp-qty-minus" data-target="fp-adults">-</button>
                            <input type="number" id="fp-adults" class="fp-qty-input" value="1" min="1" max="20">
                            <button type="button" class="fp-qty-btn fp-qty-plus" data-target="fp-adults">+</button>
                        </div>
                        <span class="fp-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                    </div>
                    
                    <div class="fp-quantity-item">
                        <label for="fp-children"><?php esc_html_e('Children (0-12)', 'fp-esperienze'); ?></label>
                        <div class="fp-quantity-input">
                            <button type="button" class="fp-qty-btn fp-qty-minus" data-target="fp-children">-</button>
                            <input type="number" id="fp-children" class="fp-qty-input" value="0" min="0" max="20">
                            <button type="button" class="fp-qty-btn fp-qty-plus" data-target="fp-children">+</button>
                        </div>
                        <span class="fp-price"><?php
                            $child_price = $product->get_meta('_child_price');
                            echo $child_price ? wc_price($child_price) : wc_price($product->get_price() * 0.7);
                        ?></span>
                    </div>
                </div>
            </div>
            
            <div class="fp-booking-step fp-step-cart" style="display: none;">
                <div class="fp-booking-summary">
                    <div id="fp-booking-details"></div>
                    <div class="fp-total-price">
                        <strong><?php esc_html_e('Total:', 'fp-esperienze'); ?> <span id="fp-total-amount"></span></strong>
                    </div>
                </div>
                
                <button type="button" id="fp-add-to-cart" class="fp-add-to-cart-btn button alt">
                    <?php esc_html_e('Add to Cart', 'fp-esperienze'); ?>
                </button>
            </div>
            
            <div class="fp-loading" style="display: none;">
                <span><?php esc_html_e('Loading...', 'fp-esperienze'); ?></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle AJAX add to cart
     */
    public function ajax_add_to_cart() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'fp_esperienze_nonce')) {
            wp_die(__('Security check failed', 'fp-esperienze'));
        }
        
        $product_id = absint($_POST['product_id']);
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $adults = absint($_POST['adults']);
        $children = absint($_POST['children']);
        
        // Validate inputs
        if (!$product_id || !$date || !$time || $adults < 1) {
            wp_send_json_error(__('Invalid booking data', 'fp-esperienze'));
        }
        
        // Validate availability and capacity
        $validation_result = $this->validate_booking($product_id, $date, $time, $adults + $children);
        if (is_wp_error($validation_result)) {
            wp_send_json_error($validation_result->get_error_message());
        }
        
        // Calculate total quantity
        $total_quantity = $adults + $children;
        
        // Add to cart with custom data
        $cart_item_data = [
            'fp_experience_date' => $date,
            'fp_experience_time' => $time,
            'fp_adults' => $adults,
            'fp_children' => $children
        ];
        
        $cart_item_key = WC()->cart->add_to_cart($product_id, $total_quantity, 0, [], $cart_item_data);
        
        if ($cart_item_key) {
            wp_send_json_success([
                'message' => __('Experience added to cart successfully!', 'fp-esperienze'),
                'cart_url' => wc_get_cart_url()
            ]);
        } else {
            wp_send_json_error(__('Failed to add experience to cart', 'fp-esperienze'));
        }
    }
    
    /**
     * Validate booking availability and capacity
     *
     * @param int $product_id
     * @param string $date
     * @param string $time
     * @param int $quantity
     * @return bool|\WP_Error
     */
    private function validate_booking($product_id, $date, $time, $quantity) {
        // Make internal request to availability API
        $request = new \WP_REST_Request('GET', '/fp-exp/v1/availability');
        $request->set_param('product_id', $product_id);
        $request->set_param('date', $date);
        
        $controller = new \FP\Esperienze\REST\AvailabilityController();
        $response = $controller->get_availability($request);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $data = $response->get_data();
        
        // Find the specific slot
        $slot_found = false;
        foreach ($data['slots'] as $slot) {
            if ($slot['time'] === $time) {
                $slot_found = true;
                
                if (!$slot['available']) {
                    return new \WP_Error('slot_unavailable', __('This time slot is no longer available', 'fp-esperienze'));
                }
                
                if ($slot['capacity_left'] < $quantity) {
                    return new \WP_Error('insufficient_capacity', sprintf(
                        __('Only %d spots available for this time slot', 'fp-esperienze'),
                        $slot['capacity_left']
                    ));
                }
                
                break;
            }
        }
        
        if (!$slot_found) {
            return new \WP_Error('slot_not_found', __('Selected time slot not found', 'fp-esperienze'));
        }
        
        return true;
    }
}