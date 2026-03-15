<?php

declare(strict_types=1);

namespace GetSkipper\Core\Credentials;

interface ExcelCredentialsInterface
{
    /**
     * Returns the Azure AD application credentials as an associative array.
     *
     * @return array{tenantId: string, clientId: string, clientSecret: string}
     */
    public function resolve(): array;
}
