<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Models;

final class Configuration
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $mlApp,
        private readonly array $globalTags = [],
        private readonly ?string $sessionId = null,
        private readonly string $endpoint = 'https://api.datadoghq.com/api/intake/llm-obs/v1/trace/spans'
    ) {
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getMlApp(): string
    {
        return $this->mlApp;
    }

    public function getGlobalTags(): array
    {
        return $this->globalTags;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public static function fromEnvironment(): self
    {
        $apiKey = $_ENV['DD_API_KEY'] ?? throw new \InvalidArgumentException('DD_API_KEY environment variable is required');
        $mlApp = $_ENV['DD_LLMOBS_ML_APP'] ?? throw new \InvalidArgumentException('DD_LLMOBS_ML_APP environment variable is required');

        $globalTags = [];
        if (isset($_ENV['DD_TAGS'])) {
            $globalTags = array_filter(explode(',', $_ENV['DD_TAGS']));
        }

        $sessionId = $_ENV['DD_LLMOBS_SESSION_ID'] ?? null;
        $endpoint = $_ENV['DD_LLMOBS_ENDPOINT'] ?? 'https://api.datadoghq.com/api/intake/llm-obs/v1/trace/spans';

        return new self($apiKey, $mlApp, $globalTags, $sessionId, $endpoint);
    }
}