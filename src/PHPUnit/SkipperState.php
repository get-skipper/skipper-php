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
     *
     * For Pest-generated tests (class name starts with "P\"):
     *   "tests/Feature/auth.php > can login"  (class name stripped)
     */
    public function buildTestId(Test $test): string
    {
        if (!($test instanceof TestMethod)) {
            return $test->id();
        }

        $file = $test->file();
        $className = $test->className();
        $methodName = $test->methodName();

        // Detect Pest-generated class: Pest uses a "P\" namespace prefix
        if ($this->isPestTest($className)) {
            return TestIdHelper::build($file, [$methodName]);
        }

        // Class-based PHPUnit: use short class name + method name
        $shortClass = substr($className, (int) strrpos($className, '\\') + 1);
        return TestIdHelper::build($file, [$shortClass, $methodName]);
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
