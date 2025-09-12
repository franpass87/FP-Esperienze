<?php
/**
 * Widget REST API for iframe embedding
 *
 * @package FP\Esperienze\REST
 */

namespace FP\Esperienze\REST;

use FP\Esperienze\Data\ScheduleManager;
use FP\Esperienze\Data\MeetingPointManager;
use FP\Esperienze\Data\ExtraManager;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Widget API class for iframe embedding
 */
class WidgetAPI {

    /**
     * Constructor
     */
    public function __construct() {
        $this->registerRoutes();
    }

    /**
     * Register REST routes
     */
    public function registerRoutes(): void {
        // Iframe widget endpoint
        register_rest_route('fp-exp/v1', '/widget/iframe/(?P<product_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getIframeWidget'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
                'width' => [
                    'default'           => '100%',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'height' => [
                    'default'           => '600px',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'theme' => [
                    'default'           => 'light',
                    'enum'              => ['light', 'dark'],
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'return_url' => [
                    'sanitize_callback' => 'esc_url_raw',
                ],
            ],
        ]);

        // Experience data endpoint for iframe
        register_rest_route('fp-exp/v1', '/widget/data/(?P<product_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'getExperienceData'],
            'permission_callback' => '__return_true',
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'validate_callback' => function($param) {
                        return is_numeric($param) && $param > 0;
                    },
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get iframe widget HTML
     */
    public function getIframeWidget(WP_REST_Request $request): WP_REST_Response {
        $product_id = $request->get_param('product_id');
        $width = $request->get_param('width');
        $height = $request->get_param('height');
        $theme = $request->get_param('theme');
        $return_url = $request->get_param('return_url');

        // Get product
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return new WP_Error('invalid_product', __('Experience not found', 'fp-esperienze'), ['status' => 404]);
        }

        // Get experience data
        $data = $this->getExperienceWidgetData($product_id);
        if (is_wp_error($data)) {
            return $data;
        }

        // Generate iframe HTML
        $html = $this->generateIframeHTML($data, $width, $height, $theme, $return_url);

        $response = new WP_REST_Response($html);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        
        // Add CORS headers for iframe embedding
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('X-Frame-Options', 'ALLOWALL');
        $response->header('Content-Security-Policy', "frame-ancestors *;");

        return $response;
    }

    /**
     * Get experience data for iframe
     */
    public function getExperienceData(WP_REST_Request $request): WP_REST_Response {
        $product_id = $request->get_param('product_id');

        $data = $this->getExperienceWidgetData($product_id);
        if (is_wp_error($data)) {
            return $data;
        }

        $response = new WP_REST_Response($data);
        $response->header('Access-Control-Allow-Origin', '*');

        return $response;
    }

    /**
     * Get experience data for widget
     */
    private function getExperienceWidgetData(int $product_id): array|\WP_Error {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return new WP_Error('invalid_product', __('Experience not found', 'fp-esperienze'), ['status' => 404]);
        }

        // Get schedules
        $schedules = ScheduleManager::getSchedules($product_id);
        if (empty($schedules)) {
            return new WP_Error('no_schedules', __('No schedules available', 'fp-esperienze'), ['status' => 404]);
        }

        $first_schedule = $schedules[0];

        // Get meeting points
        $meeting_points = MeetingPointManager::getMeetingPoints($product_id);

        // Get extras
        $extras = ExtraManager::getExtras($product_id);

        // Get images
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';

        $gallery_ids = $product->get_gallery_image_ids();
        $gallery_images = [];
        foreach ($gallery_ids as $gallery_id) {
            $gallery_images[] = [
                'url' => wp_get_attachment_image_url($gallery_id, 'large'),
                'alt' => get_post_meta($gallery_id, '_wp_attachment_image_alt', true),
            ];
        }

        return [
            'product' => [
                'id' => $product_id,
                'name' => $product->get_name(),
                'description' => $product->get_description(),
                'short_description' => $product->get_short_description(),
                'image_url' => $image_url,
                'gallery_images' => $gallery_images,
                'permalink' => get_permalink($product_id),
            ],
            'pricing' => [
                'adult_price' => floatval($first_schedule->price_adult ?? 0),
                'child_price' => floatval($first_schedule->price_child ?? 0),
                'currency' => get_woocommerce_currency(),
                'currency_symbol' => get_woocommerce_currency_symbol(),
            ],
            'details' => [
                'duration' => $first_schedule->duration_min ?? null,
                'capacity' => $first_schedule->capacity ?? null,
                'languages' => array_unique(array_filter(array_column($schedules, 'lang'))),
            ],
            'meeting_points' => array_map(function($mp) {
                return [
                    'id' => $mp->id,
                    'name' => $mp->name,
                    'address' => $mp->address,
                    'lat' => $mp->lat,
                    'lng' => $mp->lng,
                ];
            }, $meeting_points),
            'extras' => array_map(function($extra) {
                return [
                    'id' => $extra->id,
                    'name' => $extra->name,
                    'price' => floatval($extra->price),
                    'max_quantity' => $extra->max_quantity,
                ];
            }, $extras),
            'checkout_url' => home_url('/checkout'),
            'api_base' => get_rest_url(null, 'fp-exp/v1/'),
        ];
    }

    /**
     * Generate iframe HTML
     */
    private function generateIframeHTML(array $data, string $width, string $height, string $theme, ?string $return_url): string {
        $product = $data['product'];
        $pricing = $data['pricing'];
        $details = $data['details'];
        $meeting_points = $data['meeting_points'];
        $extras = $data['extras'];

        // Encode data for JavaScript
        $json_data = wp_json_encode($data);
        
        // Generate CSS classes based on theme
        $theme_class = $theme === 'dark' ? 'fp-widget-dark' : 'fp-widget-light';

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_locale()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($product['name']); ?> - Widget</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            width: <?php echo esc_attr($width); ?>;
            height: <?php echo esc_attr($height); ?>;
            overflow-x: hidden;
        }
        
        .fp-widget-light {
            background: #fff;
            color: #333;
        }
        
        .fp-widget-dark {
            background: #1a1a1a;
            color: #fff;
        }
        
        .fp-widget-container {
            padding: 20px;
            height: 100%;
            overflow-y: auto;
        }
        
        .fp-widget-header {
            margin-bottom: 20px;
        }
        
        .fp-widget-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .fp-widget-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .fp-widget-price {
            font-size: 20px;
            font-weight: 600;
            color: #007cba;
            margin-bottom: 15px;
        }
        
        .fp-widget-dark .fp-widget-price {
            color: #4fc3f7;
        }
        
        .fp-widget-details {
            margin-bottom: 20px;
        }
        
        .fp-widget-detail {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .fp-widget-dark .fp-widget-detail {
            border-bottom-color: #333;
        }
        
        .fp-widget-section {
            margin-bottom: 20px;
        }
        
        .fp-widget-section-title {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .fp-widget-button {
            background: #007cba;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: background 0.2s;
        }
        
        .fp-widget-button:hover {
            background: #005a87;
        }
        
        .fp-widget-button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .fp-widget-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
        }
        
        .fp-widget-quantity-input {
            width: 60px;
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        
        .fp-widget-dark .fp-widget-quantity-input {
            background: #333;
            border-color: #555;
            color: #fff;
        }
    </style>
</head>
<body class="<?php echo esc_attr($theme_class); ?>">
    <div class="fp-widget-container">
        <div class="fp-widget-header">
            <?php if ($product['image_url']): ?>
                <img src="<?php echo esc_url($product['image_url']); ?>" 
                     alt="<?php echo esc_attr($product['name']); ?>" 
                     class="fp-widget-image">
            <?php endif; ?>
            
            <h1 class="fp-widget-title"><?php echo esc_html($product['name']); ?></h1>
            
            <?php if ($pricing['adult_price']): ?>
                <div class="fp-widget-price">
                    <?php echo __('From', 'fp-esperienze'); ?> <?php echo esc_html($pricing['currency_symbol'] . number_format($pricing['adult_price'], 2)); ?>
                    <small><?php echo __('per person', 'fp-esperienze'); ?></small>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($product['short_description']): ?>
            <div class="fp-widget-section">
                <div class="fp-widget-section-title"><?php echo __('Description', 'fp-esperienze'); ?></div>
                <div><?php echo wp_kses_post($product['short_description']); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="fp-widget-details">
            <div class="fp-widget-section-title"><?php echo __('Details', 'fp-esperienze'); ?></div>
            
            <?php if ($details['duration']): ?>
                <div class="fp-widget-detail">
                    <span><?php echo __('Duration', 'fp-esperienze'); ?></span>
                    <span><?php echo esc_html($details['duration']); ?> <?php echo __('minutes', 'fp-esperienze'); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($details['capacity']): ?>
                <div class="fp-widget-detail">
                    <span><?php echo __('Max Participants', 'fp-esperienze'); ?></span>
                    <span><?php echo esc_html($details['capacity']); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($details['languages'])): ?>
                <div class="fp-widget-detail">
                    <span><?php echo __('Languages', 'fp-esperienze'); ?></span>
                    <span><?php echo esc_html(implode(', ', $details['languages'])); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="fp-widget-section">
            <div class="fp-widget-section-title"><?php echo __('Participants', 'fp-esperienze'); ?></div>
            
            <div class="fp-widget-quantity">
                <label><?php echo __('Adults', 'fp-esperienze'); ?>:</label>
                <input type="number" 
                       id="adult-qty" 
                       class="fp-widget-quantity-input" 
                       min="0" 
                       max="<?php echo esc_attr($details['capacity'] ?? 10); ?>" 
                       value="1">
                <span><?php echo esc_html($pricing['currency_symbol'] . number_format($pricing['adult_price'], 2)); ?></span>
            </div>
            
            <?php if ($pricing['child_price']): ?>
                <div class="fp-widget-quantity">
                    <label><?php echo __('Children', 'fp-esperienze'); ?>:</label>
                    <input type="number" 
                           id="child-qty" 
                           class="fp-widget-quantity-input" 
                           min="0" 
                           max="<?php echo esc_attr($details['capacity'] ?? 10); ?>" 
                           value="0">
                    <span><?php echo esc_html($pricing['currency_symbol'] . number_format($pricing['child_price'], 2)); ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <button id="book-now-btn" class="fp-widget-button">
            <?php echo __('Book This Experience', 'fp-esperienze'); ?>
        </button>
    </div>

    <script>
        (function() {
            'use strict';
            
            // Widget data
            const widgetData = <?php echo $json_data; ?>;
            const returnUrl = <?php echo $return_url ? wp_json_encode($return_url) : 'null'; ?>;
            
            // Elements
            const adultQtyInput = document.getElementById('adult-qty');
            const childQtyInput = document.getElementById('child-qty');
            const bookNowBtn = document.getElementById('book-now-btn');
            
            // Update total and validate
            function updateTotal() {
                const adultQty = parseInt(adultQtyInput.value) || 0;
                const childQty = parseInt(childQtyInput.value) || 0;
                const total = adultQty + childQty;
                
                // Enable/disable book button
                bookNowBtn.disabled = total === 0;
                
                // Update button text with total
                if (total > 0) {
                    const adultPrice = widgetData.pricing.adult_price * adultQty;
                    const childPrice = widgetData.pricing.child_price * childQty;
                    const totalPrice = adultPrice + childPrice;
                    
                    bookNowBtn.textContent = `<?php echo __('Book Now', 'fp-esperienze'); ?> - ${widgetData.pricing.currency_symbol}${totalPrice.toFixed(2)}`;
                } else {
                    bookNowBtn.textContent = '<?php echo __('Book This Experience', 'fp-esperienze'); ?>';
                }
            }
            
            // Handle booking
            function handleBooking() {
                const adultQty = parseInt(adultQtyInput.value) || 0;
                const childQty = parseInt(childQtyInput.value) || 0;
                
                if (adultQty + childQty === 0) {
                    alert('<?php echo esc_js(__('Please select at least one participant', 'fp-esperienze')); ?>');
                    return;
                }
                
                // Build checkout URL with booking data
                const params = new URLSearchParams({
                    'fp_widget_booking': '1',
                    'product_id': widgetData.product.id,
                    'adult_qty': adultQty,
                    'child_qty': childQty,
                });
                
                if (returnUrl) {
                    params.append('return_url', returnUrl);
                }
                
                const checkoutUrl = `${widgetData.checkout_url}?${params.toString()}`;
                
                // Try to communicate with parent window first
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({
                        type: 'fp_widget_checkout',
                        url: checkoutUrl,
                        data: {
                            product_id: widgetData.product.id,
                            adult_qty: adultQty,
                            child_qty: childQty,
                            return_url: returnUrl
                        }
                    }, '*');
                } else {
                    // Fallback: direct navigation
                    window.open(checkoutUrl, '_blank');
                }
            }
            
            // Event listeners
            adultQtyInput.addEventListener('change', updateTotal);
            childQtyInput.addEventListener('change', updateTotal);
            bookNowBtn.addEventListener('click', handleBooking);
            
            // Initial update
            updateTotal();
            
            // Notify parent window that widget is ready
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'fp_widget_ready',
                    height: document.body.scrollHeight
                }, '*');
            }
        })();
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}