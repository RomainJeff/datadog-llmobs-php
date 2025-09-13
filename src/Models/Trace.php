<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Models;

use Datadog\LLMObservability\Contracts\SpanInterface;
use Datadog\LLMObservability\Contracts\TraceInterface;

final class Trace implements TraceInterface
{
    private array $spans = [];
    private bool $active = true;
    private ?string $rootSpanId = null;

    public function __construct(
        private readonly string $name,
        private readonly string $traceId,
        private readonly ?string $sessionId = null
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getRootSpanId(): ?string
    {
        return $this->rootSpanId;
    }

    public function getCurrentParentId(): ?string
    {
        if (empty($this->spans)) {
            return null;
        }

        $lastSpan = end($this->spans);
        return $lastSpan->getSpanId();
    }

    public function addSpan(SpanInterface $span): void
    {
        if (!$this->active) {
            throw new \RuntimeException('Cannot add span to inactive trace');
        }

        if ($this->rootSpanId === null && $span->getParentId() === null) {
            $this->rootSpanId = $span->getSpanId();
        }

        $this->spans[] = $span;
    }

    public function getSpans(): array
    {
        return $this->spans;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function end(): void
    {
        $this->active = false;
    }
}