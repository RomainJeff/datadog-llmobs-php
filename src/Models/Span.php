<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Models;

use Datadog\LLMObservability\Contracts\SpanInterface;

final class Span implements SpanInterface
{
    private int $endNs;
    private string $status = 'ok';
    private ?array $error = null;

    public function __construct(
        private readonly string $name,
        private readonly string $spanId,
        private readonly string $traceId,
        private readonly ?string $parentId,
        private readonly int $startNs,
        private readonly array $meta,
        private readonly ?array $metrics = null,
        private readonly array $tags = []
    ) {
        $this->endNs = $startNs;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function getStartNs(): int
    {
        return $this->startNs;
    }

    public function getDuration(): float
    {
        return (float)($this->endNs - $this->startNs);
    }

    public function getMeta(): array
    {
        $meta = $this->meta;

        if ($this->error !== null) {
            $meta['error'] = $this->error;
        }

        return $meta;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMetrics(): ?array
    {
        return $this->metrics;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setEndTime(int $endNs): void
    {
        $this->endNs = $endNs;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setError(string $message, ?string $stack = null, ?string $type = null): void
    {
        $this->error = array_filter([
            'message' => $message,
            'stack' => $stack,
            'type' => $type,
        ]);
        $this->status = 'error';
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'span_id' => $this->spanId,
            'trace_id' => $this->traceId,
            'parent_id' => $this->parentId ?? 'undefined',
            'start_ns' => $this->startNs,
            'duration' => $this->getDuration(),
            'meta' => $this->getMeta(),
            'status' => $this->status,
        ];

        if ($this->metrics !== null) {
            $data['metrics'] = $this->metrics;
        }

        if (!empty($this->tags)) {
            $data['tags'] = $this->tags;
        }

        return $data;
    }
}