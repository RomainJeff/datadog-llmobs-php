<?php

declare(strict_types=1);

namespace Datadog\LLMObservability;

use Datadog\LLMObservability\Contracts\HttpClientInterface;
use Datadog\LLMObservability\Contracts\SpanInterface;
use Datadog\LLMObservability\Contracts\TracerInterface;
use Datadog\LLMObservability\Contracts\TraceInterface;
use Datadog\LLMObservability\Factories\SpanFactory;
use Datadog\LLMObservability\Models\Configuration;
use Datadog\LLMObservability\Models\Trace;
use Ramsey\Uuid\Uuid;

final class DatadogLLMTracer implements TracerInterface
{
    private ?TraceInterface $currentTrace = null;
    private array $completedSpans = [];
    private array $activeSpans = [];
    private array $globalTags;
    private string $mlApp;
    private ?string $sessionId;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        Configuration $configuration
    ) {
        $this->globalTags = $configuration->getGlobalTags();
        $this->mlApp = $configuration->getMlApp();
        $this->sessionId = $configuration->getSessionId();
    }

    public function createTrace(string $name, ?string $sessionId = null): string
    {
        if ($this->currentTrace !== null && $this->currentTrace->isActive()) {
            throw new \RuntimeException('A trace is already active. End the current trace before creating a new one.');
        }

        $traceId = str_replace('-', '', Uuid::uuid4()->toString());
        $this->currentTrace = new Trace($name, $traceId, $sessionId ?? $this->sessionId);

        return $traceId;
    }

    public function addSpan(
        string $name,
        string $kind,
        array $input = [],
        array $output = [],
        array $metadata = [],
        ?array $metrics = null,
        ?string $parentId = null
    ): string {
        if ($this->currentTrace === null || !$this->currentTrace->isActive()) {
            throw new \RuntimeException('No active trace. Create a trace first using createTrace().');
        }

        $effectiveParentId = $parentId ?? $this->currentTrace->getCurrentParentId();

        $span = SpanFactory::create(
            $name,
            $this->currentTrace->getTraceId(),
            $kind,
            $input,
            $output,
            $metadata,
            $metrics,
            $effectiveParentId
        );

        $span->setEndTime((int)(microtime(true) * 1_000_000_000));

        $this->currentTrace->addSpan($span);

        return $span->getSpanId();
    }

    public function endTrace(): void
    {
        if ($this->currentTrace === null) {
            throw new \RuntimeException('No active trace to end.');
        }

        $this->currentTrace->end();

        foreach ($this->currentTrace->getSpans() as $span) {
            $this->completedSpans[] = $span;
        }

        $this->currentTrace = null;
    }

    public function flush(): bool
    {
        if (empty($this->completedSpans)) {
            return true;
        }

        $spansData = array_map(fn(SpanInterface $span) => $span->toArray(), $this->completedSpans);

        $payload = [
            'data' => [
                'type' => 'span',
                'attributes' => [
                    'ml_app' => $this->mlApp,
                    'spans' => $spansData,
                ],
            ],
        ];

        if (!empty($this->globalTags)) {
            $payload['data']['attributes']['tags'] = $this->globalTags;
        }

        if ($this->sessionId !== null) {
            $payload['data']['attributes']['session_id'] = $this->sessionId;
        }

        $success = $this->httpClient->sendSpans($payload);

        if ($success) {
            $this->completedSpans = [];
        }

        return $success;
    }

    public function setGlobalTags(array $tags): void
    {
        $this->globalTags = $tags;
    }

    public function setMlApp(string $mlApp): void
    {
        $this->mlApp = $mlApp;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getCurrentTrace(): ?TraceInterface
    {
        return $this->currentTrace;
    }

    public function getPendingSpansCount(): int
    {
        return count($this->completedSpans);
    }

    public function startSpan(
        string $name,
        string $kind,
        array $input = [],
        array $metadata = [],
        ?string $parentId = null
    ): string {
        if ($this->currentTrace === null || !$this->currentTrace->isActive()) {
            throw new \RuntimeException('No active trace. Create a trace first using createTrace().');
        }

        $effectiveParentId = $parentId ?? $this->currentTrace->getCurrentParentId();

        $span = SpanFactory::create(
            $name,
            $this->currentTrace->getTraceId(),
            $kind,
            $input,
            [], // no output initially
            $metadata,
            null, // no metrics initially
            $effectiveParentId
        );

        // Store in active spans instead of immediately completing
        $this->activeSpans[$span->getSpanId()] = $span;

        return $span->getSpanId();
    }

    public function endSpan(
        string $spanId,
        array $output = [],
        ?array $metrics = null
    ): void {
        if (!isset($this->activeSpans[$spanId])) {
            throw new \RuntimeException("No active span found with ID: $spanId");
        }

        $span = $this->activeSpans[$spanId];

        // Update span with output and metrics
        if (!empty($output)) {
            $span->setOutput($output);
        }

        if ($metrics !== null) {
            $span->setMetrics($metrics);
        }

        // Set end time
        $span->setEndTime((int)(microtime(true) * 1_000_000_000));

        // Move from active to completed
        unset($this->activeSpans[$spanId]);
        $this->completedSpans[] = $span;

        // Add to current trace
        $this->currentTrace->addSpan($span);
    }

    public function endSpanWithError(
        string $spanId,
        string $errorMessage,
        ?string $errorType = null,
        ?string $stack = null,
        ?array $metrics = null
    ): void {
        if (!isset($this->activeSpans[$spanId])) {
            throw new \RuntimeException("No active span found with ID: $spanId");
        }

        $span = $this->activeSpans[$spanId];

        // Set error on span
        $span->setError($errorMessage, $stack, $errorType);

        // Set metrics if provided
        if ($metrics !== null) {
            $span->setMetrics($metrics);
        }

        // Set end time
        $span->setEndTime((int)(microtime(true) * 1_000_000_000));

        // Move from active to completed
        unset($this->activeSpans[$spanId]);
        $this->completedSpans[] = $span;

        // Add to current trace
        $this->currentTrace->addSpan($span);
    }
}