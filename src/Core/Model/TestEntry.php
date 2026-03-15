<?php

declare(strict_types=1);

namespace GetSkipper\Core\Model;

final class TestEntry
{
    public function __construct(
        public readonly string $testId,
        /** null = no date set = test is enabled */
        public readonly ?\DateTimeImmutable $disabledUntil,
        public readonly ?string $notes = null,
    ) {
    }
}
