<?php

declare(strict_types=1);

namespace GetSkipper\PHPUnit\Subscriber;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\SkipperMode;
use GetSkipper\Core\Writer\SheetsWriter;
use GetSkipper\PHPUnit\Report\QuarantineReportEmitter;
use GetSkipper\PHPUnit\Report\QuarantineReportGenerator;
use GetSkipper\PHPUnit\SkipperState;
use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;

final class TestRunnerFinishedSubscriber implements FinishedSubscriber
{
    public function __construct(
        private readonly SkipperState $state,
        private readonly SkipperConfig $config,
        private readonly bool $isMainProcess,
    ) {
    }

    public function notify(Finished $event): void
    {
        // Emit quarantine report (only main process, all modes)
        if ($this->isMainProcess) {
            $this->emitQuarantineReport();
        }

        // Flush this process's discovered IDs to the shared directory
        $this->state->flushDiscoveredIds();

        if (SkipperMode::fromEnv() !== SkipperMode::Sync) {
            return;
        }

        // Only the main process (the one that initialized the resolver) performs the sync.
        // Worker processes write their IDs but do not sync to avoid race conditions.
        if (!$this->isMainProcess) {
            return;
        }

        $dir = (string) getenv('SKIPPER_DISCOVERED_DIR');
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $ids = CacheManager::mergeDiscoveredIds($dir);
        if (empty($ids)) {
            return;
        }

        try {
            $writer = new SheetsWriter($this->config);
            $writer->sync($ids);
            Logger::log('[skipper] Sync complete.');
        } catch (\Throwable $e) {
            fwrite(STDERR, '[skipper] Sync failed: ' . $e->getMessage() . PHP_EOL);
        }

        CacheManager::cleanup($dir);
    }

    private function emitQuarantineReport(): void
    {
        try {
            $generator = new QuarantineReportGenerator($this->state->getResolver());
            $report = $generator->generate();

            $emitter = new QuarantineReportEmitter();
            $emitter->emit($report);
        } catch (\Throwable $e) {
            fwrite(STDERR, '[skipper] Report generation failed: ' . $e->getMessage() . PHP_EOL);
        }
    }
}
