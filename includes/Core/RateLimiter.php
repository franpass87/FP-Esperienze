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
     * Get client IP address.
     *
     * Uses WordPress' {@see wp_get_ip_address()} when available and falls back
     * to processing common proxy headers when behind a trusted proxy.
     * Falls back to `127.0.0.1` when the remote address is missing or
     * contains an invalid IP to ensure a stable transient key.
     *
     * @return string Client IP address
     */
    public static function getClientIP(): string {
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';

        // Validate the remote address and use a safe fallback if invalid.
        $remote_addr     = filter_var( $remote_addr, FILTER_VALIDATE_IP ) ?: '127.0.0.1';
        $trusted_proxies = apply_filters( 'fp_trusted_proxies', [] );

        if ( ! empty( $trusted_proxies ) && in_array( $remote_addr, $trusted_proxies, true ) ) {
            if ( function_exists( 'wp_get_ip_address' ) ) {
                $ip = wp_get_ip_address();
                if ( $ip ) {
                    return $ip;
                }
            }

            $forwarded_headers = [
                'HTTP_CLIENT_IP',
                'HTTP_X_REAL_IP',
                'HTTP_X_FORWARDED_FOR',
                'HTTP_X_FORWARDED',
                'HTTP_FORWARDED_FOR',
                'HTTP_FORWARDED',
            ];

            foreach ( $forwarded_headers as $header ) {
                if ( empty( $_SERVER[ $header ] ) ) {
                    continue;
                }

                $ips = [];
                if ( 'HTTP_FORWARDED' === $header || 'HTTP_FORWARDED_FOR' === $header ) {
                    $parts = explode( ',', $_SERVER[ $header ] );
                    foreach ( $parts as $part ) {
                        if ( preg_match( '/for=([^;]+)/', $part, $matches ) ) {
                            $ips[] = trim( $matches[1], " \"[]" );
                        } else {
                            $ips[] = trim( $part );
                        }
                    }
                } else {
                    $ips = explode( ',', $_SERVER[ $header ] );
                }

                foreach ( $ips as $ip ) {
                    $ip = trim( $ip );

                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                        return $ip;
                    }
                }
            }
        }

        return $remote_addr;
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
