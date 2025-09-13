<?php

declare(strict_types=1);

namespace Datadog\LLMObservability\Factories;

use Datadog\LLMObservability\Contracts\SpanInterface;
use Datadog\LLMObservability\Models\Span;
use Datadog\LLMObservability\Utils\IdGenerator;
use Datadog\LLMObservability\Utils\TimeHelper;

final class SpanFactory
{
    public static function create(
        string $name,
        string $traceId,
        string $kind,
        array $input = [],
        array $output = [],
        array $metadata = [],
        ?array $metrics = null,
        ?string $parentId = null,
        array $tags = []
    ): SpanInterface {
        $spanId = IdGenerator::generateSpanId();
        $startNs = TimeHelper::currentTimeNs();

        $meta = [
            'kind' => $kind,
        ];

        if (!empty($input)) {
            $meta['input'] = $input;
        }

        if (!empty($output)) {
            $meta['output'] = $output;
        }

        if (!empty($metadata)) {
            $meta['metadata'] = $metadata;
        }

        return new Span(
            $name,
            $spanId,
            $traceId,
            $parentId,
            $startNs,
            $meta,
            $metrics,
            $tags
        );
    }

    public static function createLLMSpan(
        string $name,
        string $traceId,
        array $inputMessages,
        array $outputMessages = [],
        array $metadata = [],
        ?array $metrics = null,
        ?string $parentId = null,
        array $tags = []
    ): SpanInterface {
        $input = ['messages' => $inputMessages];
        $output = !empty($outputMessages) ? ['messages' => $outputMessages] : [];

        return self::create(
            $name,
            $traceId,
            'llm',
            $input,
            $output,
            $metadata,
            $metrics,
            $parentId,
            $tags
        );
    }
}