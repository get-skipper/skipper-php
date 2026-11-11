<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Codeception;

use Codeception\Events;
use Codeception\Test\Cest;
use Codeception\Test\TestCaseWrapper;
use Codeception\Event\TestEvent;
use GetSkipper\Codeception\SkipperExtension;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Tests\Integration\Codeception\Fixtures\AuthCest;
use GetSkipper\Tests\Integration\Codeception\Fixtures\AuthFixtureTest;
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

    public function testBuildTestIdForCestUsesUserClass(): void
    {
        $test = new Cest(new AuthCest(), 'tryToLogin', 'tests/Acceptance/AuthCest.php');

        self::assertSame('tests/Acceptance/AuthCest.php > AuthCest > tryToLogin', $this->buildTestId($test));
    }

    public function testBuildTestIdForUnitTestUnwrapsTestCaseWrapper(): void
    {
        $test = new TestCaseWrapper(new AuthFixtureTest('testItCanLogin'));

        self::assertStringEndsWith(
            'Fixtures/AuthFixtureTest.php > AuthFixtureTest > testItCanLogin',
            $this->buildTestId($test),
        );
    }

    public function testBeforeTestMarksDisabledTestAsSkippedViaMetadata(): void
    {
        $ext = new SkipperExtension([], []);
        $resolver = SkipperResolver::fromArray([
            'tests/acceptance/authcest.php > authcest > trytologin' => (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM),
        ]);
        (new \ReflectionProperty(SkipperExtension::class, 'resolver'))->setValue($ext, $resolver);
        $test = new Cest(new AuthCest(), 'tryToLogin', 'tests/Acceptance/AuthCest.php');

        $ext->beforeTest(new TestEvent($test));

        self::assertStringStartsWith('[skipper] Test disabled until', (string) $test->getMetadata()->getSkip());
    }

    private function buildTestId(object $test): string
    {
        $method = new \ReflectionMethod(SkipperExtension::class, 'buildTestId');

        return $method->invoke(new SkipperExtension([], []), $test);
    }
}
