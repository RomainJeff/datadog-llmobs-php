<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Utils;

final class TimeHelper
{
    public static function currentTimeNs(): int
    {
        return (int)(microtime(true) * 1_000_000_000);
    }

    public static function durationFromStartNs(int $startNs): float
    {
        return (float)(self::currentTimeNs() - $startNs);
    }
}