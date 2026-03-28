<?php

declare(strict_types=1);

namespace GetSkipper\Core\Writer;

use GetSkipper\Core\Client\SheetsClient;
use GetSkipper\Core\Config\SkipperConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\TestId\TestIdHelper;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\DeleteDimensionRequest;
use Google\Service\Sheets\DimensionRange;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\ValueRange;

final class SheetsWriter
{
    private readonly SheetsClient $client;

    public function __construct(
        private readonly SkipperConfig $config,
    ) {
        $this->client = new SheetsClient($config);
    }

    /**
     * Reconciles the spreadsheet with the discovered test IDs:
     * - Appends rows for tests not yet in the primary sheet (with empty disabledUntil)
     * - Deletes rows for tests that no longer exist in the suite
     *
     * Only the primary sheet is modified. Reference sheets are never written to.
     * Rows are matched by normalized testId (case-insensitive, whitespace-collapsed).
     * The header row (row 1) is never modified.
     *
     * @param string[] $discoveredIds
     */
    public function sync(array $discoveredIds): void
    {
        $fetchResult = $this->client->fetchAll();
        $primary = $fetchResult->primary;
        $existingEntries = $fetchResult->entries;
        $service = $fetchResult->service;

        $spreadsheetId = $this->config->spreadsheetId;
        $testIdCol = $this->config->testIdColumn;
        $disabledUntilCol = $this->config->disabledUntilColumn;

        $normalizedDiscovered = [];
        foreach ($discoveredIds as $id) {
            $normalizedDiscovered[TestIdHelper::normalize($id)] = true;
        }

        $normalizedExisting = [];
        foreach ($existingEntries as $entry) {
            $normalizedExisting[TestIdHelper::normalize($entry->testId)] = $entry;
        }

        $toAdd = array_filter(
            $discoveredIds,
            fn ($id) => !isset($normalizedExisting[TestIdHelper::normalize($id)])
        );

        $toRemoveNormalized = array_filter(
            array_keys($normalizedExisting),
            fn ($nid) => !isset($normalizedDiscovered[$nid])
        );

        $allowDeletes = getenv('SKIPPER_SYNC_ALLOW_DELETE') === 'true';

        if (!$allowDeletes && !empty($toRemoveNormalized)) {
            Logger::log('[skipper] ' . count($toRemoveNormalized) . ' orphaned row(s) found. Set SKIPPER_SYNC_ALLOW_DELETE=true to prune them.');
            $toRemoveNormalized = [];
        }

        if (empty($toAdd) && empty($toRemoveNormalized)) {
            Logger::log('[skipper] Spreadsheet is already up to date.');
            return;
        }

        $rawRows = $primary->rawRows;
        $header = $primary->header;
        $sheetId = $primary->sheetId;
        $sheetName = $primary->sheetName;

        // If the sheet has no rows, auto-initialize the header row so that
        // values.append has a table to anchor to (otherwise it writes at A1).
        if (empty($rawRows)) {
            $headerValues = new ValueRange();
            $headerValues->setValues([[$testIdCol, $disabledUntilCol, 'notes']]);
            $service->spreadsheets_values->update(
                $spreadsheetId,
                "{$sheetName}!A1",
                $headerValues,
                ['valueInputOption' => 'RAW']
            );
            Logger::log('[skipper] Initialized empty sheet with header row.');
            $testIdIdx = 0;
            $disabledUntilIdx = 1;
        } else {
            $testIdIdx = array_search($testIdCol, $header, true);
            if ($testIdIdx === false) {
                throw new \RuntimeException(
                    "[skipper] Column \"{$testIdCol}\" not found in primary sheet. "
                    . 'Found: ' . implode(', ', $header)
                );
            }

            // Identify 0-based row indices (within rawRows) to delete, skipping header at 0.
            $rowIndicesToDelete = [];
            for ($i = 1, $count = count($rawRows); $i < $count; $i++) {
                $id = trim((string) ($rawRows[$i][$testIdIdx] ?? ''));
                if ($id !== '' && in_array(TestIdHelper::normalize($id), $toRemoveNormalized, true)) {
                    $rowIndicesToDelete[] = $i;
                }
            }

            // Deletions must be sorted descending to avoid index shifting.
            rsort($rowIndicesToDelete);

            if (!empty($rowIndicesToDelete)) {
                $deleteRequests = array_map(function (int $rowIdx) use ($sheetId): SheetsRequest {
                    $dimRange = new DimensionRange();
                    $dimRange->setSheetId($sheetId);
                    $dimRange->setDimension('ROWS');
                    $dimRange->setStartIndex($rowIdx);
                    $dimRange->setEndIndex($rowIdx + 1);

                    $deleteReq = new DeleteDimensionRequest();
                    $deleteReq->setRange($dimRange);

                    $req = new SheetsRequest();
                    $req->setDeleteDimension($deleteReq);
                    return $req;
                }, $rowIndicesToDelete);

                $batchRequest = new BatchUpdateSpreadsheetRequest();
                $batchRequest->setRequests($deleteRequests);
                $service->spreadsheets->batchUpdate($spreadsheetId, $batchRequest);

                Logger::log('[skipper] Removed ' . count($rowIndicesToDelete) . ' obsolete test(s) from spreadsheet.');
            }

            $disabledUntilIdx = array_search($disabledUntilCol, $header, true);
        }

        // Append new rows.
        $toAddValues = array_values($toAdd);
        if (!empty($toAddValues)) {
            $maxIdx = max(
                (int) $testIdIdx,
                $disabledUntilIdx !== false ? (int) $disabledUntilIdx : 0
            );

            $newRows = array_map(function (string $testId) use ($testIdIdx, $disabledUntilIdx, $maxIdx): array {
                $row = array_fill(0, $maxIdx + 1, '');
                $row[$testIdIdx] = $testId;
                if ($disabledUntilIdx !== false) {
                    $row[$disabledUntilIdx] = '';
                }
                return $row;
            }, $toAddValues);

            $valueRange = new ValueRange();
            $valueRange->setValues($newRows);

            $service->spreadsheets_values->append(
                $spreadsheetId,
                $sheetName,
                $valueRange,
                ['valueInputOption' => 'RAW']
            );

            Logger::log('[skipper] Added ' . count($toAddValues) . ' new test(s) to spreadsheet.');
        }
    }
}
