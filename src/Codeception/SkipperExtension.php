<?php

declare(strict_types=1);

namespace GetSkipper\Codeception;

use Codeception\Event\SuiteEvent;
use Codeception\Event\TestEvent;
use Codeception\Events;
use Codeception\Extension;
use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Credentials\FileCredentials;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\SkipperMode;
use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\Core\Writer\SheetsWriter;

/**
 * Codeception 5 extension that integrates Skipper test-gating.
 *
 * Configure in codeception.yml:
 *
 * extensions:
 *   enabled:
 *     - GetSkipper\Codeception\SkipperExtension
 *   config:
 *     GetSkipper\Codeception\SkipperExtension:
 *       spreadsheetId: 'YOUR_SPREADSHEET_ID'
 *       credentialsFile: './service-account.json'
 *       # OR: credentialsBase64: 'base64string'
 *       # OR: credentialsEnvVar: 'GOOGLE_CREDS_B64'
 *       sheetName: 'MySheet'  # optional
 *
 * Test ID format:
 *   tests/Acceptance/AuthCest.php > AuthCest > tryToLogin
 *   tests/Unit/AuthTest.php > AuthTest > testItCanLogin
 */
final class SkipperExtension extends Extension
{
    /** @var array<string, mixed> */
    public static array $events = [
        Events::SUITE_BEFORE => 'beforeSuite',
        Events::TEST_BEFORE => 'beforeTest',
        Events::SUITE_AFTER => 'afterSuite',
    ];

    private ?SkipperResolver $resolver = null;
    private ?SkipperConfig $skipperConfig = null;
    /** @var string[] */
    private array $discoveredIds = [];
    private bool $isMainProcess = false;

    public function beforeSuite(SuiteEvent $event): void
    {
        $cacheFile = (string) getenv('SKIPPER_CACHE_FILE');
        $this->isMainProcess = $cacheFile === '' || !file_exists($cacheFile);

        if ($this->isMainProcess) {
            $this->skipperConfig = $this->buildConfig();
            $resolver = new SkipperResolver($this->skipperConfig);
            $resolver->initialize();

            $dir = CacheManager::writeResolverCache($resolver->toArray());
            $cacheFile = $dir . '/cache.json';
            putenv("SKIPPER_CACHE_FILE={$cacheFile}");
            putenv("SKIPPER_DISCOVERED_DIR={$dir}");
            Logger::log('[skipper] Spreadsheet loaded and cache written.');
            $this->resolver = $resolver;
        } else {
            $data = CacheManager::readResolverCache($cacheFile);
            $this->resolver = SkipperResolver::fromArray($data);
        }
    }

    public function beforeTest(TestEvent $event): void
    {
        if ($this->resolver === null) {
            return;
        }

        $test = $event->getTest();
        $testId = $this->buildTestId($test);

        $this->discoveredIds[] = $testId;

        if (!$this->resolver->isTestEnabled($testId)) {
            $disabledUntil = $this->resolver->getDisabledUntil($testId);
            $message = '[skipper] Test disabled' . ($disabledUntil !== null ? " until {$disabledUntil}" : '') . '.';

            // PHPUnit-based tests (Unit suite)
            if ($test instanceof \PHPUnit\Framework\TestCase) {
                throw new \PHPUnit\Framework\SkippedWithMessageException($message);
            }

            // Cest-based tests (Acceptance/Functional suites)
            if (method_exists($test, 'getScenario')) {
                $scenario = $test->getScenario();
                if (method_exists($scenario, 'skip')) {
                    $scenario->skip($message);
                    return;
                }
            }

            // Fallback: mark test as incomplete
            throw new \PHPUnit\Framework\IncompleteTestError($message);
        }
    }

    public function afterSuite(SuiteEvent $event): void
    {
        if (SkipperMode::fromEnv() !== SkipperMode::Sync || !$this->isMainProcess) {
            return;
        }

        if ($this->skipperConfig === null) {
            return;
        }

        $dir = (string) getenv('SKIPPER_DISCOVERED_DIR');
        if ($dir !== '' && is_dir($dir)) {
            CacheManager::writeDiscoveredIds($dir, $this->discoveredIds);
            $allIds = CacheManager::mergeDiscoveredIds($dir);
        } else {
            $allIds = $this->discoveredIds;
        }

        if (!empty($allIds)) {
            $writer = new SheetsWriter($this->skipperConfig);
            $writer->sync($allIds);
        }

        Logger::log('[skipper] Sync complete.');
    }

    private function buildConfig(): SkipperConfig
    {
        $cfg = $this->config;

        if (!empty($cfg['credentialsFile'])) {
            $credentials = new FileCredentials($cfg['credentialsFile']);
        } elseif (!empty($cfg['credentialsBase64'])) {
            $credentials = new Base64Credentials($cfg['credentialsBase64']);
        } elseif (!empty($cfg['credentialsEnvVar'])) {
            $value = (string) getenv($cfg['credentialsEnvVar']);
            if ($value === '') {
                throw new \InvalidArgumentException(
                    "[skipper] Environment variable \"{$cfg['credentialsEnvVar']}\" is empty or not set."
                );
            }
            $credentials = new Base64Credentials($value);
        } else {
            throw new \InvalidArgumentException(
                '[skipper] No credentials provided. Use credentialsFile, credentialsBase64, or credentialsEnvVar.'
            );
        }

        return new SkipperConfig(
            spreadsheetId: (string) ($cfg['spreadsheetId'] ?? ''),
            credentials: $credentials,
            sheetName: !empty($cfg['sheetName']) ? $cfg['sheetName'] : null,
        );
    }

    private function buildTestId(mixed $test): string
    {
        $file = '';
        $titlePath = [];

        if ($test instanceof \PHPUnit\Framework\TestCase) {
            $file = (new \ReflectionClass($test))->getFileName() ?: '';
            $shortClass = (new \ReflectionClass($test))->getShortName();
            $titlePath = [$shortClass, $test->getName(false)];
        } elseif (method_exists($test, 'getScenario') && method_exists($test, 'getMetadata')) {
            // Cest test
            $metadata = $test->getMetadata();
            $file = $metadata->getFilename() ?? '';
            $shortClass = basename(str_replace('\\', '/', get_class($test)));
            $feature = $metadata->getCurrent('feature') ?? $shortClass;
            $titlePath = [$feature, $metadata->getCurrent('name') ?? ''];
        } else {
            return get_class($test) . '::' . (method_exists($test, 'getName') ? $test->getName() : '');
        }

        return TestIdHelper::build($file, $titlePath);
    }
}
