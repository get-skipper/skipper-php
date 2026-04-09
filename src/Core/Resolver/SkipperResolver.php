<?php

declare(strict_types=1);

namespace GetSkipper\Core\Resolver;

use GetSkipper\Core\Client\SheetsClient;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\SkipperMode;
use GetSkipper\Core\TestId\TestIdHelper;

/**
 * SkipperResolver is the primary interface used by framework integrations.
 *
 * Lifecycle:
 * 1. Call initialize() once before tests run (in globalSetup / before hook).
 * 2. Call isTestEnabled($testId) per test to decide whether to skip.
 * 3. Call toArray() to serialize the cache for cross-process sharing.
 * 4. In worker processes, use SkipperResolver::fromArray() to rehydrate.
 */
final class SkipperResolver
{
    /**
     * Normalized testId => disabledUntil ISO-8601 string, or null (= no date = enabled).
     *
     * @var array<string, string|null>
     */
    private array $cache = [];
    private bool $initialized = false;
    private readonly SheetsClient $client;

    public function __construct(
        private readonly SkipperConfig $config,
        ?SheetsClient $client = null,
    ) {
        $this->client = $client ?? new SheetsClient($config);
    }

    /**
     * Fetches the spreadsheet and populates the in-memory cache.
     * Must be called once before isTestEnabled().
     *
     * On API failure:
     * - Uses the on-disk fallback cache if within SKIPPER_CACHE_TTL seconds (default: 300).
     * - If no valid cache exists and SKIPPER_FAIL_OPEN is not "false", runs all tests
     *   instead of crashing (fail-open is the default).
     * - If SKIPPER_FAIL_OPEN=false, the exception is rethrown.
     */
    public function initialize(): void
    {
        $cacheFile = '.skipper-cache.json';
        $ttl = (int)(getenv('SKIPPER_CACHE_TTL') ?: 300);
        $failOpen = getenv('SKIPPER_FAIL_OPEN') !== 'false';

        try {
            $result = $this->client->fetchAll();
            $this->cache = [];

            foreach ($result->entries as $entry) {
                $key = TestIdHelper::normalize($entry->testId);
                $this->cache[$key] = $entry->disabledUntil?->format(\DateTimeInterface::ATOM);
            }

            file_put_contents($cacheFile, json_encode(['ts' => time(), 'rows' => $this->cache], \JSON_THROW_ON_ERROR));
            $this->initialized = true;
        } catch (\Exception $e) {
            $cached = $this->tryReadCache($cacheFile, $ttl);
            if ($cached !== null) {
                error_log('[skipper] API failed, using cache (' . $cached['ageSeconds'] . 's old): ' . $e->getMessage());
                $this->cache = $cached['rows'];
                $this->initialized = true;
                return;
            }
            if ($failOpen) {
                error_log('[skipper] API failed, no cache — running all tests (fail-open): ' . $e->getMessage());
                $this->cache = [];
                $this->initialized = true;
                return;
            }
            throw $e;
        }
    }

    /**
     * Reads the on-disk fallback cache and returns rows + age if within TTL.
     *
     * @return array{rows: array<string, string|null>, ageSeconds: int}|null
     */
    private function tryReadCache(string $cacheFile, int $ttl): ?array
    {
        if (!file_exists($cacheFile)) {
            return null;
        }

        $raw = file_get_contents($cacheFile);
        if ($raw === false) {
            return null;
        }

        try {
            $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!isset($data['ts'], $data['rows']) || !is_int($data['ts']) || !is_array($data['rows'])) {
            return null;
        }

        $ageSeconds = time() - $data['ts'];
        if ($ageSeconds > $ttl) {
            return null;
        }

        return ['rows' => $data['rows'], 'ageSeconds' => $ageSeconds];
    }

    /**
     * Returns true if the test should run.
     *
     * Logic:
     * - Not in spreadsheet → true (opt-out model: unknown tests run by default)
     * - disabledUntil is null or in the past → true
     * - disabledUntil is in the future → false
     */
    public function isTestEnabled(string $testId): bool
    {
        if (!$this->initialized) {
            throw new \LogicException(
                '[skipper] SkipperResolver::initialize() must be called before isTestEnabled(). '
                . 'Did you forget to configure the extension?'
            );
        }

        $normalized = TestIdHelper::normalize($testId);

        if (!array_key_exists($normalized, $this->cache)) {
            return true;
        }

        $iso = $this->cache[$normalized];
        if ($iso === null) {
            return true;
        }

        $until = new \DateTimeImmutable($iso);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now >= $until;
    }

    /**
     * Returns the ISO-8601 disabledUntil string for a test (for use in skip messages).
     */
    public function getDisabledUntil(string $testId): ?string
    {
        $normalized = TestIdHelper::normalize($testId);
        return $this->cache[$normalized] ?? null;
    }

    /**
     * Serializes the cache for cross-process sharing (e.g. main process → workers).
     * Dates are stored as ISO-8601 strings; null means no date (enabled).
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return $this->cache;
    }

    /**
     * Rehydrates a resolver from a serialized cache.
     * Used in worker processes that cannot call initialize() again.
     *
     * @param array<string, string|null> $data
     */
    public static function fromArray(array $data): self
    {
        // Dummy config — the client is never invoked after fromArray()
        $dummy = new SkipperConfig('', new Base64Credentials(''));
        $resolver = new self($dummy);
        $resolver->cache = $data;
        $resolver->initialized = true;
        return $resolver;
    }

    public function getMode(): SkipperMode
    {
        return SkipperMode::fromEnv();
    }
}
