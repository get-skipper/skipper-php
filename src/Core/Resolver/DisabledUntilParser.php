<?php

declare(strict_types=1);

namespace GetSkipper\Core\Resolver;

/**
 * Parses and evaluates disabledUntil date strings from spreadsheet rows.
 *
 * Contract:
 * - Only YYYY-MM-DD is accepted. Anything else throws \InvalidArgumentException.
 * - Dates are pinned to UTC. "Disabled until 2026-04-01" means the test is
 *   re-enabled at 2026-04-02 00:00:00 UTC (i.e. through end of that calendar day).
 * - Null or empty string → not disabled.
 */
final class DisabledUntilParser
{
    private const DATE_RE = '/^\d{4}-\d{2}-\d{2}$/';

    public static function parse(?string $raw, int $rowNum): ?\DateTimeImmutable
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $trimmed = trim($raw);

        if (!preg_match(self::DATE_RE, $trimmed)) {
            throw new \InvalidArgumentException(
                "[skipper] Row {$rowNum}: invalid disabledUntil \"{$raw}\". Use YYYY-MM-DD."
            );
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $trimmed, new \DateTimeZone('UTC'))
            ->setTime(0, 0, 0);

        // +1 day: "disabled until YYYY-MM-DD" means through the end of that calendar day UTC.
        // Comparison: now < $until  →  test is still disabled.
        return $date->modify('+1 day');
    }

    public static function isDisabled(?\DateTimeImmutable $until): bool
    {
        if ($until === null) {
            return false;
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $now < $until;
    }
}
