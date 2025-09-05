<?php
/**
 * Tracking Manager for GA4 and Meta Pixel Integration
 *
 * @package FP\Esperienze\Integrations
 */

namespace FP\Esperienze\Integrations;

defined('ABSPATH') || exit;

/**
 * Handles frontend tracking for GA4 and Meta Pixel
 */
class TrackingManager {
    
    /**
     * Integration settings
     */
    private array $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = get_option('fp_esperienze_integrations', []);
        
        add_action('wp_enqueue_scripts', [$this, 'enqueueTrackingScripts']);
        add_action('wp_footer', [$this, 'outputTrackingCode'], 5);
        
        // WooCommerce tracking hooks
        add_action('woocommerce_add_to_cart', [$this, 'trackAddToCart'], 10, 6);
        add_action('woocommerce_before_checkout_form', [$this, 'trackBeginCheckout']);
        add_action('woocommerce_checkout_process', [$this, 'trackAddPaymentInfo']);
        add_action('woocommerce_checkout_order_processed', [$this, 'trackPurchase'], 10, 1);
        
        // UTM tracking hooks
        add_action('wp_head', [$this, 'captureUTMParameters']);
        add_action('woocommerce_checkout_order_processed', [$this, 'storeUTMParameters'], 5, 1);
    }
    
    /**
     * Check if GA4 tracking is enabled
     */
    public function isGA4Enabled(): bool {
        return !empty($this->settings['ga4_measurement_id']) && !empty($this->settings['ga4_ecommerce']);
    }
    
    /**
     * Check if Meta Pixel tracking is enabled  
     */
    public function isMetaPixelEnabled(): bool {
        return !empty($this->settings['meta_pixel_id']);
    }
    
    /**
     * Check if Google Ads tracking is enabled
     */
    public function isGoogleAdsEnabled(): bool {
        return !empty($this->settings['gads_conversion_id']);
    }
    
    /**
     * Check if we should load tracking scripts on current page
     */
    private function shouldLoadTracking(): bool {
        // Only load on WooCommerce pages with experience products
        if (is_singular('product')) {
            global $post;
            $product = wc_get_product($post->ID);
            return $product && $product->get_type() === 'experience';
        }
        
        return is_cart() || is_checkout() || is_checkout_pay_page() || is_order_received_page();
    }
    
    /**
     * Enqueue tracking scripts
     */
    public function enqueueTrackingScripts(): void {
        if (!$this->shouldLoadTracking()) {
            return;
        }
        
        if ($this->isGA4Enabled() || $this->isMetaPixelEnabled() || $this->isGoogleAdsEnabled()) {
            wp_enqueue_script(
                'fp-esperienze-tracking',
                FP_ESPERIENZE_PLUGIN_URL . 'assets/js/tracking.js',
                ['jquery'],
                FP_ESPERIENZE_VERSION,
                true
            );
            
            // Localize script with tracking settings
            wp_localize_script('fp-esperienze-tracking', 'fpTrackingSettings', [
                'ga4_enabled' => $this->isGA4Enabled(),
                'meta_enabled' => $this->isMetaPixelEnabled(),
                'gads_enabled' => $this->isGoogleAdsEnabled(),
                'ga4_measurement_id' => $this->settings['ga4_measurement_id'] ?? '',
                'meta_pixel_id' => $this->settings['meta_pixel_id'] ?? '',
                'gads_conversion_id' => $this->settings['gads_conversion_id'] ?? '',
                'gads_purchase_label' => $this->settings['gads_purchase_label'] ?? '',
                'currency' => get_woocommerce_currency(),
                // Consent Mode v2 settings
                'consent_mode_enabled' => !empty($this->settings['consent_mode_enabled']),
                'consent_cookie_name' => $this->settings['consent_cookie_name'] ?? 'marketing_consent',
                'consent_js_function' => $this->settings['consent_js_function'] ?? '',
            ]);
        }
    }
    
    /**
     * Output tracking initialization code in footer
     */
    public function outputTrackingCode(): void {
        if (!$this->shouldLoadTracking()) {
            return;
        }
        
        if ($this->isGA4Enabled()) {
            $measurement_id = esc_js($this->settings['ga4_measurement_id']);
            
            // Enqueue Google Analytics script properly
            wp_enqueue_script(
                'google-analytics-gtag',
                "https://www.googletagmanager.com/gtag/js?id={$measurement_id}",
                [],
                null,
                true
            );
            
            // Add inline script for GA4 initialization
            $ga4_init_script = "
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', '{$measurement_id}');
            ";
            wp_add_inline_script('google-analytics-gtag', $ga4_init_script);
        }
        
        if ($this->isGoogleAdsEnabled()) {
            $conversion_id = esc_js($this->settings['gads_conversion_id']);
            
            // Use same gtag script if GA4 is also enabled, otherwise load it
            if (!$this->isGA4Enabled()) {
                wp_enqueue_script(
                    'google-analytics-gtag',
                    "https://www.googletagmanager.com/gtag/js?id={$conversion_id}",
                    [],
                    null,
                    true
                );
                
                $gads_init_script = "
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                ";
                wp_add_inline_script('google-analytics-gtag', $gads_init_script);
            }
            
            // Add Google Ads config
            $gads_config_script = "gtag('config', '{$conversion_id}');";
            wp_add_inline_script('google-analytics-gtag', $gads_config_script);
        }
        
        if ($this->isMetaPixelEnabled()) {
            $pixel_id = esc_js($this->settings['meta_pixel_id']);
            
            // Add Meta Pixel inline script  
            $meta_pixel_script = "
                !function(f,b,e,v,n,t,s)
                {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
                n.callMethod.apply(n,arguments):n.queue.push(arguments)};
                if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
                n.queue=[];t=b.createElement(e);t.async=!0;
                t.src=v;s=b.getElementsByTagName(e)[0];
                s.parentNode.insertBefore(t,s)}(window, document,'script',
                'https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', '{$pixel_id}');
                fbq('track', 'PageView');
            ";
            wp_add_inline_script('fp-esperienze-tracking', $meta_pixel_script);
            
            // Add noscript fallback for Meta Pixel
            add_action('wp_footer', function() use ($pixel_id) {
                echo '<noscript><img height="1" width="1" style="display:none" src="' . 
                     esc_url("https://www.facebook.com/tr?id={$pixel_id}&ev=PageView&noscript=1") . 
                     '" /></noscript>' . "\n";
            });
        }
        
        // Output tracking data for JavaScript
        $tracking_data = $this->getTrackingData();
        if (!empty($tracking_data)) {
            $tracking_script = "window.fpTrackingData = " . wp_json_encode($tracking_data) . ";";
            wp_add_inline_script('fp-esperienze-tracking', $tracking_script);
        }
    }
    
    /**
     * Generate event ID for deduplication
     */
    public function generateEventId(string $event_type, int $order_id = 0): string {
        $base = $event_type . '_' . time();
        if ($order_id > 0) {
            $base .= '_' . $order_id;
        }
        return md5($base);
    }
    
    /**
     * Track add to cart event
     */
    public function trackAddToCart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data): void {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'experience') {
            return;
        }
        
        // Store tracking data in session for JavaScript pickup
        $tracking_data = [
            'event' => 'add_to_cart',
            'product_id' => $product_id,
            'product_name' => $product->get_name(),
            'price' => floatval($product->get_price()),
            'quantity' => $quantity,
            'slot_start' => $cart_item_data['fp_slot_start'] ?? null,
            'meeting_point_id' => $cart_item_data['fp_meeting_point_id'] ?? null,
            'lang' => $cart_item_data['fp_lang'] ?? null,
        ];
        
        WC()->session->set('fp_tracking_add_to_cart', $tracking_data);
    }
    
    /**
     * Track begin checkout event
     */
    public function trackBeginCheckout(): void {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $items = [];
        $total_value = 0;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product->get_type() === 'experience') {
                $items[] = [
                    'item_id' => $product->get_id(),
                    'item_name' => $product->get_name(),
                    'item_category' => 'Experience',
                    'price' => floatval($product->get_price()),
                    'quantity' => $cart_item['quantity'],
                    'slot_start' => $cart_item['fp_slot_start'] ?? null,
                    'meeting_point_id' => $cart_item['fp_meeting_point_id'] ?? null,
                    'lang' => $cart_item['fp_lang'] ?? null,
                ];
                $total_value += floatval($product->get_price()) * $cart_item['quantity'];
            }
        }
        
        if (!empty($items)) {
            $tracking_data = [
                'event' => 'begin_checkout',
                'currency' => get_woocommerce_currency(),
                'value' => $total_value,
                'items' => $items,
            ];
            
            WC()->session->set('fp_tracking_begin_checkout', $tracking_data);
        }
    }
    
    /**
     * Track add payment info event
     */
    public function trackAddPaymentInfo(): void {
        $cart = WC()->cart;
        if (!$cart || $cart->is_empty()) {
            return;
        }
        
        $items = [];
        $total_value = 0;
        
        foreach ($cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product->get_type() === 'experience') {
                $items[] = [
                    'item_id' => $product->get_id(),
                    'item_name' => $product->get_name(),
                    'item_category' => 'Experience',
                    'price' => floatval($product->get_price()),
                    'quantity' => $cart_item['quantity'],
                    'slot_start' => $cart_item['fp_slot_start'] ?? null,
                    'meeting_point_id' => $cart_item['fp_meeting_point_id'] ?? null,
                    'lang' => $cart_item['fp_lang'] ?? null,
                ];
                $total_value += floatval($product->get_price()) * $cart_item['quantity'];
            }
        }
        
        if (!empty($items)) {
            $tracking_data = [
                'event' => 'add_payment_info',
                'currency' => get_woocommerce_currency(),
                'value' => $total_value,
                'items' => $items,
            ];
            
            WC()->session->set('fp_tracking_add_payment_info', $tracking_data);
        }
    }
    
    /**
     * Track purchase event
     */
    public function trackPurchase(int $order_id): void {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $items = [];
        $total_value = 0;
        
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'experience') {
                $items[] = [
                    'item_id' => $product->get_id(),
                    'item_name' => $product->get_name(),
                    'item_category' => 'Experience',
                    'price' => floatval($item->get_total() / $item->get_quantity()),
                    'quantity' => $item->get_quantity(),
                    'slot_start' => $item->get_meta('_fp_slot_start') ?: null,
                    'meeting_point_id' => $item->get_meta('_fp_meeting_point_id') ?: null,
                    'lang' => $item->get_meta('_fp_lang') ?: null,
                ];
                $total_value += floatval($item->get_total());
            }
        }
        
        if (!empty($items)) {
            $tracking_data = [
                'event' => 'purchase',
                'transaction_id' => $order->get_order_number(),
                'currency' => $order->get_currency(),
                'value' => $total_value,
                'items' => $items,
                'event_id' => $this->generateEventId('purchase', $order_id),
            ];
            
            // Add UTM attribution data if available
            $utm_source = $order->get_meta('_utm_source');
            $utm_medium = $order->get_meta('_utm_medium');
            $utm_campaign = $order->get_meta('_utm_campaign');
            
            if ($utm_source || $utm_medium || $utm_campaign) {
                $tracking_data['attribution'] = [
                    'utm_source' => $utm_source,
                    'utm_medium' => $utm_medium,
                    'utm_campaign' => $utm_campaign,
                    'utm_term' => $order->get_meta('_utm_term'),
                    'utm_content' => $order->get_meta('_utm_content'),
                    'gclid' => $order->get_meta('_gclid'),
                    'fbclid' => $order->get_meta('_fbclid'),
                ];
            }
            
            // Store in transient for order confirmation page
            set_transient('fp_tracking_purchase_' . $order_id, $tracking_data, HOUR_IN_SECONDS);
        }
    }
    
    /**
     * Get tracking data for current page
     */
    public function getTrackingData(): array {
        $data = [];
        
        // Check for add to cart event
        if ($add_to_cart = WC()->session->get('fp_tracking_add_to_cart')) {
            $data['add_to_cart'] = $add_to_cart;
            WC()->session->__unset('fp_tracking_add_to_cart');
        }
        
        // Check for begin checkout event
        if ($begin_checkout = WC()->session->get('fp_tracking_begin_checkout')) {
            $data['begin_checkout'] = $begin_checkout;
            WC()->session->__unset('fp_tracking_begin_checkout');
        }
        
        // Check for add payment info event
        if ($add_payment_info = WC()->session->get('fp_tracking_add_payment_info')) {
            $data['add_payment_info'] = $add_payment_info;
            WC()->session->__unset('fp_tracking_add_payment_info');
        }
        
        // Check for purchase event on order received page
        if (is_order_received_page()) {
            global $wp;
            $order_id = absint($wp->query_vars['order-received']);
            if ($order_id && $purchase = get_transient('fp_tracking_purchase_' . $order_id)) {
                $data['purchase'] = $purchase;
                delete_transient('fp_tracking_purchase_' . $order_id);
            }
        }
        
        return $data;
    }
    
    /**
     * Capture UTM parameters and store in session
     */
    public function captureUTMParameters(): void {
        // Only capture UTM parameters on first page visit
        if (!WC()->session || WC()->session->get('utm_captured')) {
            return;
        }
        
        $utm_params = [
            'utm_source' => sanitize_text_field($_GET['utm_source'] ?? ''),
            'utm_medium' => sanitize_text_field($_GET['utm_medium'] ?? ''),
            'utm_campaign' => sanitize_text_field($_GET['utm_campaign'] ?? ''),
            'utm_term' => sanitize_text_field($_GET['utm_term'] ?? ''),
            'utm_content' => sanitize_text_field($_GET['utm_content'] ?? ''),
            'gclid' => sanitize_text_field($_GET['gclid'] ?? ''), // Google Ads click ID
            'fbclid' => sanitize_text_field($_GET['fbclid'] ?? ''), // Facebook click ID
        ];
        
        // Remove empty parameters
        $utm_params = array_filter($utm_params);
        
        if (!empty($utm_params)) {
            WC()->session->set('utm_parameters', $utm_params);
            WC()->session->set('utm_captured', true);
        }
    }
    
    /**
     * Store UTM parameters with order
     */
    public function storeUTMParameters(int $order_id): void {
        if (!WC()->session) {
            return;
        }
        
        $utm_params = WC()->session->get('utm_parameters');
        if (empty($utm_params)) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Store each UTM parameter as order meta
        foreach ($utm_params as $key => $value) {
            $order->update_meta_data('_' . $key, $value);
        }
        
        // Store attribution timestamp
        $order->update_meta_data('_attribution_timestamp', current_time('mysql'));
        
        $order->save();
        
        // Clear UTM data from session after storing
        WC()->session->__unset('utm_parameters');
        WC()->session->__unset('utm_captured');
    }
    
    /**
     * Send server-side Meta Conversions API event
     */
    public function sendMetaCAPIEvent(string $event_name, array $event_data, ?string $event_id = null): bool {
        if (empty($this->settings['meta_capi_enabled']) || empty($this->settings['meta_pixel_id'])) {
            return false;
        }
        
        // This is a placeholder for Meta Conversions API implementation
        // Requires Meta access token and proper server-side setup
        // For now, we'll log the event for future implementation
        
        $payload = [
            'data' => [
                [
                    'event_name' => $event_name,
                    'event_time' => time(),
                    'action_source' => 'website',
                    'event_source_url' => home_url(),
                    'custom_data' => $event_data,
                ]
            ]
        ];
        
        if ($event_id) {
            $payload['data'][0]['event_id'] = $event_id;
        }
        
        // TODO: Implement actual Meta Conversions API call
        // This would require:
        // 1. Meta access token configuration
        // 2. Dataset ID configuration  
        // 3. Proper API authentication
        // 4. HTTP request to Meta's Conversions API endpoint
        
        error_log('FP Esperienze: Meta CAPI event (placeholder): ' . wp_json_encode($payload));
        
        return true;
    }
}