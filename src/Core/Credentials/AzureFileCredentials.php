<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

/** Azure AD application credentials loaded from a local JSON file. */
final class AzureFileCredentials implements ExcelCredentialsInterface
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function resolve(): array
    {
        $raw = file_get_contents($this->filePath);
        if ($raw === false) {
            throw new \RuntimeException("[skipper] Cannot read Azure credentials file: {$this->filePath}");
        }

        /** @var array{tenantId: string, clientId: string, clientSecret: string} $data */
        $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
