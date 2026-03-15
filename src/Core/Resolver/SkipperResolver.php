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
    ) {
        $this->client = new SheetsClient($config);
    }

    /**
     * Fetches the spreadsheet and populates the in-memory cache.
     * Must be called once before isTestEnabled().
     */
    public function initialize(): void
    {
        $result = $this->client->fetchAll();
        $this->cache = [];

        foreach ($result->entries as $entry) {
            $key = TestIdHelper::normalize($entry->testId);
            $this->cache[$key] = $entry->disabledUntil?->format(\DateTimeInterface::ATOM);
        }

        $this->initialized = true;
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

        return new \DateTimeImmutable($iso) <= new \DateTimeImmutable();
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
