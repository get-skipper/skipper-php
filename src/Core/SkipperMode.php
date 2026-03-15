<?php

declare(strict_types=1);

namespace GetSkipper\Core;

enum SkipperMode: string
{
    case ReadOnly = 'read-only';
    case Sync = 'sync';

    public static function fromEnv(): self
    {
        return self::tryFrom((string) getenv('SKIPPER_MODE')) ?? self::ReadOnly;
    }
}
