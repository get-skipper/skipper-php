<?php

declare(strict_types=1);

namespace GetSkipper\Tests\Core\Resolver;

use GetSkipper\Core\Resolver\SkipperResolver;
use PHPUnit\Framework\TestCase;

final class SkipperResolverTest extends TestCase
{
    // --- fromArray / isTestEnabled ---

    public function testUnknownTestIsEnabledByDefault(): void
    {
        $resolver = SkipperResolver::fromArray([]);
        self::assertTrue($resolver->isTestEnabled('some/test.php > SomeTest > testSomething'));
    }

    public function testTestWithNullDisabledUntilIsEnabled(): void
    {
        $resolver = SkipperResolver::fromArray([
            'some/test.php > sometest > testsomething' => null,
        ]);
        self::assertTrue($resolver->isTestEnabled('some/test.php > SomeTest > testSomething'));
    }

    public function testTestWithPastDisabledUntilIsEnabled(): void
    {
        $pastDate = (new \DateTimeImmutable('-1 day'))->format(\DateTimeInterface::ATOM);
        $resolver = SkipperResolver::fromArray([
            'some/test.php > sometest > testsomething' => $pastDate,
        ]);
        self::assertTrue($resolver->isTestEnabled('some/test.php > SomeTest > testSomething'));
    }

    public function testTestWithFutureDisabledUntilIsDisabled(): void
    {
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $resolver = SkipperResolver::fromArray([
            'some/test.php > sometest > testsomething' => $futureDate,
        ]);
        self::assertFalse($resolver->isTestEnabled('some/test.php > SomeTest > testSomething'));
    }

    public function testIsTestEnabledIsCaseInsensitive(): void
    {
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $resolver = SkipperResolver::fromArray([
            'tests/auth/logintest.php > logintest > testitcanlogin' => $futureDate,
        ]);
        // Different casing should still match
        self::assertFalse($resolver->isTestEnabled('Tests/Auth/LoginTest.php > LoginTest > testItCanLogin'));
    }

    public function testIsTestEnabledNormalizesWhitespace(): void
    {
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $resolver = SkipperResolver::fromArray([
            'tests/auth/logintest.php > logintest > test it can login' => $futureDate,
        ]);
        // Extra spaces should still match after normalization
        self::assertFalse($resolver->isTestEnabled('tests/auth/LoginTest.php  >  LoginTest  >  test it can login'));
    }

    public function testGetDisabledUntilReturnsIsoString(): void
    {
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $resolver = SkipperResolver::fromArray([
            'some/test.php > sometest > testsomething' => $futureDate,
        ]);
        self::assertSame($futureDate, $resolver->getDisabledUntil('some/test.php > SomeTest > testSomething'));
    }

    public function testGetDisabledUntilReturnsNullForUnknownTest(): void
    {
        $resolver = SkipperResolver::fromArray([]);
        self::assertNull($resolver->getDisabledUntil('unknown/test.php > Test > test'));
    }

    // --- toArray / fromArray round-trip ---

    public function testToArrayFromArrayRoundTrip(): void
    {
        $futureDate = (new \DateTimeImmutable('+1 year'))->format(\DateTimeInterface::ATOM);
        $original = [
            'tests/unit/authtest.php > authtest > test can login' => $futureDate,
            'tests/unit/usertest.php > usertest > test can register' => null,
        ];

        $resolver = SkipperResolver::fromArray($original);
        self::assertSame($original, $resolver->toArray());
    }

    // --- getMode ---

    public function testGetModeReturnsReadOnlyByDefault(): void
    {
        $prev = getenv('SKIPPER_MODE');
        putenv('SKIPPER_MODE=');
        try {
            $resolver = SkipperResolver::fromArray([]);
            $mode = $resolver->getMode();
            self::assertSame(\GetSkipper\Core\SkipperMode::ReadOnly, $mode);
        } finally {
            putenv($prev !== false ? "SKIPPER_MODE={$prev}" : 'SKIPPER_MODE');
        }
    }
}
