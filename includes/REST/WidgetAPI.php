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

    private const IFRAME_RESPONSE_HEADER = 'X-FP-Widget-Iframe';
    private const IFRAME_ROUTE_PREFIX = '/fp-exp/v1/widget/iframe';
    private const ALLOWED_ORIGINS_FILTER = 'fp_esperienze_widget_allowed_origins';

    /**
     * Constructor
     */
    public function __construct() {
        $this->registerRoutes();
        add_filter('rest_pre_serve_request', [$this, 'serveIframeWidgetResponse'], 10, 4);
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
    public function getIframeWidget(WP_REST_Request $request): WP_REST_Response|WP_Error {
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

        $allowed_origins = $this->getAllowedOrigins();
        $request_origin = $this->determineRequestOrigin($request);

        if (!$this->isOriginAllowed($request_origin, $allowed_origins)) {
            $error_code = $request_origin === null ? 'missing_origin' : 'forbidden_origin';

            return new WP_Error(
                $error_code,
                __('This domain is not allowed to embed the widget.', 'fp-esperienze'),
                ['status' => 403]
            );
        }

        // Generate iframe HTML
        $html = $this->generateIframeHTML($data, $width, $height, $theme, $return_url, $allowed_origins);

        $response = new WP_REST_Response($html);
        $response->header('Content-Type', 'text/html; charset=utf-8');

        $this->applyCorsHeaders($response, $request_origin, $allowed_origins, true);

        return $response;
    }

    /**
     * Serve iframe widget responses as raw HTML.
     */
    public function serveIframeWidgetResponse(
        bool $served,
        $result,
        WP_REST_Request $request,
        WP_REST_Server $server
    ): bool {
        if ($served) {
            return true;
        }

        if (!$result instanceof WP_REST_Response) {
            return false;
        }

        $headers = array_change_key_case($result->get_headers(), CASE_LOWER);
        $has_marker_header = isset($headers[strtolower(self::IFRAME_RESPONSE_HEADER)]);

        $route = $request->get_route();
        $is_iframe_route = is_string($route) && str_starts_with($route, self::IFRAME_ROUTE_PREFIX);

        if (!$has_marker_header && !$is_iframe_route) {
            return false;
        }

        $data = $result->get_data();

        if (!is_string($data)) {
            return false;
        }

        $result->header('Content-Type', 'text/html; charset=utf-8');

        $server->send_headers($result);
        echo $data;

        return true;
    }

    /**
     * Get experience data for iframe
     */
    public function getExperienceData(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $product_id = $request->get_param('product_id');

        $allowed_origins = $this->getAllowedOrigins();
        $request_origin = $this->determineRequestOrigin($request);

        if (!$this->isOriginAllowed($request_origin, $allowed_origins)) {
            $error_code = $request_origin === null ? 'missing_origin' : 'forbidden_origin';

            return new WP_Error(
                $error_code,
                __('This domain is not allowed to access widget data.', 'fp-esperienze'),
                ['status' => 403]
            );
        }

        $data = $this->getExperienceWidgetData($product_id);
        if (is_wp_error($data)) {
            return $data;
        }

        $response = new WP_REST_Response($data);

        $this->applyCorsHeaders($response, $request_origin, $allowed_origins, false);

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
        $meeting_points = MeetingPointManager::getMeetingPointsForProduct($product_id);
        $meeting_points_data = [];

        if (!empty($meeting_points)) {
            $meeting_points_data = array_map(function($mp) {
                return [
                    'id' => $mp->id,
                    'name' => $mp->name,
                    'address' => $mp->address,
                    'lat' => $mp->lat,
                    'lng' => $mp->lng,
                ];
            }, $meeting_points);
        }

        // Get extras
        $extras = ExtraManager::getProductExtras($product_id, true);
        if (!is_array($extras)) {
            $extras = [];
        }

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
            'meeting_points' => $meeting_points_data,
            'extras' => array_map(static function($extra) {
                $extra_id = isset($extra->id) ? (int) $extra->id : 0;

                return [
                    'id' => $extra_id,
                    'name' => (string) ($extra->name ?? ''),
                    'description' => (string) ($extra->description ?? ''),
                    'price' => isset($extra->price) ? (float) $extra->price : 0.0,
                    'billing_type' => (string) ($extra->billing_type ?? 'per_person'),
                    'tax_class' => (string) ($extra->tax_class ?? ''),
                    'is_required' => !empty($extra->is_required),
                    'max_quantity' => isset($extra->max_quantity) ? (int) $extra->max_quantity : 1,
                    'sort_order' => isset($extra->sort_order) ? (int) $extra->sort_order : 0,
                ];
            }, $extras),
            'checkout_url' => home_url('/checkout'),
            'api_base' => get_rest_url(null, 'fp-exp/v1/'),
        ];
    }

    /**
     * Generate iframe HTML
     */
    private function generateIframeHTML(
        array $data,
        string $width,
        string $height,
        string $theme,
        ?string $return_url,
        array $allowed_origins
    ): string {
        $product = $data['product'];
        $pricing = $data['pricing'];
        $details = $data['details'];
        $meeting_points = $data['meeting_points'];
        $extras = $data['extras'];

        // Normalize pricing values for display
        $adult_price_raw = $pricing['adult_price'] ?? null;
        $child_price_raw = $pricing['child_price'] ?? null;

        $has_adult_price = is_numeric($adult_price_raw);
        $has_child_price = is_numeric($child_price_raw);

        $adult_price_value = $has_adult_price ? (float) $adult_price_raw : null;
        $child_price_value = $has_child_price ? (float) $child_price_raw : null;

        // Encode data for JavaScript
        $json_data = wp_json_encode($data);

        // Generate CSS classes based on theme
        $theme_class = $theme === 'dark' ? 'fp-widget-dark' : 'fp-widget-light';

        ob_start();
        $allowed_origins_json = wp_json_encode($allowed_origins);

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
            
            <?php if ($has_adult_price): ?>
                <div class="fp-widget-price">
                    <?php echo __('From', 'fp-esperienze'); ?> <?php echo esc_html($pricing['currency_symbol'] . number_format($adult_price_value ?? 0.0, 2)); ?>
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
                <span><?php echo esc_html($pricing['currency_symbol'] . number_format($adult_price_value ?? 0.0, 2)); ?></span>
            </div>

            <?php if ($has_child_price): ?>
                <div class="fp-widget-quantity">
                    <label><?php echo __('Children', 'fp-esperienze'); ?>:</label>
                    <input type="number"
                           id="child-qty"
                           class="fp-widget-quantity-input"
                           min="0"
                           max="<?php echo esc_attr($details['capacity'] ?? 10); ?>"
                           value="0">
                    <span><?php echo esc_html($pricing['currency_symbol'] . number_format($child_price_value ?? 0.0, 2)); ?></span>
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
            const allowedOrigins = <?php echo $allowed_origins_json ?: '[]'; ?>;

            function resolveOrigin(value) {
                if (!value || typeof value !== 'string') {
                    return null;
                }

                try {
                    return new URL(value, window.location.href).origin;
                } catch (error) {
                    return null;
                }
            }

            function isAllowedOrigin(origin) {
                if (!origin) {
                    return false;
                }

                return allowedOrigins.indexOf(origin) !== -1;
            }

            const parentOrigin = resolveOrigin(document.referrer);

            function postToParent(message) {
                if (!window.parent || window.parent === window) {
                    return false;
                }

                if (!isAllowedOrigin(parentOrigin)) {
                    return false;
                }

                window.parent.postMessage(message, parentOrigin);
                return true;
            }
            
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
                if (postToParent({
                    type: 'fp_widget_checkout',
                    url: checkoutUrl,
                    data: {
                        product_id: widgetData.product.id,
                        adult_qty: adultQty,
                        child_qty: childQty,
                        return_url: returnUrl
                    }
                })) {
                    return;
                }

                // Fallback: direct navigation
                window.open(checkoutUrl, '_blank');
            }

            // Event listeners
            adultQtyInput.addEventListener('change', updateTotal);
            childQtyInput.addEventListener('change', updateTotal);
            bookNowBtn.addEventListener('click', handleBooking);

            // Initial update
            updateTotal();

            // Function to calculate and send height to parent
            function notifyHeightChange() {
                if (!window.parent || window.parent === window) {
                    return;
                }

                const height = Math.max(
                    document.body.scrollHeight,
                    document.documentElement.scrollHeight,
                    document.body.offsetHeight,
                    document.documentElement.offsetHeight
                );

                postToParent({
                    type: 'fp_widget_height_change',
                    height: height,
                    productId: widgetData.product.id
                });
            }

            // Notify parent window that widget is ready
            if (window.parent && window.parent !== window) {
                setTimeout(() => {
                    const height = Math.max(
                        document.body.scrollHeight,
                        document.documentElement.scrollHeight,
                        document.body.offsetHeight,
                        document.documentElement.offsetHeight
                    );

                    postToParent({
                        type: 'fp_widget_ready',
                        height: height,
                        productId: widgetData.product.id
                    });
                }, 100);

                // Watch for content changes and notify parent
                if (window.ResizeObserver) {
                    const resizeObserver = new ResizeObserver(() => {
                        notifyHeightChange();
                    });
                    resizeObserver.observe(document.body);
                }
                
                // Also listen for window resize
                window.addEventListener('resize', notifyHeightChange);
                
                // Watch for content changes (fallback for older browsers)
                let lastHeight = 0;
                setInterval(() => {
                    const currentHeight = Math.max(
                        document.body.scrollHeight,
                        document.documentElement.scrollHeight,
                        document.body.offsetHeight,
                        document.documentElement.offsetHeight
                    );
                    if (currentHeight !== lastHeight) {
                        lastHeight = currentHeight;
                        notifyHeightChange();
                    }
                }, 500);
            }
        })();
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Retrieve the configured list of allowed origins.
     *
     * @return array<int, string>
     */
    private function getAllowedOrigins(): array {
        $origins = [];

        $site_origin = $this->normalizeOrigin(home_url());
        if ($site_origin !== null) {
            $origins[] = $site_origin;
        }

        $configured = apply_filters(self::ALLOWED_ORIGINS_FILTER, $origins);
        if (!is_array($configured)) {
            $configured = $origins;
        }

        $normalized = [];

        foreach ($configured as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $candidate = trim($candidate);

            if (preg_match('/^[a-z0-9.-]+$/i', $candidate)) {
                $candidate = 'https://' . $candidate;
            }

            $normalized_origin = $this->normalizeOrigin($candidate);

            if ($normalized_origin !== null) {
                $normalized[] = $normalized_origin;
            }
        }

        if (empty($normalized) && $site_origin !== null) {
            $normalized[] = $site_origin;
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeOrigin(?string $origin): ?string {
        if (!is_string($origin) || $origin === '') {
            return null;
        }

        $origin = trim($origin);

        $parts = function_exists('wp_parse_url') ? wp_parse_url($origin) : parse_url($origin);

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return $scheme . '://' . $host . $port;
    }

    private function normalizeHost(?string $value): ?string {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $value = trim($value);

        if (preg_match('/^[a-z0-9.-]+$/i', $value)) {
            return strtolower($value);
        }

        $parts = function_exists('wp_parse_url') ? wp_parse_url($value) : parse_url($value);

        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }

        return strtolower($parts['host']);
    }

    private function determineRequestOrigin(WP_REST_Request $request): ?string {
        $origin_header = $this->normalizeOrigin($request->get_header('origin'));
        if ($origin_header !== null) {
            return $origin_header;
        }

        $referer_header = $this->normalizeOrigin($request->get_header('referer'));
        if ($referer_header !== null) {
            return $referer_header;
        }

        $site_origin = $this->normalizeOrigin(home_url());
        $site_host = $this->normalizeHost($site_origin);
        $request_host = $this->normalizeHost($request->get_header('host'));

        if ($site_origin !== null && $site_host !== null && $site_host === $request_host) {
            return $site_origin;
        }

        return null;
    }

    private function isOriginAllowed(?string $origin, array $allowed_origins): bool {
        if ($origin === null) {
            return false;
        }

        if (in_array($origin, $allowed_origins, true)) {
            return true;
        }

        $origin_host = $this->normalizeHost($origin);

        foreach ($allowed_origins as $allowed) {
            if ($origin_host !== null && $this->normalizeHost($allowed) === $origin_host) {
                return true;
            }
        }

        return false;
    }

    private function applyCorsHeaders(
        WP_REST_Response $response,
        ?string $request_origin,
        array $allowed_origins,
        bool $mark_iframe
    ): void {
        $origin_to_use = $request_origin;

        if ($origin_to_use === null && !empty($allowed_origins)) {
            $origin_to_use = $allowed_origins[0];
        }

        if ($origin_to_use !== null) {
            $response->header('Access-Control-Allow-Origin', $origin_to_use);
            $response->header('Vary', 'Origin');
        }

        if (!$mark_iframe) {
            return;
        }

        $response->header('X-Frame-Options', 'ALLOWALL');

        $frame_ancestors = $this->buildFrameAncestorsValue($allowed_origins);

        if ($frame_ancestors !== null) {
            $response->header('Content-Security-Policy', 'frame-ancestors ' . $frame_ancestors . ';');
        }

        $response->header(self::IFRAME_RESPONSE_HEADER, '1');
    }

    private function buildFrameAncestorsValue(array $allowed_origins): ?string {
        $values = ['\'self\''];

        foreach ($allowed_origins as $origin) {
            if (!is_string($origin) || $origin === '') {
                continue;
            }

            $values[] = $origin;
        }

        $values = array_values(array_unique(array_filter($values)));

        if (empty($values)) {
            return null;
        }

        return implode(' ', $values);
    }
}
