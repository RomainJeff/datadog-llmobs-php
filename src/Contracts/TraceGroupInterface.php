<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Contracts;

interface TraceGroupInterface
{
    public function getName(): string;

    public function getTraceId(): string;

    public function getSessionId(): ?string;

    public function getRootSpanId(): ?string;

    public function getCurrentParentId(): ?string;

    public function addSpan(SpanInterface $span): void;

    public function getSpans(): array;

    public function isActive(): bool;

    public function end(): void;
}