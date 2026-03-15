<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\PHPSpec;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\PHPSpec\SkipperExtension;
use GetSkipper\PHPSpec\SkipperListener;
use PhpSpec\Extension;
use PhpSpec\ServiceContainer;
use PHPUnit\Framework\TestCase;

final class SkipperExtensionTest extends TestCase
{
    public function testImplementsPhpSpecExtension(): void
    {
        self::assertInstanceOf(Extension::class, new SkipperExtension());
    }

    public function testLoadThrowsWhenNoCredentialsProvided(): void
    {
        $container = $this->createMock(ServiceContainer::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/No credentials provided/');

        (new SkipperExtension())->load($container, ['spreadsheetId' => 'x']);
    }

    public function testLoadThrowsWhenCredentialsEnvVarIsEmpty(): void
    {
        $container = $this->createMock(ServiceContainer::class);
        $envVar = 'SKIPPER_TEST_EMPTY_CREDS_' . bin2hex(random_bytes(4));
        putenv("{$envVar}=");

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessageMatches('/empty or not set/');

            (new SkipperExtension())->load($container, [
                'spreadsheetId' => 'x',
                'credentialsEnvVar' => $envVar,
            ]);
        } finally {
            putenv($envVar);
        }
    }

    public function testLoadRegistersListenerInContainer(): void
    {
        // Write a resolver cache so load() never calls the Google Sheets API.
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');

        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $capturedId = null;
            $capturedFactory = null;
            $capturedTags = null;

            $container = $this->createMock(ServiceContainer::class);
            $container->expects(self::once())
                ->method('define')
                ->willReturnCallback(
                    function (string $id, callable $factory, array $tags = []) use (&$capturedId, &$capturedFactory, &$capturedTags): void {
                        $capturedId = $id;
                        $capturedFactory = $factory;
                        $capturedTags = $tags;
                    }
                );

            (new SkipperExtension())->load($container, [
                'spreadsheetId' => 'test-sheet-id',
                'credentialsBase64' => base64_encode('{}'),
            ]);

            self::assertSame('skipper.listener', $capturedId);
            self::assertContains('event_dispatcher.listeners', $capturedTags);
            self::assertInstanceOf(SkipperListener::class, ($capturedFactory)($container));
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }

    public function testLoadRegistersListenerWithCorrectKey(): void
    {
        $dir = CacheManager::writeResolverCache([]);
        $prevCacheFile = getenv('SKIPPER_CACHE_FILE');

        putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");

        try {
            $registeredId = null;

            $container = $this->createMock(ServiceContainer::class);
            $container->method('define')
                ->willReturnCallback(function (string $id) use (&$registeredId): void {
                    $registeredId = $id;
                });

            (new SkipperExtension())->load($container, [
                'spreadsheetId' => 'test-sheet-id',
                'credentialsBase64' => base64_encode('{}'),
            ]);

            self::assertSame('skipper.listener', $registeredId);
        } finally {
            CacheManager::cleanup($dir);
            putenv($prevCacheFile !== false ? "SKIPPER_CACHE_FILE={$prevCacheFile}" : 'SKIPPER_CACHE_FILE');
        }
    }
}
