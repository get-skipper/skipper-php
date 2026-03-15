<?php

declare(strict_types=1);

namespace GetSkipper\Core\Client;

use GetSkipper\Core\Model\TestEntry;

final class SheetFetchResult
{
    /**
     * @param string[][]  $rawRows  Raw rows including the header row (index 0)
     * @param string[]    $header   Parsed header cells (trimmed)
     * @param TestEntry[] $entries  Parsed test entries
     */
    public function __construct(
        /** Resolved sheet tab name. */
        public readonly string $sheetName,
        /** Numeric sheet ID (used for batchUpdate deletions). */
        public readonly int $sheetId,
        public readonly array $rawRows,
        public readonly array $header,
        public readonly array $entries,
    ) {
    }
}
