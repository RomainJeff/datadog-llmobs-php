# Datadog LLM Observability PHP

A PHP library for tracing LLM calls and sending them to Datadog LLM Observability.

>[!WARNING]
>This is for DEMO only, huge work in progress

## Configuration

Set the following environment variables:

```bash
export DD_API_KEY="your-datadog-api-key"
export DD_LLMOBS_ML_APP="your-app-name"
export DD_TAGS="service:weather-bot,env:production"
```

## Usage

### Basic Usage

```php
<?php

use Datadog\LLMObservability\DatadogLLMTracer;
use Datadog\LLMObservability\Http\DatadogApiClient;
use Datadog\LLMObservability\Models\Configuration;
use OpenAI\Client as OpenAIClient;

// Initialize configuration from environment
$configuration = Configuration::fromEnvironment();

// Create HTTP client and tracer
$httpClient = new DatadogApiClient($configuration);
$tracer = new DatadogLLMTracer($httpClient, $configuration);

// Create OpenAI client
$openAIClient = OpenAIClient::create($_ENV['OPENAI_API_KEY']);

// Create a trace with input data
$traceInput = ['user_id' => '12345', 'request_type' => 'weather_lookup'];
$tracer->createTrace('my-workflow', $traceInput);

// For LLM calls, use span lifecycle for accurate timing
$inputMessages = [
    ['role' => 'user', 'content' => 'Check weather for London']
];
$metadata = [
    'model_name' => 'gpt-3.5-turbo',
    'model_provider' => 'openai',
    'temperature' => 0.7
];

$spanId = $tracer->startSpan('weather-lookup', 'llm', $inputMessages, $metadata);

try {
    // Make the actual LLM call
    $response = $openAIClient->chat()->create([
        'model' => 'gpt-3.5-turbo',
        'messages' => $inputMessages,
        'temperature' => 0.7
    ]);

    // Prepare output and metrics
    $outputMessages = [
        ['role' => 'assistant', 'content' => $response->choices[0]->message->content]
    ];
    $metrics = [
        'input_tokens' => (float)$response->usage->promptTokens,
        'output_tokens' => (float)$response->usage->completionTokens,
        'total_tokens' => (float)$response->usage->totalTokens
    ];

    $tracer->endSpan($spanId, $outputMessages, $metrics);

} catch (\Throwable $e) {
    $tracer->endSpanWithError($spanId, $e->getMessage(), get_class($e));
    // Handle error
} 


// End the trace with output data and send to Datadog
$traceOutput = ['weather_info' => 'Sunny, 22°C', 'location' => 'London, UK'];
$tracer->endTrace($traceOutput);
$tracer->flush();

```

### Automatic OpenAI Tracing

```php
<?php

use Datadog\LLMObservability\Factories\TracedOpenAIFactory;
use OpenAI\Client as OpenAIClient;

// Create OpenAI client
$openAIClient = OpenAIClient::create($_ENV['OPENAI_API_KEY']);

// Wrap with tracing
$tracedClient = TracedOpenAIFactory::create($openAIClient, $tracer);

$tracer->createTrace('chat-session', ['user_session' => 'abc123']);

// This call will be automatically traced
$response = $tracedClient->chat()->create([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, how are you?']
    ],
    'temperature' => 0.7,
    'max_completion_tokens' => 100
]);

$tracer->endTrace(['response_generated' => true, 'message_count' => 1]);
$tracer->flush();
```

## API Reference

### TracerInterface

#### Trace Management
- `createTrace(string $name, array $input = [], ?string $sessionId = null): string` - Create a new trace with optional input data
- `endTrace(array $output = []): void` - End the current trace with optional output data
- `flush(): bool` - Send all collected spans to Datadog

#### Span Management
- `addSpan(string $name, string $kind, array $input, array $output, array $metadata, ?array $metrics, ?string $parentId): string` - Add a complete span to the current trace
- `startSpan(string $name, string $kind, array $input, array $metadata, ?string $parentId): string` - Start a span and return its ID for later completion
- `endSpan(string $spanId, array $output, ?array $metrics): void` - End a span with success data
- `endSpanWithError(string $spanId, string $errorMessage, ?string $errorType, ?string $stack, ?array $metrics): void` - End a span with error details

#### Configuration
- `setGlobalTags(array $tags): void` - Set global tags for all spans
- `setMlApp(string $mlApp): void` - Set the ML application name
- `setSessionId(string $sessionId): void` - Set the session ID

### Span Kinds

Supported span kinds according to Datadog LLM Observability API:

- `llm` - LLM model calls
- `agent` - AI agent operations
- `workflow` - Workflow steps
- `tool` - Tool usage
- `task` - Task execution
- `embedding` - Embedding generation
- `retrieval` - Information retrieval

## License

MIT
