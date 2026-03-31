<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Concerns;

use Illuminate\Support\Facades\Log;

trait LogsConstellationActivity
{
    protected float $startTime;

    protected function logBefore(array $context): void
    {
        $this->startTime = microtime(true);

        $sanitized = $this->sanitizeInput($context);

        Log::channel($this->logChannel())->info('constellation.command.start', [
            'command' => $this->getName(),
            'is_fake' => $this->option('fake') ?? false,
            'input' => $sanitized,
            'operator' => get_current_user() ?: 'console',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $response
     */
    protected function logAfter(array $context, array $response): void
    {
        $duration = round((microtime(true) - $this->startTime) * 1000);

        Log::channel($this->logChannel())->info('constellation.command.complete', [
            'command' => $this->getName(),
            'is_fake' => $this->option('fake') ?? false,
            'input' => $this->sanitizeInput($context),
            'success' => $response['success'] ?? ($response['code'] ?? null) !== null,
            'response_code' => $response['data']['response_code'] ?? $response['response_code'] ?? $response['code'] ?? null,
            'wallet_id' => $response['data']['wallet_id'] ?? $response['wallet_id'] ?? $context['wallet_id'] ?? null,
            'request_id' => $response['data']['request_id'] ?? $response['request_id'] ?? $context['request_id'] ?? null,
            'duration_ms' => $duration,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    protected function logError(array $context, \Throwable $e): void
    {
        $duration = round((microtime(true) - $this->startTime) * 1000);

        Log::channel($this->logChannel())->error('constellation.command.error', [
            'command' => $this->getName(),
            'is_fake' => $this->option('fake') ?? false,
            'input' => $this->sanitizeInput($context),
            'error' => $e->getMessage(),
            'exception' => get_class($e),
            'duration_ms' => $duration,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitizeInput(array $input): array
    {
        $redacted = ['password', 'pin', 'otp', 'signature'];

        return collect($input)->map(function ($value, $key) use ($redacted) {
            return in_array($key, $redacted) ? '***REDACTED***' : $value;
        })->toArray();
    }

    protected function logChannel(): string
    {
        return config('constellation.log_channel', 'constellation');
    }
}
