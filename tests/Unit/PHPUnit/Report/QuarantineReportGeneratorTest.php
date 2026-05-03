<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Unit\PHPUnit\Report;

use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\PHPUnit\Report\QuarantineReportGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GetSkipper\PHPUnit\Report\QuarantineReportGenerator
 */
final class QuarantineReportGeneratorTest extends TestCase
{
    public function testGeneratesReportWithSuppressedTests(): void
    {
        $now = new \DateTimeImmutable('2024-05-15 12:00:00', new \DateTimeZone('UTC'));
        $tomorrow = $now->modify('+1 day')->format(\DateTimeInterface::ATOM);
        $nextWeek = $now->modify('+10 days')->format(\DateTimeInterface::ATOM);

        $cache = [
            'tests/Unit/AuthTest > testLogin' => $tomorrow,
            'tests/Unit/UserTest > testCreate' => $nextWeek,
            'tests/Unit/AdminTest > testPermission' => null,
        ];

        $resolver = $this->createMockResolver($cache);
        $generator = new QuarantineReportGenerator($resolver, $now);
        $report = $generator->generate();

        $this->assertEquals(2, $report['summary']['suppressedCount']);
        $this->assertEquals(1, $report['summary']['expiringThisWeekCount']);
        $this->assertEquals(0, $report['summary']['reenabledCount']);
        $this->assertGreaterThan(0, $report['summary']['quarantineDaysDebt']);
    }

    public function testCalculatesQuarantineDaysCorrectly(): void
    {
        $now = new \DateTimeImmutable('2024-05-15 12:00:00', new \DateTimeZone('UTC'));
        $fiveDaysLater = $now->modify('+5 days')->format(\DateTimeInterface::ATOM);
        $tenDaysLater = $now->modify('+10 days')->format(\DateTimeInterface::ATOM);

        $cache = [
            'tests/Unit/Test1' => $fiveDaysLater,
            'tests/Unit/Test2' => $tenDaysLater,
        ];

        $resolver = $this->createMockResolver($cache);
        $generator = new QuarantineReportGenerator($resolver, $now);
        $report = $generator->generate();

        $this->assertEquals(15, $report['summary']['quarantineDaysDebt']);
    }

    public function testHandlesExpiredTests(): void
    {
        $now = new \DateTimeImmutable('2024-05-15 12:00:00', new \DateTimeZone('UTC'));
        $yesterday = $now->modify('-1 day')->format(\DateTimeInterface::ATOM);

        $cache = [
            'tests/Unit/ExpiredTest' => $yesterday,
        ];

        $resolver = $this->createMockResolver($cache);
        $generator = new QuarantineReportGenerator($resolver, $now);
        $report = $generator->generate();

        $this->assertEquals(0, $report['summary']['suppressedCount']);
        $this->assertEquals(0, $report['summary']['expiringThisWeekCount']);
        $this->assertEquals(0, $report['summary']['quarantineDaysDebt']);
    }

    public function testIncludesTestsExpiringThisWeek(): void
    {
        $now = new \DateTimeImmutable('2024-05-15 12:00:00', new \DateTimeZone('UTC'));
        $threeDaysLater = $now->modify('+3 days')->format(\DateTimeInterface::ATOM);
        $fourteenDaysLater = $now->modify('+14 days')->format(\DateTimeInterface::ATOM);

        $cache = [
            'tests/Unit/ExpiringTest' => $threeDaysLater,
            'tests/Unit/NotExpiringTest' => $fourteenDaysLater,
        ];

        $resolver = $this->createMockResolver($cache);
        $generator = new QuarantineReportGenerator($resolver, $now);
        $report = $generator->generate();

        $this->assertCount(1, $report['expiringThisWeek']);
        $this->assertEquals('tests/Unit/ExpiringTest', $report['expiringThisWeek'][0]['testId']);
    }

    /**
     * @param array<string, string|null> $cache
     */
    private function createMockResolver(array $cache): SkipperResolver
    {
        return SkipperResolver::fromArray($cache);
    }
}
