<?php

declare(strict_types=1);

namespace GetSkipper\Kahlan;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\SkipperMode;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\Core\Writer\SheetsWriter;

/**
 * Kahlan 5 integration for Skipper test-gating.
 *
 * Usage in kahlan-config.php:
 *
 *   use GetSkipper\Kahlan\SkipperPlugin;
 *   use GetSkipper\Core\Config\SkipperConfig;
 *   use GetSkipper\Core\Credentials\FileCredentials;
 *
 *   $config->beforeAll(function() {
 *       SkipperPlugin::setup(new SkipperConfig(
 *           spreadsheetId: 'YOUR_SPREADSHEET_ID',
 *           credentials: new FileCredentials('./service-account.json'),
 *       ));
 *   });
 *
 *   // In each spec file or globally via kahlan-config.php:
 *   beforeEach(function() {
 *       SkipperPlugin::checkTest($this);
 *   });
 *
 *   // OR register globally for all specs in a directory:
 *   $config->scope()->beforeEach(function() {
 *       SkipperPlugin::checkTest($this);
 *   });
 *
 * Test ID format:
 *   spec/Auth/LoginSpec.php > Auth > Login > can login with valid credentials
 */
final class SkipperPlugin
{
    private static ?SkipperResolver $resolver = null;
    private static ?SkipperConfig $config = null;
    /** @var string[] */
    private static array $discoveredIds = [];

    /**
     * Initializes Skipper. Call once in kahlan-config.php beforeAll().
     */
    public static function setup(SkipperConfig $config): void
    {
        self::$config = $config;

        $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');
        if ($cacheFile !== '' && file_exists($cacheFile)) {
            self::$resolver = SkipperResolver::fromArray(CacheManager::readResolverCache($cacheFile));
        } else {
            $resolver = new SkipperResolver($config);
            $resolver->initialize();
            self::$resolver = $resolver;

            $dir = CacheManager::writeResolverCache($resolver->toArray());
            putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");
            putenv("SKIPPER_DISCOVERED_DIR={$dir}");
            Logger::log('[skipper] Spreadsheet loaded and cache written.');
        }

        // Register afterAll for sync mode
        if (SkipperMode::fromEnv() === SkipperMode::Sync) {
            register_shutdown_function([self::class, 'syncOnShutdown']);
        }
    }

    /**
     * Checks if the current test should run. Call in beforeEach().
     *
     * Usage: SkipperPlugin::checkTest($this);
     *
     * @param object $scope The Kahlan test scope ($this inside a beforeEach)
     */
    public static function checkTest(object $scope): void
    {
        if (self::$resolver === null) {
            return;
        }

        $testId = self::buildTestId($scope);
        self::$discoveredIds[] = $testId;

        if (!self::$resolver->isTestEnabled($testId)) {
            $disabledUntil = self::$resolver->getDisabledUntil($testId);
            \Kahlan\skipIf(
                true,
                '[skipper] Test disabled' . ($disabledUntil !== null ? " until {$disabledUntil}" : '') . '.'
            );
        }
    }

    /**
     * Builds a test ID for the current Kahlan scope.
     */
    private static function buildTestId(object $scope): string
    {
        // Kahlan exposes the current scope via $this->suite() and description hierarchy
        $file = '';
        $titlePath = [];

        if (method_exists($scope, 'suite')) {
            $suite = $scope->suite();
            if (method_exists($suite, 'file')) {
                $file = (string) $suite->file();
            }
            if (method_exists($suite, 'messages')) {
                $titlePath = (array) $suite->messages();
            }
        }

        return TestIdHelper::build($file, $titlePath);
    }

    /**
     * Called on shutdown to sync discovered tests in sync mode.
     *
     * @internal
     */
    public static function syncOnShutdown(): void
    {
        if (self::$config === null || empty(self::$discoveredIds)) {
            return;
        }

        $writer = new SheetsWriter(self::$config);
        $writer->sync(self::$discoveredIds);
        Logger::log('[skipper] Sync complete.');
    }

    public static function getResolver(): ?SkipperResolver
    {
        return self::$resolver;
    }
}
