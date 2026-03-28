<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Writer;

use GetSkipper\Core\Client\FetchAllResult;
use GetSkipper\Core\Client\SheetsClient;
use GetSkipper\Core\Client\SheetFetchResult;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Credentials\Base64Credentials;
use GetSkipper\Core\Model\TestEntry;
use GetSkipper\Core\Writer\SheetsWriter;
use Google\Service\Sheets as SheetsService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SheetsWriter::sync() — SKIPPER_SYNC_ALLOW_DELETE behavior.
 *
 * Spreadsheet fixture: contains testA and testB.
 * Discovered IDs: only testA → testB is orphaned.
 *
 * SKIPPER_SYNC_ALLOW_DELETE=false (default): orphaned rows are logged, NOT deleted.
 * SKIPPER_SYNC_ALLOW_DELETE=true:            orphaned rows ARE deleted via batchUpdate.
 */
final class SheetsWriterTest extends TestCase
{
    private const TEST_A = 'tests/FooTest.php > Foo > testA';
    private const TEST_B = 'tests/FooTest.php > Foo > testB';

    /** @var string|false */
    private string|false $prevAllowDelete;

    protected function setUp(): void
    {
        $this->prevAllowDelete = getenv('SKIPPER_SYNC_ALLOW_DELETE');
        putenv('SKIPPER_SYNC_ALLOW_DELETE');
    }

    protected function tearDown(): void
    {
        $this->prevAllowDelete !== false
            ? putenv("SKIPPER_SYNC_ALLOW_DELETE={$this->prevAllowDelete}")
            : putenv('SKIPPER_SYNC_ALLOW_DELETE');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /**
     * Builds a FetchAllResult as if the spreadsheet contains testA and testB.
     */
    private function makeFetchResult(SheetsService $service): FetchAllResult
    {
        $entries = [
            new TestEntry(self::TEST_A, null),
            new TestEntry(self::TEST_B, null),
        ];
        $header  = ['testId', 'disabledUntil', 'notes'];
        $rawRows = [
            $header,
            [self::TEST_A, '', ''],
            [self::TEST_B, '', ''],
        ];
        $primary = new SheetFetchResult('Sheet1', 0, $rawRows, $header, $entries);
        return new FetchAllResult($primary, $entries, $service);
    }

    private function makeWriter(FetchAllResult $result): SheetsWriter
    {
        $client = $this->createMock(SheetsClient::class);
        $client->method('fetchAll')->willReturn($result);
        return new SheetsWriter(new SkipperConfig('spreadsheet-id', new Base64Credentials('')), $client);
    }

    // ── tests ─────────────────────────────────────────────────────────────────

    public function testSyncDoesNotDeleteOrphanedRowsByDefault(): void
    {
        // SKIPPER_SYNC_ALLOW_DELETE is unset → defaults to false
        $mockSpreadsheets = $this->createMock(\Google\Service\Sheets\Resource\Spreadsheets::class);
        $mockSpreadsheets->expects(self::never())->method('batchUpdate');

        $mockService = $this->createMock(SheetsService::class);
        $mockService->spreadsheets = $mockSpreadsheets;

        $writer = $this->makeWriter($this->makeFetchResult($mockService));

        // testB is orphaned (not in discovered list) but must NOT be deleted
        $writer->sync([self::TEST_A]);
    }

    public function testSyncExplicitlyFalseDoesNotDeleteOrphanedRows(): void
    {
        putenv('SKIPPER_SYNC_ALLOW_DELETE=false');

        $mockSpreadsheets = $this->createMock(\Google\Service\Sheets\Resource\Spreadsheets::class);
        $mockSpreadsheets->expects(self::never())->method('batchUpdate');

        $mockService = $this->createMock(SheetsService::class);
        $mockService->spreadsheets = $mockSpreadsheets;

        $writer = $this->makeWriter($this->makeFetchResult($mockService));
        $writer->sync([self::TEST_A]);
    }

    public function testSyncDeletesOrphanedRowsWhenAllowed(): void
    {
        putenv('SKIPPER_SYNC_ALLOW_DELETE=true');

        $mockSpreadsheets = $this->createMock(\Google\Service\Sheets\Resource\Spreadsheets::class);
        $mockSpreadsheets->expects(self::once())->method('batchUpdate');

        $mockService = $this->createMock(SheetsService::class);
        $mockService->spreadsheets = $mockSpreadsheets;

        $writer = $this->makeWriter($this->makeFetchResult($mockService));

        // testB is orphaned — with allow-delete it must be removed via batchUpdate
        $writer->sync([self::TEST_A]);
    }
}
