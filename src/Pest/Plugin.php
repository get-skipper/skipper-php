<?php

declare(strict_types=1);

namespace GetSkipper\Pest;

use GetSkipper\Core\TestId\TestIdHelper;

/**
 * Pest integration for Skipper test-gating.
 *
 * Since Pest v2/v3 runs on top of PHPUnit, the GetSkipper\PHPUnit\SkipperExtension
 * works for Pest tests directly — register it in phpunit.xml as usual.
 *
 * This class only provides a Pest-aware test ID builder.
 *
 * --- Pest test ID format ---
 * tests/Feature/auth.php > can login
 * tests/Feature/auth.php > Auth > can login      (with describe() block)
 *
 * --- Recommended setup ---
 * Register GetSkipper\PHPUnit\SkipperExtension in phpunit.xml — it auto-detects
 * Pest-generated classes and formats IDs correctly.
 */
final class Plugin
{
    /**
     * Builds a test ID for a Pest test.
     *
     * In Pest, describe blocks are reflected in the test name as "> DescribeName > testName",
     * or the test name may already include the describe block prefix depending on the version.
     */
    public static function buildPestTestId(string $filePath, string $methodName): string
    {
        // Pest may encode the describe block as "DescribeName > test name" in the method name
        // We pass it as-is since TestIdHelper::build() will join with the file path
        $parts = explode(' > ', $methodName);
        return TestIdHelper::build($filePath, $parts);
    }
}
