<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Http;

use Datadog\LLMObservability\Contracts\TracerInterface;
use Datadog\LLMObservability\Utils\TimeHelper;
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

    public function create(array $parameters): CreateResponse
    {
        $startTime = TimeHelper::currentTimeNs();

        $inputMessages = $parameters['messages'] ?? [];
        $model = $parameters['model'] ?? 'unknown';

        try {
            $response = $this->chat->create($parameters);
            $endTime = TimeHelper::currentTimeNs();

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

            $spanId = $this->tracer->addSpan(
                'openai.chat.completions.create',
                'llm',
                ['messages' => $inputMessages],
                ['messages' => $outputMessages],
                $metadata,
                $metrics
            );

            return $response;
        } catch (\Throwable $e) {
            $this->tracer->addSpan(
                'openai.chat.completions.create',
                'llm',
                ['messages' => $inputMessages],
                [],
                [
                    'model_name' => $model,
                    'model_provider' => 'openai',
                    'error' => [
                        'message' => $e->getMessage(),
                        'type' => get_class($e),
                    ]
                ]
            );

            throw $e;
        }
    }

    public function __call(string $method, array $arguments)
    {
        return $this->chat->$method(...$arguments);
    }
}