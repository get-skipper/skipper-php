<?php

declare(strict_types=1);

namespace GetSkipper\Core\Client;

use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\ClientException;
use GetSkipper\Core\Config\ExcelConfig;
use GetSkipper\Core\Logger;
use GetSkipper\Core\Model\TestEntry;
use GetSkipper\Core\TestId\TestIdHelper;

final class ExcelClient
{
    private const GRAPH_BASE = 'https://graph.microsoft.com/v1.0';
    private const TOKEN_URL  = 'https://login.microsoftonline.com/%s/oauth2/v2.0/token';

    // Excel's epoch starts on Dec 30, 1899 (day 0).
    private const EXCEL_EPOCH_UNIX = -2209161600; // Unix timestamp of 1899-12-30

    public function __construct(
        private readonly ExcelConfig $config,
    ) {
    }

    private function fetchAccessToken(HttpClient $http): string
    {
        $creds = $this->config->credentials->resolve();
        $url   = sprintf(self::TOKEN_URL, urlencode($creds['tenantId']));

        $response = $http->post($url, [
            'form_params' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => $creds['clientId'],
                'client_secret' => $creds['clientSecret'],
                'scope'         => 'https://graph.microsoft.com/.default',
            ],
        ]);

        /** @var array{access_token: string} $data */
        $data = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        return $data['access_token'];
    }

    /**
     * Converts an Excel date serial number or ISO string to a DateTimeImmutable.
     * Graph API may return dates as numeric serials when the cell has a date format.
     */
    private function parseExcelDate(mixed $raw): ?DateTimeImmutable
    {
        if (is_int($raw) || is_float($raw)) {
            // Excel serial → Unix timestamp: (serial - 25569) * 86400
            // 25569 = days between 1899-12-30 and 1970-01-01
            $timestamp = (int) round(($raw - 25569) * 86400);

            return (new DateTimeImmutable())->setTimestamp($timestamp) ?: null;
        }

        $str = trim((string) $raw);
        if ($str === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $str)
            ?: DateTimeImmutable::createFromFormat(DATE_ATOM, $str)
            ?: DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $str)
            ?: false;

        return $parsed !== false ? $parsed : null;
    }

    /**
     * Fetches the primary worksheet and all reference worksheets in a single auth session.
     *
     * Uses Guzzle HTTP (available transitively via google/apiclient) for Microsoft Graph API calls.
     *
     * Deduplication: when the same testId appears in multiple sheets, the most
     * restrictive (latest) disabledUntil wins.
     */
    public function fetchAll(): ExcelFetchAllResult
    {
        $http        = new HttpClient();
        $accessToken = $this->fetchAccessToken($http);

        $workbookUrl = self::GRAPH_BASE . '/' . $this->config->workbookId . '/workbook';
        $headers     = ['Authorization' => "Bearer {$accessToken}"];

        // Fetch worksheet list to resolve names → IDs.
        $wsListResponse = $http->get("{$workbookUrl}/worksheets", ['headers' => $headers]);
        /** @var array{value: array<int, array{id: string, name: string}>} $wsListData */
        $wsListData    = json_decode((string) $wsListResponse->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        $worksheetList = $wsListData['value'] ?? [];

        /** @var array<string, string> $worksheetIdByName */
        $worksheetIdByName = [];
        foreach ($worksheetList as $ws) {
            $worksheetIdByName[$ws['name']] = $ws['id'];
        }

        $primaryName = $this->config->sheetName ?? ($worksheetList[0]['name'] ?? 'Sheet1');

        if (!isset($worksheetIdByName[$primaryName])) {
            throw new \RuntimeException("[skipper] Worksheet \"{$primaryName}\" not found in workbook.");
        }

        $primary = $this->fetchWorksheet($http, $accessToken, $workbookUrl, $primaryName, $worksheetIdByName[$primaryName]);

        $referenceEntries = [];
        foreach ($this->config->referenceSheets as $refName) {
            if (!isset($worksheetIdByName[$refName])) {
                Logger::warn("[skipper] Reference worksheet \"{$refName}\" not found — skipping.");
                continue;
            }
            $result = $this->fetchWorksheet($http, $accessToken, $workbookUrl, $refName, $worksheetIdByName[$refName]);
            array_push($referenceEntries, ...$result->entries);
        }

        // Merge: most restrictive (latest) disabledUntil wins
        /** @var array<string, TestEntry> $merged */
        $merged = [];
        foreach ([...$primary->entries, ...$referenceEntries] as $entry) {
            $key      = TestIdHelper::normalize($entry->testId);
            $existing = $merged[$key] ?? null;

            if ($existing === null) {
                $merged[$key] = $entry;
            } elseif ($entry->disabledUntil !== null) {
                if ($existing->disabledUntil === null || $entry->disabledUntil > $existing->disabledUntil) {
                    $merged[$key] = $entry;
                }
            }
        }

        return new ExcelFetchAllResult($primary, array_values($merged), $accessToken, $workbookUrl);
    }

    private function fetchWorksheet(
        HttpClient $http,
        string $accessToken,
        string $workbookUrl,
        string $worksheetName,
        string $worksheetId,
    ): WorksheetFetchResult {
        $testIdCol        = $this->config->testIdColumn;
        $disabledUntilCol = $this->config->disabledUntilColumn;
        $headers          = ['Authorization' => "Bearer {$accessToken}"];

        $url = "{$workbookUrl}/worksheets/" . urlencode($worksheetId) . '/usedRange';

        try {
            $response = $http->get($url, ['headers' => $headers]);
        } catch (ClientException $e) {
            // Graph returns 400 when usedRange is called on a completely empty worksheet.
            if ($e->getResponse()->getStatusCode() === 400) {
                return new WorksheetFetchResult($worksheetName, $worksheetId, [], [], []);
            }
            throw $e;
        }

        /** @var array{values?: array<int, array<int, string|int|float>>} $data */
        $data    = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        $rawRows = $data['values'] ?? [];

        if (empty($rawRows)) {
            return new WorksheetFetchResult($worksheetName, $worksheetId, [], [], []);
        }

        $header    = array_map(fn ($h) => trim((string) $h), $rawRows[0]);
        $testIdIdx = array_search($testIdCol, $header, true);

        if ($testIdIdx === false) {
            throw new \RuntimeException(
                "[skipper] Column \"{$testIdCol}\" not found in worksheet \"{$worksheetName}\". "
                . 'Found: ' . implode(', ', $header)
            );
        }

        $disabledUntilIdx = array_search($disabledUntilCol, $header, true);
        $notesIdx         = array_search('notes', $header, true);

        $entries = [];
        for ($i = 1, $count = count($rawRows); $i < $count; $i++) {
            $row    = $rawRows[$i];
            $testId = trim((string) ($row[$testIdIdx] ?? ''));
            if ($testId === '') {
                continue;
            }

            $disabledUntil = null;
            if ($disabledUntilIdx !== false && isset($row[$disabledUntilIdx]) && $row[$disabledUntilIdx] !== '') {
                $parsed = $this->parseExcelDate($row[$disabledUntilIdx]);
                if ($parsed !== null) {
                    $disabledUntil = $parsed;
                } else {
                    Logger::warn(
                        "[skipper] Row " . ($i + 1) . " in \"{$worksheetName}\": "
                        . "invalid date \"{$row[$disabledUntilIdx]}\" in \"{$disabledUntilCol}\" — treating as enabled"
                    );
                }
            }

            $notes    = ($notesIdx !== false && isset($row[$notesIdx])) ? (string) $row[$notesIdx] : null;
            $entries[] = new TestEntry($testId, $disabledUntil, $notes);
        }

        return new WorksheetFetchResult($worksheetName, $worksheetId, $rawRows, $header, $entries);
    }
}
