<?php
/**
 * User Experience Enhancement Class
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * User experience enhancer class for better interfaces and interactions
 */
class UXEnhancer {
    
    /**
     * Initialize UX enhancements
     */
    public static function init(): void {
        // Progressive loading enhancements
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueueUXScripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueueAdminUXScripts']);
        
        // Enhanced loading indicators
        add_action('wp_footer', [__CLASS__, 'addLoadingIndicators']);
        add_action('admin_footer', [__CLASS__, 'addAdminLoadingIndicators']);
        
        // Better error messaging
        add_filter('fp_esperienze_error_message', [__CLASS__, 'enhanceErrorMessage'], 10, 2);
        
        // Accessibility improvements
        add_action('wp_head', [__CLASS__, 'addAccessibilityStyles']);
        add_action('admin_head', [__CLASS__, 'addAccessibilityStyles']);
        
        // Progressive form validation
        add_action('wp_ajax_fp_validate_form_field', [__CLASS__, 'ajaxValidateFormField']);
        add_action('wp_ajax_nopriv_fp_validate_form_field', [__CLASS__, 'ajaxValidateFormField']);
        
        // Enhanced notification system
        add_action('init', [__CLASS__, 'initNotificationSystem']);
    }
    
    /**
     * Enqueue UX enhancement scripts for frontend
     */
    public static function enqueueUXScripts(): void {
        if (self::shouldLoadUXScripts()) {
            $ux_asset = AssetOptimizer::getAssetInfo('js', 'ux-enhancer', 'assets/js/ux-enhancer.js');
            wp_enqueue_script(
                'fp-ux-enhancer',
                $ux_asset['url'],
                ['jquery'],
                $ux_asset['version'],
                true
            );
            
            wp_localize_script('fp-ux-enhancer', 'fpUX', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_ux_nonce'),
                'i18n' => [
                    'loading' => __('Loading...', 'fp-esperienze'),
                    'processing' => __('Processing...', 'fp-esperienze'),
                    'error' => __('An error occurred', 'fp-esperienze'),
                    'retry' => __('Retry', 'fp-esperienze'),
                    'close' => __('Close', 'fp-esperienze'),
                    'validating' => __('Validating...', 'fp-esperienze'),
                    'field_required' => __('This field is required', 'fp-esperienze'),
                    'invalid_email' => __('Please enter a valid email address', 'fp-esperienze'),
                    'invalid_phone' => __('Please enter a valid phone number', 'fp-esperienze'),
                ]
            ]);
        }
    }
    
    /**
     * Enqueue UX enhancement scripts for admin
     */
    public static function enqueueAdminUXScripts(): void {
        if (self::shouldLoadAdminUXScripts()) {
            $admin_ux_asset = AssetOptimizer::getAssetInfo('js', 'admin-ux-enhancer', 'assets/js/admin-ux-enhancer.js');
            wp_enqueue_script(
                'fp-admin-ux-enhancer',
                $admin_ux_asset['url'],
                ['jquery', 'jquery-ui-progressbar'],
                $admin_ux_asset['version'],
                true
            );
            
            wp_localize_script('fp-admin-ux-enhancer', 'fpAdminUX', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('fp_admin_ux_nonce'),
                'i18n' => [
                    'bulk_processing' => __('Processing %d items...', 'fp-esperienze'),
                    'bulk_complete' => __('Bulk operation completed', 'fp-esperienze'),
                    'bulk_error' => __('Bulk operation failed', 'fp-esperienze'),
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'fp-esperienze'),
                    'confirm_bulk_delete' => __('Are you sure you want to delete %d items?', 'fp-esperienze'),
                    'unsaved_changes' => __('You have unsaved changes. Are you sure you want to leave?', 'fp-esperienze'),
                ]
            ]);
        }
    }
    
    /**
     * Add progressive loading indicators
     */
    public static function addLoadingIndicators(): void {
        if (!self::shouldLoadUXScripts()) {
            return;
        }
        ?>
        <div id="fp-loading-overlay" style="display: none;">
            <div class="fp-loading-spinner">
                <div class="fp-spinner"></div>
                <p class="fp-loading-text"><?php esc_html_e('Loading...', 'fp-esperienze'); ?></p>
            </div>
        </div>
        
        <div id="fp-notification-container"></div>
        
        <style>
        #fp-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .fp-loading-spinner {
            text-align: center;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .fp-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007cba;
            border-radius: 50%;
            animation: fp-spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes fp-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .fp-loading-text {
            margin: 0;
            color: #666;
        }
        
        #fp-notification-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 999998;
            max-width: 400px;
        }
        
        .fp-notification {
            background: white;
            border-left: 4px solid #007cba;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            animation: fp-slide-in 0.3s ease-out;
        }
        
        .fp-notification.success {
            border-left-color: #46b450;
        }
        
        .fp-notification.error {
            border-left-color: #dc3232;
        }
        
        .fp-notification.warning {
            border-left-color: #ffb900;
        }
        
        @keyframes fp-slide-in {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Accessibility improvements */
        .fp-sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
        
        /* Focus improvements */
        .fp-focus-visible:focus {
            outline: 2px solid #007cba;
            outline-offset: 2px;
        }
        
        /* Form validation styling */
        .fp-field-error {
            border-color: #dc3232 !important;
            box-shadow: 0 0 2px rgba(220, 50, 50, 0.5);
        }
        
        .fp-field-success {
            border-color: #46b450 !important;
            box-shadow: 0 0 2px rgba(70, 180, 80, 0.5);
        }
        
        .fp-field-message {
            display: block;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .fp-field-message.error {
            color: #dc3232;
        }
        
        .fp-field-message.success {
            color: #46b450;
        }
        </style>
        <?php
    }
    
    /**
     * Add admin loading indicators
     */
    public static function addAdminLoadingIndicators(): void {
        if (!self::shouldLoadAdminUXScripts()) {
            return;
        }
        ?>
        <div id="fp-admin-bulk-progress" style="display: none;">
            <div class="fp-progress-container">
                <h3><?php esc_html_e('Processing...', 'fp-esperienze'); ?></h3>
                <div id="fp-progress-bar"></div>
                <p id="fp-progress-text">0%</p>
            </div>
        </div>
        
        <style>
        #fp-admin-bulk-progress {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 999999;
            min-width: 300px;
        }
        
        .fp-progress-container h3 {
            margin-top: 0;
            text-align: center;
        }
        
        #fp-progress-bar {
            width: 100%;
            height: 20px;
            background: #f3f3f3;
            border-radius: 10px;
            overflow: hidden;
            margin: 1rem 0;
        }
        
        #fp-progress-text {
            text-align: center;
            margin: 0;
            font-weight: bold;
        }
        
        /* Unsaved changes warning */
        .fp-unsaved-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 0.75rem;
            margin: 1rem 0;
            color: #856404;
        }
        </style>
        <?php
    }
    
    /**
     * Enhance error messages with better formatting and actions
     *
     * @param string $message Original error message
     * @param array $context Error context
     * @return string Enhanced error message
     */
    public static function enhanceErrorMessage(string $message, array $context = []): string {
        $enhanced_message = $message;
        
        // Add context-specific suggestions
        if (isset($context['type'])) {
            switch ($context['type']) {
                case 'booking_conflict':
                    $enhanced_message .= ' ' . sprintf(
                        __('Please try selecting a different time or <a href="%s">contact support</a> for assistance.', 'fp-esperienze'),
                        esc_url($context['support_url'] ?? '#')
                    );
                    break;
                    
                case 'payment_failed':
                    $enhanced_message .= ' ' . __('Please check your payment information and try again.', 'fp-esperienze');
                    break;
                    
                case 'availability_expired':
                    $enhanced_message .= ' ' . __('Please refresh the page and select your time again.', 'fp-esperienze');
                    break;
                    
                case 'validation_error':
                    $enhanced_message .= ' ' . __('Please correct the highlighted fields and try again.', 'fp-esperienze');
                    break;
            }
        }
        
        // Add retry button for retryable operations
        if (isset($context['retryable']) && $context['retryable']) {
            $enhanced_message .= sprintf(
                ' <button type="button" class="fp-retry-button" data-action="%s">%s</button>',
                esc_attr($context['retry_action'] ?? ''),
                esc_html__('Retry', 'fp-esperienze')
            );
        }
        
        return $enhanced_message;
    }
    
    /**
     * Add accessibility styles and improvements
     */
    public static function addAccessibilityStyles(): void {
        ?>
        <style>
        /* Skip link for keyboard navigation */
        .fp-skip-link {
            position: absolute;
            left: -9999px;
            z-index: 999999;
            padding: 8px 16px;
            background: #000;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
        }
        
        .fp-skip-link:focus {
            left: 6px;
            top: 7px;
        }
        
        /* High contrast mode support */
        @media (prefers-contrast: high) {
            .fp-button,
            .fp-input {
                border: 2px solid;
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            .fp-spinner,
            .fp-notification {
                animation: none;
            }
            
            .fp-loading-overlay {
                transition: none;
            }
        }
        
        /* Focus indicators */
        .fp-input:focus,
        .fp-button:focus,
        .fp-select:focus {
            outline: 2px solid #007cba;
            outline-offset: 2px;
        }
        
        /* Screen reader improvements */
        .fp-sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0,0,0,0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX form field validation
     */
    public static function ajaxValidateFormField(): void {
        check_ajax_referer('fp_ux_nonce', 'nonce');
        
        $field_type = sanitize_text_field(wp_unslash($_POST['field_type'] ?? ''));
        $field_value = sanitize_text_field(wp_unslash($_POST['field_value'] ?? ''));
        
        $validation_result = SecurityEnhancer::enhancedInputValidation($field_value, $field_type);
        
        if (is_wp_error($validation_result)) {
            wp_send_json_error([
                'message' => $validation_result->get_error_message(),
                'field_class' => 'fp-field-error'
            ]);
        } else {
            wp_send_json_success([
                'message' => __('Valid', 'fp-esperienze'),
                'field_class' => 'fp-field-success'
            ]);
        }
    }
    
    /**
     * Initialize enhanced notification system
     */
    public static function initNotificationSystem(): void {
        // Add session-based notifications with better session handling
        add_action('init', function() {
            if (!session_id() && !headers_sent()) {
                session_start();
            }
        }, 1);
        
        add_action('wp_footer', [__CLASS__, 'displaySessionNotifications']);
        add_action('admin_footer', [__CLASS__, 'displaySessionNotifications']);
    }
    
    /**
     * Display session-based notifications
     */
    public static function displaySessionNotifications(): void {
        if (!isset($_SESSION['fp_notifications'])) {
            return;
        }
        
        $notifications = $_SESSION['fp_notifications'];
        unset($_SESSION['fp_notifications']);
        
        foreach ($notifications as $notification) {
            $type = $notification['type'] ?? 'info';
            $message = $notification['message'] ?? '';
            $dismissible = $notification['dismissible'] ?? true;
            
            printf(
                '<div class="fp-notification %s" data-auto-dismiss="%s">%s%s</div>',
                esc_attr($type),
                $dismissible ? 'true' : 'false',
                wp_kses_post($message),
                $dismissible ? '<button type="button" class="fp-notification-dismiss">&times;</button>' : ''
            );
        }
    }
    
    /**
     * Add notification to session
     *
     * @param string $message Notification message
     * @param string $type Notification type (success, error, warning, info)
     * @param bool $dismissible Whether notification is dismissible
     */
    public static function addNotification(string $message, string $type = 'info', bool $dismissible = true): void {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
        
        if (!session_id()) {
            // Fallback to storing in WordPress options for this request
            $notifications = get_option('fp_temp_notifications', []);
            $notifications[] = [
                'message' => $message,
                'type' => $type,
                'dismissible' => $dismissible
            ];
            update_option('fp_temp_notifications', $notifications);
            return;
        }
        
        if (!isset($_SESSION['fp_notifications'])) {
            $_SESSION['fp_notifications'] = [];
        }
        
        $_SESSION['fp_notifications'][] = [
            'message' => $message,
            'type' => $type,
            'dismissible' => $dismissible
        ];
    }
    
    /**
     * Check if UX scripts should be loaded on frontend
     *
     * @return bool
     */
    private static function shouldLoadUXScripts(): bool {
        global $post;
        
        // Load on single product pages for experience products
        if (is_singular('product')) {
            $product = wc_get_product($post->ID);
            if ($product && $product->get_type() === 'experience') {
                return true;
            }
        }
        
        // Load on pages with experience shortcodes
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'fp_exp_archive')) {
            return true;
        }
        
        // Load on cart and checkout pages
        if (is_cart() || is_checkout()) {
            return true;
        }
        
        // Load on shop pages and archives for better user experience
        if (is_shop() || is_product_category() || is_product_tag()) {
            return true;
        }
        
        return apply_filters('fp_esperienze_load_ux_scripts', false);
    }
    
    /**
     * Check if admin UX scripts should be loaded
     *
     * @return bool
     */
    private static function shouldLoadAdminUXScripts(): bool {
        $screen = get_current_screen();
        
        if (!$screen) {
            return false;
        }
        
        // Load on FP Esperienze admin pages
        if (strpos($screen->id, 'fp-esperienze') !== false) {
            return true;
        }
        
        // Load on product edit pages
        if ($screen->id === 'product' || $screen->id === 'edit-product') {
            return true;
        }
        
        return apply_filters('fp_esperienze_load_admin_ux_scripts', false);
    }
}