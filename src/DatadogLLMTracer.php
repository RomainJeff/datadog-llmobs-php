<?php

declare(strict_types=1);

namespace Datadog\LLMObservability;

use Datadog\LLMObservability\Contracts\HttpClientInterface;
use Datadog\LLMObservability\Contracts\SpanInterface;
use Datadog\LLMObservability\Contracts\TracerInterface;
use Datadog\LLMObservability\Contracts\TraceGroupInterface;
use Datadog\LLMObservability\Factories\SpanFactory;
use Datadog\LLMObservability\Models\Configuration;
use Datadog\LLMObservability\Models\TraceGroup;
use Datadog\LLMObservability\Utils\IdGenerator;
use Datadog\LLMObservability\Utils\TimeHelper;

final class DatadogLLMTracer implements TracerInterface
{
    private ?TraceGroupInterface $currentGroup = null;
    private array $completedSpans = [];
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

    public function createGroup(string $name, ?string $sessionId = null): string
    {
        if ($this->currentGroup !== null && $this->currentGroup->isActive()) {
            throw new \RuntimeException('A trace group is already active. End the current group before creating a new one.');
        }

        $traceId = IdGenerator::generateTraceId();
        $this->currentGroup = new TraceGroup($name, $traceId, $sessionId ?? $this->sessionId);

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
        if ($this->currentGroup === null || !$this->currentGroup->isActive()) {
            throw new \RuntimeException('No active trace group. Create a group first using createGroup().');
        }

        $effectiveParentId = $parentId ?? $this->currentGroup->getCurrentParentId();

        $span = SpanFactory::create(
            $name,
            $this->currentGroup->getTraceId(),
            $kind,
            $input,
            $output,
            $metadata,
            $metrics,
            $effectiveParentId
        );

        $span->setEndTime(TimeHelper::currentTimeNs());

        $this->currentGroup->addSpan($span);

        return $span->getSpanId();
    }

    public function endGroup(): void
    {
        if ($this->currentGroup === null) {
            throw new \RuntimeException('No active trace group to end.');
        }

        $this->currentGroup->end();

        foreach ($this->currentGroup->getSpans() as $span) {
            $this->completedSpans[] = $span;
        }

        $this->currentGroup = null;
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

    public function getCurrentGroup(): ?TraceGroupInterface
    {
        return $this->currentGroup;
    }

    public function getPendingSpansCount(): int
    {
        return count($this->completedSpans);
    }
}