<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Http;

use Datadog\LLMObservability\Contracts\TracerInterface;
use OpenAI\Client as OpenAIClient;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;

final class TracedOpenAIClient
{
    private readonly TracedChatResource $chat;

    public function __construct(
        private readonly OpenAIClient $client,
        private readonly TracerInterface $tracer
    ) {
        $this->chat = new TracedChatResource($this->client->chat(), $this->tracer);
    }

    public function chat(): TracedChatResource
    {
        return $this->chat;
    }

    public function __get(string $name)
    {
        return $this->client->$name;
    }

    public function __call(string $method, array $arguments)
    {
        return $this->client->$method(...$arguments);
    }
}

final class TracedChatResource
{
    public function __construct(
        private readonly Chat $chat,
        private readonly TracerInterface $tracer
    ) {
    }

    public function create(array $parameters, ?string $spanName = null): CreateResponse
    {
        $inputMessages = $parameters['messages'] ?? [];
        $model = $parameters['model'] ?? 'unknown';

        $metadata = [
            'model_name' => $model,
            'model_provider' => 'openai',
        ];

        if (isset($parameters['temperature'])) {
            $metadata['temperature'] = $parameters['temperature'];
        }

        if (isset($parameters['max_tokens'])) {
            $metadata['max_tokens'] = $parameters['max_tokens'];
        }

        if (isset($parameters['max_completion_tokens'])) {
            $metadata['max_tokens'] = $parameters['max_completion_tokens'];
        }

        // Start the span before making the API call
        $spanId = $this->tracer->startSpan(
            $spanName ?? 'openai.chat.completions.create',
            'llm',
            ['messages' => $inputMessages],
            $metadata
        );

        try {
            $response = $this->chat->create($parameters);

            $outputMessages = [];
            if ($response->choices) {
                foreach ($response->choices as $choice) {
                    if ($choice->message) {
                        $outputMessages[] = [
                            'role' => $choice->message->role,
                            'content' => $choice->message->content,
                        ];
                    }
                }
            }

            $metrics = [];
            if ($response->usage) {
                if ($response->usage->promptTokens) {
                    $metrics['input_tokens'] = (float)$response->usage->promptTokens;
                }
                if ($response->usage->completionTokens) {
                    $metrics['output_tokens'] = (float)$response->usage->completionTokens;
                }
                if ($response->usage->totalTokens) {
                    $metrics['total_tokens'] = (float)$response->usage->totalTokens;
                }
            }

            // End the span with the response output and metrics
            $this->tracer->endSpan(
                $spanId,
                ['messages' => $outputMessages],
                $metrics
            );

            return $response;
        } catch (\Throwable $e) {
            // End the span with error
            $this->tracer->endSpanWithError(
                $spanId,
                $e->getMessage(),
                get_class($e),
                $e->getTraceAsString()
            );

            throw $e;
        }
    }

    public function __call(string $method, array $arguments)
    {
        return $this->chat->$method(...$arguments);
    }
}