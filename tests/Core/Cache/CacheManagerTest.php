<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Cache;

use GetSkipper\Core\Cache\CacheManager;
use PHPUnit\Framework\TestCase;

final class CacheManagerTest extends TestCase
{
    private array $dirsToCleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->dirsToCleanup as $dir) {
            if (is_dir($dir)) {
                CacheManager::cleanup($dir);
            }
        }
    }

    public function testWriteAndReadResolverCache(): void
    {
        $data = [
            'tests/unit/authtest.php > authtest > test can login' => '2099-01-01T00:00:00+00:00',
            'tests/unit/usertest.php > usertest > test can register' => null,
        ];

        $dir = CacheManager::writeResolverCache($data);
        $this->dirsToCleanup[] = $dir;

        self::assertDirectoryExists($dir);

        $cacheFile = $dir . '/cache.json';
        self::assertFileExists($cacheFile);

        $read = CacheManager::readResolverCache($cacheFile);
        self::assertSame($data, $read);
    }

    public function testWriteAndMergeDiscoveredIds(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $this->dirsToCleanup[] = $dir;

        CacheManager::writeDiscoveredIds($dir, ['tests/a.php > A > test1', 'tests/b.php > B > test2']);
        CacheManager::writeDiscoveredIds($dir, ['tests/c.php > C > test3']);

        $merged = CacheManager::mergeDiscoveredIds($dir);

        self::assertCount(3, $merged);
        self::assertContains('tests/a.php > A > test1', $merged);
        self::assertContains('tests/b.php > B > test2', $merged);
        self::assertContains('tests/c.php > C > test3', $merged);
    }

    public function testMergeDeduplicatesIds(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $this->dirsToCleanup[] = $dir;

        CacheManager::writeDiscoveredIds($dir, ['tests/a.php > A > test1']);
        CacheManager::writeDiscoveredIds($dir, ['tests/a.php > A > test1']);

        $merged = CacheManager::mergeDiscoveredIds($dir);
        self::assertCount(1, $merged);
    }

    public function testMergeSkipsCacheJsonFile(): void
    {
        $dir = CacheManager::writeResolverCache(['key' => 'value']);
        $this->dirsToCleanup[] = $dir;

        // No discovered IDs written — merge should return empty (cache.json is skipped)
        $merged = CacheManager::mergeDiscoveredIds($dir);
        self::assertEmpty($merged);
    }

    public function testWriteEmptyDiscoveredIdsCreatesNoFile(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $this->dirsToCleanup[] = $dir;

        $before = glob($dir . '/*.json');
        CacheManager::writeDiscoveredIds($dir, []);
        $after = glob($dir . '/*.json');

        self::assertSame(count((array) $before), count((array) $after));
    }

    public function testCleanupRemovesDirectory(): void
    {
        $dir = CacheManager::writeResolverCache(['key' => 'value']);
        // Don't add to cleanup since we're testing cleanup itself

        CacheManager::cleanup($dir);
        self::assertDirectoryDoesNotExist($dir);
    }

    public function testReadCacheThrowsForMissingFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/');
        CacheManager::readResolverCache('/nonexistent/path/cache.json');
    }
}
