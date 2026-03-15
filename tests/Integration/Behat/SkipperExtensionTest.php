<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Behat;

use Behat\Testwork\ServiceContainer\Extension;
use GetSkipper\Behat\SkipperExtension;
use GetSkipper\Core\Cache\CacheManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SkipperExtensionTest extends TestCase
{
    public function testImplementsBehatExtension(): void
    {
        self::assertInstanceOf(Extension::class, new SkipperExtension());
    }

    public function testGetConfigKeyReturnsSkipper(): void
    {
        self::assertSame('skipper', (new SkipperExtension())->getConfigKey());
    }

    public function testLoadThrowsWhenNoCredentialsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/No credentials provided/');

        (new SkipperExtension())->load(new ContainerBuilder(), [
            'spreadsheetId' => 'x',
            'credentialsFile' => null,
            'credentialsBase64' => null,
            'credentialsEnvVar' => null,
            'sheetName' => null,
        ]);
    }

    public function testLoadThrowsWhenCredentialsEnvVarIsEmpty(): void
    {
        $envVar = 'SKIPPER_TEST_EMPTY_CREDS_' . bin2hex(random_bytes(4));
        putenv("{$envVar}=");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/empty or not set/');

            (new SkipperExtension())->load(new ContainerBuilder(), [
                'spreadsheetId' => 'x',
                'credentialsFile' => null,
                'credentialsBase64' => null,
                'credentialsEnvVar' => $envVar,
                'sheetName' => null,
            ]);
        } finally {
            putenv($envVar);
        }
    }

    public function testLoadReusesCacheFileWhenAlreadyPresent(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');

        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            // load() should not throw even when cache already exists
            (new SkipperExtension())->load(new ContainerBuilder(), [
                'spreadsheetId' => 'test-sheet-id',
                'credentialsBase64' => base64_encode('{}'),
                'credentialsFile' => null,
                'credentialsEnvVar' => null,
                'sheetName' => null,
            ]);

            // SKIPPER_CACHE_FILE must remain pointing to the pre-existing cache
            self::assertSame("{$dir}/cache.json", getenv('SKIPPER_CACHE_FILE'));
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }
}
