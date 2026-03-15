<?php

declare(strict_types=1);

namespace GetSkipper\Core\Cache;

final class CacheManager
{
    private const CACHE_FILENAME = 'cache.json';

    /**
     * Writes the resolver cache to a new temp directory and returns the directory path.
     * The caller should then set SKIPPER_CACHE_FILE and SKIPPER_DISCOVERED_DIR env vars.
     *
     * @param array<string, string|null> $data
     */
    public static function writeResolverCache(array $data): string
    {
        $dir = sys_get_temp_dir() . '/skipper-' . uniqid('', true);
        if (!mkdir($dir, 0o700, true) && !is_dir($dir)) {
            throw new \RuntimeException("[skipper] Could not create cache directory: {$dir}");
        }

        $cacheFile = $dir . '/' . self::CACHE_FILENAME;
        file_put_contents($cacheFile, json_encode($data, JSON_THROW_ON_ERROR));

        return $dir;
    }

    /**
     * Reads and decodes the resolver cache from the given file path.
     *
     * @return array<string, string|null>
     */
    public static function readResolverCache(string $cacheFile): array
    {
        if (!file_exists($cacheFile)) {
            throw new \RuntimeException("[skipper] Cache file not found: {$cacheFile}");
        }

        $raw = file_get_contents($cacheFile);
        if ($raw === false) {
            throw new \RuntimeException("[skipper] Could not read cache file: {$cacheFile}");
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Writes a worker's discovered test IDs to the shared discovered directory.
     * Each worker writes its own file (PID + microtime stamped) to avoid conflicts.
     *
     * @param string[] $ids
     */
    public static function writeDiscoveredIds(string $discoveredDir, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $suffix = getmypid() . '-' . microtime(true) . '-' . bin2hex(random_bytes(4));
        $file = $discoveredDir . '/' . $suffix . '.json';
        file_put_contents($file, json_encode($ids, JSON_THROW_ON_ERROR));
    }

    /**
     * Merges all worker-written discovered ID files from the given directory.
     * Skips the main cache.json file.
     *
     * @return string[]
     */
    public static function mergeDiscoveredIds(string $discoveredDir): array
    {
        if (!is_dir($discoveredDir)) {
            return [];
        }

        $all = [];
        $files = glob($discoveredDir . '/*.json');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            if (basename($file) === self::CACHE_FILENAME) {
                continue;
            }

            $raw = file_get_contents($file);
            if ($raw === false) {
                continue;
            }

            $ids = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($ids)) {
                array_push($all, ...$ids);
            }
        }

        return array_values(array_unique($all));
    }

    /**
     * Removes the cache directory and all its contents.
     */
    public static function cleanup(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }

        rmdir($dir);
    }
}
