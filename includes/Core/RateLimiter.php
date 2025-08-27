<?php
/**
 * Rate Limiter for REST API
 *
 * @package FP\Esperienze\Core
 */

namespace FP\Esperienze\Core;

defined('ABSPATH') || exit;

/**
 * Rate Limiter class for controlling API request rates
 */
class RateLimiter {

    /**
     * Check if request is within rate limit
     *
     * @param string $endpoint Endpoint identifier
     * @param int $limit Request limit per window
     * @param int $window Time window in seconds (default: 60 seconds)
     * @return bool True if within limit, false if exceeded
     */
    public static function checkRateLimit(string $endpoint, int $limit = 30, int $window = 60): bool {
        $client_ip = self::getClientIP();
        $key = 'fp_rate_limit_' . md5($endpoint . '_' . $client_ip);
        
        $current_count = get_transient($key);
        
        if ($current_count === false) {
            // First request in this window
            set_transient($key, 1, $window);
            return true;
        }
        
        if ($current_count >= $limit) {
            return false;
        }
        
        // Increment counter
        set_transient($key, $current_count + 1, $window);
        return true;
    }

    /**
     * Get client IP address
     *
     * @return string Client IP address
     */
    private static function getClientIP(): string {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancers/proxies
            'HTTP_X_FORWARDED',          // Proxies
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster balancers
            'HTTP_FORWARDED_FOR',        // Proxies
            'HTTP_FORWARDED',            // Proxies
            'HTTP_CLIENT_IP',            // Proxies
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to REMOTE_ADDR even if it's a private IP
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get rate limit headers for response
     *
     * @param string $endpoint Endpoint identifier
     * @param int $limit Request limit per window
     * @param int $window Time window in seconds
     * @return array Headers array
     */
    public static function getRateLimitHeaders(string $endpoint, int $limit = 30, int $window = 60): array {
        $client_ip = self::getClientIP();
        $key = 'fp_rate_limit_' . md5($endpoint . '_' . $client_ip);
        
        $current_count = get_transient($key) ?: 0;
        $remaining = max(0, $limit - $current_count);
        
        return [
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Window' => $window
        ];
    }

    /**
     * Create rate limit exceeded response
     *
     * @return \WP_Error
     */
    public static function createRateLimitResponse(): \WP_Error {
        return new \WP_Error(
            'rate_limit_exceeded',
            __('Rate limit exceeded. Please try again later.', 'fp-esperienze'),
            ['status' => 429]
        );
    }
}