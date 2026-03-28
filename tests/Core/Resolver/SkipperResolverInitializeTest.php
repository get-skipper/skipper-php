<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Resolver;

use GetSkipper\Core\Client\FetchAllResult;
use GetSkipper\Core\Client\SheetsClient;
use GetSkipper\Core\Client\SheetFetchResult;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Model\TestEntry;
use GetSkipper\Core\Resolver\SkipperResolver;
use Google\Service\Sheets as SheetsService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SkipperResolver::initialize() — disk-cache fallback and fail-open behavior.
 *
 * New env vars under test:
 *   SKIPPER_FAIL_OPEN  (default: true)  — run all tests instead of crashing on API failure
 *   SKIPPER_CACHE_TTL  (default: 300)   — seconds the on-disk cache remains valid
 */
final class SkipperResolverInitializeTest extends TestCase
{
    private const CACHE_FILE = '.skipper-cache.json';

    /** @var string|false */
    private string|false $prevFailOpen;
    /** @var string|false */
    private string|false $prevCacheTtl;

    protected function setUp(): void
    {
        $this->prevFailOpen = getenv('SKIPPER_FAIL_OPEN');
        $this->prevCacheTtl = getenv('SKIPPER_CACHE_TTL');
        putenv('SKIPPER_FAIL_OPEN');
        putenv('SKIPPER_CACHE_TTL');

        if (file_exists(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
    }

    protected function tearDown(): void
    {
        $this->prevFailOpen !== false
            ? putenv("SKIPPER_FAIL_OPEN={$this->prevFailOpen}")
            : putenv('SKIPPER_FAIL_OPEN');

        $this->prevCacheTtl !== false
            ? putenv("SKIPPER_CACHE_TTL={$this->prevCacheTtl}")
            : putenv('SKIPPER_CACHE_TTL');

        if (file_exists(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeResolver(SheetsClient $client): SkipperResolver
    {
        return new SkipperResolver(new SkipperConfig('', new Base64Credentials('')), $client);
    }

    private function makeSucceedingClient(array $entries = []): SheetsClient
    {
        $primary = new SheetFetchResult('Sheet1', 0, [], [], []);
        $result  = new FetchAllResult($primary, $entries, $this->createMock(SheetsService::class));

        $client = $this->createMock(SheetsClient::class);
        $client->method('fetchAll')->willReturn($result);
        return $client;
    }

    private function makeFailingClient(\Exception $e): SheetsClient
    {
        $client = $this->createMock(SheetsClient::class);
        $client->method('fetchAll')->willThrowException($e);
        return $client;
    }

    /** Writes a pre-built disk cache with a given age (seconds in the past). */
    private function writeDiskCache(array $rows, int $ageSeconds = 0): void
    {
        file_put_contents(
            self::CACHE_FILE,
            json_encode(['ts' => time() - $ageSeconds, 'rows' => $rows])
        );
    }

    // ── tests ────────────────────────────────────────────────────────────────

    public function testInitializeWritesDiskCacheOnSuccess(): void
    {
        $entry    = new TestEntry('tests/FooTest.php > Foo > testA', null);
        $resolver = $this->makeResolver($this->makeSucceedingClient([$entry]));

        self::assertFileDoesNotExist(self::CACHE_FILE);
        $resolver->initialize();

        self::assertFileExists(self::CACHE_FILE);
        /** @var array{ts: int, rows: array<string, string|null>} $data */
        $data = json_decode((string) file_get_contents(self::CACHE_FILE), true);
        self::assertIsInt($data['ts']);
        // Keys are stored normalized (lowercase, whitespace-collapsed)
        self::assertArrayHasKey('tests/footest.php > foo > testa', $data['rows']);
    }

    public function testFailOpenRunsAllTestsOnApiFailureWithoutCache(): void
    {
        // SKIPPER_FAIL_OPEN defaults to true — no cache file exists
        $resolver = $this->makeResolver($this->makeFailingClient(new \RuntimeException('API down')));

        $resolver->initialize();

        // Empty cache → every test runs
        self::assertTrue($resolver->isTestEnabled('tests/AnyTest.php > Any > testAnything'));
    }

    public function testFailClosedRethrowsOnApiFailureWithoutCache(): void
    {
        putenv('SKIPPER_FAIL_OPEN=false');
        $resolver = $this->makeResolver($this->makeFailingClient(new \RuntimeException('API down')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API down');
        $resolver->initialize();
    }

    public function testUsesDiskCacheOnApiFailureWithinTtl(): void
    {
        // Cache is 60 s old — well within the default 300 s TTL
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $this->writeDiskCache(
            ['tests/footest.php > foo > testa' => $futureDate],
            ageSeconds: 60
        );

        $resolver = $this->makeResolver($this->makeFailingClient(new \RuntimeException('API down')));
        $resolver->initialize();

        // Cached entry marks testA as disabled
        self::assertFalse($resolver->isTestEnabled('tests/FooTest.php > Foo > testA'));
    }

    public function testIgnoresExpiredDiskCacheAndFallsOpenOnApiFailure(): void
    {
        // Cache is 10 s old but TTL is only 5 s → expired
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $this->writeDiskCache(
            ['tests/footest.php > foo > testa' => $futureDate],
            ageSeconds: 10
        );

        putenv('SKIPPER_CACHE_TTL=5');
        $resolver = $this->makeResolver($this->makeFailingClient(new \RuntimeException('API down')));
        $resolver->initialize();

        // Expired cache is ignored → fail-open → testA runs
        self::assertTrue($resolver->isTestEnabled('tests/FooTest.php > Foo > testA'));
    }

    public function testFreshCacheWithinCustomTtlIsUsed(): void
    {
        // Cache is 3 s old and TTL is 10 s → still valid
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $this->writeDiskCache(
            ['tests/footest.php > foo > testa' => $futureDate],
            ageSeconds: 3
        );

        putenv('SKIPPER_CACHE_TTL=10');
        $resolver = $this->makeResolver($this->makeFailingClient(new \RuntimeException('API down')));
        $resolver->initialize();

        // Cache is valid → testA is disabled
        self::assertFalse($resolver->isTestEnabled('tests/FooTest.php > Foo > testA'));
    }
}
