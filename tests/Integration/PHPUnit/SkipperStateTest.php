<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\PHPUnit;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\PHPUnit\SkipperState;
use PHPUnit\Event\Code\TestDox;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\TestData\TestDataCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Metadata\MetadataCollection;

final class SkipperStateTest extends TestCase
{
    public function testBuildsTestIdForClassBasedTest(): void
    {
        $state = new SkipperState(SkipperResolver::fromArray([]), null);
        $test = $this->makeTestMethod('App\\Auth\\AuthTest', 'testCanLogin', 'tests/Unit/AuthTest.php');

        $id = $state->buildTestId($test);

        $expected = TestIdHelper::build('tests/Unit/AuthTest.php', ['AuthTest', 'testCanLogin']);
        self::assertSame($expected, $id);
    }

    public function testBuildsTestIdStrippingPestClassPrefix(): void
    {
        $state = new SkipperState(SkipperResolver::fromArray([]), null);
        $test = $this->makeTestMethod('P\\Tests\\Feature\\Auth', 'can login', 'tests/Feature/auth.php');

        $id = $state->buildTestId($test);

        $expected = TestIdHelper::build('tests/Feature/auth.php', ['can login']);
        self::assertSame($expected, $id);
    }

    public function testBuildsTestIdForNonMethodTestViaId(): void
    {
        $state = new SkipperState(SkipperResolver::fromArray([]), null);
        $test = $this->createMock(\PHPUnit\Event\Code\Test::class);
        $test->method('id')->willReturn('some::test::id');

        $id = $state->buildTestId($test);

        self::assertSame('some::test::id', $id);
    }

    public function testAddDiscoveredIdAndFlush(): void
    {
        $dir = CacheManager::writeResolverCache([]);

        try {
            $state = new SkipperState(SkipperResolver::fromArray([]), $dir);
            $state->addDiscoveredId('tests/Foo.php > FooTest > testBar');
            $state->flushDiscoveredIds();

            $ids = CacheManager::mergeDiscoveredIds($dir);
            self::assertContains('tests/Foo.php > FooTest > testBar', $ids);
        } finally {
            CacheManager::cleanup($dir);
        }
    }

    public function testFlushDoesNothingWithNullDir(): void
    {
        $state = new SkipperState(SkipperResolver::fromArray([]), null);
        $state->addDiscoveredId('tests/Foo.php > FooTest > testBar');

        // Should not throw
        $state->flushDiscoveredIds();

        $this->addToAssertionCount(1);
    }

    // ---------- helpers ----------

    private function makeTestMethod(string $className, string $methodName, string $file): TestMethod
    {
        $testDox = new TestDox($className, $methodName, $methodName);
        $metadata = MetadataCollection::fromArray([]);
        $testData = TestDataCollection::fromArray([]);
        return new TestMethod($className, $methodName, $file, 1, $testDox, $metadata, $testData);
    }
}
