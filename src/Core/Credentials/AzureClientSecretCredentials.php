<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

/** Inline Azure AD application credentials. */
final class AzureClientSecretCredentials implements ExcelCredentialsInterface
{
    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function resolve(): array
    {
        return [
            'tenantId'     => $this->tenantId,
            'clientId'     => $this->clientId,
            'clientSecret' => $this->clientSecret,
        ];
    }
}
