<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

final class FileCredentials implements CredentialsInterface
{
    public function __construct(
        /** Absolute or project-relative path to the service account JSON file. */
        public readonly string $credentialsFile,
    ) {
    }

    public function resolve(): array
    {
        if (!file_exists($this->credentialsFile)) {
            throw new \RuntimeException(
                "[skipper] Credentials file not found: {$this->credentialsFile}"
            );
        }

        $raw = file_get_contents($this->credentialsFile);
        if ($raw === false) {
            throw new \RuntimeException(
                "[skipper] Could not read credentials file: {$this->credentialsFile}"
            );
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
}
