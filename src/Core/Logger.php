<?php

declare(strict_types=1);

namespace GetSkipper\Core;

final class Logger
{
    public static function log(string $message): void
    {
        if (getenv('SKIPPER_DEBUG')) {
            fwrite(STDOUT, $message . PHP_EOL);
        }
    }

    public static function warn(string $message): void
    {
        if (getenv('SKIPPER_DEBUG')) {
            fwrite(STDERR, $message . PHP_EOL);
        }
    }
}
