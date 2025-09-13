<?php

require_once __DIR__ . '/vendor/autoload.php';

use Datadog\LLMObservability\DatadogLLMTracer;
use Datadog\LLMObservability\Factories\TracedOpenAIFactory;
use Datadog\LLMObservability\Http\DatadogApiClient;
use Datadog\LLMObservability\Models\Configuration;

$_ENV['DD_API_KEY'] = 'dd-api-key';
$_ENV['DD_LLMOBS_ML_APP'] = 'weather-assistant-workflow';
$_ENV['OPENAI_API_KEY'] = 'openai-api-key';

$configuration = Configuration::fromEnvironment();

$httpClient = new DatadogApiClient($configuration);
$tracer = new DatadogLLMTracer($httpClient, $configuration);

$openAIClient = OpenAI::client($_ENV['OPENAI_API_KEY']);
$tracedOpenAIClient = TracedOpenAIFactory::create($openAIClient, $tracer);

$tracer->createTrace('discussion');

try {
    $response1 = $tracedOpenAIClient->chat()->create([
        'model' => 'gpt-5-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a weather assistant. Help users with weather-related questions.'
            ],
            [
                'role' => 'user',
                'content' => 'What should I wear today if it\'s 20°C and sunny?'
            ]
        ]
    ]);

    echo "First response: " . $response1->choices[0]->message->content . "\n";

    // Example with structured output
    $structuredResponse = $tracedOpenAIClient->chat()->create([
        'model' => 'gpt-5-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a weather analysis assistant that provides structured weather recommendations.'
            ],
            [
                'role' => 'user',
                'content' => 'Analyze the weather for a 25°C sunny day and provide clothing and activity recommendations.'
            ]
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'weather_recommendation',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'weather_analysis' => [
                            'type' => 'object',
                            'properties' => [
                                'temperature' => ['type' => 'number'],
                                'condition' => ['type' => 'string'],
                                'comfort_level' => ['type' => 'string', 'enum' => ['cold', 'cool', 'comfortable', 'warm', 'hot']]
                            ],
                            'required' => ['temperature', 'condition', 'comfort_level'],
                            'additionalProperties' => false
                        ],
                        'clothing_recommendations' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ],
                        'activity_suggestions' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'activity' => ['type' => 'string'],
                                    'suitability' => ['type' => 'string', 'enum' => ['excellent', 'good', 'fair', 'poor']]
                                ],
                                'required' => ['activity', 'suitability'],
                                'additionalProperties' => false
                            ]
                        ]
                    ],
                    'required' => ['weather_analysis', 'clothing_recommendations', 'activity_suggestions'],
                    'additionalProperties' => false
                ]
            ]
        ]
    ]);

    echo "Structured response: " . $structuredResponse->choices[0]->message->content . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$tracer->endTrace();
$tracer->flush();