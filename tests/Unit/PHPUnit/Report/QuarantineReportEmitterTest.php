<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Unit\PHPUnit\Report;

use GetSkipper\PHPUnit\Report\QuarantineReportEmitter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GetSkipper\PHPUnit\Report\QuarantineReportEmitter
 */
final class QuarantineReportEmitterTest extends TestCase
{
    protected function tearDown(): void
    {
        if (file_exists('skipper-report.json')) {
            unlink('skipper-report.json');
        }
    }

    public function testWritesReportJsonFile(): void
    {
        $report = $this->createTestReport();
        $emitter = new QuarantineReportEmitter();
        $emitter->emit($report);

        $this->assertFileExists('skipper-report.json');
        $json = json_decode(file_get_contents('skipper-report.json'), true);
        $this->assertEquals(5, $json['summary']['suppressedCount']);
        $this->assertEquals(2, $json['summary']['quarantineDaysDebt']);
    }

    public function testWritesToGitHubStepSummary(): void
    {
        $summaryFile = tempnam(sys_get_temp_dir(), 'github-summary-');
        $report = $this->createTestReport();

        putenv("GITHUB_STEP_SUMMARY={$summaryFile}");
        $emitter = new QuarantineReportEmitter();
        $emitter->emit($report);
        putenv('GITHUB_STEP_SUMMARY=');

        $this->assertFileExists($summaryFile);
        $content = file_get_contents($summaryFile);
        $this->assertStringContainsString('Skipper Quarantine Report', $content);
        $this->assertStringContainsString('5', $content);
        unlink($summaryFile);
    }

    public function testIncludesExpiringTestsInMarkdown(): void
    {
        $report = [
            'timestamp' => '2024-05-15T12:00:00Z',
            'summary' => [
                'suppressedCount' => 2,
                'expiringThisWeekCount' => 1,
                'reenabledCount' => 0,
                'quarantineDaysDebt' => 5,
            ],
            'expiringThisWeek' => [
                [
                    'testId' => 'tests/Unit/ExpiringTest',
                    'expiresAt' => '2024-05-18T12:00:00Z',
                ],
            ],
        ];

        $summaryFile = tempnam(sys_get_temp_dir(), 'github-summary-');
        putenv("GITHUB_STEP_SUMMARY={$summaryFile}");
        $emitter = new QuarantineReportEmitter();
        $emitter->emit($report);
        putenv('GITHUB_STEP_SUMMARY=');

        $content = file_get_contents($summaryFile);
        $this->assertStringContainsString('tests/Unit/ExpiringTest', $content);
        $this->assertStringContainsString('2024-05-18T12:00:00Z', $content);
        unlink($summaryFile);
    }

    /**
     * @return array<string, mixed>
     */
    private function createTestReport(): array
    {
        return [
            'timestamp' => '2024-05-15T12:00:00Z',
            'summary' => [
                'suppressedCount' => 5,
                'expiringThisWeekCount' => 1,
                'reenabledCount' => 0,
                'quarantineDaysDebt' => 2,
            ],
            'expiringThisWeek' => [],
        ];
    }
}
