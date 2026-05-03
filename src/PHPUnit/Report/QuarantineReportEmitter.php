<?php

declare(strict_types=1);

namespace GetSkipper\PHPUnit\Report;

/**
 * Emits quarantine debt reports to GitHub Actions and local artifact.
 */
final class QuarantineReportEmitter
{
    /**
     * @param array<string, mixed> $report
     */
    public function emit(array $report): void
    {
        $markdown = $this->buildMarkdownSummary($report);

        $summaryFile = getenv('GITHUB_STEP_SUMMARY');
        if ($summaryFile !== false && $summaryFile !== '') {
            file_put_contents($summaryFile, $markdown, FILE_APPEND);
        } else {
            echo $markdown;
        }

        file_put_contents(
            'skipper-report.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @param array<string, mixed> $report
     */
    private function buildMarkdownSummary(array $report): string
    {
        $summary = $report['summary'];
        $suppressed = $summary['suppressedCount'] ?? 0;
        $expiring = $summary['expiringThisWeekCount'] ?? 0;
        $reenabled = $summary['reenabledCount'] ?? 0;
        $debt = $summary['quarantineDaysDebt'] ?? 0;

        $md = "## 🚫 Skipper Quarantine Report\n\n";
        $md .= "| Metric | Value |\n";
        $md .= "|--------|-------|\n";
        $md .= "| Suppressed Tests | {$suppressed} |\n";
        $md .= "| Expiring This Week | {$expiring} |\n";
        $md .= "| Re-enabled This Run | {$reenabled} |\n";
        $md .= "| **Quarantine Days Debt** | **{$debt}** |\n\n";

        if (!empty($report['expiringThisWeek'])) {
            $md .= "### Tests Expiring This Week\n";
            foreach ($report['expiringThisWeek'] as $test) {
                $testId = $test['testId'] ?? 'unknown';
                $expiresAt = $test['expiresAt'] ?? 'unknown';
                $md .= "- `{$testId}` expires {$expiresAt}\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}
