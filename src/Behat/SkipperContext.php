<?php

declare(strict_types=1);

namespace GetSkipper\Behat;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\AfterSuiteScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Tester\Exception\PendingException;
use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\SkipperMode;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\Core\Writer\SheetsWriter;

/**
 * Behat context that skips disabled scenarios via Skipper test-gating.
 *
 * Add to your behat.yml suite:
 *   suites:
 *     default:
 *       contexts:
 *         - GetSkipper\Behat\SkipperContext
 *
 * Requires GetSkipper\Behat\SkipperExtension to be loaded first.
 *
 * Test ID format:
 *   features/auth/login.feature > User authentication > User can log in
 */
final class SkipperContext implements Context
{
    private static ?SkipperResolver $resolver = null;
    private static ?SkipperConfig $config = null;
    /** @var string[] */
    private static array $discoveredIds = [];

    /**
     * Initializes the resolver from the cache written by SkipperExtension.
     */
    public function __construct()
    {
        if (self::$resolver === null) {
            $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');
            if ($cacheFile === '' || !file_exists($cacheFile)) {
                throw new \RuntimeException(
                    '[skipper] SKIPPER_CACHE_FILE not set. '
                    . 'Make sure GetSkipper\\Behat\\SkipperExtension is loaded in behat.yml.'
                );
            }
            $cache = CacheManager::readResolverCache($cacheFile);
            self::$resolver = SkipperResolver::fromArray($cache);
        }
    }

    /**
     * @BeforeScenario
     */
    public function checkScenarioEnabled(BeforeScenarioScope $scope): void
    {
        $feature = $scope->getFeature();
        $scenario = $scope->getScenario();

        $testId = TestIdHelper::build(
            $feature->getFile() ?? '',
            [$feature->getTitle(), $scenario->getTitle()]
        );

        self::$discoveredIds[] = $testId;

        if (!self::$resolver->isTestEnabled($testId)) {
            $disabledUntil = self::$resolver->getDisabledUntil($testId);
            throw new PendingException(
                '[skipper] Test disabled' . ($disabledUntil !== null ? " until {$disabledUntil}" : '') . '.'
            );
        }
    }

    /**
     * @AfterSuite
     */
    public static function syncAfterSuite(AfterSuiteScope $scope): void
    {
        if (SkipperMode::fromEnv() !== SkipperMode::Sync) {
            return;
        }

        if (self::$config === null) {
            Logger::warn('[skipper] Sync mode enabled but SkipperConfig not available in SkipperContext.');
            return;
        }

        $writer = new SheetsWriter(self::$config);
        $writer->sync(self::$discoveredIds);
        Logger::log('[skipper] Sync complete.');
    }

    public static function setConfig(SkipperConfig $config): void
    {
        self::$config = $config;
    }
}
