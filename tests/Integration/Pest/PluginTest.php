<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Integration\Pest;

use GetSkipper\Core\TestId\TestIdHelper;
use GetSkipper\Pest\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginTest extends TestCase
{
    public function testBuildPestTestIdSimpleTestName(): void
    {
        $id = Plugin::buildPestTestId('tests/Feature/auth.php', 'can login');

        $expected = TestIdHelper::build('tests/Feature/auth.php', ['can login']);
        self::assertSame($expected, $id);
    }

    public function testBuildPestTestIdWithDescribeBlock(): void
    {
        $id = Plugin::buildPestTestId('tests/Feature/auth.php', 'Auth > can login');

        $expected = TestIdHelper::build('tests/Feature/auth.php', ['Auth', 'can login']);
        self::assertSame($expected, $id);
    }

    public function testBuildPestTestIdWithNestedDescribeBlocks(): void
    {
        $id = Plugin::buildPestTestId('tests/Feature/auth.php', 'Auth > Login > can login with valid credentials');

        $expected = TestIdHelper::build('tests/Feature/auth.php', ['Auth', 'Login', 'can login with valid credentials']);
        self::assertSame($expected, $id);
    }

    public function testBuildPestTestIdFormatMatchesTestIdHelperBuild(): void
    {
        $file = 'tests/Unit/user.php';
        $method = 'User > Profile > can update email';

        $fromPlugin = Plugin::buildPestTestId($file, $method);
        $manual = TestIdHelper::build($file, ['User', 'Profile', 'can update email']);

        self::assertSame($manual, $fromPlugin);
    }
}
