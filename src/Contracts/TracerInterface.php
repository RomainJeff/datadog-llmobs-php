<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Contracts;

interface TracerInterface
{
    public function createTrace(string $name, ?string $sessionId = null): string;

    public function addSpan(
        string $name,
        string $kind,
        array $input = [],
        array $output = [],
        array $metadata = [],
        ?array $metrics = null,
        ?string $parentId = null
    ): string;

    public function endTrace(): void;

    public function flush(): bool;

    public function setGlobalTags(array $tags): void;

    public function setMlApp(string $mlApp): void;

    public function setSessionId(string $sessionId): void;
}