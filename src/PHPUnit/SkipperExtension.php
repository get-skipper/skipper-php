<?php

declare(strict_types=1);

namespace GetSkipper\PHPUnit;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\PHPUnit\Subscriber\TestPreparedSubscriber;
use GetSkipper\PHPUnit\Subscriber\TestRunnerFinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * PHPUnit 10+ extension that integrates Skipper test-gating.
 *
 * Configure in phpunit.xml:
 *
 * <extensions>
 *   <bootstrap class="GetSkipper\PHPUnit\SkipperExtension">
 *     <parameter name="spreadsheetId" value="YOUR_SPREADSHEET_ID"/>
 *     <parameter name="credentialsFile" value="./service-account.json"/>
 *     <!-- OR: credentialsBase64 | credentialsEnvVar -->
 *     <parameter name="sheetName" value="MySheet"/>
 *   </bootstrap>
 * </extensions>
 *
 * Environment variables:
 *   SKIPPER_MODE=sync        — enable sync mode (default: read-only)
 *   SKIPPER_DEBUG=1          — enable verbose logging
 *   SKIPPER_CACHE_FILE       — set by main process; workers rehydrate from this file
 *   SKIPPER_DISCOVERED_DIR   — shared temp directory for discovered test IDs
 */
final class SkipperExtension implements Extension
{
    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $config = $this->buildConfig($parameters);

        $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');
        $isMainProcess = $cacheFile === '' || !file_exists($cacheFile);

        if ($isMainProcess) {
            // Main process: fetch from Google Sheets and write cache
            $resolver = new SkipperResolver($config);
            $resolver->initialize();

            $discoveredDir = CacheManager::writeResolverCache($resolver->toArray());
            $cacheFile = $discoveredDir . '/cache.json';

            putenv("SKIPPER_CACHE_FILE={$cacheFile}");
            putenv("SKIPPER_DISCOVERED_DIR={$discoveredDir}");

            Logger::log('[skipper] Spreadsheet loaded and cache written.');
        } else {
            // Worker process: rehydrate from cache file
            $data = CacheManager::readResolverCache($cacheFile);
            $resolver = SkipperResolver::fromArray($data);
            $discoveredDir = (string) getenv('SKIPPER_DISCOVERED_DIR') ?: null;
        }

        $state = new SkipperState($resolver, $discoveredDir ?? null);

        $facade->registerSubscriber(new TestPreparedSubscriber($state));
        $facade->registerSubscriber(new TestRunnerFinishedSubscriber($state, $config, $isMainProcess));
    }

    private function buildConfig(ParameterCollection $parameters): SkipperConfig
    {
        $spreadsheetId = $parameters->get('spreadsheetId');

        if ($parameters->has('credentialsFile')) {
            $credentials = new FileCredentials($parameters->get('credentialsFile'));
        } elseif ($parameters->has('credentialsBase64')) {
            $credentials = new Base64Credentials($parameters->get('credentialsBase64'));
        } elseif ($parameters->has('credentialsEnvVar')) {
            $envVar = $parameters->get('credentialsEnvVar');
            $value = (string) getenv($envVar);
            if ($value === '') {
                throw new \InvalidArgumentException(
                    "[skipper] Environment variable \"{$envVar}\" is empty or not set."
                );
            }
            $credentials = new Base64Credentials($value);
        } else {
            throw new \InvalidArgumentException(
                '[skipper] No credentials provided. Use credentialsFile, credentialsBase64, or credentialsEnvVar.'
            );
        }

        return new SkipperConfig(
            spreadsheetId: $spreadsheetId,
            credentials: $credentials,
            sheetName: $parameters->has('sheetName') ? $parameters->get('sheetName') : null,
        );
    }
}
