<?php

declare(strict_types=1);

namespace GetSkipper\Core\Config;

use GetSkipper\Core\Credentials\CredentialsInterface;

final class SkipperConfig
{
    /**
     * @param string[]           $referenceSheets  Additional read-only sheet tab names
     */
    public function __construct(
        /** Google Spreadsheet ID (from the URL). */
        public readonly string $spreadsheetId,
        /**
         * Service account credentials. Three implementations accepted:
         * - FileCredentials('./service-account.json')   — path to JSON file (local dev)
         * - Base64Credentials(getenv('GOOGLE_CREDS'))   — base64-encoded JSON (CI)
         * - ServiceAccountCredentials(...)               — inline object
         */
        public readonly CredentialsInterface $credentials,
        /** Sheet tab name. Defaults to the first sheet. */
        public readonly ?string $sheetName = null,
        /**
         * Additional sheet tab names to read from (read-only).
         * When the same test ID appears in multiple sheets, the most
         * restrictive (latest) disabledUntil wins.
         */
        public readonly array $referenceSheets = [],
        /** Header name of the test ID column. Defaults to "testId". */
        public readonly string $testIdColumn = 'testId',
        /** Header name of the disabledUntil date column. Defaults to "disabledUntil". */
        public readonly string $disabledUntilColumn = 'disabledUntil',
    ) {
    }
}
