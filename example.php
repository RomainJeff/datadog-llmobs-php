<?php

require_once __DIR__ . '/vendor/autoload.php';

use Datadog\LLMObservability\DatadogLLMTracer;
use Datadog\LLMObservability\Factories\TracedOpenAIFactory;
use Datadog\LLMObservability\Http\DatadogApiClient;
use Datadog\LLMObservability\Models\Configuration;

$_ENV['DD_API_KEY'] = getenv('DD_API_KEY');
$_ENV['DD_LLMOBS_ML_APP'] = getenv('DD_LLMOBS_ML_APP');

$configuration = Configuration::fromEnvironment();

$httpClient = new DatadogApiClient($configuration);
$tracer = new DatadogLLMTracer($httpClient, $configuration);

$openAIClient = OpenAI::client(getenv('OPENAI_API_KEY'));
$tracedOpenAIClient = TracedOpenAIFactory::create($openAIClient, $tracer);

function analyzeText(string $text): string
{
    global $tracedOpenAIClient;
    $response = $tracedOpenAIClient->chat()->create([
        'model' => 'gpt-5-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a text analysis assistant. Help users with text analysis questions.'
            ],
            [
                'role' => 'user',
                'content' => 'Analyze the following text: ' . $text
            ]
        ]
    ], 'text-analysis');
    return $response->choices[0]->message->content;
}

function extractInfoFromText(string $text): string
{
    global $tracedOpenAIClient;
    $response = $tracedOpenAIClient->chat()->create([
        'model' => 'gpt-5-mini',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a text analysis assistant that provides structured text analysis.'
            ],
            [
                'role' => 'user',
                'content' => 'Extract key information from the text: ' . $text
            ]
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'info_extraction',
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'info' => [
                            'type' => 'array',
                            'items' => ['type' => 'string']
                        ]
                    ],
                    'required' => ['info'],
                    'additionalProperties' => false
                ]
            ]
        ]
    ], 'info-extraction');
    return $response->choices[0]->message->content;
}

$textToAnalyze = "William Shakespeare was an English playwright, poet and actor. He is widely regarded as the greatest writer in the English language and the world's pre-eminent dramatist. He is often called England's national poet and the 'Bard of Avon' or simply 'the Bard'.";

$tracer->createTrace('text-analysis', ['text' => $textToAnalyze]);

try {
    $textAnalysis = analyzeText($textToAnalyze);
    echo "First response: " . $textAnalysis . "\n";

    $infoExtraction = extractInfoFromText($textToAnalyze);
    echo "Structured response: " . $infoExtraction . "\n";

    $tracer->endTrace(['textAnalysis' => $textAnalysis, 'infoExtraction' => $infoExtraction]);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    $tracer->endTrace();
}

$tracer->flush();