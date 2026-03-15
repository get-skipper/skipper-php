<?php

declare(strict_types=1);

namespace GetSkipper\PHPUnit\Subscriber;

use GetSkipper\PHPUnit\SkipperState;
use PHPUnit\Event\Test\Prepared;
use PHPUnit\Event\Test\PreparedSubscriber;
use PHPUnit\Framework\Assert;

final class TestPreparedSubscriber implements PreparedSubscriber
{
    public function __construct(
        private readonly SkipperState $state,
    ) {
    }

    public function notify(Prepared $event): void
    {
        $test = $event->test();
        $testId = $this->state->buildTestId($test);

        // Always collect for sync mode
        $this->state->addDiscoveredId($testId);

        if (!$this->state->getResolver()->isTestEnabled($testId)) {
            $disabledUntil = $this->state->getResolver()->getDisabledUntil($testId);
            // Call Assert::markTestSkipped() so the exception is thrown from PHPUnit's own
            // source tree. PHPUnit's DirectDispatcher only re-throws exceptions that originate
            // from its own files; exceptions from third-party files are silently converted to
            // warnings and the test runs anyway. By delegating to Assert, the exception file
            // is vendor/phpunit/phpunit/src/Framework/Assert.php, which passes the check and
            // propagates to TestCase::run()'s catch (SkippedTest) block for a clean skip.
            Assert::markTestSkipped(
                '[skipper] Test disabled' . ($disabledUntil !== null ? " until {$disabledUntil}" : '') . '.'
            );
        }
    }
}
