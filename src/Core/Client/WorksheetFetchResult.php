<?php

declare(strict_types=1);

namespace GetSkipper\Core\Client;

use GetSkipper\Core\Model\TestEntry;

final class WorksheetFetchResult
{
    /**
     * @param array<int, array<int, string|int|float>> $rawRows  Raw rows including the header row (index 0)
     * @param string[]    $header   Parsed header cells (trimmed)
     * @param TestEntry[] $entries  Parsed test entries
     */
    public function __construct(
        /** Resolved worksheet tab name. */
        public readonly string $worksheetName,
        /** Graph API worksheet string ID (GUID). */
        public readonly string $worksheetId,
        public readonly array $rawRows,
        public readonly array $header,
        public readonly array $entries,
    ) {
    }
}
