<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

interface CredentialsInterface
{
    /**
     * Returns the parsed service account JSON as an associative array.
     *
     * @return array<string, mixed>
     */
    public function resolve(): array;
}
