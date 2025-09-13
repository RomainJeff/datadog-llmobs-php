<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Contracts;

interface HttpClientInterface
{
    public function sendSpans(array $payload): bool;
}