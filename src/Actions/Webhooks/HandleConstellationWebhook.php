<?php

namespace LBHurtado\EmiPaynamicsConstellation\Actions\Webhooks;

use LBHurtado\EmiCore\Models\WebhookReceipt;
use LBHurtado\EmiPaynamicsConstellation\Support\ConstellationSignatureVerifier;
use Lorisleiva\Actions\Concerns\AsAction;

class HandleConstellationWebhook
{
    use AsAction;

    public function __construct(private ConstellationSignatureVerifier $verifier) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): WebhookReceipt
    {
        $postbackData = $payload['data'] ?? [];
        $postbackId = $payload['postback_id'] ?? null;

        // Idempotency: skip if already processed
        $existing = WebhookReceipt::where('postback_id', $postbackId)
            ->where('processing_status', 'processed')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Store raw payload immediately
        $receipt = WebhookReceipt::create([
            'provider_code' => 'paynamics_constellation',
            'event_type' => $payload['originating_flow'] ?? null,
            'request_id' => $postbackData['request_id'] ?? null,
            'postback_id' => $postbackId,
            'signature' => $postbackData['signature'] ?? null,
            'signature_verified' => false,
            'payload' => $payload,
            'processing_status' => 'received',
        ]);

        // Verify signature
        $signatureValid = $this->verifier->verifySignature(
            $postbackData,
            config('constellation.merchant_key'),
        );

        $receipt->update([
            'signature_verified' => $signatureValid,
            'processing_status' => $signatureValid ? 'processed' : 'signature_failed',
            'processed_at' => $signatureValid ? now() : null,
            'error_message' => $signatureValid ? null : 'Signature verification failed',
        ]);

        return $receipt->fresh();
    }
}
