<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Contracts;

interface SpanInterface
{
    public function getName(): string;

    public function getSpanId(): string;

    public function getTraceId(): string;

    public function getParentId(): ?string;

    public function getStartNs(): int;

    public function getDuration(): float;

    public function getMeta(): array;

    public function getStatus(): string;

    public function getMetrics(): ?array;

    public function getTags(): array;

    public function setEndTime(int $endNs): void;

    public function setStatus(string $status): void;

    public function setError(string $message, ?string $stack = null, ?string $type = null): void;

    public function toArray(): array;
}