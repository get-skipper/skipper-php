<?php

declare(strict_types=1);

namespace GetSkipper\Core\Client;

use GetSkipper\Core\Model\TestEntry;
use Google\Service\Sheets as SheetsService;

final class FetchAllResult
{
    /**
     * @param TestEntry[] $entries  Merged entries from primary + all referenceSheets
     */
    public function __construct(
        /** Full data for the primary (writable) sheet — used by SheetsWriter. */
        public readonly SheetFetchResult $primary,
        public readonly array $entries,
        /**
         * Authenticated Sheets service — returned here so callers (SheetsWriter)
         * can reuse the same auth session for write operations without a second auth call.
         */
        public readonly SheetsService $service,
    ) {
    }
}
