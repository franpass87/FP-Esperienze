<?php
/**
 * Security Enhancement Class
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Security enhancer class for additional security measures
 */
class SecurityEnhancer {
    
    /**
     * Initialize security enhancements
     */
    public static function init(): void {
        // Content Security Policy headers
        add_action('wp_head', [__CLASS__, 'addSecurityHeaders'], 1);
        add_action('admin_head', [__CLASS__, 'addSecurityHeaders'], 1);
        
        // Enhanced input validation
        add_filter('fp_esperienze_validate_input', [__CLASS__, 'enhancedInputValidation'], 10, 3);
        
        // Rate limiting for sensitive operations
        add_action('init', [__CLASS__, 'initRateLimiting']);
        
        // Security logging
        add_action('fp_esperienze_security_event', [__CLASS__, 'logSecurityEvent'], 10, 3);
        
        // Prevent information disclosure
        add_filter('rest_authentication_errors', [__CLASS__, 'restrictRestAccess'], 20);
    }
    
    /**
     * Add security headers for Content Security Policy
     */
    public static function addSecurityHeaders(): void {
        // Only add CSP if not already set and explicitly enabled
        if (!headers_sent() && apply_filters('fp_esperienze_enable_csp', false)) {
            $csp_directives = [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' *.google.com *.googleapis.com *.gstatic.com *.facebook.com *.meta.com https://js.chargebee.com https://assets.calendly.com",
                "style-src 'self' 'unsafe-inline' *.google.com *.googleapis.com *.gstatic.com https://fonts.googleapis.com",
                "img-src 'self' data: blob: *.google.com *.googleusercontent.com *.facebook.com *.meta.com",
                "font-src 'self' data: *.google.com *.gstatic.com https://fonts.gstatic.com",
                "connect-src 'self' *.google.com *.googleapis.com *.facebook.com *.meta.com",
                "frame-src 'self' *.google.com *.facebook.com *.youtube.com *.vimeo.com",
                "worker-src 'self' blob:",
                "manifest-src 'self'"
            ];
            
            $csp = implode('; ', apply_filters('fp_esperienze_csp_directives', $csp_directives));
            
            // Use meta tag instead of header to avoid conflicts
            echo '<meta http-equiv="Content-Security-Policy" content="' . esc_attr($csp) . '">' . "\n";
        }
    }
    
    /**
     * Enhanced input validation
     *
     * @param mixed $value Input value
     * @param string $type Expected type
     * @param array $options Validation options
     * @return mixed Validated value
     */
    public static function enhancedInputValidation($value, string $type, array $options = []) {
        switch ($type) {
            case 'booking_date':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return new \WP_Error('invalid_date', __('Invalid date format.', 'fp-esperienze'));
                }
                $timestamp = strtotime($value);
                if ($timestamp === false || $timestamp < time() - 86400) { // Allow yesterday for timezone edge cases
                    return new \WP_Error('invalid_date', __('Date cannot be in the past.', 'fp-esperienze'));
                }
                break;
                
            case 'booking_time':
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $value)) {
                    return new \WP_Error('invalid_time', __('Invalid time format.', 'fp-esperienze'));
                }
                break;
                
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return new \WP_Error('invalid_email', __('Invalid email address.', 'fp-esperienze'));
                }
                break;
                
            case 'phone':
                // International phone number validation
                if (!preg_match('/^[\+]?[1-9][\d]{0,15}$/', preg_replace('/[\s\-\(\)]/', '', $value))) {
                    return new \WP_Error('invalid_phone', __('Invalid phone number.', 'fp-esperienze'));
                }
                break;
                
            case 'voucher_code':
                if (!preg_match('/^[A-Z0-9\-]{6,20}$/', $value)) {
                    return new \WP_Error('invalid_voucher', __('Invalid voucher code format.', 'fp-esperienze'));
                }
                break;
                
            case 'product_id':
                $value = absint($value);
                if ($value <= 0 || get_post_type($value) !== 'product') {
                    return new \WP_Error('invalid_product', __('Invalid product ID.', 'fp-esperienze'));
                }
                break;
                
            default:
                // Generic sanitization based on type
                if (is_string($value)) {
                    $value = sanitize_text_field($value);
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Initialize rate limiting for sensitive operations
     */
    public static function initRateLimiting(): void {
        // Hook into sensitive operations
        add_action('wp_ajax_fp_reschedule_booking', [__CLASS__, 'checkReschedulingRateLimit'], 1);
        add_action('wp_ajax_fp_cancel_booking', [__CLASS__, 'checkCancellationRateLimit'], 1);
        add_action('rest_api_init', [__CLASS__, 'addRestRateLimiting']);
    }
    
    /**
     * Check rate limit for booking rescheduling
     */
    public static function checkReschedulingRateLimit(): void {
        if (!RateLimiter::checkRateLimit('reschedule_booking', 3, 300)) { // 3 per 5 minutes
            do_action('fp_esperienze_security_event', 'rate_limit_exceeded', 'reschedule_booking', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => get_current_user_id()
            ]);
            
            wp_send_json_error([
                'message' => __('Too many rescheduling attempts. Please try again in 5 minutes.', 'fp-esperienze')
            ]);
        }
    }
    
    /**
     * Check rate limit for booking cancellation
     */
    public static function checkCancellationRateLimit(): void {
        if (!RateLimiter::checkRateLimit('cancel_booking', 5, 300)) { // 5 per 5 minutes
            do_action('fp_esperienze_security_event', 'rate_limit_exceeded', 'cancel_booking', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_id' => get_current_user_id()
            ]);
            
            wp_send_json_error([
                'message' => __('Too many cancellation attempts. Please try again in 5 minutes.', 'fp-esperienze')
            ]);
        }
    }
    
    /**
     * Add rate limiting to REST API endpoints
     */
    public static function addRestRateLimiting(): void {
        add_filter('rest_pre_dispatch', [__CLASS__, 'checkRestRateLimit'], 10, 3);
    }
    
    /**
     * Check rate limit for REST API calls
     *
     * @param mixed $result
     * @param \WP_REST_Server $server
     * @param \WP_REST_Request $request
     * @return mixed
     */
    public static function checkRestRateLimit($result, $server, $request) {
        $route = $request->get_route();
        
        // Apply rate limiting to specific FP Esperienze endpoints
        if (strpos($route, '/fp-esperienze/') === 0) {
            $limit_key = 'rest_api_' . sanitize_key($route);
            
            if (!RateLimiter::checkRateLimit($limit_key, 60, 60)) { // 60 requests per minute
                do_action('fp_esperienze_security_event', 'api_rate_limit_exceeded', $route, [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
                
                return new \WP_Error(
                    'rest_rate_limit_exceeded',
                    __('API rate limit exceeded. Please try again later.', 'fp-esperienze'),
                    ['status' => 429]
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Log security events
     *
     * @param string $event_type Type of security event
     * @param string $context Context of the event
     * @param array $data Additional event data
     */
    public static function logSecurityEvent(string $event_type, string $context, array $data = []): void {
        if (apply_filters('fp_esperienze_enable_security_logging', defined('WP_DEBUG') && WP_DEBUG)) {
            $log_entry = [
                'timestamp' => current_time('mysql'),
                'event_type' => $event_type,
                'context' => $context,
                'data' => $data,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'user_id' => get_current_user_id() ?: null
            ];
            
            Log::write('security', $log_entry);
        }
    }
    
    /**
     * Restrict REST API access to authenticated users for sensitive endpoints
     *
     * @param mixed $result
     * @return mixed
     */
    public static function restrictRestAccess($result) {
        // Only apply to our plugin's endpoints
        if (is_wp_error($result) || !isset($_SERVER['REQUEST_URI'])) {
            return $result;
        }
        
        $request_uri = $_SERVER['REQUEST_URI'];
        if (strpos($request_uri, '/wp-json/fp-esperienze/') !== false) {
            // Allow public access to availability endpoints
            $public_endpoints = ['availability', 'archive'];
            $is_public = false;
            
            foreach ($public_endpoints as $endpoint) {
                if (strpos($request_uri, "/$endpoint/") !== false) {
                    $is_public = true;
                    break;
                }
            }
            
            if (!$is_public && !is_user_logged_in()) {
                return new \WP_Error(
                    'rest_forbidden',
                    __('Access denied. Authentication required.', 'fp-esperienze'),
                    ['status' => 401]
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Generate secure random token
     *
     * @param int $length Token length
     * @return string
     */
    public static function generateSecureToken(int $length = 32): string {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } else {
            // Fallback for older PHP versions
            return substr(str_shuffle(str_repeat('0123456789abcdef', ceil($length / 16))), 0, $length);
        }
    }
    
    /**
     * Sanitize and validate file upload
     *
     * @param array $file $_FILES array
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @return array|WP_Error
     */
    public static function validateFileUpload(array $file, array $allowed_types = [], int $max_size = 0): array {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return new \WP_Error('invalid_upload', __('Invalid file upload.', 'fp-esperienze'));
        }
        
        // Check file size
        if ($max_size > 0 && $file['size'] > $max_size) {
            return new \WP_Error('file_too_large', __('File size exceeds maximum allowed.', 'fp-esperienze'));
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!empty($allowed_types) && !in_array($mime_type, $allowed_types, true)) {
            return new \WP_Error('invalid_file_type', __('File type not allowed.', 'fp-esperienze'));
        }
        
        // Sanitize filename
        $file['name'] = sanitize_file_name($file['name']);
        
        return $file;
    }
}