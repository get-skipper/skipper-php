<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Codeception\Fixtures;

use PHPUnit\Framework\TestCase;

/** Fixture only: wrapped by Codeception's TestCaseWrapper in SkipperExtensionTest. */
final class AuthFixtureTest extends TestCase
{
    public function testItCanLogin(): void
    {
        self::assertTrue(true);
    }
}
