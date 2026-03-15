<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Example;

use PHPUnit\Framework\TestCase;

final class SkipperExampleTest extends TestCase
{
    public function testShouldBeSkipped(): void
    {
        self::assertTrue(true);
    }
}
