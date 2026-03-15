<?php

declare(strict_types=1);

namespace GetSkipper\Core\Config;

use GetSkipper\Core\Credentials\ExcelCredentialsInterface;

final class ExcelConfig
{
    /**
     * @param string[] $referenceSheets Additional read-only worksheet tab names
     */
    public function __construct(
        /**
         * OneDrive / SharePoint workbook identifier. Use the full drive-relative path:
         *   "drives/{driveId}/items/{itemId}"
         * Obtain via Graph Explorer: GET /v1.0/drives/{driveId}/root/children
         */
        public readonly string $workbookId,
        /**
         * Azure AD application credentials. Three implementations accepted:
         * - AzureClientSecretCredentials(tenantId, clientId, clientSecret) — inline
         * - AzureFileCredentials('./azure-creds.json')  — path to JSON file (local dev)
         * - AzureBase64Credentials(getenv('AZURE_CREDS_B64')) — base64-encoded JSON (CI)
         */
        public readonly ExcelCredentialsInterface $credentials,
        /** Worksheet tab name. Defaults to the first sheet. */
        public readonly ?string $sheetName = null,
        /**
         * Additional worksheet tab names to read from (read-only).
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
