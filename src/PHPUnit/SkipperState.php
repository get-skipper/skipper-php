<?php

declare(strict_types=1);

namespace GetSkipper\PHPUnit;

use GetSkipper\Core\Cache\CacheManager;
use GetSkipper\Core\Resolver\SkipperResolver;
use GetSkipper\Core\TestId\TestIdHelper;
use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;

/**
 * Shared state object passed to all PHPUnit event subscribers.
 */
final class SkipperState
{
    /** @var string[] */
    private array $discoveredIds = [];

    public function __construct(
        private readonly SkipperResolver $resolver,
        private readonly ?string $discoveredDir,
    ) {
    }

    public function getResolver(): SkipperResolver
    {
        return $this->resolver;
    }

    /**
     * Builds a test ID from a PHPUnit event Test object.
     *
     * For class-based PHPUnit tests:
     *   "tests/Unit/AuthTest.php > AuthTest > testItCanLogin"
     *   "tests/Unit/AuthTest.php > AuthTest > testWithDataProvider with data set "valid""
     *
     * For Pest-generated tests (class name starts with "P\"):
     *   "tests/Feature/auth.php > can login"
     *   "tests/Feature/auth.php > Auth > can login"  (describe() block)
     */
    public function buildTestId(Test $test): string
    {
        if (!($test instanceof TestMethod)) {
            return $test->id();
        }

        $file = $test->file();
        $className = $test->className();
        $methodName = $test->methodName();
        // name() = methodName plus ' with data set "..."' for data-provider tests
        $dataSet = substr($test->name(), \strlen($methodName));

        if ($this->isPestTest($className)) {
            $parts = self::pestTitlePath($test);
            $parts[\count($parts) - 1] .= $dataSet;

            return TestIdHelper::build($file, $parts);
        }

        // Class-based PHPUnit: use short class name + method name
        $shortClass = substr($className, (int) strrpos($className, '\\') + 1);

        return TestIdHelper::build($file, [$shortClass, $methodName . $dataSet]);
    }

    /**
     * Pest encodes the description into the method name (lossy), but also emits it verbatim
     * as a method-level "@testdox". describe() blocks appear as "`Auth` → can login".
     *
     * @return non-empty-list<string>
     */
    private static function pestTitlePath(TestMethod $test): array
    {
        $testDox = $test->metadata()->isTestDox()->isMethodLevel()->asArray();
        if ($testDox !== [] && $testDox[0] instanceof \PHPUnit\Metadata\TestDox) {
            return array_map(
                static fn (string $part): string => trim($part, '`'),
                explode(' → ', $testDox[0]->text()),
            );
        }

        // Fallback: decode Str::evaluable() ("__pest_evaluable_can_login" → "can login")
        $name = (string) preg_replace('/^__pest_evaluable_/', '', $test->methodName());

        return [str_replace("\0", '_', str_replace('_', ' ', str_replace('__', "\0", $name)))];
    }

    public function addDiscoveredId(string $id): void
    {
        $this->discoveredIds[] = $id;
    }

    /**
     * Flushes collected discovered IDs to the shared discovered directory.
     */
    public function flushDiscoveredIds(): void
    {
        if ($this->discoveredDir !== null && !empty($this->discoveredIds)) {
            CacheManager::writeDiscoveredIds($this->discoveredDir, $this->discoveredIds);
        }
    }

    private function isPestTest(string $className): bool
    {
        return str_starts_with($className, 'P\\');
    }
}
