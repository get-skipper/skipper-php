<?php

declare(strict_types=1);

namespace GetSkipper\PHPUnit\Report;

use GetSkipper\Core\Resolver\SkipperResolver;

/**
 * Generates a quarantine debt report from the resolver's cache.
 */
final class QuarantineReportGenerator
{
    public function __construct(
        private readonly SkipperResolver $resolver,
        private readonly \DateTimeImmutable $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $cache = $this->resolver->toArray();
        $suppressed = [];
        $expiringThisWeek = [];
        $quarantineDays = 0;

        $weekFromNow = $this->now->modify('+7 days');

        foreach ($cache as $testId => $disabledUntil) {
            if ($disabledUntil === null) {
                continue;
            }

            $until = new \DateTimeImmutable($disabledUntil, new \DateTimeZone('UTC'));

            // Test is suppressed if disabledUntil is in the future
            if ($this->now >= $until) {
                continue;
            }

            $suppressed[] = $testId;

            if ($until <= $weekFromNow) {
                $expiringThisWeek[] = [
                    'testId' => $testId,
                    'expiresAt' => $disabledUntil,
                ];
            }

            // Calculate days until the test is re-enabled
            $diff = $until->diff($this->now);
            $quarantineDays += (int) $diff->days;
        }

        return [
            'timestamp' => $this->now->format(\DateTimeInterface::ATOM),
            'summary' => [
                'suppressedCount' => count($suppressed),
                'expiringThisWeekCount' => count($expiringThisWeek),
                'reenabledCount' => 0,
                'quarantineDaysDebt' => $quarantineDays,
            ],
            'expiringThisWeek' => $expiringThisWeek,
        ];
    }
}
