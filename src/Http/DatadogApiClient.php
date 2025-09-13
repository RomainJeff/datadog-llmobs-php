<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Http;

use Datadog\LLMObservability\Contracts\HttpClientInterface;
use Datadog\LLMObservability\Models\Configuration;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class DatadogApiClient implements HttpClientInterface
{
    private readonly Client $client;

    public function __construct(
        private readonly Configuration $configuration,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    public function sendSpans(array $payload): bool
    {
        try {
            $response = $this->client->post($this->configuration->getEndpoint(), [
                'headers' => [
                    'DD-API-KEY' => $this->configuration->getApiKey(),
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'datadog-llm-observability-php/1.0.0',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->debug('Successfully sent spans to Datadog', [
                    'status_code' => $statusCode,
                    'span_count' => count($payload['data']['attributes']['spans'] ?? []),
                ]);
                return true;
            }

            $this->logger->warning('Unexpected response from Datadog API', [
                'status_code' => $statusCode,
                'response_body' => $response->getBody()->getContents(),
            ]);

            return false;
        } catch (GuzzleException $e) {
            $this->logger->error('Failed to send spans to Datadog', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        }
    }
}