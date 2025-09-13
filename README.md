# Datadog LLM Observability PHP

A PHP library for tracing LLM calls and sending them to Datadog LLM Observability.

>[!WARNING]
>This is for DEMO only, huge work in progress

## Features

- ✅ Trace chat completions with automatic timing and token counting
- ✅ Hierarchical span management with parent-child relationships
- ✅ Automatic OpenAI client wrapping for seamless integration
- ✅ PSR-compliant interfaces and logging
- ✅ Configurable via environment variables
- ✅ Support for custom metadata and metrics
- ✅ Error handling and status tracking

## Installation

```bash
composer require datadog/llm-observability-php
```

## Configuration

Set the following environment variables:

```bash
export DD_API_KEY="your-datadog-api-key"
export DD_LLMOBS_ML_APP="your-app-name"
export DD_TAGS="service:weather-bot,env:production"
export DD_LLMOBS_SESSION_ID="optional-session-id"
export OPENAI_API_KEY="your-openai-api-key"
```

## Usage

### Basic Usage

```php
<?php

use Datadog\LLMObservability\DatadogLLMTracer;
use Datadog\LLMObservability\Http\DatadogApiClient;
use Datadog\LLMObservability\Models\Configuration;

// Initialize configuration from environment
$configuration = Configuration::fromEnvironment();

// Create HTTP client and tracer
$httpClient = new DatadogApiClient($configuration);
$tracer = new DatadogLLMTracer($httpClient, $configuration);

// Create a trace
$tracer->createTrace('my-workflow');

// Add spans manually
$tracer->addSpan('user-query', 'agent', [
    'value' => 'What is the weather like today?'
], [
    'value' => 'It\'s sunny and 20°C'
]);

$tracer->addSpan('weather-lookup', 'llm', [
    'messages' => [
        ['role' => 'user', 'content' => 'Check weather for London']
    ]
], [
    'messages' => [
        ['role' => 'assistant', 'content' => 'Current weather in London: Sunny, 20°C']
    ]
], [
    'model_name' => 'gpt-3.5-turbo',
    'model_provider' => 'openai'
]);

// End the trace and send to Datadog
$tracer->endTrace();
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

$tracer->createTrace('chat-session');

// This call will be automatically traced
$response = $tracedClient->chat()->create([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello, how are you?']
    ],
    'temperature' => 0.7,
    'max_completion_tokens' => 100
]);

$tracer->endTrace();
$tracer->flush();
```

## API Reference

### TracerInterface

- `createTrace(string $name, ?string $sessionId = null): string` - Create a new trace
- `addSpan(string $name, string $kind, array $input, array $output, array $metadata, ?array $metrics, ?string $parentId): string` - Add a span to the current trace
- `endTrace(): void` - End the current trace
- `flush(): bool` - Send all collected spans to Datadog
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
