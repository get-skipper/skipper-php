<?php

declare(strict_types=1);

namespace GetSkipper\Behat;

use Behat\Testwork\ServiceContainer\Extension;
use Behat\Testwork\ServiceContainer\ExtensionManager;
use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Behat extension that bootstraps Skipper test-gating.
 *
 * Configure in behat.yml:
 *
 * default:
 *   extensions:
 *     GetSkipper\Behat\SkipperExtension:
 *       spreadsheetId: 'YOUR_SPREADSHEET_ID'
 *       credentialsFile: './service-account.json'
 *       # OR: credentialsBase64: 'base64string'
 *       # OR: credentialsEnvVar: 'GOOGLE_CREDS_B64'
 *       sheetName: 'MySheet'           # optional
 *   suites:
 *     default:
 *       contexts:
 *         - GetSkipper\Behat\SkipperContext
 */
final class SkipperExtension implements Extension
{
    public function getConfigKey(): string
    {
        return 'skipper';
    }

    public function initialize(ExtensionManager $extensionManager): void
    {
    }

    public function configure(ArrayNodeDefinition $builder): void
    {
        $builder
            ->children()
                ->scalarNode('spreadsheetId')->isRequired()->end()
                ->scalarNode('credentialsFile')->defaultNull()->end()
                ->scalarNode('credentialsBase64')->defaultNull()->end()
                ->scalarNode('credentialsEnvVar')->defaultNull()->end()
                ->scalarNode('sheetName')->defaultNull()->end()
            ->end();
    }

    public function load(ContainerBuilder $container, array $config): void
    {
        if ($config['credentialsFile'] !== null) {
            $credentials = new FileCredentials($config['credentialsFile']);
        } elseif ($config['credentialsBase64'] !== null) {
            $credentials = new Base64Credentials($config['credentialsBase64']);
        } elseif ($config['credentialsEnvVar'] !== null) {
            $value = (string) getenv($config['credentialsEnvVar']);
            if ($value === '') {
                throw new \InvalidArgumentException(
                    "[skipper] Environment variable \"{$config['credentialsEnvVar']}\" is empty or not set."
                );
            }
            $credentials = new Base64Credentials($value);
        } else {
            throw new \InvalidArgumentException(
                '[skipper] No credentials provided. Use credentialsFile, credentialsBase64, or credentialsEnvVar.'
            );
        }

        $skipperConfig = new SkipperConfig(
            spreadsheetId: $config['spreadsheetId'],
            credentials: $credentials,
            sheetName: $config['sheetName'] ?? null,
        );

        // Initialize resolver and write cache to a temp file
        $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');
        if ($cacheFile === '' || !file_exists($cacheFile)) {
            $resolver = new SkipperResolver($skipperConfig);
            $resolver->initialize();

            $dir = CacheManager::writeResolverCache($resolver->toArray());
            $cacheFile = $dir . '/cache.json';
            putenv("SKIPPER_CACHE_FILE={$cacheFile}");
            putenv("SKIPPER_DISCOVERED_DIR={$dir}");
            Logger::log('[skipper] Spreadsheet loaded and cache written.');
        }

    }

    public function process(ContainerBuilder $container): void
    {
    }
}
