<?php
declare(strict_types=1);

namespace FP\Esperienze\Tests\Helpers;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DateHelperTest extends TestCase
{
    public function testFormatsTimestampsUsingSiteTimezone(): void
    {
        $timestamp = 1700000000; // 2023-11-14 22:13:20 UTC
        $timezone = new DateTimeZone('Europe/Rome');

        $formatted = \fp_esperienze_wp_date('Y-m-d H:i', $timestamp, $timezone);

        $this->assertSame('2023-11-14 23:13', $formatted);
    }

    public function testReturnsEmptyStringWhenStringCannotBeParsed(): void
    {
        $this->assertSame('', \fp_esperienze_wp_date('Y-m-d', 'not-a-date'));
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testForcesLegacyFallbackWhenConstantEnabled(): void
    {
        if (!defined('FP_ESPERIENZE_FORCE_LEGACY_DATE')) {
            define('FP_ESPERIENZE_FORCE_LEGACY_DATE', true);
        }

        $date = new DateTimeImmutable('2024-01-10 12:45:00', new DateTimeZone('UTC'));
        $formatted = \fp_esperienze_wp_date('Y-m-d H:i', $date);

        $this->assertSame('2024-01-10 12:45', $formatted);
    }
}
