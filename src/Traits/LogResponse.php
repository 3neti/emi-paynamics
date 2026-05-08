<?php

namespace LBHurtado\EmiPaynamicsConstellation\Traits;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use LBHurtado\EmiPaynamicsConstellation\Exceptions\ConstellationRequestException;

trait LogResponse
{
    protected function handleResponse(string $method, string $endpoint, array $payload, Response $response): Response
    {
        $context = $this->buildContext($method, $endpoint, $payload, $response);

        if ($this->isFailureResponse($response, $context)) {
            $this->logFailure($context);

            if ($this->shouldThrowOnConstellationFailure()) {
                throw new ConstellationRequestException(
                    $context['api_message']
                        ?: $this->buildMessage($method, $endpoint, $response),
                    $context
                );
            }
        }

        return $response;
    }

    protected function isFailureResponse(Response $response, array $context): bool
    {
        if ($response->failed()) {
            return true;
        }

        return $context['success'] === false;
    }

    protected function buildContext(string $method, string $endpoint, array $payload, Response $response): array
    {
        $json = $response->json();

        return [
            'method' => $method,
            'endpoint' => $endpoint,
            'url' => rtrim((string) config('constellation.base_url'), '/') . $endpoint,
            'status' => $response->status(),
            'reason' => $response->reason(),
            'success' => is_array($json) ? data_get($json, 'success') : null,
            'api_code' => is_array($json)
                ? (
                    data_get($json, 'data.response_code')
                    ?? data_get($json, 'response_code')
                    ?? data_get($json, 'code')
                )
                : null,
            'api_message' => is_array($json)
                ? (
                    data_get($json, 'data.response_message')
                    ?? data_get($json, 'response_message')
                    ?? data_get($json, 'message')
                )
                : null,
            'api_advise' => is_array($json)
                ? (
                    data_get($json, 'data.response_advise')
                    ?? data_get($json, 'response_advise')
                    ?? data_get($json, 'advise')
                )
                : null,
            'request_id' => is_array($json)
                ? (
                    data_get($json, 'data.request_id')
                    ?? data_get($json, 'request_id')
                )
                : null,
            'response_id' => is_array($json)
                ? (
                    data_get($json, 'data.response_id')
                    ?? data_get($json, 'response_id')
                )
                : null,
            'timestamp' => is_array($json)
                ? (
                    data_get($json, 'data.timestamp')
                    ?? data_get($json, 'timestamp')
                )
                : null,
            'validation_details' => $this->extractValidationDetails($json),
            'response_headers' => $response->headers(),
            'response_body' => $response->body(),
            'response_json' => $json,
            'request_payload' => $this->sanitize($payload),
        ];
    }

    protected function extractValidationDetails(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        return array_filter([
            'errors' => data_get($json, 'data.errors') ?? data_get($json, 'errors'),
            'details' => data_get($json, 'data.details') ?? data_get($json, 'details'),
            'invalid_fields' => data_get($json, 'data.invalid_fields') ?? data_get($json, 'invalid_fields'),
            'violations' => data_get($json, 'data.violations') ?? data_get($json, 'violations'),
        ], fn ($value) => ! is_null($value) && $value !== [] && $value !== '');
    }

    protected function logFailure(array $context): void
    {
        Log::warning('constellation.http.failure', [
            'method' => $context['method'],
            'endpoint' => $context['endpoint'],
            'url' => $context['url'],
            'status' => $context['status'],
            'reason' => $context['reason'],
            'success' => $context['success'],
            'api_code' => $context['api_code'],
            'api_message' => $context['api_message'],
            'api_advise' => $context['api_advise'],
            'request_id' => $context['request_id'],
            'response_id' => $context['response_id'],
            'timestamp' => $context['timestamp'],
            'validation_details' => $context['validation_details'],
            'request_payload' => $context['request_payload'],
            'response_json' => $context['response_json'],
        ]);
    }

    protected function shouldThrowOnConstellationFailure(): bool
    {
        return (bool) config('constellation.throw_on_http_failure', false);
    }

    protected function buildMessage(string $method, string $endpoint, Response $response): string
    {
        $json = $response->json();

        $apiMessage = is_array($json)
            ? (
                data_get($json, 'data.response_message')
                ?? data_get($json, 'response_message')
                ?? data_get($json, 'message')
            )
            : null;

        return $apiMessage
            ? "Constellation API request failed: {$apiMessage}"
            : "Constellation API request failed for [{$method} {$endpoint}] with HTTP {$response->status()}";
    }

    protected function sanitize(array $payload): array
    {
        foreach (['password', 'pin', 'otp', 'signature'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '***REDACTED***';
            }
        }

        return $payload;
    }
}