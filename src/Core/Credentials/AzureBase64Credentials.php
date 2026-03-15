<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

/** Azure AD application credentials from a base64-encoded JSON string (recommended for CI). */
final class AzureBase64Credentials implements ExcelCredentialsInterface
{
    public function __construct(
        private readonly string $base64,
    ) {
    }

    public function resolve(): array
    {
        $raw = base64_decode($this->base64, strict: true);
        if ($raw === false) {
            throw new \RuntimeException('[skipper] Invalid base64 string for Azure credentials.');
        }

        /** @var array{tenantId: string, clientId: string, clientSecret: string} $data */
        $data = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
