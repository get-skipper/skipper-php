<?php

declare(strict_types=1);

namespace GetSkipper\Core\Writer;

use GuzzleHttp\Client as HttpClient;
use GetSkipper\Core\Client\ExcelClient;
use GetSkipper\Core\Config\ExcelConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\TestId\TestIdHelper;

final class ExcelWriter
{
    private readonly ExcelClient $client;

    public function __construct(
        private readonly ExcelConfig $config,
    ) {
        $this->client = new ExcelClient($config);
    }

    /**
     * Reconciles the workbook with the discovered test IDs:
     * - Appends rows for tests not yet in the primary worksheet (with empty disabledUntil)
     * - Deletes rows for tests that no longer exist in the suite
     *
     * Only the primary worksheet is modified. Reference worksheets are never written to.
     * Rows are matched by normalized testId (case-insensitive, whitespace-collapsed).
     * The header row (row 1) is never modified.
     *
     * Graph API has no batch row-delete endpoint, so deletions are issued sequentially
     * in descending row index order to avoid index shifting.
     *
     * @param string[] $discoveredIds
     */
    public function sync(array $discoveredIds): void
    {
        $fetchResult     = $this->client->fetchAll();
        $primary         = $fetchResult->primary;
        $existingEntries = $fetchResult->entries;
        $accessToken     = $fetchResult->accessToken;
        $workbookUrl     = $fetchResult->workbookUrl;

        $testIdCol        = $this->config->testIdColumn;
        $disabledUntilCol = $this->config->disabledUntilColumn;

        $normalizedDiscovered = [];
        foreach ($discoveredIds as $id) {
            $normalizedDiscovered[TestIdHelper::normalize($id)] = true;
        }

        $normalizedExisting = [];
        foreach ($existingEntries as $entry) {
            $normalizedExisting[TestIdHelper::normalize($entry->testId)] = $entry;
        }

        $toAdd = array_values(array_filter(
            $discoveredIds,
            fn ($id) => !isset($normalizedExisting[TestIdHelper::normalize($id)])
        ));

        $toRemoveNormalized = array_filter(
            array_keys($normalizedExisting),
            fn ($nid) => !isset($normalizedDiscovered[$nid])
        );

        if (empty($toAdd) && empty($toRemoveNormalized)) {
            Logger::log('[skipper] Workbook is already up to date.');
            return;
        }

        $rawRows     = $primary->rawRows;
        $header      = $primary->header;
        $worksheetId = $primary->worksheetId;

        $testIdIdx = array_search($testIdCol, $header, true);
        if ($testIdIdx === false) {
            throw new \RuntimeException(
                "[skipper] Column \"{$testIdCol}\" not found in primary worksheet. "
                . 'Found: ' . implode(', ', $header)
            );
        }

        $wsUrl   = $workbookUrl . '/worksheets/' . urlencode($worksheetId);
        $headers = [
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type'  => 'application/json',
        ];
        $http    = new HttpClient();

        // --- Deletions (descending row index to avoid index shifting) ---

        $rowIndicesToDelete = [];
        for ($i = 1, $count = count($rawRows); $i < $count; $i++) {
            $id = trim((string) ($rawRows[$i][$testIdIdx] ?? ''));
            if ($id !== '' && in_array(TestIdHelper::normalize($id), $toRemoveNormalized, true)) {
                $rowIndicesToDelete[] = $i;
            }
        }

        rsort($rowIndicesToDelete); // descending

        foreach ($rowIndicesToDelete as $rowIdx) {
            $http->delete("{$wsUrl}/rows(index={$rowIdx})", ['headers' => $headers]);
        }

        if (!empty($rowIndicesToDelete)) {
            Logger::log('[skipper] Removed ' . count($rowIndicesToDelete) . ' obsolete test(s) from workbook.');
        }

        // --- Append new rows ---

        if (!empty($toAdd)) {
            $disabledUntilIdx = array_search($disabledUntilCol, $header, true);
            $maxColIdx        = max((int) $testIdIdx, $disabledUntilIdx !== false ? (int) $disabledUntilIdx : 0);

            // Compute next row: after deletions, the used range is shorter.
            $nextRow = count($rawRows) - count($rowIndicesToDelete) + 1; // 1-based

            foreach ($toAdd as $testId) {
                $rowValues             = array_fill(0, $maxColIdx + 1, '');
                $rowValues[$testIdIdx] = $testId;

                $colStart    = $this->colIndexToA1(0);
                $colEnd      = $this->colIndexToA1($maxColIdx);
                $rangeAddress = "{$colStart}{$nextRow}:{$colEnd}{$nextRow}";

                $http->patch(
                    "{$wsUrl}/range(address='{$rangeAddress}')",
                    [
                        'headers' => $headers,
                        'body'    => json_encode(['values' => [$rowValues]], \JSON_THROW_ON_ERROR),
                    ]
                );

                $nextRow++;
            }

            Logger::log('[skipper] Added ' . count($toAdd) . ' new test(s) to workbook.');
        }
    }

    /**
     * Converts a 0-based column index to an A1-notation column letter (A, B, …, Z, AA, …).
     */
    private function colIndexToA1(int $index): string
    {
        $result = '';
        $n      = $index + 1;
        while ($n > 0) {
            $r      = ($n - 1) % 26;
            $result = chr(65 + $r) . $result;
            $n      = (int) (($n - 1) / 26);
        }
        return $result;
    }
}
