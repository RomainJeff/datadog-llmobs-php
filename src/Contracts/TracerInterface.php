<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Contracts;

interface TracerInterface
{
    public function createTrace(string $name, array $input = [], ?string $sessionId = null): string;

    public function addSpan(
        string $name,
        string $kind,
        array $input = [],
        array $output = [],
        array $metadata = [],
        ?array $metrics = null,
        ?string $parentId = null
    ): string;

    public function startSpan(
        string $name,
        string $kind,
        array $input = [],
        array $metadata = [],
        ?string $parentId = null
    ): string;

    public function endSpan(
        string $spanId,
        array $output = [],
        ?array $metrics = null
    ): void;

    public function endSpanWithError(
        string $spanId,
        string $errorMessage,
        ?string $errorType = null,
        ?string $stack = null,
        ?array $metrics = null
    ): void;

    public function endTrace(array $output = []): void;

    public function flush(): bool;

    public function setGlobalTags(array $tags): void;

    public function setMlApp(string $mlApp): void;

    public function setSessionId(string $sessionId): void;
}