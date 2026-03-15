<?php

declare(strict_types=1);

namespace GetSkipper\PHPSpec;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use PhpSpec\Extension;
use PhpSpec\ServiceContainer;

/**
 * PHPSpec extension that integrates Skipper test-gating.
 *
 * Configure in phpspec.yml:
 *
 * extensions:
 *   GetSkipper\PHPSpec\SkipperExtension:
 *     spreadsheetId: 'YOUR_SPREADSHEET_ID'
 *     credentialsFile: './service-account.json'
 *     # OR: credentialsBase64: 'base64string'
 *     # OR: credentialsEnvVar: 'GOOGLE_CREDS_B64'
 *     sheetName: 'MySheet'  # optional
 *
 * Test ID format:
 *   spec/Auth/LoginSpec.php > LoginSpec > it login with valid credentials
 *   spec/Auth/LoginSpec.php > LoginSpec > it reject invalid passwords
 */
final class SkipperExtension implements Extension
{
    public function load(ServiceContainer $container, array $params): void
    {
        $skipperConfig = $this->buildConfig($params);

        $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');
        if ($cacheFile !== '' && file_exists($cacheFile)) {
            $resolver = SkipperResolver::fromArray(CacheManager::readResolverCache($cacheFile));
        } else {
            $resolver = new SkipperResolver($skipperConfig);
            $resolver->initialize();

            $dir = CacheManager::writeResolverCache($resolver->toArray());
            putenv("SKIPPER_CACHE_FILE={$dir}/cache.json");
            putenv("SKIPPER_DISCOVERED_DIR={$dir}");
            Logger::log('[skipper] Spreadsheet loaded.');
        }

        $container->define(
            'skipper.listener',
            fn ($c) => new SkipperListener($resolver, $skipperConfig),
            ['event_dispatcher.listeners']
        );
    }

    private function buildConfig(array $params): SkipperConfig
    {
        if (!empty($params['credentialsFile'])) {
            $credentials = new FileCredentials($params['credentialsFile']);
        } elseif (!empty($params['credentialsBase64'])) {
            $credentials = new Base64Credentials($params['credentialsBase64']);
        } elseif (!empty($params['credentialsEnvVar'])) {
            $value = (string) getenv($params['credentialsEnvVar']);
            if ($value === '') {
                throw new \InvalidArgumentException(
                    "[skipper] Environment variable \"{$params['credentialsEnvVar']}\" is empty or not set."
                );
            }
            $credentials = new Base64Credentials($value);
        } else {
            throw new \InvalidArgumentException(
                '[skipper] No credentials provided. Use credentialsFile, credentialsBase64, or credentialsEnvVar.'
            );
        }

        return new SkipperConfig(
            spreadsheetId: (string) ($params['spreadsheetId'] ?? ''),
            credentials: $credentials,
            sheetName: !empty($params['sheetName']) ? $params['sheetName'] : null,
        );
    }
}
