<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Testwork\Environment\Environment;
use GetSkipper\Behat\SkipperContext;
use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\TestId\TestIdHelper;
use PHPUnit\Framework\TestCase;

final class SkipperContextTest extends TestCase
{
    private const FEATURE_FILE = '/project/features/auth/login.feature';
    private const FEATURE_TITLE = 'User authentication';

    protected function setUp(): void
    {
        $this->resetStaticState();
    }

    protected function tearDown(): void
    {
        $this->resetStaticState();
    }

    public function testImplementsContext(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');
        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            self::assertInstanceOf(Context::class, new SkipperContext());
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    public function testConstructorThrowsWithoutCacheFile(): void
    {
        $prev = getenv('SKIPPER_CACHE_FILE');
        putenv('SKIPPER_CACHE_FILE');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessageMatches('/SKIPPER_CACHE_FILE not set/');
            new SkipperContext();
        } finally {
            putenv($prev !== false ? "SKIPPER_CACHE_FILE={$prev}" : 'SKIPPER_CACHE_FILE');
        }
    }

    public function testCheckScenarioEnabledThrowsPendingForDisabledTest(): void
    {
        $scenarioTitle = 'User can log in';
        $testId = TestIdHelper::normalize(
            TestIdHelper::build(self::FEATURE_FILE, [self::FEATURE_TITLE, $scenarioTitle])
        );

        $dir = CacheManager::writeResolverCache([$testId => '2099-12-31T00:00:00+00:00']);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');
        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $context = new SkipperContext();

            $this->expectException(PendingException::class);
            $this->expectExceptionMessageMatches('/\[skipper\] Test disabled until 2099-12-31/');

            $context->checkScenarioEnabled($this->makeScope($scenarioTitle));
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    public function testCheckScenarioEnabledDoesNotThrowForEnabledTest(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');
        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $context = new SkipperContext();
            $context->checkScenarioEnabled($this->makeScope('User can log in'));

            $this->addToAssertionCount(1);
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    // ---------- helpers ----------

    private function makeScope(string $scenarioTitle): BeforeScenarioScope
    {
        $feature = $this->createMock(FeatureNode::class);
        $feature->method('getFile')->willReturn(self::FEATURE_FILE);
        $feature->method('getTitle')->willReturn(self::FEATURE_TITLE);

        $scenario = $this->createMock(ScenarioInterface::class);
        $scenario->method('getTitle')->willReturn($scenarioTitle);

        $env = $this->createMock(Environment::class);

        return new BeforeScenarioScope($env, $feature, $scenario);
    }

    private function resetStaticState(): void
    {
        $ref = new \ReflectionClass(SkipperContext::class);

        $resolver = $ref->getProperty('resolver');
        $resolver->setAccessible(true);
        $resolver->setValue(null, null);

        $config = $ref->getProperty('config');
        $config->setAccessible(true);
        $config->setValue(null, null);

        $ids = $ref->getProperty('discoveredIds');
        $ids->setAccessible(true);
        $ids->setValue(null, []);
    }
}
