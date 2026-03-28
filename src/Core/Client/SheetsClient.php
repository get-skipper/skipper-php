<?php

declare(strict_types=1);

namespace GetSkipper\Core\Client;

use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Model\TestEntry;
use GetSkipper\Core\TestId\TestIdHelper;
use Google\Client as GoogleClient;
use Google\Service\Sheets as SheetsService;

class SheetsClient
{
    public function __construct(
        private readonly SkipperConfig $config,
    ) {
    }

    private function buildSheetsService(): SheetsService
    {
        $client = new GoogleClient();
        $client->setScopes([SheetsService::SPREADSHEETS]);
        $client->setAuthConfig($this->config->credentials->resolve());

        return new SheetsService($client);
    }

    /**
     * Fetches the primary sheet and all reference sheets in a single API session.
     *
     * Returns:
     * - `primary`: the primary sheet's full result (for writer use)
     * - `entries`: merged test entries from all sheets (for resolver use)
     * - `service`: the authenticated Sheets service (reuse for write operations)
     *
     * Deduplication: when the same testId appears in multiple sheets, the most
     * restrictive (latest) disabledUntil wins.
     */
    public function fetchAll(): FetchAllResult
    {
        $service = $this->buildSheetsService();
        $spreadsheetId = $this->config->spreadsheetId;

        // Get spreadsheet metadata to resolve sheet names → IDs
        $spreadsheet = $service->spreadsheets->get($spreadsheetId);
        $allSheetMeta = $spreadsheet->getSheets();

        /** @var array<string, int> $sheetIdByName */
        $sheetIdByName = [];
        foreach ($allSheetMeta as $sheet) {
            $props = $sheet->getProperties();
            $sheetIdByName[$props->getTitle()] = (int) $props->getSheetId();
        }

        $primaryName = $this->config->sheetName
            ?? ($allSheetMeta[0]?->getProperties()?->getTitle() ?? 'Sheet1');

        if (!isset($sheetIdByName[$primaryName])) {
            throw new \RuntimeException(
                "[skipper] Sheet \"{$primaryName}\" not found in spreadsheet."
            );
        }

        $primary = $this->fetchSheet($service, $primaryName, $sheetIdByName[$primaryName]);

        $referenceEntries = [];
        foreach ($this->config->referenceSheets as $refName) {
            if (!isset($sheetIdByName[$refName])) {
                Logger::warn("[skipper] Reference sheet \"{$refName}\" not found — skipping.");
                continue;
            }
            $result = $this->fetchSheet($service, $refName, $sheetIdByName[$refName]);
            array_push($referenceEntries, ...$result->entries);
        }

        // Merge: most restrictive (latest) disabledUntil wins
        /** @var array<string, TestEntry> $merged */
        $merged = [];
        foreach ([...$primary->entries, ...$referenceEntries] as $entry) {
            $key = TestIdHelper::normalize($entry->testId);
            $existing = $merged[$key] ?? null;

            if ($existing === null) {
                $merged[$key] = $entry;
            } elseif ($entry->disabledUntil !== null) {
                if ($existing->disabledUntil === null || $entry->disabledUntil > $existing->disabledUntil) {
                    $merged[$key] = $entry;
                }
            }
        }

        return new FetchAllResult($primary, array_values($merged), $service);
    }

    private function fetchSheet(SheetsService $service, string $sheetName, int $sheetId): SheetFetchResult
    {
        $spreadsheetId = $this->config->spreadsheetId;
        $testIdCol = $this->config->testIdColumn;
        $disabledUntilCol = $this->config->disabledUntilColumn;

        $response = $service->spreadsheets_values->get($spreadsheetId, $sheetName);
        /** @var string[][] $rawRows */
        $rawRows = $response->getValues() ?? [];

        if (empty($rawRows)) {
            return new SheetFetchResult($sheetName, $sheetId, [], [], []);
        }

        $header = array_map(fn ($h) => trim((string) $h), $rawRows[0]);
        $testIdIdx = array_search($testIdCol, $header, true);

        if ($testIdIdx === false) {
            throw new \RuntimeException(
                "[skipper] Column \"{$testIdCol}\" not found in sheet \"{$sheetName}\". "
                . 'Found: ' . implode(', ', $header)
            );
        }

        $disabledUntilIdx = array_search($disabledUntilCol, $header, true);
        $notesIdx = array_search('notes', $header, true);

        $entries = [];
        for ($i = 1, $count = count($rawRows); $i < $count; $i++) {
            $row = $rawRows[$i];
            $testId = trim((string) ($row[$testIdIdx] ?? ''));
            if ($testId === '') {
                continue;
            }

            $disabledUntil = null;
            if ($disabledUntilIdx !== false && isset($row[$disabledUntilIdx]) && $row[$disabledUntilIdx] !== '') {
                $raw = trim((string) $row[$disabledUntilIdx]);
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $raw)
                    ?: \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $raw)
                    ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $raw)
                    ?: false;

                if ($parsed !== false) {
                    $disabledUntil = $parsed;
                } else {
                    Logger::warn(
                        "[skipper] Row " . ($i + 1) . " in \"{$sheetName}\": "
                        . "invalid date \"{$raw}\" in \"{$disabledUntilCol}\" — treating as enabled"
                    );
                }
            }

            $notes = ($notesIdx !== false && isset($row[$notesIdx])) ? (string) $row[$notesIdx] : null;
            $entries[] = new TestEntry($testId, $disabledUntil, $notes);
        }

        return new SheetFetchResult($sheetName, $sheetId, $rawRows, $header, $entries);
    }
}
