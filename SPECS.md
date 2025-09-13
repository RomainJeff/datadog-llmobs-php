You need to implement a LLM Tracer that will be used to trace LLM calls and send them to Datadog.
You will have to wrap the OpenAI client to automatically trace the calls.

The tracer will have to be able to trace the following:
- Chat completions

Here is the documentation of the Datadog LLM Observability API:
Endpoint : https://api.datadoghq.com/api/intake/llm-obs/v1/trace/spans
Method : POST
Headers : 
```
DD-API-KEY: <YOUR_API_KEY>
Content-Type: application/json
```
Payload example : 
```json
{
  "data": {
    "type": "span",
    "attributes": {
      "ml_app": "weather-bot",
      "session_id": "1",
      "tags": [
        "service:weather-bot",
        "env:staging",
        "user_handle:example-user@example.com",
        "user_id:1234"
      ],
      "spans": [
        {
          "parent_id": "undefined",
          "trace_id": "<TEST_TRACE_ID>",
          "span_id": "<AGENT_SPAN_ID>",
          "name": "health_coach_agent",
          "meta": {
            "kind": "agent",
            "input": {
              "value": "What is the weather like today and do i wear a jacket?"
            },
            "output": {
              "value": "It's very hot and sunny, there is no need for a jacket"
            }
          },
          "start_ns": 1713889389104152000,
          "duration": 10000000000
        },
        {
          "parent_id": "<AGENT_SPAN_ID>",
          "trace_id": "<TEST_TRACE_ID>",
          "span_id": "<WORKFLOW_ID>",
          "name": "qa_workflow",
          "meta": {
            "kind": "workflow",
            "input": {
              "value": "What is the weather like today and do i wear a jacket?"
            },
            "output": {
              "value":  "It's very hot and sunny, there is no need for a jacket"
            }
          },
          "start_ns": 1713889389104152000,
          "duration": 5000000000
        },
        {
          "parent_id": "<WORKFLOW_SPAN_ID>",
          "trace_id": "<TEST_TRACE_ID>",
          "span_id": "<LLM_SPAN_ID>",
          "name": "generate_response",
          "meta": {
            "kind": "llm",
            "input": {
              "messages": [
                {
                  "role": "system",
                  "content": "Your role is to ..."
                },
                {
                  "role": "user",
                  "content": "What is the weather like today and do i wear a jacket?"
                }
              ]
            },
            "output": {
              "messages": [
                {
                  "content": "It's very hot and sunny, there is no need for a jacket",
                  "role": "assistant"
                }
              ]
            }
          },
          "start_ns": 1713889389104152000,
          "duration": 2000000000
        }
      ]
    }
  }
}
```
Documentation of the API payload : 
```
Error
Field	Type	Description
message	string	The error message.
stack	string	The stack trace.
type	string	The error type.


Meta
Field	Type	Description
kind [required]	string	The span kind: "agent", "workflow", "llm", "tool", "task", "embedding", or "retrieval".
error	Error	Error information on the span.
input	IO	The span’s input information.
output	IO	The span’s output information.
metadata	Dict[key (string), value] where the value is a float, bool, or string	Data about the span that is not input or output related. Use the following metadata keys for LLM spans: temperature, max_tokens, model_name, and model_provider.

Metrics
Field	Type	Description
input_tokens	float64	The number of input tokens. Only valid for LLM spans.
output_tokens	float64	The number of output tokens. Only valid for LLM spans.
total_tokens	float64	The total number of tokens associated with the span. Only valid for LLM spans.
time_to_first_token	float64	The time in seconds it takes for the first output token to be returned in streaming-based LLM applications. Set for root spans.
time_per_output_token	float64	The time in seconds it takes for the per output token to be returned in streaming-based LLM applications. Set for root spans.

Span
Field	Type	Description
name [required]	string	The name of the span.
span_id [required]	string	An ID unique to the span.
trace_id [required]	string	A unique ID shared by all spans in the same trace.
parent_id [required]	string	ID of the span’s direct parent. If the span is a root span, the parent_id must be undefined.
start_ns [required]	uint64	The span’s start time in nanoseconds.
duration [required]	float64	The span’s duration in nanoseconds.
meta [required]	Meta	The core content relative to the span.
status	string	Error status ("ok" or "error"). Defaults to "ok".
apm_trace_id	string	The ID of the associated APM trace. Defaults to match the trace_id field.
metrics	Metrics	Datadog metrics to collect.
session_id	string	The span’s session_id. Overrides the top-level session_id field.
tags	[Tag]	A list of tags to apply to this particular span.

SpansRequestData
Field	Type	Description
type [required]	string	Identifier for the request. Set to span.
attributes [required]	SpansPayload	The body of the request.

SpansPayload
Field	Type	Description
ml_app [required]	string	The name of your LLM application. See Application naming guidelines.
spans [required]	[Span]	A list of spans.
tags	[Tag]	A list of top-level tags to apply to each span.
session_id	string	The session the list of spans belongs to. Can be overridden or set on individual spans as well.

Tag
Tags should be formatted as a list of strings (for example, ["user_handle:dog@gmail.com", "app_version:1.0.0"]). They are meant to store contextual information surrounding the span.

For more information about tags, see Getting Started with Tags.

Application naming guidelines
Your application name (the value of DD_LLMOBS_ML_APP) must be a lowercase Unicode string. It may contain the characters listed below:
Alphanumerics
Underscores
Minuses
Colons
Periods
Slashes
The name can be up to 193 characters long and may not contain contiguous or trailing underscores.
```

How i want to be able to use the Tracer:
```
$tracer->createGroup('name');

// call OpenAI
$tracer->addSpan('name', 'llm', [...]);
// call OpenAI
$tracer->addSpan('name', 'llm', [...]);

$tracer->endGroup();

// trigger send to datadog
$tracer->flush();
```

Language : PHP
Package manager : Composer

Use the OpenAI PHP SDK.
Use PSRs when possible.
Respect SOLID principles.