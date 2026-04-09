<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Resolver;

use GetSkipper\Core\Resolver\DisabledUntilParser;
use PHPUnit\Framework\TestCase;

final class DisabledUntilParserTest extends TestCase
{
    // --- parse() ---

    public function testNullReturnsNull(): void
    {
        self::assertNull(DisabledUntilParser::parse(null, 1));
    }

    public function testEmptyStringReturnsNull(): void
    {
        self::assertNull(DisabledUntilParser::parse('', 2));
    }

    public function testWhitespaceOnlyReturnsNull(): void
    {
        self::assertNull(DisabledUntilParser::parse('   ', 3));
    }

    public function testValidDateReturnsMidnightPlusOneDayUtc(): void
    {
        $result = DisabledUntilParser::parse('2026-04-01', 5);

        self::assertNotNull($result);
        // +1 day → 2026-04-02 00:00:00 UTC
        self::assertSame('2026-04-02', $result->format('Y-m-d'));
        self::assertSame('UTC', $result->getTimezone()->getName());
        self::assertSame('00:00:00', $result->format('H:i:s'));
    }

    public function testMalformedDateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Row 7.*invalid disabledUntil.*2026-4-1.*YYYY-MM-DD/');

        DisabledUntilParser::parse('2026-4-1', 7);
    }

    public function testSpreadsheetFormattedDateThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DisabledUntilParser::parse('04/01/2026', 10);
    }

    public function testIso8601WithTimeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        DisabledUntilParser::parse('2026-04-01T00:00:00+00:00', 12);
    }

    public function testWhitespaceTrimmedBeforeValidation(): void
    {
        $result = DisabledUntilParser::parse('  2026-04-01  ', 5);

        self::assertNotNull($result);
        self::assertSame('2026-04-02', $result->format('Y-m-d'));
    }

    // --- isDisabled() ---

    public function testNullUntilIsNotDisabled(): void
    {
        self::assertFalse(DisabledUntilParser::isDisabled(null));
    }

    public function testPastUntilIsNotDisabled(): void
    {
        $past = new \DateTimeImmutable('-1 day', new \DateTimeZone('UTC'));
        self::assertFalse(DisabledUntilParser::isDisabled($past));
    }

    public function testFutureUntilIsDisabled(): void
    {
        $future = new \DateTimeImmutable('+1 year', new \DateTimeZone('UTC'));
        self::assertTrue(DisabledUntilParser::isDisabled($future));
    }

    // --- cross-timezone consistency ---

    /**
     * A test disabled until 2026-04-01 must expire at the same UTC instant
     * regardless of the server's date.timezone ini setting.
     */
    public function testExpiryIsConsistentAcrossTimezones(): void
    {
        $originalTz = date_default_timezone_get();

        try {
            $results = [];
            foreach (['UTC', 'America/New_York', 'Asia/Tokyo', 'Europe/Rome'] as $tz) {
                date_default_timezone_set($tz);
                $parsed = DisabledUntilParser::parse('2026-04-01', 1);
                $results[$tz] = $parsed?->format(\DateTimeInterface::ATOM);
            }

            // All timezones must produce the same UTC instant
            self::assertCount(1, array_unique(array_values($results)), sprintf(
                'Expiry instant differs across timezones: %s',
                json_encode($results)
            ));
        } finally {
            date_default_timezone_set($originalTz);
        }
    }
}
