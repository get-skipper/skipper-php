<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

final class Base64Credentials implements CredentialsInterface
{
    public function __construct(
        /** Base64-encoded service account JSON string. */
        public readonly string $credentialsBase64,
    ) {
    }

    public function resolve(): array
    {
        $decoded = base64_decode($this->credentialsBase64, strict: true);
        if ($decoded === false) {
            throw new \RuntimeException('[skipper] Failed to base64-decode credentials.');
        }

        return json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
    }
}
