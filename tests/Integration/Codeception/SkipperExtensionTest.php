<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Codeception;

use Codeception\Events;
use GetSkipper\Codeception\SkipperExtension;
use PHPUnit\Framework\TestCase;

final class SkipperExtensionTest extends TestCase
{
    public function testEventsArraySubscribesToSuiteBefore(): void
    {
        self::assertArrayHasKey(Events::SUITE_BEFORE, SkipperExtension::$events);
    }

    public function testEventsArraySubscribesToTestBefore(): void
    {
        self::assertArrayHasKey(Events::TEST_BEFORE, SkipperExtension::$events);
    }

    public function testEventsArraySubscribesToSuiteAfter(): void
    {
        self::assertArrayHasKey(Events::SUITE_AFTER, SkipperExtension::$events);
    }

    public function testSuiteBeforeHandlerIsBeforeSuite(): void
    {
        self::assertSame('beforeSuite', SkipperExtension::$events[Events::SUITE_BEFORE]);
    }

    public function testTestBeforeHandlerIsBeforeTest(): void
    {
        self::assertSame('beforeTest', SkipperExtension::$events[Events::TEST_BEFORE]);
    }

    public function testSuiteAfterHandlerIsAfterSuite(): void
    {
        self::assertSame('afterSuite', SkipperExtension::$events[Events::SUITE_AFTER]);
    }

    public function testExtensionIsInstantiable(): void
    {
        $ext = new SkipperExtension([], []);
        self::assertInstanceOf(SkipperExtension::class, $ext);
    }
}
