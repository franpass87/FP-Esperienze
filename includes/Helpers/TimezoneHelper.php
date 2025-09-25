<?php
/**
 * Timezone helper utilities.
 *
 * @package FP\Esperienze\Helpers
 */

namespace FP\Esperienze\Helpers;

use DateTimeZone;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Provides safe access to the site's timezone configuration.
 */
class TimezoneHelper {

    /**
     * Retrieve the site's timezone with defensive fallbacks.
     *
     * Always returns a valid {@see DateTimeZone} instance even when WordPress
     * utility functions are unavailable (for example in CLI scripts or unit
     * tests).
     */
    public static function getSiteTimezone(): DateTimeZone {
        $timezone = self::getTimezoneFromWordPress();

        if (null === $timezone) {
            $timezone = self::getTimezoneFromSettings();
        }

        if (null === $timezone) {
            $timezone = self::getTimezoneFromDefaults();
        }

        if (null === $timezone) {
            $timezone = new DateTimeZone('UTC');
        }

        if (function_exists('apply_filters')) {
            /**
             * Filter the timezone used by FP Esperienze calculations.
             *
             * @param DateTimeZone $timezone Current timezone instance.
             */
            $filtered = apply_filters('fp_esperienze_site_timezone', $timezone);

            if ($filtered instanceof DateTimeZone) {
                $timezone = $filtered;
            }
        }

        return $timezone;
    }

    /**
     * Attempt to retrieve the timezone using WordPress helper functions.
     */
    private static function getTimezoneFromWordPress(): ?DateTimeZone {
        if (!function_exists('wp_timezone')) {
            return null;
        }

        try {
            $timezone = wp_timezone();
        } catch (Throwable $exception) {
            return null;
        }

        return $timezone instanceof DateTimeZone ? $timezone : null;
    }

    /**
     * Build a timezone using site options when the WP helpers are unavailable.
     */
    private static function getTimezoneFromSettings(): ?DateTimeZone {
        $timezone_string = '';

        if (function_exists('wp_timezone_string')) {
            $timezone_string = (string) wp_timezone_string();
        } elseif (function_exists('get_option')) {
            $option_value = get_option('timezone_string');
            if (is_string($option_value)) {
                $timezone_string = $option_value;
            }
        }

        if ($timezone_string !== '') {
            try {
                return new DateTimeZone($timezone_string);
            } catch (Throwable $exception) {
                // Fall back to offset handling below.
            }
        }

        if (!function_exists('get_option')) {
            return null;
        }

        $offset = get_option('gmt_offset');
        if (!is_numeric($offset)) {
            return null;
        }

        $offset_seconds = (int) round(((float) $offset) * 3600);
        if (0 === $offset_seconds) {
            return self::createTimezoneSafe('UTC');
        }

        $timezone_name = timezone_name_from_abbr('', $offset_seconds, 0);
        if (is_string($timezone_name) && $timezone_name !== '') {
            $timezone = self::createTimezoneSafe($timezone_name);
            if (null !== $timezone) {
                return $timezone;
            }
        }

        $sign = $offset_seconds >= 0 ? '+' : '-';
        $seconds = abs($offset_seconds);
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) round(($seconds % 3600) / 60);

        $offset_string = sprintf('%s%02d:%02d', $sign, $hours, $minutes);

        return self::createTimezoneSafe($offset_string);
    }

    /**
     * Attempt to create a timezone from the PHP default configuration.
     */
    private static function getTimezoneFromDefaults(): ?DateTimeZone {
        $default = @date_default_timezone_get();

        if (!is_string($default) || $default === '') {
            return null;
        }

        return self::createTimezoneSafe($default);
    }

    /**
     * Safely instantiate a DateTimeZone instance.
     */
    private static function createTimezoneSafe(string $identifier): ?DateTimeZone {
        try {
            return new DateTimeZone($identifier);
        } catch (Throwable $exception) {
            return null;
        }
    }
}

