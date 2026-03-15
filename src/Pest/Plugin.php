<?php

declare(strict_types=1);

namespace GetSkipper\Pest;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\TestId\TestIdHelper;

/**
 * Pest integration for Skipper test-gating.
 *
 * Since Pest v2/v3 runs on top of PHPUnit, the GetSkipper\PHPUnit\SkipperExtension
 * works for Pest tests directly — register it in phpunit.xml as usual.
 *
 * This Plugin class provides:
 * 1. A Pest-aware test ID builder (strips Pest-generated "P\" class names)
 * 2. A skipperSetup() helper for tests/Pest.php as an alternative hook-based approach
 *
 * --- Pest test ID format ---
 * tests/Feature/auth.php > can login
 * tests/Feature/auth.php > Auth > can login      (with describe() block)
 *
 * --- Recommended setup ---
 * Register GetSkipper\PHPUnit\SkipperExtension in phpunit.xml — it auto-detects
 * Pest-generated classes and formats IDs correctly.
 *
 * --- Alternative (hook-based) setup for tests/Pest.php ---
 * use GetSkipper\Pest\Plugin;
 * use GetSkipper\Core\Config\SkipperConfig;
 * use GetSkipper\Core\Credentials\FileCredentials;
 *
 * Plugin::skipperSetup(new SkipperConfig(
 *     spreadsheetId: 'YOUR_SPREADSHEET_ID',
 *     credentials: new FileCredentials('./service-account.json'),
 * ));
 */
final class Plugin
{
    /**
     * Sets up Skipper for Pest via a global beforeEach hook in tests/Pest.php.
     *
     * This approach is an alternative to the PHPUnit extension and is useful when
     * you prefer Pest-native configuration. It requires the cache to be initialized
     * before Pest runs (e.g. by also registering SkipperExtension for initialization,
     * or by pre-setting SKIPPER_CACHE_FILE in the environment).
     *
     * Usage in tests/Pest.php:
     *
     *   Plugin::skipperSetup(new SkipperConfig(...));
     */
    public static function skipperSetup(SkipperConfig $config): void
    {
        $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');

        if ($cacheFile !== '' && file_exists($cacheFile)) {
            $resolver = SkipperResolver::fromArray(CacheManager::readResolverCache($cacheFile));
        } else {
            $resolver = new SkipperResolver($config);
            $resolver->initialize();

            $dir = CacheManager::writeResolverCache($resolver->toArray());
            $cacheFile = $dir . '/cache.json';
            putenv("SKIPPER_CACHE_FILE={$cacheFile}");
            putenv("SKIPPER_DISCOVERED_DIR={$dir}");
        }

        // Register a global Pest beforeEach hook to skip disabled tests
        \Pest\PendingCalls\TestCall::beforeEach(static function () use ($resolver): void {
            /** @var \Pest\TestCase $this */
            $testId = self::buildPestTestId(
                test()->getTestPath() ?? '',
                $this->getName(false)
            );

            if (!$resolver->isTestEnabled($testId)) {
                $disabledUntil = $resolver->getDisabledUntil($testId);
                test()->skip('[skipper] Test disabled' . ($disabledUntil !== null ? " until {$disabledUntil}" : '') . '.');
            }
        });
    }

    /**
     * Builds a test ID for a Pest test.
     *
     * In Pest, describe blocks are reflected in the test name as "> DescribeName > testName",
     * or the test name may already include the describe block prefix depending on the version.
     *
     * @internal Used by SkipperState::buildTestId() for Pest-generated PHPUnit classes.
     */
    public static function buildPestTestId(string $filePath, string $methodName): string
    {
        // Pest may encode the describe block as "DescribeName > test name" in the method name
        // We pass it as-is since TestIdHelper::build() will join with the file path
        $parts = explode(' > ', $methodName);
        return TestIdHelper::build($filePath, $parts);
    }
}
