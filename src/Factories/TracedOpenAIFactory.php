<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Factories;

use Datadog\LLMObservability\Contracts\TracerInterface;
use Datadog\LLMObservability\Http\TracedOpenAIClient;
use OpenAI\Client as OpenAIClient;

final class TracedOpenAIFactory
{
    public static function create(
        OpenAIClient $openAIClient,
        TracerInterface $tracer
    ): TracedOpenAIClient {
        return new TracedOpenAIClient($openAIClient, $tracer);
    }
}