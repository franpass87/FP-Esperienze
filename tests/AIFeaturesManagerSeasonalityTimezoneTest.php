<?php
declare(strict_types=1);

namespace {
    use FP\Esperienze\AI\AIFeaturesManager;

    date_default_timezone_set('UTC');

    if (!defined('ABSPATH')) {
        define('ABSPATH', __DIR__);
    }

    $GLOBALS['__wp_timezone_string'] = 'UTC';
    $GLOBALS['__wp_test_now'] = strtotime('2024-03-31 23:30:00 UTC');

    if (!function_exists('wp_timezone')) {
        function wp_timezone(): \DateTimeZone
        {
            $timezone = $GLOBALS['__wp_timezone_string'] ?? 'UTC';

            return new \DateTimeZone($timezone);
        }
    }

    if (!function_exists('wp_date')) {
        function wp_date(string $format, $timestamp = null, $timezone = null): string
        {
            $timestamp = $timestamp ?? ($GLOBALS['__wp_test_now'] ?? time());

            if (null === $timezone) {
                $timezone = wp_timezone();
            } elseif (!$timezone instanceof \DateTimeZone) {
                $timezone = new \DateTimeZone((string) $timezone);
            }

            $datetime = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);

            return $datetime->format($format);
        }
    }

    if (!function_exists('current_time')) {
        function current_time(string $type, $gmt = 0)
        {
            $now = $GLOBALS['__wp_test_now'] ?? time();

            if ('timestamp' === $type) {
                if ($gmt) {
                    return $now;
                }

                $timezone = wp_timezone();
                $offset   = $timezone->getOffset(new \DateTimeImmutable('@' . $now));

                return $now + $offset;
            }

            return (int) wp_date($type, $now, $gmt ? new \DateTimeZone('UTC') : null);
        }
    }

    if (!function_exists('get_option')) {
        function get_option(string $name, $default = false)
        {
            return $default;
        }
    }

    require_once __DIR__ . '/../includes/AI/AIFeaturesManager.php';

    $reflection = new \ReflectionClass(AIFeaturesManager::class);
    /** @var AIFeaturesManager $manager */
    $manager = $reflection->newInstanceWithoutConstructor();

    $method = $reflection->getMethod('calculateSeasonalityFactor');
    $method->setAccessible(true);

    $GLOBALS['__wp_timezone_string'] = 'Australia/Sydney';
    $GLOBALS['__wp_test_now'] = strtotime('2024-03-31 23:30:00 UTC');
    $southernFactor = $method->invoke($manager, 501);

    if (abs($southernFactor - 1.1) > 0.001) {
        echo "Seasonality factor should reflect April peak for Australia/Sydney timezone\n";
        exit(1);
    }

    $GLOBALS['__wp_timezone_string'] = 'America/New_York';
    $northernFactor = $method->invoke($manager, 501);

    if (abs($northernFactor - 0.9) > 0.001) {
        echo "Seasonality factor should remain March shoulder season for America/New_York timezone\n";
        exit(1);
    }

    echo "AI seasonality factor timezone regression passed\n";
}
