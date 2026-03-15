<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\PHPSpec;

use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\PHPSpec\SkipperListener;
use PhpSpec\Event\ExampleEvent;
use PhpSpec\Exception\Example\SkippingException;
use PhpSpec\Loader\Node\ExampleNode;
use PhpSpec\Loader\Node\SpecificationNode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SkipperListenerTest extends TestCase
{
    private const SPEC_FILE = '/project/spec/LoginSpec.php';
    private const SPEC_SHORT_NAME = 'LoginSpec';

    public function testImplementsEventSubscriberInterface(): void
    {
        self::assertInstanceOf(EventSubscriberInterface::class, new SkipperListener(SkipperResolver::fromArray([])));
    }

    public function testSubscribesBeforeExampleEvent(): void
    {
        self::assertArrayHasKey('beforeExample', SkipperListener::getSubscribedEvents());
    }

    public function testSubscribesAfterSuiteEvent(): void
    {
        self::assertArrayHasKey('afterSuite', SkipperListener::getSubscribedEvents());
    }

    public function testBeforeExampleSkipsDisabledTestWithDate(): void
    {
        $resolver = SkipperResolver::fromArray([
            $this->normalizedId('it logs a user in') => '2099-12-31T00:00:00+00:00',
        ]);

        $this->expectException(SkippingException::class);
        $this->expectExceptionMessageMatches('/\[skipper\] Test disabled until 2099-12-31/');

        (new SkipperListener($resolver))->beforeExample($this->makeExampleEvent('it logs a user in'));
    }

    public function testBeforeExampleDoesNotSkipTrackedButEnabledTest(): void
    {
        // null disabledUntil = test is tracked (discovered via sync) but not disabled
        $resolver = SkipperResolver::fromArray([
            $this->normalizedId('it logs a user in') => null,
        ]);

        (new SkipperListener($resolver))->beforeExample($this->makeExampleEvent('it logs a user in'));

        $this->addToAssertionCount(1);
    }

    public function testBeforeExampleDoesNotThrowForEnabledTest(): void
    {
        $resolver = SkipperResolver::fromArray([]);

        (new SkipperListener($resolver))->beforeExample($this->makeExampleEvent('it logs a user in'));

        $this->addToAssertionCount(1);
    }

    public function testBeforeExampleDoesNotThrowForPastDisabledUntil(): void
    {
        $resolver = SkipperResolver::fromArray([
            $this->normalizedId('it logs a user in') => '2000-01-01T00:00:00+00:00',
        ]);

        (new SkipperListener($resolver))->beforeExample($this->makeExampleEvent('it logs a user in'));

        $this->addToAssertionCount(1);
    }

    public function testBeforeExampleBuildsTestIdContainingExampleTitle(): void
    {
        // Pre-disable a specific test; if the ID is built correctly the exception fires.
        $resolver = SkipperResolver::fromArray([
            $this->normalizedId('it rejects an empty password') => '2099-12-31T00:00:00+00:00',
        ]);

        $this->expectException(SkippingException::class);

        (new SkipperListener($resolver))->beforeExample($this->makeExampleEvent('it rejects an empty password'));
    }

    public function testBeforeExampleBuildsTestIdContainingSpecName(): void
    {
        $testId = TestIdHelper::normalize(
            TestIdHelper::build(self::SPEC_FILE, [self::SPEC_SHORT_NAME, 'it does something'])
        );

        $resolver = SkipperResolver::fromArray([$testId => '2099-12-31T00:00:00+00:00']);

        $this->expectException(SkippingException::class);

        (new SkipperListener($resolver))->beforeExample($this->makeExampleEvent('it does something'));
    }

    // ---------- helpers ----------

    /** Returns the normalized test ID for a given example title using the fixed spec. */
    private function normalizedId(string $exampleTitle): string
    {
        return TestIdHelper::normalize(
            TestIdHelper::build(self::SPEC_FILE, [self::SPEC_SHORT_NAME, $exampleTitle])
        );
    }

    private function makeExampleEvent(string $exampleTitle): ExampleEvent
    {
        $classReflection = $this->createMock(\ReflectionClass::class);
        $classReflection->method('getFileName')->willReturn(self::SPEC_FILE);
        $classReflection->method('getShortName')->willReturn(self::SPEC_SHORT_NAME);

        $spec = $this->createMock(SpecificationNode::class);
        $spec->method('getClassReflection')->willReturn($classReflection);

        $example = $this->createMock(ExampleNode::class);
        $example->method('getTitle')->willReturn($exampleTitle);

        $event = $this->createMock(ExampleEvent::class);
        $event->method('getExample')->willReturn($example);
        $event->method('getSpecification')->willReturn($spec);

        return $event;
    }
}
