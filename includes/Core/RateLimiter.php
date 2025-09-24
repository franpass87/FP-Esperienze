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
        $remote_addr_raw = $_SERVER['REMOTE_ADDR'] ?? '';
        $remote_addr     = filter_var($remote_addr_raw, FILTER_VALIDATE_IP);
        if (!is_string($remote_addr)) {
            $remote_addr = '127.0.0.1';
        }

        $trusted_proxies = self::normalizeTrustedProxies(apply_filters('fp_trusted_proxies', []));

        if ($trusted_proxies !== [] && in_array($remote_addr, $trusted_proxies, true)) {
            if (function_exists('wp_get_ip_address')) {
                $wp_ip_raw = wp_get_ip_address();
                if (is_string($wp_ip_raw)) {
                    $wp_ip = filter_var($wp_ip_raw, FILTER_VALIDATE_IP);
                    if (is_string($wp_ip)) {
                        return apply_filters('fp_resolved_client_ip', $wp_ip, $remote_addr);
                    }
                }
            }

            $header_order = apply_filters(
                'fp_trusted_proxy_headers',
                [
                    'HTTP_CF_CONNECTING_IP',
                    'HTTP_CLIENT_IP',
                    'HTTP_X_CLUSTER_CLIENT_IP',
                    'HTTP_X_REAL_IP',
                    'HTTP_X_FORWARDED_FOR',
                    'HTTP_X_FORWARDED',
                    'HTTP_FORWARDED_FOR',
                    'HTTP_FORWARDED',
                ]
            );

            foreach (is_array($header_order) ? $header_order : (array) $header_order as $header) {
                if (!is_string($header) || $header === '') {
                    continue;
                }

                if (!array_key_exists($header, $_SERVER)) {
                    continue;
                }

                $raw_header_value = (string) $_SERVER[$header];
                if ($raw_header_value === '') {
                    continue;
                }

                $candidate = self::extractIpFromHeader($header, $raw_header_value);
                if ($candidate !== null && $candidate !== '') {
                    return apply_filters('fp_resolved_client_ip', $candidate, $remote_addr);
                }
            }
        }

        return apply_filters('fp_resolved_client_ip', $remote_addr, $remote_addr);
    }

    /**
     * Normalize the list of trusted proxies returned by the filter.
     *
     * @param mixed $proxies Raw list provided by filter consumers.
     * @return array<int, string>
     */
    private static function normalizeTrustedProxies($proxies): array {
        if (!is_array($proxies)) {
            $proxies = [$proxies];
        }

        $normalized = [];

        foreach ($proxies as $proxy) {
            $candidate = is_string($proxy) ? trim($proxy) : '';
            $validated = filter_var($candidate, FILTER_VALIDATE_IP);
            if (is_string($validated)) {
                $normalized[] = $validated;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Extract a valid client IP from a forwarded header.
     *
     * @param string $header Header name.
     * @param string $value  Header value.
     * @return string|null
     */
    private static function extractIpFromHeader(string $header, string $value): ?string {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $candidates = self::normalizeHeaderCandidates($header, $value);
        $private_candidate = null;

        foreach ($candidates as $candidate) {
            $candidate = self::sanitizeIpCandidate($candidate);

            if ($candidate === null || $candidate === '') {
                continue;
            }

            $public_candidate = filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
            if (is_string($public_candidate)) {
                return $public_candidate;
            }

            if ($private_candidate === null) {
                $validated = filter_var($candidate, FILTER_VALIDATE_IP);
                if (is_string($validated)) {
                    $private_candidate = $validated;
                }
            }
        }

        return $private_candidate;
    }

    /**
     * Convert the forwarded header value into a list of raw candidates.
     *
     * @param string $header Header name.
     * @param string $value  Header value.
     * @return array<int, string>
     */
    private static function normalizeHeaderCandidates(string $header, string $value): array {
        $header = strtoupper($header);

        switch ($header) {
            case 'HTTP_FORWARDED':
                $parts = preg_split('/\s*,\s*/', $value);
                if ($parts === false) {
                    return [];
                }

                $candidates = [];
                foreach ($parts as $part) {
                    if (preg_match('/for=([^;]+)/i', $part, $matches) > 0) {
                        $candidates[] = $matches[1];
                    }
                }

                return $candidates;

            case 'HTTP_X_FORWARDED_FOR':
            case 'HTTP_X_FORWARDED':
            case 'HTTP_FORWARDED_FOR':
                $parts = preg_split('/\s*,\s*/', $value);
                if ($parts === false) {
                    return [];
                }

                return $parts;

            default:
                return [$value];
        }
    }

    /**
     * Sanitize a potential IP value extracted from a header.
     *
     * @param string $candidate Raw candidate string.
     * @return string|null
     */
    private static function sanitizeIpCandidate(string $candidate): ?string {
        $candidate = trim($candidate);

        if ($candidate === '' || strtolower($candidate) === 'unknown') {
            return null;
        }

        if (stripos($candidate, 'for=') === 0) {
            $candidate = substr($candidate, 4);
        }

        $candidate = trim($candidate, " \"'");

        if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $candidate, $matches) > 0) {
            $candidate = $matches[1];
        }

        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}:\d+$/', $candidate) > 0) {
            $candidate = explode(':', $candidate, 2)[0];
        }

        if ($candidate === '') {
            return null;
        }

        return $candidate;
    }

    /**
     * Get rate limit headers for response
     *
     * @param string $endpoint Endpoint identifier
     * @param int    $limit    Request limit per window
     * @param int    $window   Time window in seconds
     * @return array<string, int> Headers array
     */
    public static function getRateLimitHeaders(string $endpoint, int $limit = 30, int $window = 60): array {
        $client_ip = self::getClientIP();
        $key       = 'fp_rate_limit_' . md5($endpoint . '_' . $client_ip);

        $current_count = get_transient($key);
        if ($current_count === false) {
            $current_count = 0;
        } else {
            $current_count = (int) $current_count;
        }

        $remaining = $limit - $current_count;
        if ($remaining < 0) {
            $remaining = 0;
        }

        return [
            'X-RateLimit-Limit'     => $limit,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Window'    => $window,
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
