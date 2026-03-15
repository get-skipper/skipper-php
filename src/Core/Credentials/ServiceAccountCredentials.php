<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

final class ServiceAccountCredentials implements CredentialsInterface
{
    public function __construct(
        public readonly string $type,
        public readonly string $projectId,
        public readonly string $privateKeyId,
        public readonly string $privateKey,
        public readonly string $clientEmail,
        public readonly string $clientId,
        public readonly string $authUri,
        public readonly string $tokenUri,
    ) {
    }

    /**
     * Creates an instance from a raw service account JSON array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: (string) ($data['type'] ?? 'service_account'),
            projectId: (string) ($data['project_id'] ?? ''),
            privateKeyId: (string) ($data['private_key_id'] ?? ''),
            privateKey: (string) ($data['private_key'] ?? ''),
            clientEmail: (string) ($data['client_email'] ?? ''),
            clientId: (string) ($data['client_id'] ?? ''),
            authUri: (string) ($data['auth_uri'] ?? ''),
            tokenUri: (string) ($data['token_uri'] ?? ''),
        );
    }

    public function resolve(): array
    {
        return [
            'type' => $this->type,
            'project_id' => $this->projectId,
            'private_key_id' => $this->privateKeyId,
            'private_key' => $this->privateKey,
            'client_email' => $this->clientEmail,
            'client_id' => $this->clientId,
            'auth_uri' => $this->authUri,
            'token_uri' => $this->tokenUri,
        ];
    }
}
