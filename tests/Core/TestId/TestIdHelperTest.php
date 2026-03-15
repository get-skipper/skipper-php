<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\TestId;

use GetSkipper\Core\TestId\TestIdHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestIdHelperTest extends TestCase
{
    // --- normalize ---

    public function testNormalizeTrimWhitespace(): void
    {
        self::assertSame('hello world', TestIdHelper::normalize('  hello world  '));
    }

    public function testNormalizeLowercase(): void
    {
        self::assertSame('auth > login', TestIdHelper::normalize('Auth > Login'));
    }

    public function testNormalizeCollapsesMultipleSpaces(): void
    {
        self::assertSame('auth > login', TestIdHelper::normalize('auth  >  login'));
    }

    public function testNormalizeCollapsesTabs(): void
    {
        self::assertSame('auth > login', TestIdHelper::normalize("auth\t>\tlogin"));
    }

    public function testNormalizeEmptyString(): void
    {
        self::assertSame('', TestIdHelper::normalize(''));
    }

    // --- build ---

    public function testBuildJoinsFilePathAndTitlePath(): void
    {
        $id = TestIdHelper::build('tests/Unit/AuthTest.php', ['AuthTest', 'testItCanLogin']);
        self::assertSame('tests/Unit/AuthTest.php > AuthTest > testItCanLogin', $id);
    }

    public function testBuildSingleTitlePathElement(): void
    {
        $id = TestIdHelper::build('tests/Feature/auth.php', ['can login']);
        self::assertSame('tests/Feature/auth.php > can login', $id);
    }

    public function testBuildNormalizesDirectorySeparator(): void
    {
        $id = TestIdHelper::build('tests' . DIRECTORY_SEPARATOR . 'Unit' . DIRECTORY_SEPARATOR . 'AuthTest.php', ['AuthTest', 'test']);
        self::assertSame('tests/Unit/AuthTest.php > AuthTest > test', $id);
    }

    public function testBuildMakesAbsolutePathRelative(): void
    {
        $cwd = (string) getcwd();
        $absolute = $cwd . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Unit' . DIRECTORY_SEPARATOR . 'AuthTest.php';

        $id = TestIdHelper::build($absolute, ['AuthTest', 'test']);
        self::assertSame('tests/Unit/AuthTest.php > AuthTest > test', $id);
    }

    public function testBuildEmptyTitlePath(): void
    {
        $id = TestIdHelper::build('tests/Unit/AuthTest.php', []);
        self::assertSame('tests/Unit/AuthTest.php', $id);
    }

    // --- normalize consistency ---

    #[DataProvider('provideEquivalentIds')]
    public function testNormalizeProducesConsistentIds(string $idA, string $idB): void
    {
        self::assertSame(TestIdHelper::normalize($idA), TestIdHelper::normalize($idB));
    }

    public static function provideEquivalentIds(): array
    {
        return [
            'case insensitive' => [
                'Tests/Auth/LoginTest.php > LoginTest > testItCanLogin',
                'tests/auth/logintest.php > logintest > testitcanlogin',
            ],
            'extra spaces' => [
                'tests/Auth/LoginTest.php > LoginTest > testItCanLogin',
                'tests/Auth/LoginTest.php  >  LoginTest  >  testItCanLogin',
            ],
        ];
    }
}
