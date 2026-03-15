<?php

declare(strict_types=1);

namespace GetSkipper\Core\TestId;

final class TestIdHelper
{
    /**
     * Normalizes a testId for consistent comparison:
     * - trim leading/trailing whitespace
     * - lowercase
     * - collapse multiple whitespace characters into a single space
     */
    public static function normalize(string $id): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($id)) ?? trim($id));
    }

    /**
     * Builds a canonical testId from a file path and the test title path.
     *
     * Format: "{relativePath} > {titlePath[0]} > {titlePath[1]} > ..."
     * Example: "tests/auth/login.php > LoginTest > it can login with valid credentials"
     *
     * The filePath is made relative to CWD if it is absolute.
     * The titlePath is the array of describe block names + the test name,
     * as provided by the test framework (never pre-joined).
     *
     * @param string[] $titlePath
     */
    public static function build(string $filePath, array $titlePath): string
    {
        // Make relative to CWD if absolute
        if (str_starts_with($filePath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Z]:/i', $filePath)) {
            $cwd = (string) getcwd();
            if (str_starts_with($filePath, $cwd)) {
                $filePath = ltrim(substr($filePath, strlen($cwd)), DIRECTORY_SEPARATOR);
            }
        }

        // Normalize to forward slashes
        $filePath = str_replace(DIRECTORY_SEPARATOR, '/', $filePath);

        return implode(' > ', [$filePath, ...$titlePath]);
    }
}
