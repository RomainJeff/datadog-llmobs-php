<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Utils;

use Ramsey\Uuid\Uuid;

final class IdGenerator
{
    public static function generateTraceId(): string
    {
        return str_replace('-', '', Uuid::uuid4()->toString());
    }

    public static function generateSpanId(): string
    {
        return str_replace('-', '', Uuid::uuid4()->toString());
    }
}