<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Kahlan;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\Kahlan\SkipperPlugin;
use PHPUnit\Framework\TestCase;

final class SkipperPluginTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetStaticState();
    }

    protected function tearDown(): void
    {
        $this->resetStaticState();
    }

    public function testGetResolverReturnsNullBeforeSetup(): void
    {
        self::assertNull(SkipperPlugin::getResolver());
    }

    public function testSetupPopulatesResolverFromCache(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');
        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $config = new \GetSkipper\Core\Config\SkipperConfig(
                spreadsheetId: 'test-id',
                credentials: new \GetSkipper\Core\Credentials\Base64Credentials(base64_encode('{}'))
            );

            SkipperPlugin::setup($config);

            self::assertNotNull(SkipperPlugin::getResolver());
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    public function testCheckTestDoesNothingWhenResolverIsNull(): void
    {
        // Resolver is null; checkTest() should return early without error
        SkipperPlugin::checkTest(new \stdClass());

        $this->addToAssertionCount(1);
    }

    public function testCheckTestDoesNothingForEnabledTest(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');
        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $config = new \GetSkipper\Core\Config\SkipperConfig(
                spreadsheetId: 'test-id',
                credentials: new \GetSkipper\Core\Credentials\Base64Credentials(base64_encode('{}'))
            );
            SkipperPlugin::setup($config);

            // A scope without suite() → empty test ID → not in cache → enabled
            SkipperPlugin::checkTest(new \stdClass());

            $this->addToAssertionCount(1);
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    public function testGetResolverReturnsPopulatedResolverAfterSetup(): void
    {
        $testId = TestIdHelper::normalize(
            TestIdHelper::build('spec/auth.php', ['Auth', 'can login'])
        );

        $dir = CacheManager::writeResolverCache([$testId => '2099-12-31T00:00:00+00:00']);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');
        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $config = new \GetSkipper\Core\Config\SkipperConfig(
                spreadsheetId: 'test-id',
                credentials: new \GetSkipper\Core\Credentials\Base64Credentials(base64_encode('{}'))
            );
            SkipperPlugin::setup($config);

            $resolver = SkipperPlugin::getResolver();
            self::assertNotNull($resolver);
            self::assertFalse($resolver->isTestEnabled(
                TestIdHelper::build('spec/auth.php', ['Auth', 'can login'])
            ));
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    // ---------- helpers ----------

    private function resetStaticState(): void
    {
        $ref = new \ReflectionClass(SkipperPlugin::class);

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
