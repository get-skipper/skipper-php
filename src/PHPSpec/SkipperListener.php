<?php

declare(strict_types=1);

namespace GetSkipper\PHPSpec;

use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\SkipperMode;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\Core\Writer\SheetsWriter;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Event\SuiteEvent;
use PhpSpec\Exception\Example\SkippingException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * PHPSpec event subscriber that skips disabled examples via Skipper test-gating.
 */
final class SkipperListener implements EventSubscriberInterface
{
    /** @var string[] */
    private array $discoveredIds = [];

    public function __construct(
        private readonly SkipperResolver $resolver,
        private readonly ?\GetSkipper\Core\Config\SkipperConfig $config = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'beforeExample' => ['beforeExample', 0],
            'afterSuite' => ['afterSuite', 0],
        ];
    }

    public function beforeExample(ExampleEvent $event): void
    {
        $example = $event->getExample();
        $spec = $event->getSpecification();

        $file = $spec->getClassReflection()->getFileName() ?: '';
        $specShortName = $spec->getClassReflection()->getShortName();
        $exampleTitle = $example->getTitle();

        $testId = TestIdHelper::build($file, [$specShortName, $exampleTitle]);
        $this->discoveredIds[] = $testId;

        if (!$this->resolver->isTestEnabled($testId)) {
            $disabledUntil = $this->resolver->getDisabledUntil($testId);
            throw new SkippingException(
                '[skipper] Test disabled' . ($disabledUntil !== null ? " until {$disabledUntil}" : '') . '.'
            );
        }
    }

    public function afterSuite(SuiteEvent $event): void
    {
        if (SkipperMode::fromEnv() !== SkipperMode::Sync || $this->config === null) {
            return;
        }

        if (empty($this->discoveredIds)) {
            return;
        }

        $writer = new SheetsWriter($this->config);
        $writer->sync($this->discoveredIds);
        Logger::log('[skipper] Sync complete.');
    }
}
