<?php

declare(strict_types=1);

namespace GetSkipper\Core\Client;

use GetSkipper\Core\Model\TestEntry;

final class ExcelFetchAllResult
{
    /**
     * @param TestEntry[] $entries  Merged entries from primary + all referenceSheets
     */
    public function __construct(
        /** Full data for the primary (writable) worksheet — used by ExcelWriter. */
        public readonly WorksheetFetchResult $primary,
        public readonly array $entries,
        /** Bearer token reused by ExcelWriter for write operations. */
        public readonly string $accessToken,
        /** Workbook base URL, e.g. https://graph.microsoft.com/v1.0/drives/{id}/items/{id}/workbook */
        public readonly string $workbookUrl,
    ) {
    }
}
